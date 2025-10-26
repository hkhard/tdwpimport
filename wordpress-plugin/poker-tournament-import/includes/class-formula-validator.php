<?php
/**
 * Formula Validator Class
 *
 * Handles Tournament Director formula validation, parsing, and calculation
 * with support for customizable formulas and validation against TD specification
 */

// Prevent direct file access
if (!defined('ABSPATH')) {
    exit;
}

class Poker_Tournament_Formula_Validator {

    /**
     * Available Tournament Director variables and functions
     * Complete TD v3.7.2+ specification with 145+ variables
     */
    private $td_variables = array(
        // TOURNAMENT INFORMATION VARIABLES (115+)
        // Add-on related variables
        'addOnsAllowed' => array('type' => 'bool', 'description' => '1 (true) if add-ons are currently allowed, 0 (false) otherwise'),
        'addOnsLastRound' => array('type' => 'int', 'description' => 'The last (final) round that add-ons are allowed'),
        'addOnsLeft' => array('type' => 'int', 'description' => 'The number of add-ons remaining'),
        'addOnsMaxPerPlayer' => array('type' => 'int', 'description' => 'The maximum number of add-ons allowed per-player'),
        'addOnsMaxTotal' => array('type' => 'int', 'description' => 'The maximum total number of add-ons allowed (for all players)'),
        'addOnsMinPlayers' => array('type' => 'int', 'description' => 'The minimum number of players that must be in the tournament for add-ons to be allowed'),
        'addOnsOver' => array('type' => 'bool', 'description' => '1 (true) if the add-on period has expired, 0 (false) otherwise'),
        'addOnsSecondsLeft' => array('type' => 'int', 'description' => 'The amount of time, in seconds, remaining until the add-on period ends'),

        // Blind and ante variables
        'ante' => array('type' => 'decimal', 'description' => 'The amount of the ante in the current round'),
        'bigBlind' => array('type' => 'decimal', 'description' => 'The amount of the big blind in the current round'),
        'smallBlind' => array('type' => 'decimal', 'description' => 'The amount of the small blind in the current round'),
        'firstAnteAmount' => array('type' => 'decimal', 'description' => 'The ante amount in the first round with a non-zero ante'),
        'firstAnteRound' => array('type' => 'int', 'description' => 'The round number of the first round with a non-zero ante'),

        // Bounty variables
        'bountyTotal' => array('type' => 'decimal', 'description' => 'The total amount of money collected for bounty chips'),
        'restrictBounties' => array('type' => 'bool', 'description' => '1 (true) if "Restrict bounties" is currently checked, 0 (false) otherwise'),
        'usePlayerBountyChips' => array('type' => 'bool', 'description' => '1 (true) if "Use player bounty chips" is currently checked, 0 (false) otherwise'),

        // Break and round variables
        'breakNum' => array('type' => 'int', 'description' => 'The current break number, or 0 if the first break has not yet occurred'),
        'isBreak' => array('type' => 'bool', 'description' => '1 (true) if currently in a break, 0 (false) if currently in a round'),
        'isRound' => array('type' => 'bool', 'description' => '1 (true) if currently in a round, 0 (false) if currently in a break'),
        'hasMoreBreaks' => array('type' => 'bool', 'description' => '1 (true) if the schedule has at least one break after the current level'),
        'hasMoreRounds' => array('type' => 'bool', 'description' => '1 (true) if the schedule has at least one round after the current level'),
        'nextChipUp' => array('type' => 'bool', 'description' => '1 (true) if the next break has been designated as a Chip Up break'),
        'nextIsBreak' => array('type' => 'bool', 'description' => '1 (true) if the next level in the schedule is a break'),
        'nextIsRound' => array('type' => 'bool', 'description' => '1 (true) if the next level in the schedule is a round'),

        // Buyin and entry variables
        'buyins' => array('type' => 'int', 'description' => 'The number of players that have bought-in to the tournament (aliases: numberofplayers, n)'),
        'n' => array('type' => 'int', 'description' => 'Total number of players (alias for buyins)'),
        'numberofplayers' => array('type' => 'int', 'description' => 'Total number of players (alias for buyins)'),

        // Bust-out tracking
        'bustsUntilFinalTable' => array('type' => 'int', 'description' => 'The number of players that must bust out before all remaining players will be at the final table'),
        'bustsUntilMoney' => array('type' => 'int', 'description' => 'The number of players that must bust out before all remaining players will be "in the money"'),

        // Chip variables
        'chipCount' => array('type' => 'decimal', 'description' => 'The current chip count (total chips in play; includes any adjustment)'),
        'chipCountAdjustment' => array('type' => 'decimal', 'description' => 'The value of the "Adjust chip count by" field on the Game tab'),
        'chipUp' => array('type' => 'bool', 'description' => '1 (true) if the current level is a break and has been designated as a Chip Up break'),
        'unadjustedChipCount' => array('type' => 'decimal', 'description' => 'The current chip count not including any adjustment as designated on the Game tab'),

        // Clock and time variables
        'clockPaused' => array('type' => 'bool', 'description' => '1 (true) if the tournament clock is currently paused, 0 (false) otherwise'),
        'clockPausedSeconds' => array('type' => 'int', 'description' => 'The number of seconds for which the tournament clock has been paused'),
        'secondsElapsed' => array('type' => 'int', 'description' => 'The number of seconds that have elapsed since the start of the current level'),
        'secondsLeft' => array('type' => 'int', 'description' => 'The number of seconds remaining on the tournament clock'),
        'time' => array('type' => 'int', 'description' => 'The current time-of-day (seconds since January 1, 1970)'),
        'timeOfDay' => array('type' => 'int', 'description' => 'The current time-of-day, in military-style time, including seconds'),
        'lastPlayerBustOutTime' => array('type' => 'int', 'description' => 'The time-of-day of the most recent player bust-out'),
        'lastPlayerMoveTime' => array('type' => 'int', 'description' => 'The time-of-day that the last player movement suggestion was accepted'),
        'lastScreenChangeTime' => array('type' => 'int', 'description' => 'The time-of-day of the most recent screen change'),

        // Default costs and fees
        'defaultAddOnChips' => array('type' => 'decimal', 'description' => 'The chips received for an add-on, as listed on the Game tab'),
        'defaultAddOnFee' => array('type' => 'decimal', 'description' => 'The fee (price) of an add-on, as listed on the Game tab'),
        'defaultAddOnRake' => array('type' => 'decimal', 'description' => 'The rake applied to add-ons, as listed on the Game tab'),
        'defaultBuyinChips' => array('type' => 'decimal', 'description' => 'The chips received at buy-in, as listed on the Game tab'),
        'defaultBuyinFee' => array('type' => 'decimal', 'description' => 'The fee (price) of a buy-in, as listed on the Game tab'),
        'defaultBuyinRake' => array('type' => 'decimal', 'description' => 'The rake applied to buy-ins, as listed on the Game tab'),
        'defaultRebuyChips' => array('type' => 'decimal', 'description' => 'The chips received for a rebuy, as listed on the Game tab'),
        'defaultRebuyFee' => array('type' => 'decimal', 'description' => 'The fee (price) of a rebuy, as listed on the Game tab'),
        'defaultRebuyRake' => array('type' => 'decimal', 'description' => 'The rake applied to rebuys, as listed on the Game tab'),

        // Financial and rake variables
        'fixedRake' => array('type' => 'decimal', 'description' => 'The portion of the Fixed Rake that can be attributed to this player'),
        'guaranteedPot' => array('type' => 'decimal', 'description' => 'The guaranteed pot amount, as listed on the Game tab'),
        'houseAdds' => array('type' => 'decimal', 'description' => 'The amount that must be added to the pot to reach the guaranteed pot amount'),
        'houseContribution' => array('type' => 'decimal', 'description' => 'The house contribution amount, as listed on the Game tab'),
        'pot' => array('type' => 'decimal', 'description' => 'The current prize pool or pot (aliases: prizepool, pp)'),
        'prizepool' => array('type' => 'decimal', 'description' => 'The current prize pool (alias for pot)'),
        'pp' => array('type' => 'decimal', 'description' => 'The current prize pool (alias for pot)'),
        'preGuaranteedPot' => array('type' => 'decimal', 'description' => 'The pot amount as computed before adding any to reach the specified guaranteed pot'),
        'totalFixedRake' => array('type' => 'decimal', 'description' => 'The total amount collected by the Fixed Rake'),

        // Game type and name variables
        'gameName' => array('type' => 'string', 'description' => 'The name of the game for the current round'),
        'gameType' => array('type' => 'int', 'description' => 'A number indicating the game type: 0=limit, 1=pot limit, 2=no limit'),
        'nextGameName' => array('type' => 'string', 'description' => 'The name of the game for the next round'),
        'nextGameType' => array('type' => 'int', 'description' => 'Game type for next round: 0=limit, 1=pot limit, 2=no limit'),

        // Level and round variables
        'level' => array('type' => 'int', 'description' => 'The current level number'),
        'levelDuration' => array('type' => 'int', 'description' => 'The number of minutes the current level is configured to last'),
        'nextLevelDuration' => array('type' => 'int', 'description' => 'The number of minutes the next level is configured to last'),
        'roundNum' => array('type' => 'int', 'description' => 'The current round number'),

        // Limit variables (8 limit fields)
        'limit1' => array('type' => 'decimal', 'description' => 'The amount of the limit1 value in the current round'),
        'limit2' => array('type' => 'decimal', 'description' => 'The amount of the limit2 value in the current round'),
        'limit3' => array('type' => 'decimal', 'description' => 'The amount of the limit3 value in the current round'),
        'limit4' => array('type' => 'decimal', 'description' => 'The amount of the limit4 value in the current round'),
        'limit5' => array('type' => 'decimal', 'description' => 'The amount of the limit5 value in the current round'),
        'limit6' => array('type' => 'decimal', 'description' => 'The amount of the limit6 value in the current round'),
        'limit7' => array('type' => 'decimal', 'description' => 'The amount of the limit7 value in the current round'),
        'limit8' => array('type' => 'decimal', 'description' => 'The amount of the limit8 value in the current round'),

        // Next round variables
        'nextAnte' => array('type' => 'decimal', 'description' => 'The amount of the ante in the next round'),
        'nextBigBlind' => array('type' => 'decimal', 'description' => 'The amount of the big blind in the next round'),
        'nextSmallBlind' => array('type' => 'decimal', 'description' => 'The amount of the small blind in the next round'),
        'nextLimit1' => array('type' => 'decimal', 'description' => 'The amount of the limit1 in the next round'),
        'nextLimit2' => array('type' => 'decimal', 'description' => 'The amount of the limit2 in the next round'),
        'nextLimit3' => array('type' => 'decimal', 'description' => 'The amount of the limit3 in the next round'),
        'nextLimit4' => array('type' => 'decimal', 'description' => 'The amount of the limit4 in the next round'),
        'nextLimit5' => array('type' => 'decimal', 'description' => 'The amount of the limit5 in the next round'),
        'nextLimit6' => array('type' => 'decimal', 'description' => 'The amount of the limit6 in the next round'),
        'nextLimit7' => array('type' => 'decimal', 'description' => 'The amount of the limit7 in the next round'),
        'nextLimit8' => array('type' => 'decimal', 'description' => 'The amount of the limit8 in the next round'),

        // League variables
        'numberOfLeagueMembers' => array('type' => 'int', 'description' => 'The number of players in tournament who are league members (aliases: nm)'),
        'nm' => array('type' => 'int', 'description' => 'Number of league members (alias for numberOfLeagueMembers)'),

        // Money and prize variables
        'inTheMoneyRank' => array('type' => 'int', 'description' => 'The highest rank necessary for player to be "in the money" (aliases: mr)'),
        'mr' => array('type' => 'int', 'description' => 'Money rank (alias for inTheMoneyRank)'),

        // Player count variables
        'playersLeft' => array('type' => 'int', 'description' => 'The number of players currently still in the tournament (aliases: players)'),
        'players' => array('type' => 'int', 'description' => 'Players remaining (alias for playersLeft)'),

        // Rebuy variables
        'rebuys' => array('type' => 'int', 'description' => 'Total number of rebuys'),
        'rebuysAllowed' => array('type' => 'bool', 'description' => '1 (true) if rebuys are currently allowed, 0 (false) otherwise'),
        'rebuysLastRound' => array('type' => 'int', 'description' => 'The last (final) round that rebuys are allowed'),
        'rebuysLeft' => array('type' => 'int', 'description' => 'The number of rebuys remaining'),
        'rebuysMaxPerPlayer' => array('type' => 'int', 'description' => 'The maximum number of rebuys allowed per-player'),
        'rebuysMaxTotal' => array('type' => 'int', 'description' => 'The maximum total number of rebuys allowed (for all players)'),
        'rebuysMinPlayers' => array('type' => 'int', 'description' => 'The minimum number of players that must be in tournament for rebuys to be allowed'),
        'rebuysOver' => array('type' => 'bool', 'description' => '1 (true) if the rebuy period has expired, 0 (false) otherwise'),
        'rebuysSecondsLeft' => array('type' => 'int', 'description' => 'The amount of time, in seconds, remaining until the rebuy period ends'),

        // State variables
        'state' => array('type' => 'int', 'description' => 'Tournament state: 0=not started, 1=countdown, 2=in progress, 3=ended'),
        'stateDesc' => array('type' => 'string', 'description' => 'State description: "before", "countdown", "inprogress", "after"'),

        // Table variables
        'tablesLeft' => array('type' => 'int', 'description' => 'The number of tables currently in use (aliases: tables)'),
        'tables' => array('type' => 'int', 'description' => 'Tables in use (alias for tablesLeft)'),

        // Total add-on variables
        'totalAddOns' => array('type' => 'int', 'description' => 'The total number of add-ons bought by all players (aliases: totalnumberofaddons, tna)'),
        'totalnumberofaddons' => array('type' => 'int', 'description' => 'Total add-ons (alias for totalAddOns)'),
        'tna' => array('type' => 'int', 'description' => 'Total add-ons (alias for totalAddOns)'),
        'totalAddOnsAmount' => array('type' => 'decimal', 'description' => 'The total amount collected from all add-ons'),
        'totalAddOnsChips' => array('type' => 'decimal', 'description' => 'The total number of chips received from all add-ons'),
        'totalAddOnsRake' => array('type' => 'decimal', 'description' => 'The total amount raked from all add-ons'),

        // Total buyin variables
        'totalBuyinsAmount' => array('type' => 'decimal', 'description' => 'The total amount collected from all buy-ins'),
        'totalBuyinsChips' => array('type' => 'decimal', 'description' => 'The total number of chips received from all buy-ins'),
        'totalBuyinsRake' => array('type' => 'decimal', 'description' => 'The total amount raked from all buy-ins'),

        // Total rebuy variables
        'totalRebuys' => array('type' => 'int', 'description' => 'The total number of rebuys bought by all players (aliases: totalnumberofrebuys, tnr)'),
        'totalnumberofrebuys' => array('type' => 'int', 'description' => 'Total rebuys (alias for totalRebuys)'),
        'tnr' => array('type' => 'int', 'description' => 'Total rebuys (alias for totalRebuys)'),
        'totalRebuysAmount' => array('type' => 'decimal', 'description' => 'The total amount collected from all rebuys'),
        'totalRebuysChips' => array('type' => 'decimal', 'description' => 'The total number of chips received from all rebuys'),
        'totalRebuysRake' => array('type' => 'decimal', 'description' => 'The total amount raked from all rebuys'),

        // PLAYER INFORMATION VARIABLES (30+)
        // Add-on player variables
        'addOnCost' => array('type' => 'decimal', 'description' => 'The total amount the player paid for all add-ons (aliases: ac)'),
        'ac' => array('type' => 'decimal', 'description' => 'Add-on cost (alias for addOnCost)'),
        'addOnRake' => array('type' => 'decimal', 'description' => 'The total amount raked from all add-ons purchased by player (aliases: ar)'),
        'ar' => array('type' => 'decimal', 'description' => 'Add-on rake (alias for addOnRake)'),

        // Bounty player variables
        'bountyChipCost' => array('type' => 'decimal', 'description' => 'The amount the player paid for all bounty chips purchased (aliases: bcc)'),
        'bcc' => array('type' => 'decimal', 'description' => 'Bounty chip cost (alias for bountyChipCost)'),
        'bountyMoneyKept' => array('type' => 'decimal', 'description' => 'Money paid for bounty chips but kept (aliases: bmk)'),
        'bmk' => array('type' => 'decimal', 'description' => 'Bounty money kept (alias for bountyMoneyKept)'),
        'bountyWinnings' => array('type' => 'decimal', 'description' => 'Money won by busting other players (aliases: bw)'),
        'bw' => array('type' => 'decimal', 'description' => 'Bounty winnings (alias for bountyWinnings)'),

        // Buyin player variables
        'buyinCost' => array('type' => 'decimal', 'description' => 'The amount the player paid to buy-in (aliases: bc)'),
        'bc' => array('type' => 'decimal', 'description' => 'Buyin cost (alias for buyinCost)'),
        'buyinRake' => array('type' => 'decimal', 'description' => 'The amount raked from player buy-in (aliases: br)'),
        'br' => array('type' => 'decimal', 'description' => 'Buyin rake (alias for buyinRake)'),

        // Chip player variables
        'chipStack' => array('type' => 'decimal', 'description' => "The player's current chip stack, or chip count"),

        // Final table and money variables
        'finalTable' => array('type' => 'bool', 'description' => '1 (true) if this player made the final table, 0 (false) otherwise'),
        'inTheMoney' => array('type' => 'bool', 'description' => '1 (true) if player ranked "in the money" (aliases: m, placed)'),
        'm' => array('type' => 'bool', 'description' => 'In the money (alias for inTheMoney)'),
        'placed' => array('type' => 'bool', 'description' => 'Placed in money (alias for inTheMoney)'),

        // ID variables
        'ID' => array('type' => 'string', 'description' => "The player's ID field"),
        'internalID' => array('type' => 'int', 'description' => 'The internal ID used to uniquely identify this player'),

        // League player variables
        'inLeague' => array('type' => 'bool', 'description' => '1 (true) if player is a member of league, 0 (false) otherwise'),
        'leagueRank' => array('type' => 'int', 'description' => 'Ranks player relative to other league members, 0 if not in league'),

        // Number of actions
        'numberOfAddOns' => array('type' => 'int', 'description' => 'The number of add-ons for a player (aliases: addons, na)'),
        'addons' => array('type' => 'int', 'description' => 'Add-ons count (alias for numberOfAddOns)'),
        'na' => array('type' => 'int', 'description' => 'Add-ons count (alias for numberOfAddOns)'),
        'numberOfBountiesKept' => array('type' => 'int', 'description' => 'The number of bounty chips a player has kept (aliases: nbk)'),
        'nbk' => array('type' => 'int', 'description' => 'Bounties kept (alias for numberOfBountiesKept)'),
        'numberOfBountiesWon' => array('type' => 'int', 'description' => 'The number of bounty chips a player has won (aliases: nb)'),
        'nb' => array('type' => 'int', 'description' => 'Bounties won (alias for numberOfBountiesWon)'),
        'numberOfBountyChips' => array('type' => 'int', 'description' => 'The number of bounty chips the player bought (aliases: nbc)'),
        'nbc' => array('type' => 'int', 'description' => 'Bounty chips count (alias for numberOfBountyChips)'),
        'numberOfHits' => array('type' => 'int', 'description' => 'The number of hits a player has made (aliases: nh)'),
        'nh' => array('type' => 'int', 'description' => 'Hits count (alias for numberOfHits)'),
        'numberOfRebuys' => array('type' => 'int', 'description' => 'The number of rebuys for a player (aliases: nr)'),
        'nr' => array('type' => 'int', 'description' => 'Rebuys count (alias for numberOfRebuys)'),

        // Player status and position
        'playersAtSameTable' => array('type' => 'int', 'description' => 'The number of players at same table (including this player)'),
        'playingTime' => array('type' => 'int', 'description' => 'The number of seconds this player was active in the tournament'),
        'position' => array('type' => 'int', 'description' => 'The position of a player (inverse of rank, order of bust-out)'),

        // Prize and winnings
        'prizeWinnings' => array('type' => 'decimal', 'description' => 'Money won by qualifying for one or more prizes (aliases: pw)'),
        'pw' => array('type' => 'decimal', 'description' => 'Prize winnings (alias for prizeWinnings)'),

        // Rank variables
        'rank' => array('type' => 'int', 'description' => 'The rank of a player (aliases: r)'),
        'r' => array('type' => 'int', 'description' => 'Player rank (alias for rank)'),

        // Rebuy player variables
        'rebuyCost' => array('type' => 'decimal', 'description' => 'The total amount player paid for all rebuys (aliases: rc)'),
        'rc' => array('type' => 'decimal', 'description' => 'Rebuy cost (alias for rebuyCost)'),
        'rebuyRake' => array('type' => 'decimal', 'description' => 'The total amount raked from all rebuys (aliases: rr)'),
        'rr' => array('type' => 'decimal', 'description' => 'Rebuy rake (alias for rebuyRake)'),

        // Round out
        'roundOut' => array('type' => 'int', 'description' => 'The round in which this player busted out (aliases: ro)'),
        'ro' => array('type' => 'int', 'description' => 'Round out (alias for roundOut)'),

        // Total costs and winnings
        'take' => array('type' => 'decimal', 'description' => 'The total profit for this player (winnings minus paid) (aliases: t)'),
        't' => array('type' => 'decimal', 'description' => 'Player take (alias for take)'),
        'totalCost' => array('type' => 'decimal', 'description' => 'The total amount player paid for participating (aliases: tc)'),
        'tc' => array('type' => 'decimal', 'description' => 'Total cost (alias for totalCost)'),
        'totalRake' => array('type' => 'decimal', 'description' => 'The total amount raked from all money player paid (aliases: tr)'),
        'tr' => array('type' => 'decimal', 'description' => 'Total rake (alias for totalRake)'),
        'totalWinnings' => array('type' => 'decimal', 'description' => 'The total amount of money won by player (aliases: tw)'),
        'tw' => array('type' => 'decimal', 'description' => 'Total winnings (alias for totalWinnings)'),

        // LEGACY/CALCULATED VARIABLES (maintained for backward compatibility)
        'monies' => array('type' => 'decimal', 'description' => 'Total money in pot (calculated)'),
        'avgBC' => array('type' => 'decimal', 'description' => 'Average buy-in cost (calculated)'),
        'numberofHits' => array('type' => 'int', 'description' => 'Number of eliminations (alias for numberOfHits)'),
        'place' => array('type' => 'int', 'description' => 'Player finish position (alias for r)'),
        'entrants' => array('type' => 'int', 'description' => 'Total entrants (alias for n)'),
        'T33' => array('type' => 'int', 'description' => 'Round(n/3) - Top third cutoff (calculated)'),
        'T80' => array('type' => 'int', 'description' => 'Floor(n*0.9) - 80% cutoff (calculated)'),
        'points' => array('type' => 'decimal', 'description' => 'Calculated points (result variable)'),
        'temp' => array('type' => 'decimal', 'description' => 'Temporary variable for calculations'),
        'winnings' => array('type' => 'decimal', 'description' => 'Player winnings (alias for totalWinnings)'),
        'prizePool' => array('type' => 'decimal', 'description' => 'Total prize pool (alias for pot)'),
        'buyinAmount' => array('type' => 'decimal', 'description' => 'Buy-in amount (alias for defaultBuyinFee)'),
        'feeAmount' => array('type' => 'decimal', 'description' => 'Fee amount (calculated)'),
        'totalBuyInsAmount' => array('type' => 'decimal', 'description' => 'Total buy-ins amount (alias for totalBuyinsAmount)'),
    );

    /**
     * Mapping from our internal data keys to official Tournament Director variable names
     * This ensures formulas using TD specification names work correctly with our parsed data
     */
    private $td_variable_map = array(
        // Tournament Information Variables (our key => TD variable name)
        'total_players' => 'buyins',              // Also aliased as n, numberofplayers
        'finish_position' => 'rank',              // Also aliased as r
        'hits' => 'numberOfHits',                 // Also aliased as nh
        'total_money' => 'pot',                   // Also aliased as prizepool, pp
        'prize_pool' => 'pot',

        // Financial Variables
        'total_buyins_amount' => 'totalBuyinsAmount',
        'total_rebuys_amount' => 'totalRebuysAmount',
        'total_addons_amount' => 'totalAddOnsAmount',
        'buyin_amount' => 'defaultBuyinFee',
        'rebuy_amount' => 'defaultRebuyFee',
        'addon_amount' => 'defaultAddOnFee',
        'fee_amount' => 'fixedRake',

        // Player Information Variables (our key => TD variable name)
        'winnings' => 'prizeWinnings',            // Also aliased as pw
        'number_of_rebuys' => 'numberOfRebuys',   // Also aliased as rebuys, nr
        'number_of_addons' => 'numberOfAddOns',   // Also aliased as addons, na
        'chip_stack' => 'chipStack',
        'in_the_money' => 'inTheMoney',

        // Count Variables
        'total_rebuys' => 'totalRebuys',
        'total_addons' => 'totalAddOns',
        'players_remaining' => 'playersLeft',
    );

    /**
     * Available Tournament Director functions
     * Complete TD v3.7.2+ specification with 43+ functions
     */
    private $td_functions = array(
        // MATHEMATICAL FUNCTIONS
        'abs' => array('params' => 1, 'description' => 'Returns the absolute value of a number', 'example' => 'abs(-3) returns 3'),
        'acos' => array('params' => 1, 'description' => 'Returns the arccosine of a number (in radians)', 'example' => 'acos(.1) returns 1.4706289056333368'),
        'asin' => array('params' => 1, 'description' => 'Returns the arcsine of a number (in radians)', 'example' => 'asin(.1) returns 0.1001674211615598'),
        'atan' => array('params' => 1, 'description' => 'Returns the arctangent of a number (in radians)', 'example' => 'atan(.1) returns 0.09966865249116204'),
        'cos' => array('params' => 1, 'description' => 'The cosine of a number', 'example' => 'cos(1) returns 0.5403023058681398'),
        'exp' => array('params' => 1, 'description' => "E (Euler's constant) raised to the power of a number", 'example' => 'exp(1) returns 2.718281828459045'),
        'log' => array('params' => '1-2', 'description' => 'Natural logarithm of a number. Optional second parameter for base', 'example' => 'log(2) returns 0.6931471805599453'),
        'ln' => array('params' => 1, 'description' => 'Natural logarithm (alias of log)', 'example' => 'ln(2) returns 0.6931471805599453'),
        'log10' => array('params' => 1, 'description' => 'The logarithm (base 10) of a number', 'example' => 'log10(2) returns 0.30102999566398114'),
        'pow' => array('params' => 2, 'description' => 'An exponent of a number (base, exponent)', 'example' => 'pow(2, 5) returns 32'),
        'power' => array('params' => 2, 'description' => 'Same as pow() function', 'example' => 'power(5, 2) returns 25'),
        'random' => array('params' => 0, 'description' => 'A random number between 0 and 1', 'example' => 'random() returns value like 0.14851873902445567'),
        'sin' => array('params' => 1, 'description' => 'The sine of a number', 'example' => 'sin(1) returns 0.8414709848078965'),
        'sqrt' => array('params' => 1, 'description' => 'The square root of a number', 'example' => 'sqrt(16) returns 4'),
        'tan' => array('params' => 1, 'description' => 'The tangent of a number', 'example' => 'tan(1) returns 1.5574077246549023'),
        'triangle' => array('params' => 1, 'description' => 'Returns the triangle number for given value', 'example' => 'triangle(3) returns 6'),

        // ROUNDING FUNCTIONS
        'ceil' => array('params' => 1, 'description' => 'The ceiling - smallest integer greater than or equal to number', 'example' => 'ceil(2.2) returns 3'),
        'floor' => array('params' => 1, 'description' => 'The floor - largest integer less than or equal to number', 'example' => 'floor(2.2) returns 2'),
        'round' => array('params' => '1-2', 'description' => 'Rounds to precision (second param, default 0)', 'example' => 'round(1.55, 1) returns 1.6'),
        'roundUpToNearest' => array('params' => 2, 'description' => 'Rounds first param up to nearest multiple of second param', 'example' => 'roundUpToNearest(371, 25) returns 375'),
        'roundToNearest' => array('params' => 2, 'description' => 'Rounds first param to nearest multiple of second param', 'example' => 'roundToNearest(371, 50) returns 350'),
        'roundDownToNearest' => array('params' => 2, 'description' => 'Rounds first param down to nearest multiple of second param', 'example' => 'roundDownToNearest(371, 25) returns 350'),

        // CONDITIONAL FUNCTIONS
        'if' => array('params' => 3, 'description' => 'Conditional: if(condition, true_value, false_value)', 'example' => 'if(rank < 3, 10, 0)'),
        'switch' => array('params' => 'variable', 'description' => 'Switch on value with comparison pairs. First param is value, then pairs of (comparison, result)', 'example' => 'switch(3, 1, 10, 2, 20, 3, 30) returns 30'),
        'lswitch' => array('params' => 'variable', 'description' => 'Linear switch - first param is index (1-based), rest are result values', 'example' => 'lswitch(4, 10, 20, 30, 40) returns 40'),

        // LOGICAL FUNCTIONS
        'and' => array('params' => 2, 'description' => 'Logical AND', 'example' => '(10 > 5) and (20 != 10) returns true'),
        'or' => array('params' => 2, 'description' => 'Logical OR', 'example' => '(10 > 5) or (20 = 10) returns true'),
        'not' => array('params' => 1, 'description' => 'Logical NOT', 'example' => 'not(0) returns 1'),

        // LIST/ARRAY FUNCTIONS
        'sum' => array('params' => 'variable', 'description' => 'Returns the sum of all parameters. List may be provided', 'example' => 'sum(1, 2, 3, 4, 5, 6) returns 21'),
        'product' => array('params' => 'variable', 'description' => 'Returns the product of all parameters. List may be provided', 'example' => 'product(1, 2, 3, 4, 5, 6) returns 720'),
        'average' => array('params' => 'variable', 'description' => 'Returns the average of all parameters. List may be provided', 'example' => 'average(1, 2, 3, 4, 5, 6) returns 3.5'),
        'count' => array('params' => 'variable', 'description' => 'Returns count of parameters. List may be provided', 'example' => 'count(1, 2, 3, 4, 5, 6) returns 6'),
        'max' => array('params' => 2, 'description' => 'The greater of two numbers', 'example' => 'max(2, 5) returns 5'),
        'min' => array('params' => 2, 'description' => 'The lesser of two numbers', 'example' => 'min(2, 5) returns 2'),
        'top' => array('params' => 'variable', 'description' => 'Returns list of top N values. First param is count', 'example' => 'top(5, 1, 2, 3, 4, 5, 6) returns [2, 3, 4, 5, 6]'),
        'bottom' => array('params' => 'variable', 'description' => 'Returns list of bottom N values. First param is count', 'example' => 'bottom(5, 1, 2, 3, 4, 5, 6) returns [1, 2, 3, 4, 5]'),
        'oneof' => array('params' => 'variable', 'description' => 'Returns true if first param is in remaining params', 'example' => 'oneof(r, 2, 4, 12, 24)'),
        'index' => array('params' => 2, 'description' => 'Returns value of list at given index (0-based)', 'example' => 'index(scores, 0) returns first element'),
        'setListLength' => array('params' => 2, 'description' => 'Sets length of list by removing or adding zeros', 'example' => 'setListLength(scores, 5)'),

        // ASSIGNMENT FUNCTION
        'assign' => array('params' => 2, 'description' => 'Assigns value to variable. First param is variable name (quoted), second is value', 'example' => 'assign("a", 6)'),

        // PROFILE FUNCTIONS - Buyin Profiles
        'buyinProfileFee' => array('params' => '0-1', 'description' => 'Returns buy-in fee for profile (default if not specified)', 'example' => 'buyinProfileFee("Early Arrivers")'),
        'buyinProfileRake' => array('params' => '0-2', 'description' => 'Returns buy-in rake for profile and rake name', 'example' => 'buyinProfileRake("Early Arrivers", "Year End")'),
        'buyinProfileChips' => array('params' => '0-1', 'description' => 'Returns buy-in chips for profile', 'example' => 'buyinProfileChips("Early Arrivers")'),
        'buyinProfilePoints' => array('params' => '0-1', 'description' => 'Returns buy-in points for profile', 'example' => 'buyinProfilePoints("Early Arrivers")'),

        // PROFILE FUNCTIONS - Rebuy Profiles
        'rebuyProfileFee' => array('params' => '0-1', 'description' => 'Returns rebuy fee for profile', 'example' => 'rebuyProfileFee("Early Arrivers")'),
        'rebuyProfileRake' => array('params' => '0-2', 'description' => 'Returns rebuy rake for profile and rake name', 'example' => 'rebuyProfileRake("Early Arrivers", "Year End")'),
        'rebuyProfileChips' => array('params' => '0-1', 'description' => 'Returns rebuy chips for profile', 'example' => 'rebuyProfileChips("Early Arrivers")'),
        'rebuyProfilePoints' => array('params' => '0-1', 'description' => 'Returns rebuy points for profile', 'example' => 'rebuyProfilePoints("Early Arrivers")'),

        // PROFILE FUNCTIONS - Add-on Profiles
        'addOnProfileFee' => array('params' => '0-1', 'description' => 'Returns add-on fee for profile', 'example' => 'addOnProfileFee("Early Arrivers")'),
        'addOnProfileRake' => array('params' => '0-2', 'description' => 'Returns add-on rake for profile and rake name', 'example' => 'addOnProfileRake("Early Arrivers", "Year End")'),
        'addOnProfileChips' => array('params' => '0-1', 'description' => 'Returns add-on chips for profile', 'example' => 'addOnProfileChips("Early Arrivers")'),
        'addOnProfilePoints' => array('params' => '0-1', 'description' => 'Returns add-on points for profile', 'example' => 'addOnProfilePoints("Early Arrivers")'),

        // SPECIAL FUNCTIONS
        'totalForRake' => array('params' => '0-1', 'description' => 'Returns total collected for named rake (all rakes if not specified)', 'example' => 'totalForRake("Tournament of Champions")'),
    );

    /**
     * Default Tournament Director formulas
     */
    private $default_formulas = array(
        'tournament_points' => array(
            'name' => 'Tournament Points (PokerStars Formula)',
            'description' => 'PokerStars-based points with piecewise decay and threshold logic',
            'formula' => 'assign("points", if((T80 > r) and (T33 < r), round(baseFromT33 * decay) + hits, if(T33 >= r, baseAtRank + hits, 1 + hits)))',
            'dependencies' => array(
                'assign("nSafe", max(n, 1))',
                'assign("buyinsSafe", max(buyins, 1))',
                'assign("T33", round(nSafe / 3))',
                'assign("T80", floor(nSafe * 0.9))',
                'assign("monies", totalBuyinsAmount + totalRebuysAmount + totalAddOnsAmount)',
                'assign("avgBC", monies / buyinsSafe)',
                'assign("scale", 10 * sqrt(nSafe))',
                'assign("logTerm", 1 + log(avgBC + 0.25))',
                'assign("hits", numberofHits * 10)',
                'assign("baseAtRank", round((scale / sqrt(r)) * logTerm))',
                'assign("baseFromT33", round((scale / sqrt(T33 + 1)) * logTerm))',
                'assign("decay", pow(0.66, (r - T33)))'
            ),
            'category' => 'points'
        ),
        'simple_points' => array(
            'name' => 'Simple Points',
            'description' => 'Simple points system: 10 points per player eliminated',
            'formula' => 'assign("points", numberofHits * 10)',
            'dependencies' => array(
                'assign("numberofHits", hits)'
            ),
            'category' => 'points'
        ),
        'linear_points' => array(
            'name' => 'Linear Points',
            'description' => 'Linear points: (n - r + 1) * 10',
            'formula' => 'assign("points", (n - r + 1) * 10)',
            'dependencies' => array(),
            'category' => 'points'
        ),
        'exponential_points' => array(
            'name' => 'Exponential Points',
            'description' => 'Exponential decay: 100 * pow(0.9, r-1)',
            'formula' => 'assign("points", round(100 * pow(0.9, r-1)))',
            'dependencies' => array(),
            'category' => 'points'
        ),
        'season_total' => array(
            'name' => 'Season Total Points',
            'description' => 'Sum of all tournament points in season',
            'formula' => 'assign("points", sum(tournament_points))',
            'dependencies' => array(),
            'category' => 'season'
        ),
        'season_best' => array(
            'name' => 'Season Best Points',
            'description' => 'Best single tournament points in season',
            'formula' => 'assign("points", max(tournament_points))',
            'dependencies' => array(),
            'category' => 'season'
        )
    );

    /**
     * Constructor
     */
    public function __construct() {
        add_action('admin_init', array($this, 'register_formula_settings'));
        add_action('admin_menu', array($this, 'add_formula_admin_menu'), 11); // Priority 11: run after parent menu (priority 10)
    }

    /**
     * Validate formula syntax and variables
     */
    public function validate_formula($formula, $context = 'tournament') {
        $errors = array();
        $warnings = array();

        // Check for balanced parentheses
        if (!$this->check_balanced_parentheses($formula)) {
            $errors[] = 'Unbalanced parentheses in formula';
        }

        // Check for balanced quotes
        if (!$this->check_balanced_quotes($formula)) {
            $errors[] = 'Unbalanced quotes in formula';
        }

        // Extract variables and functions
        $tokens = $this->tokenize_formula($formula);
        $used_variables = array();
        $used_functions = array();

        foreach ($tokens as $token) {
            if ($token['type'] === 'variable') {
                $used_variables[] = $token['value'];
            } elseif ($token['type'] === 'function') {
                $used_functions[] = $token['value'];
            }
        }

        // Validate variables
        foreach ($used_variables as $variable) {
            if (!isset($this->td_variables[$variable])) {
                $warnings[] = "Unknown variable: {$variable}";
            }
        }

        // Validate functions
        foreach ($used_functions as $function) {
            if (!isset($this->td_functions[$function])) {
                $errors[] = "Unknown function: {$function}";
            }
        }

        // Check for assignment statements
        if (strpos($formula, 'assign(') === false && $context === 'tournament') {
            $warnings[] = 'Formula should assign points value for tournament context';
        }

        // Context-specific validation
        if ($context === 'season') {
            $season_functions = array('sum', 'max', 'min', 'avg', 'count');
            $has_season_func = false;
            foreach ($season_functions as $func) {
                if (in_array($func, $used_functions)) {
                    $has_season_func = true;
                    break;
                }
            }
            if (!$has_season_func) {
                $warnings[] = 'Season formulas should use aggregation functions (sum, max, min, avg)';
            }
        }

        return array(
            'valid' => empty($errors),
            'errors' => $errors,
            'warnings' => $warnings,
            'used_variables' => $used_variables,
            'used_functions' => $used_functions
        );
    }

    /**
     * Check for balanced parentheses
     */
    private function check_balanced_parentheses($formula) {
        $count = 0;
        $length = strlen($formula);

        for ($i = 0; $i < $length; $i++) {
            $char = $formula[$i];
            if ($char === '(') {
                $count++;
            } elseif ($char === ')') {
                $count--;
                if ($count < 0) {
                    return false;
                }
            }
        }

        return $count === 0;
    }

    /**
     * Check for balanced quotes
     */
    private function check_balanced_quotes($formula) {
        $count = 0;
        $length = strlen($formula);
        $escaped = false;

        for ($i = 0; $i < $length; $i++) {
            $char = $formula[$i];
            if ($char === '\\' && !$escaped) {
                $escaped = true;
                continue;
            }
            if ($char === '"' && !$escaped) {
                $count++;
            }
            $escaped = false;
        }

        return $count % 2 === 0;
    }

    /**
     * Tokenize formula into variables, functions, numbers, and operators
     */
    private function tokenize_formula($formula) {
        $tokens = array();
        $length = strlen($formula);
        $i = 0;

        while ($i < $length) {
            $char = $formula[$i];

            // Skip whitespace
            if (ctype_space($char)) {
                $i++;
                continue;
            }

            // Numbers
            if (ctype_digit($char) || ($char === '.' && $i + 1 < $length && ctype_digit($formula[$i + 1]))) {
                $start = $i;
                while ($i < $length && (ctype_digit($formula[$i]) || $formula[$i] === '.')) {
                    $i++;
                }
                $tokens[] = array('type' => 'number', 'value' => substr($formula, $start, $i - $start));
                continue;
            }

            // Variables and functions (alphabetic characters)
            if (ctype_alpha($char)) {
                $start = $i;
                while ($i < $length && (ctype_alpha($formula[$i]) || ctype_digit($formula[$i]))) {
                    $i++;
                }
                $value = substr($formula, $start, $i - $start);

                // Check if it's a function (followed by parentheses)
                if ($i < $length && $formula[$i] === '(') {
                    $tokens[] = array('type' => 'function', 'value' => $value);
                } else {
                    $tokens[] = array('type' => 'variable', 'value' => $value);
                }
                continue;
            }

            // Operators and punctuation
            if (in_array($char, array('+', '-', '*', '/', '^', '=', '<', '>', '!', '&', '|', ',', '(', ')'))) {
                // Handle multi-character operators
                if ($i + 1 < $length) {
                    $two_char = $char . $formula[$i + 1];
                    if (in_array($two_char, array('==', '!=', '<=', '>=', '&&', '||'))) {
                        $tokens[] = array('type' => 'operator', 'value' => $two_char);
                        $i += 2;
                        continue;
                    }
                }

                $tokens[] = array('type' => 'operator', 'value' => $char);
                $i++;
                continue;
            }

            // Strings
            if ($char === '"') {
                $start = $i + 1;
                $end = strpos($formula, '"', $start);
                if ($end === false) {
                    $tokens[] = array('type' => 'error', 'value' => 'Unterminated string');
                    break;
                }
                $tokens[] = array('type' => 'string', 'value' => substr($formula, $start, $end - $start));
                $i = $end + 1;
                continue;
            }

            $i++;
        }

        return $tokens;
    }

    /**
     * Calculate formula result with given data
     */
    public function calculate_formula($formula, $data, $context = 'tournament') {
        // Validate formula first
        $validation = $this->validate_formula($formula, $context);
        if (!$validation['valid']) {
            return array(
                'success' => false,
                'error' => implode(', ', $validation['errors']),
                'result' => null
            );
        }

        try {
            // Prepare variables
            $variables = $this->prepare_variables($data, $context);

            // Debug: Log variables prepared
            if (current_user_can('manage_options') && $context === 'season') {
                error_log("=== Formula Validator Debug START ===");
                error_log("Variables prepared: " . implode(', ', array_keys($variables)));
                if (isset($variables['listpoints'])) {
                    error_log("listpoints array: " . json_encode($variables['listpoints']));
                } else {
                    error_log("ERROR: listpoints variable NOT SET!");
                }
            }

            // Process assignment statements first
            $processed_formula = $this->process_assignments($formula, $variables);

            // Debug: Log after assignment processing
            if (current_user_can('manage_options') && $context === 'season') {
                error_log("After assignments - variables: " . implode(', ', array_keys($variables)));
                if (isset($variables['season_points'])) {
                    error_log("season_points value: " . $variables['season_points']);
                } else {
                    error_log("WARNING: season_points variable NOT SET after assignments!");
                }
                if (isset($variables['lp'])) {
                    error_log("lp variable: " . json_encode($variables['lp']));
                }
                error_log("Processed formula (remaining): " . $processed_formula);
                error_log("=== Formula Validator Debug END ===");
            }

            // FIX v2.4.25: If no expression remains after processing assignments,
            // return the 'points' variable that was set by the last assignment
            // This handles formulas where the formula field itself contains an assignment
            $processed_formula = trim($processed_formula, "; \t\n\r\0\x0B");
            if (empty($processed_formula)) {
                // All statements were assignments - check for context-specific result variable
                if ($context === 'season' && isset($variables['season_points'])) {
                    $result = $variables['season_points'];
                } elseif (isset($variables['points'])) {
                    $result = $variables['points'];  // Backward compatibility
                } else {
                    $result = 1;  // Last resort fallback
                }
            } else {
                // Evaluate the final expression
                $result = $this->evaluate_expression($processed_formula, $variables);
            }

            return array(
                'success' => true,
                'result' => $result,
                'variables' => $variables,
                'warnings' => $validation['warnings']
            );

        } catch (Exception $e) {
            // Enhanced error reporting with full context
            Poker_Tournament_Import_Debug::log('Formula Calculation Error Details:');
            Poker_Tournament_Import_Debug::log('Error: ' . $e->getMessage());
            Poker_Tournament_Import_Debug::log('Formula: ' . $formula);
            Poker_Tournament_Import_Debug::log('Context: ' . $context);

            if (isset($variables)) {
                Poker_Tournament_Import_Debug::log('Variables provided:');
                foreach ($variables as $key => $value) {
                    if (is_array($value)) {
                        $value_str = json_encode($value);
                    } elseif (is_bool($value)) {
                        $value_str = $value ? 'true' : 'false';
                    } else {
                        $value_str = (string)$value;
                    }
                    Poker_Tournament_Import_Debug::log("  {$key} = {$value_str}");
                }
            }

            if (isset($data)) {
                Poker_Tournament_Import_Debug::log('Raw input data:');
                foreach ($data as $key => $value) {
                    if (is_array($value)) {
                        $value_str = json_encode($value);
                    } elseif (is_bool($value)) {
                        $value_str = $value ? 'true' : 'false';
                    } else {
                        $value_str = (string)$value;
                    }
                    Poker_Tournament_Import_Debug::log("  {$key} = {$value_str}");
                }
            }

            if (isset($processed_formula)) {
                Poker_Tournament_Import_Debug::log('Processed formula: ' . $processed_formula);
            }

            return array(
                'success' => false,
                'error' => $e->getMessage(),
                'result' => null,
                'debug_info' => array(
                    'formula' => $formula,
                    'context' => $context,
                    'variables' => $variables ?? null,
                    'processed_formula' => $processed_formula ?? null,
                    'exception_trace' => $e->getTraceAsString()
                )
            );
        }
    }

    /**
     * Prepare variables for calculation
     * Maps our internal data keys to official Tournament Director variable names
     */
    private function prepare_variables($data, $context) {
        $variables = array();

        // Map our internal keys to TD official variable names using the mapping table
        foreach ($this->td_variable_map as $our_key => $td_variable) {
            if (isset($data[$our_key])) {
                $value = $data[$our_key];

                // Type casting based on variable type from TD specification
                if (in_array($td_variable, ['buyins', 'rank', 'numberOfHits', 'numberOfRebuys', 'numberOfAddOns', 'totalRebuys', 'totalAddOns', 'playersLeft'])) {
                    $variables[$td_variable] = intval($value);
                } else if (in_array($td_variable, ['inTheMoney'])) {
                    $variables[$td_variable] = boolval($value);
                } else {
                    // Default to float for financial values
                    $variables[$td_variable] = floatval($value);
                }
            }
        }

        // Set safe defaults for critical TD variables if not provided
        if (!isset($variables['buyins'])) {
            $variables['buyins'] = intval($data['total_players'] ?? 1);
        }
        if (!isset($variables['rank'])) {
            $variables['rank'] = intval($data['finish_position'] ?? 1);
        }
        if (!isset($variables['numberOfHits'])) {
            $variables['numberOfHits'] = 0;
        }
        if (!isset($variables['pot'])) {
            $variables['pot'] = floatval($data['total_money'] ?? 0);
        }
        if (!isset($variables['totalBuyinsAmount'])) {
            $variables['totalBuyinsAmount'] = $variables['pot'];
        }
        if (!isset($variables['totalRebuysAmount'])) {
            $variables['totalRebuysAmount'] = 0;
        }
        if (!isset($variables['totalAddOnsAmount'])) {
            $variables['totalAddOnsAmount'] = 0;
        }
        if (!isset($variables['prizeWinnings'])) {
            $variables['prizeWinnings'] = floatval($data['winnings'] ?? 0);
        }
        if (!isset($variables['defaultBuyinFee'])) {
            $variables['defaultBuyinFee'] = floatval($data['buyin_amount'] ?? 0);
        }

        // Add all official TD aliases from specification
        // Tournament Information Variable Aliases
        $variables['n'] = $variables['buyins'];                    // Alias for buyins
        $variables['numberofplayers'] = $variables['buyins'];      // Alias for buyins
        $variables['r'] = $variables['rank'];                      // Alias for rank
        $variables['nh'] = $variables['numberOfHits'];             // Alias for numberOfHits
        $variables['prizepool'] = $variables['pot'];               // Alias for pot
        $variables['pp'] = $variables['pot'];                      // Alias for pot

        // Player Information Variable Aliases
        $variables['pw'] = $variables['prizeWinnings'];            // Alias for prizeWinnings
        if (isset($variables['numberOfRebuys'])) {
            $variables['rebuys'] = $variables['numberOfRebuys'];   // Alias for numberOfRebuys
            $variables['nr'] = $variables['numberOfRebuys'];       // Alias for numberOfRebuys
        }
        if (isset($variables['numberOfAddOns'])) {
            $variables['addons'] = $variables['numberOfAddOns'];   // Alias for numberOfAddOns
            $variables['na'] = $variables['numberOfAddOns'];       // Alias for numberOfAddOns
        }

        // Legacy aliases for backward compatibility
        $variables['place'] = $variables['rank'];
        $variables['entrants'] = $variables['buyins'];
        $variables['hits'] = $variables['numberOfHits'];
        $variables['winnings'] = $variables['prizeWinnings'];
        $variables['prizePool'] = $variables['pot'];
        $variables['buyinAmount'] = $variables['defaultBuyinFee'];

        // Computed helper variables
        $variables['monies'] = $variables['totalBuyinsAmount']
                             + $variables['totalRebuysAmount']
                             + $variables['totalAddOnsAmount'];

        // Average buy-in cost
        $variables['avgBC'] = $variables['buyins'] > 0 ? $variables['monies'] / $variables['buyins'] : 0;

        // Initialize calculation variables
        $variables['points'] = 1;
        $variables['temp'] = 0;

        // Add listpoints for season points calculation
        if (isset($data['tournament_points']) && is_array($data['tournament_points'])) {
            $variables['listpoints'] = $data['tournament_points'];
        }

        return $variables;
    }

    /**
     * Process assignment statements in formula
     * FIX #1: Stack-based parser to handle nested parentheses correctly
     */
    private function process_assignments($formula, &$variables) {
        // Process all assign() statements
        while (($pos = strpos($formula, 'assign(')) !== false) {
            // Find the matching closing parenthesis by counting depth
            $start = $pos + 7; // after "assign("
            $depth = 1;
            $i = $start;
            $length = strlen($formula);

            while ($i < $length && $depth > 0) {
                if ($formula[$i] === '(') {
                    $depth++;
                } elseif ($formula[$i] === ')') {
                    $depth--;
                }
                $i++;
            }

            if ($depth !== 0) {
                throw new Exception("Unmatched parentheses in assignment");
            }

            // Extract content between parentheses
            $content = substr($formula, $start, $i - $start - 1);

            // Split by comma at depth 0 to separate variable name from expression
            list($var_name, $expression) = $this->split_assignment($content);

            // Evaluate the expression
            $value = $this->evaluate_expression($expression, $variables);
            $variables[$var_name] = $value;

            // Remove the assignment from formula
            $formula = substr_replace($formula, '', $pos, $i - $pos);
        }

        return trim($formula);
    }

    /**
     * Split assignment content by comma at depth 0
     * Helper for stack-based assignment parser
     */
    private function split_assignment($content) {
        $depth = 0;
        $in_quotes = false;
        $length = strlen($content);

        for ($i = 0; $i < $length; $i++) {
            $char = $content[$i];

            // Handle quotes
            if ($char === '"' && ($i === 0 || $content[$i - 1] !== '\\')) {
                $in_quotes = !$in_quotes;
                continue;
            }

            // Skip if inside quotes
            if ($in_quotes) {
                continue;
            }

            // Track parentheses depth
            if ($char === '(') {
                $depth++;
            } elseif ($char === ')') {
                $depth--;
            } elseif ($char === ',' && $depth === 0) {
                // Found the comma separator at depth 0
                $var_part = substr($content, 0, $i);
                $expr_part = substr($content, $i + 1);

                // Extract variable name from quotes
                if (preg_match('/"([^"]+)"/', $var_part, $matches)) {
                    $var_name = $matches[1];
                } else {
                    throw new Exception("Invalid assignment - variable name must be quoted");
                }

                return array($var_name, trim($expr_part));
            }
        }

        throw new Exception("Invalid assignment format - expected: assign(\"varname\", expression)");
    }

    /**
     * Evaluate mathematical expression safely using AST-based evaluation
     * SECURITY: Replaces eval() with safe Abstract Syntax Tree evaluation
     * FIX #3: Preprocess TD logical operators before tokenization
     */
    private function evaluate_expression($expression, $variables) {
        try {
            // FIX #3: Convert Tournament Director logical operators to standard ones
            $expression = $this->preprocess_operators($expression);

            // Tokenize the expression
            $tokens = $this->tokenize_expression($expression);

            // Replace variables with their values in tokens
            $tokens = $this->substitute_variables($tokens, $variables);

            // Build Abstract Syntax Tree
            $ast = $this->build_ast($tokens);

            // Evaluate AST safely (no eval)
            $result = $this->evaluate_ast($ast, $variables);

            // Arrays are valid results (e.g., listpoints, arrays from top() function)
            if (is_array($result)) {
                return $result;
            }
            return is_finite($result) ? $result : 0;

        } catch (Exception $e) {
            throw new Exception(esc_html("Evaluation error: " . $e->getMessage()));
        }
    }

    /**
     * Preprocess Tournament Director operators
     * FIX #3: Convert 'and'/'or' to '&&'/'||' with word boundary checks
     */
    private function preprocess_operators($expression) {
        // Replace logical operators (word boundaries to avoid partial matches)
        // Use word boundaries \b to ensure we don't replace 'and' inside 'rand' or 'sand'
        $expression = preg_replace('/\band\b/', '&&', $expression);
        $expression = preg_replace('/\bor\b/', '||', $expression);
        return $expression;
    }

    /**
     * Tokenize expression into operators, operands, and function calls
     */
    private function tokenize_expression($expression) {
        $tokens = array();
        $length = strlen($expression);
        $i = 0;

        while ($i < $length) {
            $char = $expression[$i];

            // Skip whitespace
            if (ctype_space($char)) {
                $i++;
                continue;
            }

            // Numbers (including decimals)
            if (ctype_digit($char) || ($char === '.' && $i + 1 < $length && ctype_digit($expression[$i + 1]))) {
                $start = $i;
                $has_decimal = false;
                while ($i < $length && (ctype_digit($expression[$i]) || (!$has_decimal && $expression[$i] === '.'))) {
                    if ($expression[$i] === '.') {
                        $has_decimal = true;
                    }
                    $i++;
                }
                $tokens[] = array('type' => 'number', 'value' => floatval(substr($expression, $start, $i - $start)));
                continue;
            }

            // Variables and functions
            if (ctype_alpha($char) || $char === '_' || $char === '$') {
                $start = $i;
                while ($i < $length && (ctype_alnum($expression[$i]) || $expression[$i] === '_')) {
                    $i++;
                }
                $name = substr($expression, $start, $i - $start);

                // Skip whitespace after name
                while ($i < $length && ctype_space($expression[$i])) {
                    $i++;
                }

                // Check if it's a function (followed by '(')
                if ($i < $length && $expression[$i] === '(') {
                    $tokens[] = array('type' => 'function', 'value' => $name);
                } else {
                    $tokens[] = array('type' => 'variable', 'value' => $name);
                }
                continue;
            }

            // Operators and punctuation
            if (in_array($char, array('+', '-', '*', '/', '%', '^', '(', ')', ',', '?', ':'))) {
                $tokens[] = array('type' => 'operator', 'value' => $char);
                $i++;
                continue;
            }

            // Comparison and logical operators (multi-character)
            if (in_array($char, array('<', '>', '=', '!', '&', '|'))) {
                $op = $char;
                if ($i + 1 < $length) {
                    $two_char = $char . $expression[$i + 1];
                    if (in_array($two_char, array('<=', '>=', '==', '!=', '&&', '||'))) {
                        $op = $two_char;
                        $i += 2;
                    } else {
                        $i++;
                    }
                } else {
                    $i++;
                }
                $tokens[] = array('type' => 'operator', 'value' => $op);
                continue;
            }

            // Unknown character - skip it
            $i++;
        }

        return $tokens;
    }

    /**
     * Substitute variables in token stream
     * FIX v2.4.24: Case-insensitive variable lookup to handle formula variations
     */
    private function substitute_variables($tokens, $variables) {
        // Create lowercase-keyed lookup map for case-insensitive matching
        $lowercase_map = array();
        foreach ($variables as $key => $value) {
            $lowercase_map[strtolower($key)] = $key;  // Map lowercase to original key
        }

        $result = array();
        foreach ($tokens as $token) {
            if ($token['type'] === 'variable') {
                $lookup_key = strtolower($token['value']);
                if (isset($lowercase_map[$lookup_key])) {
                    $original_key = $lowercase_map[$lookup_key];
                    $result[] = array('type' => 'number', 'value' => $variables[$original_key]);
                } else {
                    $result[] = $token;  // Keep unresolved variable
                }
            } else {
                $result[] = $token;
            }
        }
        return $result;
    }

    /**
     * Build Abstract Syntax Tree from tokens using Shunting Yard algorithm
     */
    private function build_ast($tokens) {
        $output = array();  // Output queue (RPN)
        $operators = array();  // Operator stack

        $precedence = array(
            '||' => 1, 'or' => 1,
            '&&' => 2, 'and' => 2,
            '==' => 3, '!=' => 3, '<' => 3, '>' => 3, '<=' => 3, '>=' => 3,
            '+' => 4, '-' => 4,
            '*' => 5, '/' => 5, '%' => 5,
            '^' => 6,
            '?' => 0  // Ternary operator
        );

        $right_associative = array('^', '?');

        for ($i = 0; $i < count($tokens); $i++) {
            $token = $tokens[$i];

            if ($token['type'] === 'number') {
                $output[] = $token;
            } elseif ($token['type'] === 'function') {
                $operators[] = $token;
            } elseif ($token['type'] === 'operator') {
                if ($token['value'] === '(') {
                    $operators[] = $token;
                } elseif ($token['value'] === ')') {
                    // Pop operators until '('
                    while (!empty($operators) && end($operators)['value'] !== '(') {
                        $output[] = array_pop($operators);
                    }
                    if (!empty($operators)) {
                        array_pop($operators);  // Remove '('
                    }
                    // If there's a function on top, pop it to output
                    if (!empty($operators) && end($operators)['type'] === 'function') {
                        $output[] = array_pop($operators);
                    }
                } elseif ($token['value'] === ',') {
                    // Comma separates function arguments
                    while (!empty($operators) && end($operators)['value'] !== '(') {
                        $output[] = array_pop($operators);
                    }
                } else {
                    // Regular operator
                    $prec = $precedence[$token['value']] ?? 0;
                    $is_right = in_array($token['value'], $right_associative);

                    while (!empty($operators)) {
                        $top = end($operators);
                        if ($top['type'] !== 'operator' || $top['value'] === '(') {
                            break;
                        }
                        $top_prec = $precedence[$top['value']] ?? 0;
                        if (($is_right && $prec < $top_prec) || (!$is_right && $prec <= $top_prec)) {
                            $output[] = array_pop($operators);
                        } else {
                            break;
                        }
                    }
                    $operators[] = $token;
                }
            }
        }

        // Pop remaining operators
        while (!empty($operators)) {
            $output[] = array_pop($operators);
        }

        return $output;
    }

    /**
     * Evaluate Abstract Syntax Tree (in RPN form) safely without eval()
     */
    private function evaluate_ast($ast, $variables) {
        $stack = array();

        foreach ($ast as $token) {
            if ($token['type'] === 'number') {
                $stack[] = $token['value'];
            } elseif ($token['type'] === 'function') {
                // Call function - collect arguments from stack
                $func_name = $token['value'];
                $args = array();

                // Functions take arguments from stack
                // For simplicity, we'll handle common functions with known arg counts
                $arg_count = $this->get_function_arg_count($func_name, $stack);

                for ($i = 0; $i < $arg_count; $i++) {
                    if (!empty($stack)) {
                        array_unshift($args, array_pop($stack));
                    }
                }

                $result = $this->call_function($func_name, $args);
                $stack[] = $result;

            } elseif ($token['type'] === 'operator') {
                $op = $token['value'];

                // Binary operators
                if (in_array($op, array('+', '-', '*', '/', '%', '^', '&&', '||', '==', '!=', '<', '>', '<=', '>='))) {
                    if (count($stack) < 2) {
                        throw new Exception(esc_html("Insufficient operands for operator {$op}"));
                    }
                    $b = array_pop($stack);
                    $a = array_pop($stack);
                    $stack[] = $this->apply_operator($op, $a, $b);
                }
                // Ternary operator handled separately
                elseif ($op === '?') {
                    // Ternary: condition ? true_val : false_val
                    // Stack has: false_val, true_val, condition (top to bottom)
                    if (count($stack) < 3) {
                        throw new Exception(esc_html("Insufficient operands for ternary operator"));
                    }
                    $false_val = array_pop($stack);
                    $true_val = array_pop($stack);
                    $condition = array_pop($stack);
                    $stack[] = $condition ? $true_val : $false_val;
                }
            }
        }

        if (count($stack) !== 1) {
            throw new Exception(esc_html("Invalid expression evaluation - stack has " . count($stack) . " items"));
        }

        return $stack[0];
    }

    /**
     * Get function argument count (simplified - assumes all args are on stack)
     * FIX #2: Added 'if' function with 3 arguments
     */
    private function get_function_arg_count($func_name, &$stack) {
        // Known function argument counts
        $fixed_args = array(
            'abs' => 1, 'sqrt' => 1, 'log' => 1, 'log10' => 1, 'ln' => 1,
            'exp' => 1, 'sin' => 1, 'cos' => 1, 'tan' => 1,
            'asin' => 1, 'acos' => 1, 'atan' => 1,
            'floor' => 1, 'ceil' => 1, 'round' => 1,
            'triangle' => 1, 'not' => 1,
            'if' => 3,  // FIX #2: if(condition, true_value, false_value)
            'pow' => 2, 'power' => 2, 'min' => 2, 'max' => 2,
            'roundUpToNearest' => 2, 'roundToNearest' => 2, 'roundDownToNearest' => 2,
        );

        if (isset($fixed_args[$func_name])) {
            return $fixed_args[$func_name];
        }

        // Variable argument functions - take all available on stack
        // This is simplified; real implementation would track argument boundaries
        return min(count($stack), 10);  // Limit to prevent stack underflow
    }

    /**
     * Call a function safely
     */
    private function call_function($func_name, $args) {
        switch ($func_name) {
            // Math functions
            case 'abs': return abs($args[0] ?? 0);
            case 'sqrt': return sqrt($args[0] ?? 0);
            case 'log': case 'ln': return log($args[0] ?? 1);
            case 'log10': return log10($args[0] ?? 1);
            case 'exp': return exp($args[0] ?? 0);
            case 'sin': return sin($args[0] ?? 0);
            case 'cos': return cos($args[0] ?? 0);
            case 'tan': return tan($args[0] ?? 0);
            case 'asin': return asin($args[0] ?? 0);
            case 'acos': return acos($args[0] ?? 0);
            case 'atan': return atan($args[0] ?? 0);

            // Rounding functions
            case 'floor': return floor($args[0] ?? 0);
            case 'ceil': return ceil($args[0] ?? 0);
            case 'round':
                $result = round($args[0] ?? 0, $args[1] ?? 0);
                if (current_user_can('manage_options')) {
                    error_log("round() input: " . ($args[0] ?? 0) . " => result: " . $result);
                }
                return $result;
            case 'roundUpToNearest':
                $val = $args[0] ?? 0;
                $mult = $args[1] ?? 1;
                return ceil($val / $mult) * $mult;
            case 'roundToNearest':
                $val = $args[0] ?? 0;
                $mult = $args[1] ?? 1;
                return round($val / $mult) * $mult;
            case 'roundDownToNearest':
                $val = $args[0] ?? 0;
                $mult = $args[1] ?? 1;
                return floor($val / $mult) * $mult;

            // Power and comparison
            case 'pow': case 'power': return pow($args[0] ?? 0, $args[1] ?? 0);
            case 'min': return min($args[0] ?? 0, $args[1] ?? 0);
            case 'max': return max($args[0] ?? 0, $args[1] ?? 0);

            // List functions
            case 'sum':
                // DEBUG: Log BEFORE flatten attempt
                if (current_user_can('manage_options')) {
                    error_log("=== sum() BEFORE flatten ===");
                    error_log("args full: " . json_encode($args));
                    error_log("args count: " . count($args));
                    if (count($args) > 0) {
                        error_log("args[0] type: " . gettype($args[0]));
                        error_log("args[0] value: " . json_encode($args[0]));
                        error_log("is_array(args[0]): " . (is_array($args[0]) ? 'YES' : 'NO'));
                    }
                    error_log("Condition check: count===1? " . (count($args) === 1 ? 'YES' : 'NO') .
                              ", is_array(args[0])? " . (isset($args[0]) && is_array($args[0]) ? 'YES' : 'NO'));
                }

                // FIX: If args contains a single array element, flatten it
                // This handles sum(countedResults) where countedResults is an array variable
                if (count($args) === 1 && is_array($args[0])) {
                    if (current_user_can('manage_options')) {
                        error_log("*** FLATTENING EXECUTED ***");
                        error_log("Before: " . json_encode($args));
                    }
                    $args = $args[0];
                    if (current_user_can('manage_options')) {
                        error_log("After: " . json_encode($args));
                    }
                } else {
                    if (current_user_can('manage_options')) {
                        error_log("*** FLATTEN SKIPPED - condition not met ***");
                    }
                }

                // DEBUG: Log AFTER flatten attempt
                if (current_user_can('manage_options')) {
                    error_log("=== sum() AFTER flatten ===");
                    error_log("args: " . json_encode($args));
                }

                $result = array_sum($args);

                if (current_user_can('manage_options')) {
                    error_log("sum() final result: " . $result);
                    error_log("=== sum() END ===");
                }
                return $result;
            case 'average':
                // DEBUG: Log before flatten
                if (current_user_can('manage_options')) {
                    error_log("average() BEFORE: args=" . json_encode($args) . ", count=" . count($args));
                }

                // FIX: Flatten if single array argument
                if (count($args) === 1 && is_array($args[0])) {
                    if (current_user_can('manage_options')) {
                        error_log("average() FLATTENING");
                    }
                    $args = $args[0];
                }

                if (current_user_can('manage_options')) {
                    error_log("average() AFTER: args=" . json_encode($args));
                }

                return count($args) > 0 ? array_sum($args) / count($args) : 0;
            case 'product':
                // DEBUG: Log before flatten
                if (current_user_can('manage_options')) {
                    error_log("product() BEFORE: args=" . json_encode($args) . ", count=" . count($args));
                }

                // FIX: Flatten if single array argument
                if (count($args) === 1 && is_array($args[0])) {
                    if (current_user_can('manage_options')) {
                        error_log("product() FLATTENING");
                    }
                    $args = $args[0];
                }

                if (current_user_can('manage_options')) {
                    error_log("product() AFTER: args=" . json_encode($args));
                }

                return array_product($args);
            case 'count':
                // DEBUG: Log before flatten
                if (current_user_can('manage_options')) {
                    error_log("count() BEFORE: args=" . json_encode($args) . ", count=" . count($args));
                }

                // FIX: Flatten if single array argument
                if (count($args) === 1 && is_array($args[0])) {
                    if (current_user_can('manage_options')) {
                        error_log("count() FLATTENING");
                    }
                    $args = $args[0];
                }

                if (current_user_can('manage_options')) {
                    error_log("count() AFTER: args=" . json_encode($args));
                }

                return count($args);
            case 'top':
                // top(N, array) - Return top N highest values from array
                if (count($args) < 2) {
                    throw new Exception("top() requires 2 arguments: count, array");
                }
                $n = intval($args[0]);
                $array = is_array($args[1]) ? $args[1] : array($args[1]);

                // DEBUG
                if (current_user_can('manage_options')) {
                    error_log("=== top() function debug ===");
                    error_log("N (count): " . $n);
                    error_log("Input array type: " . gettype($args[1]));
                    error_log("Input array: " . json_encode($args[1]));
                    error_log("Converted array: " . json_encode($array));
                }

                // Sort descending (highest first)
                rsort($array);

                // Return top N values as array
                $result = array_slice($array, 0, $n);

                // DEBUG
                if (current_user_can('manage_options')) {
                    error_log("After rsort: " . json_encode($array));
                    error_log("Result (top " . $n . "): " . json_encode($result));
                    error_log("=== top() function debug END ===");
                }

                return $result;

            // Special functions
            case 'triangle':
                $n = $args[0] ?? 0;
                return $n * ($n + 1) / 2;
            case 'random': return mt_rand() / mt_getrandmax();

            // Logical
            case 'not': return !($args[0] ?? 0);

            // Conditional function
            // FIX #2: Add if() function for piecewise logic
            case 'if':
                if (count($args) < 3) {
                    throw new Exception("if() requires 3 arguments: condition, true_value, false_value");
                }
                $condition = $args[0];
                $true_val = $args[1];
                $false_val = $args[2];
                return $condition ? $true_val : $false_val;

            // Custom TD functions
            case 'switch': return call_user_func_array(array($this, 'td_switch'), $args);
            case 'lswitch': return call_user_func_array(array($this, 'td_lswitch'), $args);
            case 'oneof': return call_user_func_array(array($this, 'td_oneof'), $args);

            default:
                throw new Exception("Unknown function: " . esc_html($func_name));
        }
    }

    /**
     * Apply binary operator safely
     */
    private function apply_operator($op, $a, $b) {
        switch ($op) {
            case '+': return $a + $b;
            case '-': return $a - $b;
            case '*': return $a * $b;
            case '/': return $b != 0 ? $a / $b : 0;  // Prevent division by zero
            case '%': return $b != 0 ? $a % $b : 0;
            case '^': return pow($a, $b);

            // Comparison
            case '==': return $a == $b ? 1 : 0;
            case '!=': return $a != $b ? 1 : 0;
            case '<': return $a < $b ? 1 : 0;
            case '>': return $a > $b ? 1 : 0;
            case '<=': return $a <= $b ? 1 : 0;
            case '>=': return $a >= $b ? 1 : 0;

            // Logical
            case '&&': return ($a && $b) ? 1 : 0;
            case '||': return ($a || $b) ? 1 : 0;

            default:
                throw new Exception("Unknown operator: " . esc_html($op));
        }
    }

    /**
     * Replace TD functions with PHP equivalents
     * Enhanced to support all 43+ Tournament Director functions
     */
    private function replace_functions($expression) {
        // Direct PHP function mappings (1:1 correspondence)
        $simple_replacements = array(
            // Mathematical functions
            'abs(' => 'abs(',
            'acos(' => 'acos(',
            'asin(' => 'asin(',
            'atan(' => 'atan(',
            'cos(' => 'cos(',
            'exp(' => 'exp(',
            'log(' => 'log(',
            'ln(' => 'log(',  // ln is alias for log in TD
            'log10(' => 'log10(',
            'pow(' => 'pow(',
            'power(' => 'pow(',  // power is alias for pow
            'sin(' => 'sin(',
            'sqrt(' => 'sqrt(',
            'tan(' => 'tan(',

            // Rounding functions
            'ceil(' => 'ceil(',
            'floor(' => 'floor(',
            'round(' => 'round(',

            // List/array functions
            'max(' => 'max(',
            'min(' => 'min(',
            'sum(' => 'array_sum(',  // sum maps to array_sum for lists
            'average(' => '$this->td_average(',  // Custom implementation needed
            'count(' => 'count(',
        );

        foreach ($simple_replacements as $td_func => $php_func) {
            $expression = str_replace($td_func, $php_func, $expression);
        }

        // Complex functions requiring custom implementation
        // These will be handled by separate methods called via $this->

        // Triangle number function: triangle(n) = n*(n+1)/2
        $expression = preg_replace_callback(
            '/triangle\(([^)]+)\)/',
            function($matches) {
                $n = trim($matches[1]);
                return "({$n} * ({$n} + 1) / 2)";
            },
            $expression
        );

        // roundUpToNearest(value, multiple)
        $expression = preg_replace_callback(
            '/roundUpToNearest\(([^,]+),([^)]+)\)/',
            function($matches) {
                $value = trim($matches[1]);
                $multiple = trim($matches[2]);
                return "(ceil({$value} / {$multiple}) * {$multiple})";
            },
            $expression
        );

        // roundToNearest(value, multiple)
        $expression = preg_replace_callback(
            '/roundToNearest\(([^,]+),([^)]+)\)/',
            function($matches) {
                $value = trim($matches[1]);
                $multiple = trim($matches[2]);
                return "(round({$value} / {$multiple}) * {$multiple})";
            },
            $expression
        );

        // roundDownToNearest(value, multiple)
        $expression = preg_replace_callback(
            '/roundDownToNearest\(([^,]+),([^)]+)\)/',
            function($matches) {
                $value = trim($matches[1]);
                $multiple = trim($matches[2]);
                return "(floor({$value} / {$multiple}) * {$multiple})";
            },
            $expression
        );

        // random() -> mt_rand() / mt_getrandmax()
        $expression = str_replace('random()', '(mt_rand() / mt_getrandmax())', $expression);

        return $expression;
    }

    /**
     * Helper function for average calculation
     * Used by formula evaluation system
     */
    private function td_average() {
        $args = func_get_args();
        if (empty($args)) {
            return 0;
        }
        // Handle list as first argument
        if (is_array($args[0])) {
            $args = $args[0];
        }
        $sum = array_sum($args);
        $count = count($args);
        return $count > 0 ? $sum / $count : 0;
    }

    /**
     * Helper function for product calculation
     * Used by formula evaluation system
     */
    private function td_product() {
        $args = func_get_args();
        if (empty($args)) {
            return 0;
        }
        // Handle list as first argument
        if (is_array($args[0])) {
            $args = $args[0];
        }
        $product = 1;
        foreach ($args as $value) {
            $product *= $value;
        }
        return $product;
    }

    /**
     * Helper function for switch statement
     * Used by formula evaluation system
     */
    private function td_switch() {
        $args = func_get_args();
        if (count($args) < 3) {
            return 0;
        }

        $condition_value = $args[0];
        $default_value = (count($args) % 2 === 0) ? $args[count($args) - 1] : 0;

        // Process comparison pairs
        for ($i = 1; $i < count($args) - 1; $i += 2) {
            if ($args[$i] == $condition_value) {
                return $args[$i + 1];
            }
        }

        return $default_value;
    }

    /**
     * Helper function for linear switch statement
     * Used by formula evaluation system
     */
    private function td_lswitch() {
        $args = func_get_args();
        if (count($args) < 2) {
            return 0;
        }

        $index = intval($args[0]);
        $values = array_slice($args, 1);

        // Index is 1-based in TD
        if ($index >= 1 && $index <= count($values)) {
            return $values[$index - 1];
        }

        // Return last value as default
        return $values[count($values) - 1];
    }

    /**
     * Helper function for oneof statement
     * Used by formula evaluation system
     */
    private function td_oneof() {
        $args = func_get_args();
        if (count($args) < 2) {
            return false;
        }

        $search_value = $args[0];
        $search_list = array_slice($args, 1);

        return in_array($search_value, $search_list) ? 1 : 0;
    }

    /**
     * Handle conditional expressions
     */
    private function handle_conditionals($expression) {
        // Replace TD if(condition, true, false) with PHP ternary
        $expression = preg_replace('/if\s*\(\s*([^,]+)\s*,\s*([^,]+)\s*,\s*([^)]+)\s*\)/', '($1) ? ($2) : ($3)', $expression);

        // Replace logical operators
        $expression = str_replace('and', '&&', $expression);
        $expression = str_replace('or', '||', $expression);
        $expression = str_replace('not', '!', $expression);

        return $expression;
    }

    /**
     * Get available variables
     */
    public function get_available_variables() {
        return $this->td_variables;
    }

    /**
     * Get available functions
     */
    public function get_available_functions() {
        return $this->td_functions;
    }

    /**
     * Get default formulas
     */
    public function get_default_formulas() {
        return $this->default_formulas;
    }

    /**
     * Save custom formula to database
     */
    public function save_formula($name, $formula_data) {
        $formulas = get_option('poker_tournament_formulas', array());
        $formulas[$name] = $formula_data;
        update_option('poker_tournament_formulas', $formulas);
    }

    /**
     * Get saved formula
     */
    public function get_formula($name) {
        // Check saved custom formulas first (allows overriding defaults)
        $formulas = get_option('poker_tournament_formulas', array());
        if (isset($formulas[$name])) {
            // NORMALIZE: Ensure dependencies is an array
            return $this->normalize_formula_data($formulas[$name]);
        }

        // Fall back to default formulas (built-in formulas)
        if (isset($this->default_formulas[$name])) {
            return $this->normalize_formula_data($this->default_formulas[$name]);
        }

        return null;
    }

    /**
     * Normalize formula data to ensure dependencies is always an array
     *
     * Fixes TypeError when dependencies is stored as string but code expects array
     * Provides backward compatibility for formulas saved in different formats
     */
    private function normalize_formula_data($formula_data) {
        if (!isset($formula_data['dependencies'])) {
            $formula_data['dependencies'] = array();
            return $formula_data;
        }

        // If dependencies is a string, convert to array
        if (is_string($formula_data['dependencies'])) {
            // Split by newlines or semicolons
            $deps_string = $formula_data['dependencies'];
            if (empty(trim($deps_string))) {
                $formula_data['dependencies'] = array();
            } else {
                // Try splitting by newlines first, then semicolons
                $deps_array = preg_split('/[\r\n;]+/', $deps_string, -1, PREG_SPLIT_NO_EMPTY);
                $formula_data['dependencies'] = array_map('trim', $deps_array);
            }
        }

        // Ensure it's an array even if empty
        if (!is_array($formula_data['dependencies'])) {
            $formula_data['dependencies'] = array();
        }

        return $formula_data;
    }

    /**
     * Get all saved formulas
     */
    public function get_all_formulas() {
        $saved_formulas = get_option('poker_tournament_formulas', array());
        return array_merge($this->default_formulas, $saved_formulas);
    }

    /**
     * Delete formula
     */
    public function delete_formula($name) {
        $formulas = get_option('poker_tournament_formulas', array());
        if (isset($formulas[$name])) {
            unset($formulas[$name]);
            update_option('poker_tournament_formulas', $formulas);
            return true;
        }
        return false;
    }

    /**
     * Register admin settings
     */
    public function register_formula_settings() {
        register_setting('poker_formulas', 'poker_tournament_formulas', array(
            'sanitize_callback' => array($this, 'sanitize_formulas')
        ));
    }

    /**
     * Sanitize formulas array
     */
    public function sanitize_formulas($value) {
        if (!is_array($value)) {
            return array();
        }
        return $value; // Formulas are already validated through the validator class
    }

    /**
     * Add admin menu
     */
    public function add_formula_admin_menu() {
        include_once POKER_TOURNAMENT_IMPORT_PLUGIN_DIR . 'admin/formula-manager-page.php';
        $formula_manager_page = new Poker_Formula_Manager_Page();

        // Add spade SVG icon as data URI
        $spade_icon = 'data:image/svg+xml;base64,' . base64_encode('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="black"><path d="M12 2L8.5 9.5C7 11 5 13 5 15.5C5 18.5 7.5 21 10.5 21C11.5 21 12.5 20.5 13 19.5H11.5C11.5 20 11 20.5 10.5 20.5C8 20.5 5.5 18 5.5 15.5C5.5 13.5 7 11.5 8.5 10L12 2ZM12 2L15.5 9.5C17 11 19 13 19 15.5C19 18.5 16.5 21 13.5 21C12.5 21 11.5 20.5 11 19.5H12.5C12.5 20 13 20.5 13.5 20.5C16 20.5 18.5 18 18.5 15.5C18.5 13.5 17 11.5 15.5 10L12 2Z"/><path d="M11 19H13V22H11V19Z"/></svg>');

        add_submenu_page(
            'poker-tournament-import',
            __('Formula Manager', 'poker-tournament-import'),
            __('Formulas', 'poker-tournament-import'),
            'manage_options',
            'poker-formula-manager',
            array($formula_manager_page, 'render_page')
        );
    }

    /**
     * Render formula manager interface
     */
    public function render_formula_manager() {
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Tournament Formula Manager', 'poker-tournament-import'); ?></h1>

            <div class="poker-formula-tabs">
                <ul class="tab-nav">
                    <li><a href="#formulas" class="tab-active"><?php esc_html_e('Formulas', 'poker-tournament-import'); ?></a></li>
                    <li><a href="#validator"><?php esc_html_e('Formula Validator', 'poker-tournament-import'); ?></a></li>
                    <li><a href="#variables"><?php esc_html_e('Variables & Functions', 'poker-tournament-import'); ?></a></li>
                </ul>

                <div id="formulas" class="tab-content">
                    <?php $this->render_formulas_tab(); ?>
                </div>

                <div id="validator" class="tab-content" style="display:none;">
                    <?php $this->render_validator_tab(); ?>
                </div>

                <div id="variables" class="tab-content" style="display:none;">
                    <?php $this->render_variables_tab(); ?>
                </div>
            </div>
        </div>

        <style>
        .poker-formula-tabs {
            margin-top: 20px;
        }
        .tab-nav {
            list-style: none;
            margin: 0;
            padding: 0;
            border-bottom: 1px solid #ccc;
        }
        .tab-nav li {
            display: inline-block;
            margin-right: 10px;
        }
        .tab-nav a {
            display: block;
            padding: 10px 15px;
            text-decoration: none;
            border: 1px solid #ccc;
            border-bottom: none;
            background: #f1f1f1;
            color: #333;
        }
        .tab-nav a.tab-active {
            background: #fff;
            color: #000;
        }
        .tab-content {
            padding: 20px;
            border: 1px solid #ccc;
            border-top: none;
        }
        .formula-editor {
            max-width: 800px;
        }
        .formula-test {
            margin-top: 20px;
            padding: 15px;
            background: #f9f9f9;
            border-left: 4px solid #0073aa;
        }
        .variable-list {
            columns: 2;
            column-gap: 30px;
        }
        .variable-item {
            margin-bottom: 10px;
            break-inside: avoid;
        }
        .variable-name {
            font-family: monospace;
            background: #eee;
            padding: 2px 4px;
            border-radius: 3px;
        }
        </style>

        <script>
        jQuery(document).ready(function($) {
            $('.tab-nav a').click(function(e) {
                e.preventDefault();
                var target = $(this).attr('href');

                $('.tab-nav a').removeClass('tab-active');
                $(this).addClass('tab-active');

                $('.tab-content').hide();
                $(target).show();
            });
        });
        </script>
        <?php
    }

    /**
     * Render formulas management tab
     */
    private function render_formulas_tab() {
        $formulas = $this->get_all_formulas();
        ?>
        <div class="formula-editor">
            <h2><?php esc_html_e('Manage Formulas', 'poker-tournament-import'); ?></h2>

            <button type="button" class="button button-primary" onclick="pokerAddNewFormula()">
                <?php esc_html_e('Add New Formula', 'poker-tournament-import'); ?>
            </button>

            <table class="wp-list-table widefat fixed striped" style="margin-top: 20px;">
                <thead>
                    <tr>
                        <th><?php esc_html_e('Name', 'poker-tournament-import'); ?></th>
                        <th><?php esc_html_e('Category', 'poker-tournament-import'); ?></th>
                        <th><?php esc_html_e('Description', 'poker-tournament-import'); ?></th>
                        <th><?php esc_html_e('Actions', 'poker-tournament-import'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($formulas as $key => $formula): ?>
                        <tr>
                            <td><?php echo esc_html($formula['name']); ?></td>
                            <td><?php echo esc_html($formula['category']); ?></td>
                            <td><?php echo esc_html($formula['description']); ?></td>
                            <td>
                                <button type="button" class="button" onclick="pokerEditFormula('<?php echo esc_js($key); ?>')">
                                    <?php esc_html_e('Edit', 'poker-tournament-import'); ?>
                                </button>
                                <?php if (!isset($this->default_formulas[$key])): ?>
                                    <button type="button" class="button" onclick="pokerDeleteFormula('<?php echo esc_js($key); ?>')">
                                        <?php esc_html_e('Delete', 'poker-tournament-import'); ?>
                                    </button>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    /**
     * Render formula validator tab
     */
    private function render_validator_tab() {
        ?>
        <div class="formula-validator">
            <h2><?php esc_html_e('Formula Validator', 'poker-tournament-import'); ?></h2>

            <div class="formula-test">
                <h3><?php esc_html_e('Test Your Formula', 'poker-tournament-import'); ?></h3>

                <div class="form-field">
                    <label for="formula-input"><?php esc_html_e('Formula:', 'poker-tournament-import'); ?></label>
                    <textarea id="formula-input" rows="6" style="width: 100%; font-family: monospace;"
                              placeholder="assign(&quot;points&quot;, round(10 * (sqrt(n) / sqrt(r)) * (1 + log(avgBC + 0.25))) + (numberofHits * 10))"></textarea>
                </div>

                <div class="form-field">
                    <label><?php esc_html_e('Test Data:', 'poker-tournament-import'); ?></label>
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 10px;">
                        <input type="number" id="test-n" placeholder="Total Players (n)" value="20">
                        <input type="number" id="test-r" placeholder="Finish Position (r)" value="3">
                        <input type="number" id="test-hits" placeholder="Hits (numberofHits)" value="5">
                        <input type="number" id="test-money" placeholder="Total Money" value="2000">
                        <input type="number" id="test-buyins" placeholder="Buy-ins" value="20">
                    </div>
                </div>

                <button type="button" class="button button-primary" onclick="pokerValidateFormula()">
                    <?php esc_html_e('Validate & Test', 'poker-tournament-import'); ?>
                </button>

                <div id="validation-result" style="margin-top: 15px;"></div>
            </div>
        </div>

        <script>
        function pokerValidateFormula() {
            var formula = document.getElementById('formula-input').value;
            var testData = {
                n: parseInt(document.getElementById('test-n').value) || 20,
                r: parseInt(document.getElementById('test-r').value) || 3,
                hits: parseInt(document.getElementById('test-hits').value) || 5,
                total_money: parseInt(document.getElementById('test-money').value) || 2000,
                total_buyins: parseInt(document.getElementById('test-buyins').value) || 20
            };

            jQuery.post(ajaxurl, {
                action: 'poker_validate_formula',
                formula: formula,
                test_data: testData,
                nonce: '<?php echo esc_attr(wp_create_nonce("poker_formula_validator")); ?>'
            }, function(response) {
                document.getElementById('validation-result').innerHTML = response;
            });
        }
        </script>
        <?php
    }

    /**
     * Render variables and functions reference tab
     */
    private function render_variables_tab() {
        ?>
        <div class="formula-reference">
            <h2><?php esc_html_e('Available Variables', 'poker-tournament-import'); ?></h2>

            <div class="variable-list">
                <?php foreach ($this->td_variables as $name => $info): ?>
                    <div class="variable-item">
                        <span class="variable-name"><?php echo esc_html($name); ?></span>
                        <span class="variable-type">(<?php echo esc_html($info['type']); ?>)</span>
                        <p><?php echo esc_html($info['description']); ?></p>
                    </div>
                <?php endforeach; ?>
            </div>

            <h2><?php esc_html_e('Available Functions', 'poker-tournament-import'); ?></h2>

            <div class="variable-list">
                <?php foreach ($this->td_functions as $name => $info): ?>
                    <div class="variable-item">
                        <span class="variable-name"><?php echo esc_html($name); ?>()</span>
                        <span class="variable-type">(<?php echo esc_html($info['params']); ?> params)</span>
                        <p><?php echo esc_html($info['description']); ?></p>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php
    }
}