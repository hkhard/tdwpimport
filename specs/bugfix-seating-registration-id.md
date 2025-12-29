# Bug Fix: First Player Skipped in Auto-Seating

**Issue**: When adding multiple players to a tournament, the first player is not included in the auto-seating list.

**Version**: 3.2.0-beta5

## Root Cause

The `tdwp_tournament_seats` table only tracks `player_id` (WordPress post ID) without tracking which specific tournament **registration** (`entry_number`) is seated. This causes issues when:

1. A player has multiple entries (re-entries)
2. One entry gets seated
3. The LEFT JOIN incorrectly excludes ALL entries for that player from the unseated list

### Current Schema (BROKEN)

**tdwp_tournament_seats**:
```sql
player_id bigint(20)  -- WordPress post ID (can be duplicated for re-entries)
```

**tdwp_tournament_players** (registration table):
```sql
id bigint(20) AUTO_INCREMENT  -- Registration ID (PK)
player_id bigint(20)          -- WordPress post ID
entry_number int              -- 1, 2, 3 for re-entries
UNIQUE KEY (tournament_id, player_id, entry_number)
```

### Broken Query (class-seat-manager.php:315-320)

```sql
SELECT tp.player_id
FROM wp_tdwp_tournament_players tp
LEFT JOIN wp_tdwp_tournament_seats ts ON tp.player_id = ts.player_id
WHERE tp.tournament_id = %d
AND tp.status IN ('paid', 'active')
AND ts.player_id IS NULL  -- ❌ Excludes ALL entries if ANY entry is seated!
```

## Solution

Add `registration_id` column to `tdwp_tournament_seats` to track the SPECIFIC registration being seated.

### Schema Changes

1. Add `registration_id` column to `tdwp_tournament_seats`
2. Update LEFT JOIN to match on `registration_id` instead of `player_id`
3. Change `get_unseated_players()` to return registration data, not player post objects
4. Update `auto_seat_players()` to work with registration IDs
5. Update `move_player()` signature to accept `registration_id`

## Implementation Plan

### Phase 1: Database Migration (v3.2.0-beta5)

**File**: `class-database-schema.php`

Add migration method `migrate_beta_seating_registration_id()`:

```php
public static function migrate_beta_seating_registration_id() {
    global $wpdb;

    // Check if migration already done
    $migration_done = get_option( 'tdwp_beta_seating_registration_id', false );
    if ( $migration_done ) {
        return true;
    }

    $seats_table = $wpdb->prefix . 'tdwp_tournament_seats';

    // Add registration_id column
    $column_exists = $wpdb->get_results(
        $wpdb->prepare( 'SHOW COLUMNS FROM `' . $seats_table . '` LIKE %s', 'registration_id' )
    );

    if ( empty( $column_exists ) ) {
        $wpdb->query(
            "ALTER TABLE `{$seats_table}`
            ADD COLUMN registration_id bigint(20) UNSIGNED DEFAULT NULL AFTER player_id,
            ADD INDEX registration_id (registration_id)"
        );
    }

    // Migrate existing data: Find registration_id for each seated player
    // For players with only 1 entry, this is straightforward
    // For players with re-entries, assume entry_number=1 was seated (best guess)
    $players_table = $wpdb->prefix . 'tdwp_tournament_players';

    $wpdb->query(
        "UPDATE {$seats_table} seats
        INNER JOIN {$players_table} players
            ON seats.player_id = players.player_id
            AND players.entry_number = 1
        SET seats.registration_id = players.id
        WHERE seats.registration_id IS NULL"
    );

    update_option( 'tdwp_beta_seating_registration_id', true );
    error_log( 'Seating Migration: Added registration_id column and migrated existing seats' );

    return true;
}
```

Call this migration in plugin activation.

### Phase 2: Update Seat Manager

**File**: `class-seat-manager.php`

#### 2.1 Update `move_player()` signature

```php
/**
 * Move player registration to a seat
 *
 * @since 3.2.0
 * @param int $registration_id Registration ID from tdwp_tournament_players.id
 * @param int $to_table_id Destination table ID
 * @param int $to_seat_number Destination seat number
 * @return bool True on success
 */
public static function move_player( $registration_id, $to_table_id, $to_seat_number ) {
    global $wpdb;

    $players_table = $wpdb->prefix . 'tdwp_tournament_players';
    $seats_table   = $wpdb->prefix . 'tdwp_tournament_seats';

    // Get registration data
    $registration = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT * FROM {$players_table} WHERE id = %d",
            $registration_id
        )
    );

    if ( ! $registration ) {
        return false;
    }

    // Validate destination seat is empty
    if ( ! self::is_seat_empty( $to_table_id, $to_seat_number ) ) {
        return false;
    }

    // Check if this registration is currently seated
    $current_seat = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT * FROM {$seats_table} WHERE registration_id = %d",
            $registration_id
        )
    );

    // Save movement history
    $moved_from_table_id = null;
    $moved_from_seat_number = null;

    if ( $current_seat ) {
        $moved_from_table_id = $current_seat->table_id;
        $moved_from_seat_number = $current_seat->seat_number;

        // Clear old seat
        $wpdb->update(
            $seats_table,
            array(
                'player_id'       => null,
                'registration_id' => null,
                'status'          => 'empty',
                'assigned_at'     => null,
            ),
            array( 'id' => $current_seat->id ),
            array( '%d', '%d', '%s', '%s' ),
            array( '%d' )
        );
    }

    // Assign registration to new seat
    $result = $wpdb->update(
        $seats_table,
        array(
            'player_id'              => $registration->player_id,
            'registration_id'        => $registration_id,
            'status'                 => 'occupied',
            'assigned_at'            => current_time( 'mysql' ),
            'moved_from_table_id'    => $moved_from_table_id,
            'moved_from_seat_number' => $moved_from_seat_number,
        ),
        array(
            'table_id'    => $to_table_id,
            'seat_number' => $to_seat_number,
        ),
        array( '%d', '%d', '%s', '%s', '%d', '%d' ),
        array( '%d', '%d' )
    );

    if ( $result !== false ) {
        $table = TDWP_Table_Manager::get_table( $to_table_id );
        if ( $table ) {
            do_action( 'tdwp_player_moved', $table->tournament_id, $registration->player_id, $to_table_id, $to_seat_number );
        }
    }

    return $result !== false;
}
```

#### 2.2 Update `get_unseated_players()`

```php
/**
 * Get unseated player registrations for tournament
 *
 * Returns registration data for unseated entries
 *
 * @since 3.2.0
 * @param int $tournament_id Tournament post ID
 * @return array Array of registration objects with player data
 */
public static function get_unseated_players( $tournament_id ) {
    global $wpdb;

    $players_table = $wpdb->prefix . 'tdwp_tournament_players';
    $seats_table   = $wpdb->prefix . 'tdwp_tournament_seats';

    // Query returns registration records that are NOT seated
    $registrations = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT tp.*, p.post_title as player_name
            FROM {$players_table} tp
            LEFT JOIN {$seats_table} ts ON tp.id = ts.registration_id
            INNER JOIN {$wpdb->posts} p ON tp.player_id = p.ID
            WHERE tp.tournament_id = %d
            AND tp.status IN ('paid', 'active')
            AND ts.registration_id IS NULL
            ORDER BY tp.registration_date ASC",
            $tournament_id
        )
    );

    return $registrations;
}
```

#### 2.3 Update `auto_seat_players()`

```php
public static function auto_seat_players( $tournament_id ) {
    $unseated_registrations = self::get_unseated_players( $tournament_id );

    if ( empty( $unseated_registrations ) ) {
        return array(
            'success'      => true,
            'seated_count' => 0,
            'message'      => __( 'No unseated players to seat', 'poker-tournament-import' ),
        );
    }

    $seated_count = 0;
    $errors       = array();

    foreach ( $unseated_registrations as $registration ) {
        $seat = self::find_optimal_seat( $tournament_id );

        if ( ! $seat ) {
            $display_name = $registration->player_name;
            if ( $registration->entry_number > 1 ) {
                $display_name .= ' (Entry #' . $registration->entry_number . ')';
            }

            $errors[] = sprintf(
                __( 'No available seat for %s', 'poker-tournament-import' ),
                $display_name
            );
            continue;
        }

        $success = self::move_player( $registration->id, $seat->table_id, $seat->seat_number );

        if ( $success ) {
            ++$seated_count;
        } else {
            $errors[] = sprintf(
                __( 'Failed to seat %s', 'poker-tournament-import' ),
                $registration->player_name
            );
        }
    }

    return array(
        'success'      => true,
        'seated_count' => $seated_count,
        'errors'       => $errors,
        'message'      => sprintf(
            __( 'Seated %d player(s)', 'poker-tournament-import' ),
            $seated_count
        ),
    );
}
```

### Phase 3: Update AJAX Handlers

**File**: `class-tournament-manager-ajax.php`

Find the `move_player` AJAX handler and update to use `registration_id`:

```php
public static function move_player() {
    self::verify_request();

    $registration_id = isset( $_POST['registration_id'] ) ? intval( $_POST['registration_id'] ) : 0;
    $to_table_id     = isset( $_POST['to_table_id'] ) ? intval( $_POST['to_table_id'] ) : 0;
    $to_seat_number  = isset( $_POST['to_seat_number'] ) ? intval( $_POST['to_seat_number'] ) : 0;

    if ( ! $registration_id || ! $to_table_id || ! $to_seat_number ) {
        wp_send_json_error( array( 'message' => __( 'Invalid parameters', 'poker-tournament-import' ) ) );
    }

    $success = TDWP_Seat_Manager::move_player( $registration_id, $to_table_id, $to_seat_number );

    if ( $success ) {
        wp_send_json_success( array( 'message' => __( 'Player moved successfully', 'poker-tournament-import' ) ) );
    } else {
        wp_send_json_error( array( 'message' => __( 'Failed to move player', 'poker-tournament-import' ) ) );
    }
}
```

### Phase 4: Update Frontend JavaScript

**File**: `assets/js/table-manager.js`

Update `movePlayer()` to send `registration_id`:

```javascript
movePlayer: function(registrationId, toTableId, toSeatNumber) {
    this.ajaxCall('tdwp_tm_move_player', {
        registration_id: registrationId,
        to_table_id: toTableId,
        to_seat_number: toSeatNumber
    }, (response) => {
        location.reload();
    });
}
```

Update drag-and-drop to use `data-registration-id`:

```javascript
const registrationId = $dragged.data('registration-id');
```

### Phase 5: Update Templates

**File**: `admin/tabs/tables-tab.php`

Ensure player rows include `data-registration-id` attribute.

## Testing Plan

1. ✅ Add 3 players to tournament
2. ✅ Click "Auto Seat" - verify all 3 are seated
3. ✅ Add player with re-entry (Entry #1 and Entry #2)
4. ✅ Auto-seat - verify BOTH entries are seated
5. ✅ Manual drag-drop - verify works with re-entries
6. ✅ Verify migration works on existing installations

## Version Update

- **Version**: 3.2.0-beta5
- **Files to update**:
  - poker-tournament-import.php (header + constant)
- **ZIP**: poker-tournament-import-v3.2.0-beta5.zip
