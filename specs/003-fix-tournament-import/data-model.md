# Data Model: Fix Tournament Import Button and Public Import Function

**Feature**: 003-fix-tournament-import
**Date**: 2025-12-29

## Overview

This feature fixes regressions in existing functionality. No new data structures are introduced - we are restoring missing access to existing WordPress data structures.

## Existing Data Entities

### Tournament Post (WordPress Custom Post Type)

**Type**: `tournament` (Custom Post Type)
**Storage**: WordPress `wp_posts` table with post_type='tournament'
**Registration**: `includes/class-post-types.php`

**Key Attributes**:
- `ID`: WordPress post ID (primary key)
- `post_title`: Tournament name
- `post_content`: Tournament description/details
- `post_date`: Tournament date
- `post_status`: 'publish', 'draft', 'pending', etc.
- `post_author`: WordPress user ID who imported/created

**Relationships**:
- Has many: Tournament players (custom table)
- Belongs to: Tournament series (taxonomy)
- Belongs to: Season (taxonomy)

### Tournament Import Request (Transient State)

**Type**: PHP Session / WordPress Transient
**Storage**: Transient during upload process
**Lifecycle**: Created on file upload, destroyed after processing

**Key Attributes**:
- `file`: Uploaded .tdt file path
- `user_id`: WordPress user ID performing import
- `timestamp`: Upload timestamp
- `status`: 'uploading', 'parsing', 'creating', 'complete', 'error'
- `error_message`: Error details if status='error'

**State Transitions**:
```
uploading → parsing → creating → complete
     ↓         ↓        ↓
   error     error    error
```

### Import Error Log

**Type**: WordPress Error Log / Custom Logging
**Storage**: `wp-content/debug.log` or custom database table
**Purpose**: Track failed import attempts for troubleshooting

**Key Attributes**:
- `timestamp`: When error occurred
- `user_id`: User who attempted import
- `file_name`: Name of uploaded file
- `error_code`: Error identifier
- `error_message`: Detailed error message
- `stack_trace`: PHP stack trace (if available)
- `request_data`: POST data for debugging

## WordPress Database Tables

### Existing Tables (No Changes Required)

**wp_posts**:
- Stores tournament post data
- Custom post type: 'tournament'
- Standard WordPress fields used

**wp_postmeta**:
- Tournament metadata
- Key tournament attributes stored as post meta
- Examples: _tournament_date, _tournament_buyin, _tournament_prize_pool

**wp_poker_tournament_players** (Custom Table):
- Player results for each tournament
- Linked by post_id (tournament ID)

**wp_poker_statistics** (Custom Table):
- Aggregated player statistics
- Data mart for performance

## Validation Rules

### File Upload Validation

**File Extension**:
- Must be: `.tdt`
- Validation: Check `pathinfo($file['name'], PATHINFO_EXTENSION)`

**File Size**:
- Maximum: PHP `upload_max_filesize` setting
- Validation: Check `$file['size']` before processing

**File Format**:
- Must be valid Tournament Director format
- Validation: Parse and verify structure
- Fallback: Show user-friendly error on parse failure

### User Capability Validation

**Admin Import**:
- Required capability: `manage_options`
- Validation: `current_user_can('manage_options')`
- Fallback: Show "Access Denied" message

**Public Import**:
- Required capability: None (publicly accessible)
- Validation: None required
- Rate limiting: Consider adding for abuse prevention

### Security Validation

**Nonce Verification**:
- Required for all AJAX requests
- Validation: `check_ajax_referer('poker_import_nonce', 'nonce')`
- Fallback: Show "Security verification failed" message

**Input Sanitization**:
- All user inputs sanitized
- Functions: `sanitize_text_field()`, `wp_kses_post()`
- Validation: Applied before database insert/update

## Data Flow

### Admin Import Flow

```
1. User navigates to Admin Dashboard → Poker Tournaments → Import
2. WordPress renders import form (menu callback)
3. User selects .tdt file and submits form
4. AJAX request sent to: wp_ajax_poker_import_tournament
5. Server verifies nonce and capabilities
6. Server validates file upload
7. Parser processes .tdt file
8. Tournament post created in wp_posts
9. Players inserted into wp_poker_tournament_players
10. AJAX response: success/error message
11. User sees confirmation or error
```

### Public Import Flow

```
1. User visits public statistics page
2. WordPress renders page with [poker_tournament_import] shortcode
3. Shortcode displays import form
4. User selects .tdt file and submits form
5. AJAX request sent to: wp_ajax_nopriv_poker_import_public_tournament
6. Server verifies nonce
7. Server validates file upload
8. Parser processes .tdt file
9. Tournament post created in wp_posts (status='publish' or configured default)
10. Players inserted into wp_poker_tournament_players
11. AJAX response: success/error message
12. User sees confirmation or error on page
```

## Error States

### File Upload Errors
- File too large: Show max file size
- Wrong file type: Show ".tdt files only"
- Upload failed: Show WordPress error message

### Parsing Errors
- Invalid .tdt format: Show "Invalid file format"
- Corrupted data: Show "File appears corrupted"
- Missing required data: Show "Tournament data incomplete"

### Database Errors
- Connection failed: Show "Database error, please try again"
- Insert failed: Log error, show "Failed to save tournament"

### Security Errors
- Nonce verification failed: Show "Security verification failed"
- Capability check failed: Show "Access denied" (admin only)

## No Schema Changes

This feature does NOT require any database schema changes. All data structures already exist in the WordPress database. We are restoring access to existing functionality, not creating new data structures.
