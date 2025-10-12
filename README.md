# TDWPImport - WordPress Tournament Director Plugin

A WordPress plugin for importing and displaying poker tournament results from Tournament Director (.tdt) files.

## 🎯 Overview

TDWPImport automates the process of importing poker tournament results from Tournament Director software, enabling tournament organizers to:
- Import .tdt files with complete data extraction
- Display professional tournament results
- Track player statistics across series and seasons
- Generate responsive tournament pages automatically

## ✨ Features

### 🎮 Tournament Director Integration
- **Complete .tdt file parsing**: Extract all tournament data from Tournament Director v3.7.2+
- **Exact points calculation**: Replicates Tournament Director's proprietary points formula
- **Season/League bucketing**: Automatic series and season tracking
- **Prize distribution**: Accurate winnings calculation and display

### 📊 Data Management
- **Custom post types**: Tournament, Series, Season, Player management
- **Player statistics**: Comprehensive performance tracking
- **Series standings**: Season-long leaderboards and rankings
- **Import workflow**: Drag-and-drop file upload with preview

### 🎨 Display Options
- **Responsive templates**: Mobile-friendly tournament results
- **Shortcode system**: Easy integration into any WordPress page
- **Player profiles**: Individual statistics and tournament history
- **Series overview**: Complete tournament series management

## 🚀 Installation

### Prerequisites
- WordPress 6.0+
- PHP 8.0+
- MySQL 5.7+ or MariaDB 10.2+

### Quick Install
1. Download the latest release from the [Releases](https://github.com/hkhard/tdwpimport/releases) page
2. Upload the plugin to your WordPress `/wp-content/plugins/` directory
3. Activate the plugin through the WordPress admin interface
4. Navigate to **Poker Import → Import Tournament** to start importing

## 📖 Usage

### Importing Tournaments
1. Go to **Poker Import → Import Tournament**
2. Upload your Tournament Director (.tdt) file
3. Review the import preview
4. Confirm and publish the tournament

### Displaying Results

#### Tournament Results
```html
[tournament_results id="123"]
```

#### Series Overview
```html
[tournament_series id="456"]
```

#### Player Profile
```html
[player_profile name="John Doe"]
```

## 🔧 Development

### Local Development Setup
1. Clone the repository:
   ```bash
   git clone https://github.com/hkhard/tdwpimport.git
   cd tdwpimport
   ```

2. Install dependencies and run tests
3. Make your changes
4. Submit a pull request

### Project Structure
```
tdwpimport/
├── wordpress-plugin/
│   └── poker-tournament-import/
│       ├── poker-tournament-import.php    # Main plugin file
│       ├── includes/
│       │   ├── class-parser.php            # TDT file parser
│       │   ├── class-post-types.php        # Custom post types
│       │   └── class-shortcodes.php        # Display shortcodes
│       ├── admin/
│       │   └── class-admin.php             # Admin interface
│       ├── assets/
│       │   ├── css/
│       │   └── js/
│       └── languages/
├── tdtfiles/                               # Sample .tdt files
├── CLAUDE.md                              # Development documentation
└── README.md                              # This file
```

## 🧪 Testing

### Sample Files
The repository includes sample .tdt files in the `tdtfiles/` directory for testing:
- Koffsta series tournaments
- ORF Poker series tournaments
- Various tournament structures and player counts

### Parser Testing
Run the standalone parser test:
```bash
cd wordpress-plugin/poker-tournament-import
php test-parser.php
```

## 📊 Technical Details

### TDT File Format
The parser handles Tournament Director's JavaScript serialization format:
- JavaScript constructor calls (`new Tournament({...})`)
- Nested object structures with Map.from() syntax
- Millisecond timestamps for precise timing
- Complex mathematical formulas for points calculation

### Points Formula
Tournament Director points calculation:
```
Points = 10*(sqrt(n)/sqrt(r))*(1+log(avgBC+0.25)) + (numberofHits * 10)
```
Where:
- `n` = total players
- `r` = finish position
- `avgBC` = average buy-in amount
- `numberofHits` = eliminations

### Data Model
- **Tournaments**: Individual tournament results
- **Series**: Tournament series (UUID-based linking)
- **Seasons**: Time-based tournament groupings
- **Players**: Comprehensive player statistics

## 🤝 Contributing

We welcome contributions! Please see our [Contributing Guidelines](CONTRIBUTING.md) for details.

### Development Workflow
1. Fork the repository
2. Create a feature branch
3. Make your changes
4. Add tests for new functionality
5. Submit a pull request

## 📝 License

This project is licensed under the GPL v2 or later. See the [LICENSE](LICENSE) file for details.

## 🐛 Support

### Known Issues
- Large .tdt files (>2MB) may require increased PHP memory limits
- Some Tournament Director versions may have slight format variations

### Getting Help
- [GitHub Issues](https://github.com/hkhard/tdwpimport/issues): Bug reports and feature requests
- [Documentation](https://github.com/hkhard/tdwpimport/wiki): Detailed usage guides

## 📈 Roadmap

### v1.1.0 (Planned)
- [ ] Batch import functionality
- [ ] Enhanced reporting and analytics
- [ ] Additional tournament management features
- [ ] API endpoints for third-party integration

### v2.0.0 (Future)
- [ ] Live tournament updates
- [ ] Mobile companion app
- [ ] Advanced analytics dashboard
- [ ] Multi-tournament support

## 🏆 Acknowledgments

- Built for poker tournament organizers who want to streamline their result publishing workflow
- Inspired by the need for accurate Tournament Director data integration
- Powered by WordPress and modern PHP development practices

---

**TDWPImport** - Streamlining poker tournament management, one import at a time.