# Codebase Structure

## Directory Layout
```
wordpress-plugin/poker-tournament-import/
├── poker-tournament-import.php          # Main plugin entry point
├── includes/                            # Core classes
│   ├── class-parser.php                # .tdt file parser
│   ├── class-post-types.php            # Custom post types
│   ├── class-taxonomies.php            # Custom taxonomies
│   ├── class-shortcodes.php            # Frontend shortcodes
│   ├── class-statistics-engine.php     # Statistics calculations
│   ├── class-formula-validator.php     # Formula validation
│   ├── class-series-standings.php      # Series standings
│   ├── class-tdt-lexer.php            # TDT lexer
│   ├── class-tdt-ast-parser.php       # TDT AST parser
│   └── class-tdt-domain-mapper.php    # Domain mapping
├── admin/                              # Admin interface
│   ├── class-admin.php                # Admin panel
│   └── class-data-mart-cleaner.php    # Data maintenance
├── templates/                          # Display templates
├── assets/                             # CSS/JS files
│   ├── css/                           # Stylesheets
│   └── js/                            # JavaScript
├── tests/                             # Test files
└── languages/                         # i18n files

## Key Classes
- **Poker_Tournament_Import**: Main singleton class
- **Poker_Tournament_Parser**: Parses .tdt files
- **Poker_Tournament_Post_Types**: Registers custom post types
- **Poker_Tournament_Taxonomies**: Registers taxonomies
- **Poker_Tournament_Shortcodes**: Frontend shortcodes
- **Poker_Tournament_Statistics_Engine**: Stats calculations
- **Poker_Tournament_Formula_Validator**: Formula validation

## Custom Post Types
- `tournament`: Individual tournament results
- `tournament_series`: Series of related tournaments
- `player`: Player profiles and statistics

## Database Tables (wp_ prefix)
- `poker_tournament_players`: Tournament participation
- `poker_statistics`: Dashboard statistics (data mart)
- `poker_tournament_costs`: Tournament costs
- `poker_financial_summary`: Financial analytics
- `poker_player_roi`: Player ROI metrics
- `poker_revenue_analytics`: Monthly revenue
