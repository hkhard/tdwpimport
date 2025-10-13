# User Manual - Poker Tournament Import Plugin

## Table of Contents

1. [Getting Started](#getting-started)
2. [Installation Guide](#installation-guide)
3. [Your First Tournament Import](#your-first-tournament-import)
4. [Understanding Tournament Data](#understanding-tournament-data)
5. [Displaying Results](#displaying-results)
6. [Managing Tournaments](#managing-tournaments)
7. [Player Management](#player-management)
8. [Series and Seasons](#series-and-seasons)
9. [Customizing Display](#customizing-display)
10. [Troubleshooting](#troubleshooting)
11. [FAQ](#faq)

## Getting Started

### Welcome to Poker Tournament Import!

The Poker Tournament Import plugin simplifies the process of publishing tournament results from Tournament Director software to your WordPress website. This guide will walk you through everything you need to know to get your tournament results online quickly and professionally.

### What You'll Need

- **WordPress website** (version 6.0 or newer)
- **Administrator access** to your WordPress dashboard
- **Tournament Director software** installed on your computer
- **Tournament result files** (.tdt format) exported from Tournament Director

### Key Benefits

- **90% reduction** in manual data entry time
- **100% accurate** tournament results
- **Professional appearance** with mobile-friendly displays
- **Automatic player statistics** and rankings
- **SEO optimized** for search engines
- **No technical skills** required

## Installation Guide

### Method 1: WordPress Admin Dashboard (Recommended)

#### Step 1: Download the Plugin

1. Visit the [WordPress Plugin Directory](https://wordpress.org/plugins/poker-tournament-import/)
2. Click "Download" to save the plugin file to your computer
3. The downloaded file will be named `poker-tournament-import.zip`

#### Step 2: Upload to WordPress

1. Log in to your WordPress admin dashboard
2. Navigate to **Plugins → Add New**
3. Click the **Upload Plugin** button at the top of the page
4. Click **Choose File** and select the downloaded `.zip` file
5. Click **Install Now**

#### Step 3: Activate and Configure

1. After installation completes, click **Activate Plugin**
2. You'll be redirected to the plugin welcome screen
3. Follow the setup wizard to configure basic settings
4. Grant necessary permissions when prompted

### Method 2: Manual FTP Installation

#### Step 1: Extract the Plugin

1. Right-click on `poker-tournament-import.zip`
2. Select "Extract All" or "Unzip"
3. This creates a folder named `poker-tournament-import`

#### Step 2: Upload via FTP

1. Connect to your website via FTP or file manager
2. Navigate to `/wp-content/plugins/`
3. Upload the extracted `poker-tournament-import` folder
4. Ensure file permissions are set to 755 for folders and 644 for files

#### Step 3: Activate Plugin

1. Log in to WordPress admin dashboard
2. Navigate to **Plugins → Installed Plugins**
3. Find "Poker Tournament Import" in the list
4. Click **Activate**

### Initial Setup

Once activated, you'll see a new menu item called **"Poker Tournaments"** in your WordPress admin menu. Click on it to begin the setup process.

The setup wizard will guide you through:

1. **Basic Settings**: Configure tournament date formats, default currency, etc.
2. **Display Options**: Choose default layouts and colors
3. **Import Settings**: Set file size limits and validation options
4. **SEO Configuration**: Optimize for search engines

## Your First Tournament Import

### Preparing Your Tournament Director File

#### Step 1: Export from Tournament Director

1. Open Tournament Director on your computer
2. Load the tournament you want to publish
3. Go to **File → Export → Tournament Data**
4. Choose **"JavaScript (.tdt)"** format
5. Save the file to your computer (note the location)

#### Step 2: Verify Your File

Your exported file should:
- Have a `.tdt` extension
- Be less than 10MB in size
- Contain tournament data including players and results

### Import Process

#### Step 1: Navigate to Import Page

1. In WordPress admin, go to **Poker Tournaments → Import Tournament**
2. You'll see the import wizard interface

#### Step 2: Upload Your File

1. Click **"Choose File"** or drag and drop your `.tdt` file
2. Select the tournament file from your computer
3. Wait for the file to upload (progress bar will show)

#### Step 3: Review Tournament Information

The plugin will automatically parse your file and display:

- **Tournament Name**: Verify the tournament title
- **Date and Time**: Check when the tournament occurred
- **Buy-in Information**: Confirm buy-in amounts
- **Number of Players**: Total participants
- **Prize Pool**: Total winnings amount

#### Step 4: Configure Import Options

Choose from these options:

- **Publish Status**:
  - `Publish`: Immediately visible to visitors
  - `Draft`: Save as draft for later review
  - `Private`: Only visible to administrators

- **Tournament Series**:
  - Create new series or select existing one
  - Helps organize related tournaments

- **Player Profiles**:
  - `Create new players`: Automatically create player pages
  - `Link to existing players`: Match with existing player records

#### Step 5: Import and Review

1. Click **"Import Tournament"**
2. Wait for processing (usually takes 10-30 seconds)
3. Review the import summary page
4. Click **"View Tournament"** to see the published results

### Import Status Messages

You may see these status messages during import:

- ✅ **"File uploaded successfully"**: File received and validated
- ✅ **"Tournament data parsed"**: File content processed
- ✅ **"Player profiles created"**: Player records generated
- ✅ **"Statistics calculated"**: Points and rankings computed
- ✅ **"Tournament published"**: Results are now live

## Understanding Tournament Data

### Tournament Information Fields

Your imported tournament includes these key pieces of information:

#### Basic Tournament Details

- **Tournament Name**: The official title of your event
- **Date and Time**: When the tournament took place
- **Location**: Where the tournament was held
- **Tournament Type**: Hold'em, Omaha, Stud, etc.
- **Format**: No-Limit, Pot-Limit, Fixed-Limit

#### Financial Information

- **Buy-in Amount**: Cost to enter the tournament
- **Entry Fee**: Additional administrative fee
- **Total Prize Pool**: Sum of all buy-ins
- **Prize Distribution**: How winnings are divided

#### Player Data

- **Player Names**: Full names of all participants
- **Finish Positions**: Where each player placed
- **Winnings**: Amount won by each player
- **Eliminations**: Number of players eliminated by each person

### Points Calculation

The plugin uses the official Tournament Director points formula:

```
Points = 10 × (√players ÷ √finish) × (1 + log(average_buy_in + 0.25)) + (eliminations × 10)
```

This ensures consistent scoring across all your tournaments.

### Data Validation

The plugin automatically validates:

- **File Format**: Ensures file is valid .tdt format
- **Data Completeness**: Checks for required information
- **Value Ranges**: Validates amounts and positions
- **Player Consistency**: Ensures player names match across tournaments

## Displaying Results

### Automatic Tournament Pages

Once imported, each tournament gets its own professional-looking page with:

- Tournament header with date and location
- Complete results table with player names and positions
- Prize distribution breakdown
- Tournament statistics and highlights

### Using Shortcodes

Shortcodes let you display tournament information anywhere on your site.

#### Display a Single Tournament

```
[tournament_results id="123"]
```

Where `123` is the tournament ID (found in the tournament edit screen).

**Options:**
- `show_details="yes"`: Show tournament information
- `show_players="yes"`: Show player results
- `show_statistics="yes"`: Show tournament statistics

**Example:**
```
[tournament_results id="123" show_details="yes" show_players="yes" show_statistics="no"]
```

#### Display Tournament Series

```
[tournament_series id="456"]
```

Shows all tournaments in a series with standings and overall results.

#### Display Player Profile

```
[player_profile name="John Doe"]
```

Shows a player's tournament history and statistics.

#### Show All Recent Tournaments

```
[poker_tournaments limit="10"]
```

Shows your most recent tournaments in a list format.

#### Display Leaderboard

```
[poker_leaderboard season="2024" limit="25"]
```

Shows the top players for a specific season or all-time.

### Page Templates

The plugin creates these automatic pages:

- **Individual Tournament Pages**: `/tournaments/tournament-name/`
- **Tournament Archive**: `/tournaments/`
- **Series Pages**: `/tournament-series/series-name/`
- **Player Profiles**: `/players/player-name/`

### Adding to Navigation Menus

1. Go to **Appearance → Menus**
2. Add tournament pages to your menu
3. Use **"Tournaments"** for the archive page
4. Add specific tournaments or series as needed

## Managing Tournaments

### Viewing All Tournaments

1. Go to **Poker Tournaments → All Tournaments**
2. You'll see a list of all imported tournaments
3. Each entry shows:
   - Tournament name and date
   - Number of players
   - Current status (Published/Draft/Private)
   - Quick actions (Edit/View/Delete)

### Editing Tournament Information

1. In the tournaments list, hover over a tournament
2. Click **"Edit"**
3. You can modify:
   - Tournament title and description
   - Additional details and notes
   - Featured image
   - SEO metadata

### Updating Tournament Results

If you need to correct tournament data:

1. **Best Method**: Re-import the corrected .tdt file
   - The plugin will update existing tournament
   - Maintains all original player statistics

2. **Manual Correction**: Edit individual player results
   - Go to tournament edit screen
   - Scroll to "Tournament Results" section
   - Modify player positions or winnings
   - Plugin will recalculate statistics automatically

### Managing Tournament Status

Change how tournaments appear on your site:

#### Published
- Visible to all website visitors
- Appears in tournament listings
- Included in search results

#### Draft
- Not visible to public
- Saved for later review
- Can be previewed by administrators

#### Private
- Only visible to logged-in administrators
- Useful for internal testing
- Not included in public listings

### Bulk Operations

Select multiple tournaments to:

- **Bulk Edit**: Change status, categories, or series
- **Bulk Delete**: Remove multiple tournaments
- **Export Data**: Download tournament information

## Player Management

### Automatic Player Creation

When you import tournaments, the plugin automatically:

- Creates player profiles for all participants
- Tracks tournament history for each player
- Calculates lifetime statistics
- Generates player rankings

### Viewing Player Profiles

1. Go to **Poker Tournaments → Players**
2. Browse the player directory or search for specific players
3. Click on any player name to see:
   - Complete tournament history
   - Lifetime statistics and achievements
   - Best finishes and biggest winnings
   - Tournament participation graph

### Player Statistics

Each player profile includes:

#### Performance Metrics
- **Total Tournaments**: Number of events played
- **Total Winnings**: Career earnings
- **Average Finish**: Typical finishing position
- **Best Finish**: Highest placement achieved
- **Cashes**: Number of times finishing in the money
- **Wins**: Number of tournament victories

#### Detailed History
- **Tournament List**: All events with dates and results
- **Earnings Chart**: Visual representation of winnings over time
- **Performance Trends**: Improvements or changes in play

### Managing Player Information

#### Edit Player Details

1. Go to **Poker Tournaments → Players**
2. Find the player and click **"Edit"**
3. Update information:
   - Display name
   - Profile picture
   - Bio/description
   - Contact information (if applicable)
   - Social media links

#### Merge Duplicate Players

If the same player appears with different name variations:

1. Select both player entries
2. Click **"Merge Players"**
3. Choose the primary player record
4. All tournament history will be consolidated

#### Player Privacy Options

Players can request privacy settings:
- **Public**: Full statistics visible
- **Limited**: Only basic information shown
- **Private**: Information hidden from public view

## Series and Seasons

### Understanding Tournament Organization

The plugin helps organize tournaments in two ways:

#### Tournament Series
- **Related events** that are part of a larger competition
- Examples: "Summer Championship Series," "Weekly Thursday Tournament"
- Share common branding and structure
- Have series-wide standings and prizes

#### Seasons
- **Time-based groupings** (typically yearly)
- Examples: "2024 Season," "Fall 2023"
- Help track annual statistics and rankings
- Useful for yearly awards and recognition

### Creating Tournament Series

#### Method 1: During Import

1. When importing a tournament, choose **"Create New Series"**
2. Enter series name and description
3. Add series details (start/end dates, rules, etc.)
4. Tournament automatically added to series

#### Method 2: Create Series First

1. Go to **Poker Tournaments → Series**
2. Click **"Add New Series"**
3. Fill in series information:
   - Series name and description
   - Start and end dates
   - Series rules and structure
   - Prize information
4. Save series, then add tournaments to it

### Managing Series

#### Series Dashboard

1. Go to **Poker Tournaments → Series**
2. Click on any series to see:
   - All tournaments in the series
   - Current series standings
   - Overall statistics
   - Remaining schedule

#### Series Standings

The plugin automatically calculates series-wide standings based on:
- **Points earned** across all series tournaments
- **Total winnings** in series events
- **Consistency** (number of tournaments played)
- **Final tournament** results (if applicable)

### Season Management

#### Automatic Season Creation

The plugin automatically creates seasons based on tournament dates:
- **2024 Season**: January 1 - December 31, 2024
- **Fall 2023**: September 1 - November 30, 2023

#### Manual Season Configuration

1. Go to **Poker Tournaments → Seasons**
2. Edit season settings:
   - Season name and dates
   - Which tournaments are included
   - Season-specific awards or recognition
   - Special rules or exceptions

### Series Awards and Recognition

#### Automatic Awards

The plugin can generate:
- **Series Champion**: Player with most points
- **Money Leader**: Player with highest winnings
- **Most Cashes**: Player with most money finishes
- **Rookie of the Year**: Best first-season performance

#### Custom Awards

Create custom awards:
1. Go to **Poker Tournaments → Awards**
2. Click **"Add New Award"**
3. Define award criteria and rules
4. Plugin automatically calculates winners

## Customizing Display

### Tournament Appearance Options

#### Colors and Branding

1. Go to **Poker Tournaments → Settings → Display**
2. Customize:
   - **Primary Color**: Main color scheme
   - **Secondary Color**: Accent colors
   - **Font Styles**: Typography choices
   - **Logo**: Upload your venue or league logo

#### Layout Options

Choose from different layout styles:
- **Classic**: Traditional tournament results table
- **Modern**: Card-based design with player photos
- **Compact**: Space-efficient for sidebars
- **Detailed**: Maximum information display

### Custom Templates

If you're comfortable with WordPress customization:

#### Create Child Theme

1. Create a child theme for your current theme
2. Copy plugin templates to your child theme
3. Customize the appearance without affecting plugin updates

#### Template Files

Key template files you can customize:
- `tournament-single.php`: Individual tournament page
- `tournament-archive.php`: Tournament listings
- `player-single.php`: Player profile pages
- `series-single.php`: Series overview pages

### CSS Customization

Add custom CSS to override default styles:

1. Go to **Appearance → Customize → Additional CSS**
2. Add your custom styles

**Common Customizations:**

```css
/* Change tournament title color */
.tournament-title {
    color: #your-color-hex;
}

/* Adjust results table styling */
.tournament-results-table {
    border: 2px solid #your-color-hex;
}

/* Customize player profile layout */
.player-profile-header {
    background-color: #your-color-hex;
}
```

### Mobile Optimization

The plugin automatically adapts to mobile devices, but you can fine-tune:

```css
/* Mobile-specific adjustments */
@media (max-width: 768px) {
    .tournament-results-table {
        font-size: 14px;
    }

    .player-profile-sidebar {
        display: none;
    }
}
```

### Integration with Page Builders

The plugin works with popular page builders:

#### Elementor Integration

1. Add a **Shortcode** widget
2. Use any tournament shortcode
3. Style the widget using Elementor controls

#### Divi Integration

1. Add a **Code** module
2. Insert tournament shortcode
3. Use Divi styling options

#### Gutenberg Integration

1. Add a **Shortcode** block
2. Enter tournament shortcode
3. Use block settings for layout

## Troubleshooting

### Common Import Issues

#### "Invalid File Format" Error

**Solution:**
1. Ensure you're exporting from Tournament Director as **JavaScript (.tdt)**
2. Check that the file hasn't been renamed or corrupted
3. Try re-exporting the file from Tournament Director
4. Verify file size is under 10MB

#### "Upload Failed" Error

**Solutions:**
1. Check your PHP upload limits:
   - Go to **Poker Tournaments → System Info**
  . Look for "upload_max_filesize" and "post_max_size"
   - Contact your hosting provider to increase if needed

2. Check file permissions:
   - Upload folder should be writable (755 permissions)
   - Contact your hosting provider if unsure

3. Try a different browser or clear browser cache

#### "Duplicate Tournament" Warning

**This is normal behavior** when importing the same tournament twice. Choose one of these options:

- **Update Existing**: Overwrites the previous import with new data
- **Create New**: Creates a separate tournament entry
- **Cancel**: Stops the import process

### Display Issues

#### Tournament Page Shows Blank

**Troubleshooting steps:**
1. Check if plugin is activated
2. Try deactivating other plugins to test for conflicts
3. Switch to a default WordPress theme temporarily
4. Check browser console for JavaScript errors
5. Clear website and browser caches

#### Shortcode Not Working

**Common fixes:**
1. Verify correct shortcode syntax (check for extra spaces or quotes)
2. Ensure tournament ID is correct
3. Try a different shortcode to test if plugin is working
4. Check that you're using the Text editor, not Visual editor

#### Styling Problems

**Solutions:**
1. Clear your website cache
2. Check for CSS conflicts with your theme
3. Try adding `!important` to custom CSS rules
4. Test with a different theme

### Performance Issues

#### Slow Loading Times

**Optimization tips:**
1. Enable caching in **Poker Tournaments → Settings → Performance**
2. Limit number of tournaments shown per page
3. Use pagination for large player lists
4. Optimize your images (if using player photos)

#### Memory Errors

**Solutions:**
1. Increase PHP memory limit in wp-config.php:
   ```php
   define('WP_MEMORY_LIMIT', '256M');
   ```
2. Break large tournament series into smaller groups
3. Contact your hosting provider for server optimization

### Getting Help

#### Plugin Support Resources

1. **Documentation**: This user manual and online guides
2. **Support Forum**: WordPress.org support forums
3. **Email Support**: Contact plugin developer directly
4. **Video Tutorials**: Step-by-step video guides

#### Reporting Issues

When reporting problems, include:
1. WordPress version
2. Plugin version
3. PHP version
4. Browser and operating system
5. Steps to reproduce the issue
6. Screenshots if possible
7. Error messages (if any)

#### System Information

Find technical details in **Poker Tournaments → System Info**:
- Server configuration
- PHP settings
- Plugin status
- Database information

## FAQ

### General Questions

**Q: Can I import tournaments from other poker software?**
A: Currently, the plugin only supports Tournament Director (.tdt) files. Support for other formats may be added in future versions.

**Q: How many tournaments can I store?**
A: There's no built-in limit. You can store thousands of tournaments, though performance may vary based on your hosting plan.

**Q: Can I export my tournament data?**
A: Yes, you can export tournament data in CSV format from the admin dashboard.

**Q: Is the plugin mobile-friendly?**
A: Yes, all tournament displays are fully responsive and work on all devices.

**Q: Can I use the plugin with any WordPress theme?**
A: The plugin is designed to work with any WordPress theme that follows WordPress coding standards.

### Import-Related Questions

**Q: What happens if I import the same tournament twice?**
A: The plugin will warn you about duplicates and let you choose whether to update the existing tournament or create a new one.

**Q: Can I edit tournament results after importing?**
A: Yes, you can manually edit player results, positions, and winnings. The plugin will automatically recalculate statistics.

**Q: How long does importing take?**
A: Most tournaments import in 10-30 seconds. Large tournaments (1000+ players) may take up to 2 minutes.

**Q: Can I import multiple tournaments at once?**
A: Currently, tournaments are imported one at a time. Batch import functionality is planned for a future version.

### Display and Customization Questions

**Q: Can I customize how tournament results look?**
A: Yes, you can customize colors, layouts, and even create custom templates if you're comfortable with WordPress theme development.

**Q: Can I show tournament results on my homepage?**
A: Yes, use shortcodes in widgets, page builders, or theme files to display tournaments anywhere on your site.

**Q: Are player profiles automatically created?**
A: Yes, the plugin automatically creates player profiles when you import tournaments. Players get complete statistics and tournament history.

**Q: Can I hide certain information from public view?**
A: Yes, you can set tournaments to private status and adjust privacy settings for individual players.

### Technical Questions

**Q: What are the server requirements?**
A: WordPress 6.0+, PHP 8.0+, and MySQL 5.7+. Most modern hosting plans meet these requirements.

**Q: Does the plugin work on multisite installations?**
A: Yes, the plugin is fully compatible with WordPress multisite networks.

**Q: Is my data backed up?**
A: Tournament data is stored in your WordPress database. Regular database backups are recommended.

**Q: Can I integrate with other plugins?**
A: The plugin includes hooks and filters that developers can use to extend functionality. Integration with popular plugins is supported.

### Business and Legal Questions

**Q: Can I use this plugin for commercial tournaments?**
A: Yes, the plugin is suitable for both recreational and commercial tournament management.

**Q: Are player privacy concerns addressed?**
A: Yes, the plugin includes privacy features and is GDPR compliant. Players can request data removal or privacy settings.

**Q: Can I charge for tournament entry through the plugin?**
A: The plugin handles results display. For online registration and payment processing, integration with e-commerce plugins is recommended.

**Q: Is there a limit on how many websites I can use the plugin on?**
A: The plugin is free and can be used on unlimited websites.

---

**Need More Help?**

- **Documentation**: [Online User Guide](https://docs.example.com/poker-tournament-import)
- **Support Forum**: [WordPress.org Support](https://wordpress.org/support/plugin/poker-tournament-import)
- **Video Tutorials**: [YouTube Channel](https://youtube.com/poker-tournament-import)
- **Contact**: support@example.com

**Stay Updated**

- Subscribe to our newsletter for plugin updates and tips
- Follow us on social media for announcements
- Join our community forum to connect with other tournament directors

---

*This user manual covers all features available in version 1.0.0 of the Poker Tournament Import plugin. Features and interface elements may vary slightly in different versions.*