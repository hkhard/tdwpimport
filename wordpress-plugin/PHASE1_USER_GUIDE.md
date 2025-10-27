# Tournament Manager User Guide
## Phase 1: Foundation Features

**Version:** 3.0.0
**For:** Poker Tournament Organizers
**Skill Level:** Beginner to Intermediate

---

## Table of Contents

1. [Getting Started](#1-getting-started)
2. [Tournament Templates](#2-tournament-templates)
3. [Blind Builder](#3-blind-builder)
4. [Prize Calculator](#4-prize-calculator)
5. [Player Management](#5-player-management)
6. [Player Registration (Frontend)](#6-player-registration-frontend)
7. [Complete Tournament Setup Workflow](#7-complete-tournament-setup-workflow)
8. [Troubleshooting](#8-troubleshooting)
9. [FAQ](#9-faq)

---

## 1. Getting Started

### What is Tournament Manager?

Tournament Manager is a comprehensive system for creating and managing poker tournaments directly from your WordPress site—no Tournament Director software required.

### What's Included in Phase 1?

Phase 1 provides the **foundation** for tournament creation:

✅ **Tournament Templates** - Save time with reusable tournament configurations
✅ **Blind Builder** - Create custom blind schedules with ease
✅ **Prize Calculator** - Calculate prize distributions automatically
✅ **Player Management** - Manage your player database
✅ **Player Registration** - Let players register online via your website

### Accessing Tournament Manager

After activating the plugin, you'll find two new menu items in your WordPress admin:

1. **Tournament Manager** (trophy icon)
   - Templates
   - Blind Builder
   - Prize Calculator

2. **Tournaments** → **Player Management**

---

## 2. Tournament Templates

### What are Tournament Templates?

Templates are **reusable configurations** for tournaments you run regularly. Instead of entering buy-in, rake, rebuy, and add-on settings every time, save them as a template.

### Creating Your First Template

**Step 1: Navigate to Templates**
```
WordPress Admin → Tournament Manager → Templates
```

**Step 2: Click "Add New Template"**

**Step 3: Fill in Template Details**

#### Basic Information
- **Template Name:** "Friday Night Tournament" (required)
- **Description:** "Weekly $50 freezeout with 1 rebuy" (optional)

#### Financial Settings

**Buy-in Configuration:**
- **Buy-in Amount:** $50.00
- **Rake Type:** Percentage or Fixed
  - If Percentage: 10% (player pays $55, $50 to pool, $5 rake)
  - If Fixed: $5 (player pays $55, $50 to pool, $5 rake)

**Rebuy Settings:**
- **Enable Rebuys:** Yes/No
- **Rebuy Amount:** $50.00 (if enabled)
- **Rebuy Rake:** 10% or $5
- **Max Rebuys per Player:** 1 (0 = unlimited)

**Add-on Settings:**
- **Enable Add-ons:** Yes/No
- **Add-on Amount:** $25.00 (if enabled)
- **Add-on Rake:** 10% or $2.50
- **Add-on Chips:** 5,000

**Step 4: Save Template**

Click **"Save Template"** button at the bottom.

### Using a Template

When creating a new tournament:
1. Select template from dropdown
2. All financial settings auto-populate
3. Customize if needed for this specific tournament

### Editing Templates

1. Go to **Tournament Manager → Templates**
2. Find your template in the list
3. Click **"Edit"**
4. Make changes
5. Click **"Save Template"**

### Cloning Templates

To create a similar template:
1. Find template in list
2. Click **"Clone"**
3. Rename and modify
4. Save as new template

**Example:** Clone "Friday $50" to create "Saturday $100"

### Deleting Templates

1. Find template in list
2. Click **"Delete"**
3. Confirm deletion

⚠️ **Warning:** Deleting a template does NOT affect tournaments already created with it.

---

## 3. Blind Builder

### What is the Blind Builder?

The Blind Builder lets you create **custom blind schedules** for your tournaments. Control every aspect: small blind, big blind, ante, and level duration.

### Default Blind Schedules

Three templates are included:

1. **Turbo** - 10-minute levels, fast-paced
2. **Standard** - 15-minute levels, balanced
3. **Deep Stack** - 20-minute levels, more play

### Creating a Custom Blind Schedule

**Step 1: Navigate to Blind Builder**
```
WordPress Admin → Tournament Manager → Blind Builder
```

**Step 2: Click "Add New Schedule"**

**Step 3: Enter Schedule Details**

- **Schedule Name:** "Friday Night 15-min"
- **Description:** "Custom schedule for weekly tournament"

**Step 4: Add Blind Levels**

Click **"Add Level"** for each blind level:

#### Level 1 (Example)
- **Small Blind:** 25
- **Big Blind:** 50
- **Ante:** 0
- **Duration:** 15 (minutes)
- **Level Type:** Regular

#### Level 5 (Break Example)
- **Level Type:** Break
- **Duration:** 10 (minutes)
- **Description:** "15-minute break"

**Step 5: Continue Adding Levels**

Add as many levels as needed. Typical schedules have 15-30 levels.

**Step 6: Save Schedule**

Click **"Save Blind Schedule"**

### Quick Start: Clone a Template

Instead of starting from scratch:

1. Click **"Clone"** on Turbo, Standard, or Deep Stack
2. Rename to your preference
3. Modify levels as needed
4. Save

**Example Modifications:**
- Change all durations from 15 to 20 minutes
- Add antes starting at Level 5
- Insert extra break levels

### Understanding Blind Level Progression

**Common Patterns:**

**Slow Progression (More Play):**
```
25/50 → 50/100 → 75/150 → 100/200
```

**Standard Progression:**
```
25/50 → 50/100 → 100/200 → 200/400
```

**Fast Progression (Turbo):**
```
25/50 → 100/200 → 300/600 → 600/1200
```

**Adding Antes:**
```
Level 1-4: No ante
Level 5+: Ante = 10% of big blind
```

### Exporting to PDF

To print your blind schedule:

1. Open schedule in Blind Builder
2. Click **"Export to PDF"**
3. Print or save PDF

Perfect for displaying on tablets/monitors during tournaments!

### Best Practices

✅ **Plan Total Duration:**
- Turbo: 2-3 hours (10-min levels)
- Standard: 4-5 hours (15-min levels)
- Deep Stack: 6+ hours (20-min levels)

✅ **Add Breaks:**
- Every 4-6 levels
- 10-15 minute duration
- Consider meal break for long tournaments

✅ **Ante Timing:**
- No antes: Levels 1-4
- Start antes: Level 5+
- Increase antes with blinds

---

## 4. Prize Calculator

### What is the Prize Calculator?

The Prize Calculator helps you **distribute prize money** fairly among tournament winners. No more manual calculations!

### Creating a Prize Structure

**Step 1: Navigate to Prize Calculator**
```
WordPress Admin → Tournament Manager → Prize Calculator
```

**Step 2: Click "Add New Structure"**

**Step 3: Enter Structure Details**

- **Structure Name:** "Top 3 Payout"
- **Description:** "50/30/20 distribution for 20+ players"

**Step 4: Set Number of Paid Places**

- **Positions Paid:** 3

**Step 5: Set Percentages**

Enter percentage for each position (must total 100%):

- **1st Place:** 50%
- **2nd Place:** 30%
- **3rd Place:** 20%

**Step 6: Save Structure**

Click **"Save Prize Structure"**

### Using Prize Templates

Six templates are included:

1. **Winner Takes All** - 100% to 1st
2. **Top 2 (60/40)** - 60% 1st, 40% 2nd
3. **Top 3 (50/30/20)** - 50% 1st, 30% 2nd, 20% 3rd
4. **Top 5 (40/25/20/10/5)** - Five places paid
5. **Top 10** - Ten places paid
6. **Top 20** - Twenty places paid

### Calculating Prizes

**Example:**

**Tournament Details:**
- 40 players
- $50 buy-in each
- Total prize pool: $2,000

**Using "Top 3 (50/30/20)" structure:**

1. **1st Place:** $2,000 × 50% = **$1,000**
2. **2nd Place:** $2,000 × 30% = **$600**
3. **3rd Place:** $2,000 × 20% = **$400**

### Handling Chops

When players agree to split the remaining prize money:

**Example Chop:**

**Before Chop (2 players remaining):**
- 1st: $1,000
- 2nd: $600

**After Even Chop:**
- Player A: $800 ($1,600 ÷ 2)
- Player B: $800 ($1,600 ÷ 2)

**After ICM Chop (based on chip counts):**
- Player A (75% chips): $1,000
- Player B (25% chips): $600

The calculator can help you determine fair chop amounts based on chip stacks.

### Common Prize Structures by Player Count

**10-20 Players:** Pay top 3 (50/30/20)
**20-40 Players:** Pay top 5 (40/25/20/10/5)
**40-100 Players:** Pay top 10 (30/20/15/10/7/6/5/3/2/2)
**100+ Players:** Pay top 10-20% of field

---

## 5. Player Management

### Overview

Player Management lets you maintain a **database of players** who participate in your tournaments. Store names, emails, UUIDs, and track their tournament history.

### Accessing Player Management

```
WordPress Admin → Tournaments → Player Management
```

### The Three Tabs

1. **Players List** - View all players
2. **Add/Edit Player** - Create or modify player records
3. **Import Players** - Bulk import from CSV

---

### Players List Tab

#### What You'll See

A table displaying all players with columns:
- **Name** - Player's full name
- **Email** - Contact email
- **UUID** - Unique identifier
- **Status** - Publish, Draft, or Pending
- **Created** - Registration date
- **Actions** - Edit, View, Delete buttons

#### Searching Players

Use the search box to find players by:
- Name
- Email
- UUID

**Example:** Type "john" to find all players with "john" in their name.

#### Sorting Players

Click column headers to sort:
- **By Name:** Alphabetical
- **By Date:** Newest first
- **By Status:** Group by status

#### Player Actions

**Edit:** Click "Edit" to modify player details
**View:** Click player name to see profile
**Delete:** Click "Delete" to remove player (with confirmation)

---

### Add/Edit Player Tab

#### Adding a New Player

**Step 1: Click "Add New Player" or go to Add/Edit Tab**

**Step 2: Fill in Player Information**

**Required Fields:**
- **Player Name:** Full name (e.g., "John Smith")

**Optional Fields:**
- **Email Address:** For communication
- **Phone Number:** For contact
- **Bio:** Player background/notes

**System Fields:**
- **UUID:** Auto-generated unique ID
- **Status:**
  - **Publish:** Active player
  - **Pending:** Awaiting approval
  - **Draft:** Inactive/test player

**Step 3: Save Player**

Click **"Save Player"** button.

#### Editing an Existing Player

1. Go to Players List tab
2. Click **"Edit"** next to player name
3. Modify fields
4. Click **"Save Player"**

#### Player Statistics

When editing a player, you'll see statistics:

- **Tournaments Played:** Total count
- **Wins:** 1st place finishes
- **Final Tables:** Top finishes
- **Total Winnings:** Cumulative prize money

These stats are calculated automatically from tournament results.

---

### Import Players Tab

#### When to Use Import

- **Migrating** from another system
- **Bulk adding** 100+ players
- **Updating** player information

#### Preparing Your CSV File

**Required Columns:**

| name | email | phone | uuid |
|------|-------|-------|------|
| John Smith | john@example.com | 555-1234 | UUID-001 |
| Jane Doe | jane@example.com | 555-5678 | UUID-002 |

**CSV Example:**
```csv
name,email,phone,uuid
John Smith,john@example.com,555-1234,UUID-001
Jane Doe,jane@example.com,555-5678,UUID-002
Mike Johnson,mike@example.com,555-9012,UUID-003
```

**Column Requirements:**
- **name:** Required
- **email:** Optional (but recommended)
- **phone:** Optional
- **uuid:** Optional (auto-generated if empty)

#### Import Process

**Step 1: Go to Import Tab**

**Step 2: Select CSV File**

Click **"Choose File"** and select your CSV.

**Step 3: Configure Import Options**

**Duplicate Handling:**
- **Skip Duplicates:** Don't import if UUID or email exists
- **Update Existing:** Replace existing player data

**Player Status:**
- **Publish:** Make all imported players active
- **Draft:** Import as inactive
- **Pending:** Import for review

**Step 4: Preview Import**

Click **"Preview Import"** button.

You'll see:
- **Total Rows:** Count in your CSV
- **Valid:** Rows that will import successfully
- **Invalid:** Rows with errors
- **Duplicates Found:** Existing players

**Step 5: Review Errors (if any)**

Common errors:
- Missing name
- Invalid email format
- Duplicate UUID

Fix errors in your CSV and re-upload if needed.

**Step 6: Execute Import**

If preview looks good, click **"Import Players"** button.

**Step 7: Review Results**

You'll see:
- **Created:** New players added
- **Updated:** Existing players modified
- **Skipped:** Duplicates not imported
- **Failed:** Errors encountered

#### Import Tips

✅ **Test First:** Import 5-10 rows as a test
✅ **Backup:** Export existing players before bulk import
✅ **Clean Data:** Remove duplicate rows in Excel first
✅ **UTF-8 Encoding:** Save CSV as UTF-8 to preserve special characters

---

## 6. Player Registration (Frontend)

### What is Frontend Registration?

Let players **register themselves** through your website using a simple form. No admin intervention required (unless you want approval).

### Adding Registration Form to Your Site

**Step 1: Create or Edit a Page**

Go to **Pages → Add New** (or edit existing page)

**Step 2: Add Shortcode**

In the page editor, add this shortcode:

```
[player_registration]
```

**Step 3: Publish Page**

Save and publish your page.

That's it! Players can now register at that URL.

### Customizing the Registration Form

The shortcode accepts several attributes:

#### Basic Customization

```
[player_registration title="Join Our League"]
```

**Available Attributes:**

| Attribute | Default | Options | Description |
|-----------|---------|---------|-------------|
| `title` | "Player Registration" | Any text | Form heading |
| `require_email` | "yes" | "yes" or "no" | Make email required |
| `require_phone` | "no" | "yes" or "no" | Make phone required |
| `show_bio` | "no" | "yes" or "no" | Show bio field |
| `success_message` | Default message | Any text | Custom success message |

#### Example: Full Customization

```
[player_registration
    title="Join Friday Night Poker"
    require_email="yes"
    require_phone="yes"
    show_bio="yes"
    success_message="Thanks! We'll contact you soon."]
```

### What Players See

1. **Form Fields:**
   - Full Name (required)
   - Email Address (required if enabled)
   - Phone Number (optional/required)
   - Bio (if enabled)

2. **Submit Button:** "Register"

3. **Success Message:** After submission

4. **Error Messages:** If validation fails

### Registration Workflow

**Player Side:**
1. Player visits your registration page
2. Fills out form
3. Clicks "Register"
4. Sees success message

**Admin Side:**
1. Receives email notification
2. Goes to **Tournaments → Player Management**
3. Sees new player with **Status: Pending**
4. Reviews player details
5. Changes status to **Publish** to approve

### Email Notification

When a player registers, you (admin) receive an email:

```
Subject: [Your Site] New Player Registration

A new player has registered:

Name: John Smith
Email: john@example.com

Review this player:
[Link to edit player]
```

### Spam Protection

The registration form includes **honeypot protection**:

- Hidden field that bots typically fill out
- Real users never see this field
- If filled, submission is rejected

This prevents most spam bot registrations without requiring CAPTCHA.

### Styling the Form

The form uses CSS classes you can customize:

```css
.tdwp-player-registration-form {
    max-width: 600px;
    margin: 0 auto;
}

.player-registration-form input[type="text"],
.player-registration-form input[type="email"] {
    width: 100%;
    padding: 12px;
}

.player-registration-form .submit-button {
    background: #2271b1;
    color: white;
    padding: 14px 40px;
}
```

Add custom CSS in **Appearance → Customize → Additional CSS**.

---

## 7. Complete Tournament Setup Workflow

### End-to-End Example

Let's set up a complete tournament from scratch using all Phase 1 features.

#### Scenario: Friday Night $50 Tournament

**Tournament Details:**
- 30-40 players expected
- $50 buy-in + $5 rake
- 1 rebuy allowed ($50 + $5)
- 1 add-on available ($25 + $2.50)
- 15-minute blind levels
- Top 3 paid (50/30/20)

---

### Step 1: Create Tournament Template

**Path:** Tournament Manager → Templates → Add New

**Settings:**
```
Name: Friday Night $50
Buy-in: $50
Rake: 10% ($5)
Rebuys: Enabled, $50, 10% rake, Max 1
Add-on: Enabled, $25, 10% rake
```

**Result:** Template saved for weekly reuse

---

### Step 2: Create Blind Schedule

**Path:** Tournament Manager → Blind Builder → Clone "Standard"

**Modifications:**
```
Name: Friday 15-Min Standard
Clone from: Standard Template

Levels (sample):
1.  25/50 (15 min)
2.  50/100 (15 min)
3.  75/150 (15 min)
4.  100/200 (15 min)
--- BREAK (10 min) ---
5.  150/300 (15 min)
6.  200/400 (15 min)
7.  300/600 (15 min)
8.  400/800 (15 min)
... continue to Level 15
```

**Result:** Custom schedule saved

---

### Step 3: Create Prize Structure

**Path:** Tournament Manager → Prize Calculator → Use Template

**Settings:**
```
Template: Top 3 (50/30/20)
Name: Friday Standard Payout

Percentages:
1st: 50%
2nd: 30%
3rd: 20%
```

**Result:** Prize structure ready

---

### Step 4: Set Up Player Registration

**Path:** Pages → Add New

**Page Setup:**
```
Title: Friday Night Poker Registration
Slug: /register-friday-poker

Content:
Join us every Friday night for exciting poker action!

[player_registration
    title="Register for Friday Night Poker"
    require_email="yes"
    require_phone="yes"]

Tournament Details:
• $50 buy-in + $5 rake
• 1 rebuy and 1 add-on available
• Starts at 7:00 PM sharp
• Top 3 paid

Questions? Email us at poker@yoursite.com
```

**Result:** Public registration page live

---

### Step 5: Import Existing Players

**Path:** Tournaments → Player Management → Import

**Process:**
1. Prepare CSV with regular players
2. Upload CSV
3. Preview: 25 players, all valid
4. Import with status: "Publish"

**Result:** 25 regular players in database

---

### Step 6: Calculate Final Prizes

**Tournament Day Results:**
```
Total Players: 35
Buy-ins: 35 × $55 = $1,925
Rake: 35 × $5 = $175
Prize Pool: $1,925 - $175 = $1,750

Using "Top 3 (50/30/20)":
1st Place: $1,750 × 50% = $875
2nd Place: $1,750 × 30% = $525
3rd Place: $1,750 × 20% = $350
```

---

## 8. Troubleshooting

### Common Issues

#### "Permission Denied" Error

**Problem:** Can't access Tournament Manager pages
**Solution:** You need Administrator role. Contact site admin.

#### Player Import Fails

**Problem:** CSV import shows all errors
**Solution:**
- Ensure CSV has "name" column (required)
- Check file encoding is UTF-8
- Verify no special characters in data
- Try importing just 5 rows as a test

#### Registration Form Not Showing

**Problem:** Shortcode displays as text
**Solution:**
- Check you're using text/code editor, not visual
- Ensure shortcode is spelled correctly: `[player_registration]`
- Clear cache if using caching plugin

#### Blind Schedule PDF Won't Download

**Problem:** PDF export button doesn't work
**Solution:**
- Check browser pop-up blocker
- Try different browser
- Contact host about PDF library support

---

## 9. FAQ

### General Questions

**Q: Can I use Tournament Manager without Tournament Director software?**
A: Yes! That's the whole point. Phase 1 lets you create tournaments entirely within WordPress.

**Q: Will this work with my existing tournaments?**
A: Yes, Phase 1 features complement existing .tdt import functionality.

**Q: Can I migrate from another tournament system?**
A: Yes, use CSV import to bring over player data.

### Template Questions

**Q: Can I edit a template after tournaments are created with it?**
A: Yes, but it won't affect tournaments already created. Templates are like blueprints.

**Q: How many templates can I create?**
A: Unlimited.

**Q: Can templates be shared between WordPress sites?**
A: Not directly, but you can export/import via database if needed.

### Blind Builder Questions

**Q: Can I create different schedules for different tournament types?**
A: Yes! Create as many schedules as you need.

**Q: What's the maximum number of blind levels?**
A: No technical limit, but 30-50 is practical maximum.

**Q: Can I include color-up breaks?**
A: Yes, use "Break" level type and note "Color-up" in description.

### Player Management Questions

**Q: What happens to player statistics when I delete a player?**
A: Player post is deleted, but historical tournament results remain in database.

**Q: Can players update their own information?**
A: Not in Phase 1. They register once, admin manages thereafter.

**Q: How do I prevent duplicate players?**
A: Use UUID or email matching during import. Manual adds should check existing players first.

### Registration Questions

**Q: Can I require admin approval for registrations?**
A: Yes! All registrations default to status="Pending". Approve by changing to "Publish".

**Q: How do I stop spam registrations?**
A: Built-in honeypot protection catches most bots. Add reCAPTCHA for extra protection (see Security Recommendations).

**Q: Can I customize the registration form design?**
A: Yes, using custom CSS in your theme.

---

## Next Steps

### Phase 2 Features (Coming Soon)

- Live tournament clock
- Table management and seating
- Real-time player tracking
- Automatic statistics

### Need Help?

- **Documentation:** https://docs.yoursite.com
- **Support Forum:** https://support.yoursite.com
- **Email:** support@yoursite.com

---

**Document Version:** 1.0
**Last Updated:** 2025-01-27
**Compatible With:** Tournament Import Plugin v3.0.0+

**Feedback?** We'd love to hear how you're using Tournament Manager! Contact us with suggestions or feature requests.
