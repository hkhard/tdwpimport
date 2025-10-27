import DocLayout from '@/components/doc/DocLayout'
import Link from 'next/link'
import {
  AlertCircle,
  CheckCircle,
  Info,
  Download,
  Upload,
  Calendar,
  Users,
  TrendingUp,
  Monitor,
  Code,
  Palette,
  HelpCircle,
  ChevronRight
} from 'lucide-react'

export const metadata = {
  title: 'User Manual - TD WP Import',
  description: 'Complete user manual for the Poker Tournament Import WordPress plugin',
}

export default function UserManualPage() {
  return (
    <DocLayout
      title="User Manual"
      description="Complete guide to using the Poker Tournament Import plugin"
    >
      {/* Table of Contents */}
      <nav className="bg-gradient-to-br from-primary/5 to-navy/5 rounded-xl p-8 mb-8 border border-primary/20 not-prose">
        <div className="flex items-center gap-3 mb-6">
          <div className="w-12 h-12 bg-primary/10 rounded-lg flex items-center justify-center">
            <Code className="w-6 h-6 text-primary" />
          </div>
          <h2 className="text-2xl font-bold text-navy mb-0">Table of Contents</h2>
        </div>
        <div className="grid grid-cols-1 md:grid-cols-2 gap-3">
          <a href="#installation" className="flex items-center gap-2 px-4 py-3 bg-white rounded-lg hover:bg-primary/5 transition-all border border-gray-200 hover:border-primary group">
            <Download className="w-4 h-4 text-primary" />
            <span className="text-gray-700 group-hover:text-primary font-medium">1. Installation</span>
            <ChevronRight className="w-4 h-4 ml-auto text-gray-400 group-hover:text-primary" />
          </a>
          <a href="#first-import" className="flex items-center gap-2 px-4 py-3 bg-white rounded-lg hover:bg-primary/5 transition-all border border-gray-200 hover:border-primary group">
            <Upload className="w-4 h-4 text-primary" />
            <span className="text-gray-700 group-hover:text-primary font-medium">2. Your First Import</span>
            <ChevronRight className="w-4 h-4 ml-auto text-gray-400 group-hover:text-primary" />
          </a>
          <a href="#tournaments" className="flex items-center gap-2 px-4 py-3 bg-white rounded-lg hover:bg-primary/5 transition-all border border-gray-200 hover:border-primary group">
            <Calendar className="w-4 h-4 text-primary" />
            <span className="text-gray-700 group-hover:text-primary font-medium">3. Managing Tournaments</span>
            <ChevronRight className="w-4 h-4 ml-auto text-gray-400 group-hover:text-primary" />
          </a>
          <a href="#players" className="flex items-center gap-2 px-4 py-3 bg-white rounded-lg hover:bg-primary/5 transition-all border border-gray-200 hover:border-primary group">
            <Users className="w-4 h-4 text-primary" />
            <span className="text-gray-700 group-hover:text-primary font-medium">4. Player Management</span>
            <ChevronRight className="w-4 h-4 ml-auto text-gray-400 group-hover:text-primary" />
          </a>
          <a href="#series" className="flex items-center gap-2 px-4 py-3 bg-white rounded-lg hover:bg-primary/5 transition-all border border-gray-200 hover:border-primary group">
            <TrendingUp className="w-4 h-4 text-primary" />
            <span className="text-gray-700 group-hover:text-primary font-medium">5. Series & Seasons</span>
            <ChevronRight className="w-4 h-4 ml-auto text-gray-400 group-hover:text-primary" />
          </a>
          <a href="#display" className="flex items-center gap-2 px-4 py-3 bg-white rounded-lg hover:bg-primary/5 transition-all border border-gray-200 hover:border-primary group">
            <Monitor className="w-4 h-4 text-primary" />
            <span className="text-gray-700 group-hover:text-primary font-medium">6. Displaying Results</span>
            <ChevronRight className="w-4 h-4 ml-auto text-gray-400 group-hover:text-primary" />
          </a>
          <a href="#shortcodes" className="flex items-center gap-2 px-4 py-3 bg-white rounded-lg hover:bg-primary/5 transition-all border border-gray-200 hover:border-primary group">
            <Code className="w-4 h-4 text-primary" />
            <span className="text-gray-700 group-hover:text-primary font-medium">7. Shortcodes</span>
            <ChevronRight className="w-4 h-4 ml-auto text-gray-400 group-hover:text-primary" />
          </a>
          <a href="#customization" className="flex items-center gap-2 px-4 py-3 bg-white rounded-lg hover:bg-primary/5 transition-all border border-gray-200 hover:border-primary group">
            <Palette className="w-4 h-4 text-primary" />
            <span className="text-gray-700 group-hover:text-primary font-medium">8. Customization</span>
            <ChevronRight className="w-4 h-4 ml-auto text-gray-400 group-hover:text-primary" />
          </a>
          <a href="#troubleshooting" className="flex items-center gap-2 px-4 py-3 bg-white rounded-lg hover:bg-primary/5 transition-all border border-gray-200 hover:border-primary group">
            <AlertCircle className="w-4 h-4 text-primary" />
            <span className="text-gray-700 group-hover:text-primary font-medium">9. Troubleshooting</span>
            <ChevronRight className="w-4 h-4 ml-auto text-gray-400 group-hover:text-primary" />
          </a>
          <a href="#faq" className="flex items-center gap-2 px-4 py-3 bg-white rounded-lg hover:bg-primary/5 transition-all border border-gray-200 hover:border-primary group">
            <HelpCircle className="w-4 h-4 text-primary" />
            <span className="text-gray-700 group-hover:text-primary font-medium">10. FAQ</span>
            <ChevronRight className="w-4 h-4 ml-auto text-gray-400 group-hover:text-primary" />
          </a>
        </div>
      </nav>

      {/* Installation */}
      <section id="installation" className="mb-12 scroll-mt-20">
        <div className="border-l-4 border-blue-500 bg-blue-50/50 rounded-r-xl p-8">
          <div className="flex items-center gap-4 mb-6">
            <div className="w-14 h-14 bg-blue-500 rounded-xl flex items-center justify-center flex-shrink-0">
              <Download className="w-7 h-7 text-white" />
            </div>
            <div>
              <h2 className="text-3xl font-bold text-navy mb-1">1. Installation</h2>
              <p className="text-gray-600 mb-0">Get the plugin installed on your WordPress site</p>
            </div>
          </div>

          <div className="space-y-6">
            <div className="bg-white rounded-lg p-6 border border-blue-200">
              <h3 className="text-xl font-semibold text-navy mb-4 flex items-center gap-2">
                <span className="w-8 h-8 bg-blue-100 rounded-lg flex items-center justify-center text-blue-600 font-bold text-sm">A</span>
                Method 1: WordPress Admin (Recommended)
              </h3>
              <ol className="space-y-2">
                <li>Visit <a href="https://wordpress.org/plugins/poker-tournament-import/" target="_blank" rel="noopener noreferrer">WordPress Plugin Directory</a></li>
                <li>Click <strong>Download</strong> to save poker-tournament-import.zip</li>
                <li>In WordPress admin, go to <strong>Plugins → Add New</strong></li>
                <li>Click <strong>Upload Plugin</strong></li>
                <li>Choose the downloaded .zip file and click <strong>Install Now</strong></li>
                <li>Click <strong>Activate Plugin</strong></li>
              </ol>
            </div>

            <div className="bg-white rounded-lg p-6 border border-blue-200">
              <h3 className="text-xl font-semibold text-navy mb-4 flex items-center gap-2">
                <span className="w-8 h-8 bg-blue-100 rounded-lg flex items-center justify-center text-blue-600 font-bold text-sm">B</span>
                Method 2: FTP Upload
              </h3>
              <ol className="space-y-2">
                <li>Extract poker-tournament-import.zip on your computer</li>
                <li>Connect to your site via FTP</li>
                <li>Upload the poker-tournament-import folder to /wp-content/plugins/</li>
                <li>In WordPress admin, go to <strong>Plugins</strong></li>
                <li>Find "Poker Tournament Import" and click <strong>Activate</strong></li>
              </ol>
            </div>

            <div className="bg-blue-50 border border-blue-200 rounded-lg p-6">
              <div className="flex items-start gap-3">
                <Info className="w-6 h-6 text-blue-600 flex-shrink-0 mt-1" />
                <div>
                  <p className="font-semibold text-navy mb-2">Initial Setup</p>
                  <p className="text-gray-700 mb-0">After activation, you'll see a new "Poker Import" menu in your WordPress admin. The setup wizard will guide you through basic configuration.</p>
                </div>
              </div>
            </div>
          </div>
        </div>
      </section>

      {/* First Import */}
      <section id="first-import" className="mb-12 scroll-mt-20">
        <div className="border-l-4 border-green-500 bg-green-50/50 rounded-r-xl p-8">
          <div className="flex items-center gap-4 mb-6">
            <div className="w-14 h-14 bg-green-500 rounded-xl flex items-center justify-center flex-shrink-0">
              <Upload className="w-7 h-7 text-white" />
            </div>
            <div>
              <h2 className="text-3xl font-bold text-navy mb-1">2. Your First Tournament Import</h2>
              <p className="text-gray-600 mb-0">Import your first tournament from Tournament Director</p>
            </div>
          </div>

          <div className="space-y-6">
            <div className="bg-white rounded-lg p-6 border border-green-200">
              <h3 className="text-xl font-semibold text-navy mb-4">Step 1: Export from Tournament Director</h3>
              <ol className="space-y-2">
                <li>Open Tournament Director software</li>
                <li>Load the tournament you want to publish</li>
                <li>Go to <strong>File → Export → Tournament Data</strong></li>
                <li>Choose <strong>JavaScript (.tdt)</strong> format</li>
                <li>Save the file to your computer</li>
              </ol>
            </div>

            <div className="bg-white rounded-lg p-6 border border-green-200">
              <h3 className="text-xl font-semibold text-navy mb-4">Step 2: Import to WordPress</h3>
              <ol className="space-y-2">
                <li>In WordPress admin, go to <strong>Poker Import → Import Tournament</strong></li>
                <li>Click <strong>Choose File</strong> or drag and drop your .tdt file</li>
                <li>Review the parsed tournament information</li>
                <li>Configure import options:
                  <ul className="mt-2 space-y-1">
                    <li><strong>Publish Status:</strong> Publish, Draft, or Private</li>
                    <li><strong>Tournament Series:</strong> Create new or select existing</li>
                    <li><strong>Player Profiles:</strong> Create new players or link existing</li>
                  </ul>
                </li>
                <li>Click <strong>Import Tournament</strong></li>
                <li>Wait for processing (10-30 seconds)</li>
                <li>Click <strong>View Tournament</strong> to see published results</li>
              </ol>
            </div>

            <div className="bg-green-50 border border-green-200 rounded-lg p-6">
              <div className="flex items-start gap-3">
                <CheckCircle className="w-6 h-6 text-green-600 flex-shrink-0 mt-1" />
                <div>
                  <p className="font-semibold text-navy mb-2">Success!</p>
                  <p className="text-gray-700 mb-0">Your tournament is now published with automatic player profiles, statistics, and rankings. Results are immediately visible to site visitors.</p>
                </div>
              </div>
            </div>
          </div>
        </div>
      </section>

      {/* Managing Tournaments */}
      <section id="tournaments" className="mb-12 scroll-mt-20">
        <div className="border-l-4 border-purple-500 bg-purple-50/50 rounded-r-xl p-8">
          <div className="flex items-center gap-4 mb-6">
            <div className="w-14 h-14 bg-purple-500 rounded-xl flex items-center justify-center flex-shrink-0">
              <Calendar className="w-7 h-7 text-white" />
            </div>
            <div>
              <h2 className="text-3xl font-bold text-navy mb-1">3. Managing Tournaments</h2>
              <p className="text-gray-600 mb-0">View, edit, and organize your tournaments</p>
            </div>
          </div>

          <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div className="bg-white rounded-lg p-6 border border-purple-200">
              <h3 className="text-lg font-semibold text-navy mb-3">Viewing All Tournaments</h3>
              <p className="text-gray-700 mb-3">Go to <strong>Poker Import → All Tournaments</strong> to see:</p>
              <ul className="space-y-1 text-gray-700">
                <li>• Tournament name and date</li>
                <li>• Number of players</li>
                <li>• Current status (Published/Draft/Private)</li>
                <li>• Quick actions (Edit/View/Delete)</li>
              </ul>
            </div>

            <div className="bg-white rounded-lg p-6 border border-purple-200">
              <h3 className="text-lg font-semibold text-navy mb-3">Editing Tournament Information</h3>
              <ol className="space-y-1 text-gray-700">
                <li>Hover over a tournament in the list</li>
                <li>Click <strong>Edit</strong></li>
                <li>Modify tournament details, description, or featured image</li>
                <li>Click <strong>Update</strong> to save changes</li>
              </ol>
            </div>

            <div className="bg-white rounded-lg p-6 border border-purple-200">
              <h3 className="text-lg font-semibold text-navy mb-3">Updating Tournament Results</h3>
              <p className="text-gray-700 mb-2">If you need to correct tournament data:</p>
              <ul className="space-y-1 text-gray-700">
                <li><strong>Best Method:</strong> Re-import the corrected .tdt file</li>
                <li><strong>Manual Method:</strong> Edit individual player results in the tournament edit screen</li>
              </ul>
            </div>

            <div className="bg-white rounded-lg p-6 border border-purple-200">
              <h3 className="text-lg font-semibold text-navy mb-3">Tournament Status Options</h3>
              <ul className="space-y-2 text-gray-700">
                <li><strong className="text-navy">Published:</strong> Visible to all website visitors</li>
                <li><strong className="text-navy">Draft:</strong> Not visible to public, can be previewed</li>
                <li><strong className="text-navy">Private:</strong> Only visible to administrators</li>
              </ul>
            </div>
          </div>
        </div>
      </section>

      {/* Player Management */}
      <section id="players" className="mb-12 scroll-mt-20">
        <div className="border-l-4 border-amber-500 bg-amber-50/50 rounded-r-xl p-8">
          <div className="flex items-center gap-4 mb-6">
            <div className="w-14 h-14 bg-amber-500 rounded-xl flex items-center justify-center flex-shrink-0">
              <Users className="w-7 h-7 text-white" />
            </div>
            <div>
              <h2 className="text-3xl font-bold text-navy mb-1">4. Player Management</h2>
              <p className="text-gray-600 mb-0">Manage player profiles and statistics</p>
            </div>
          </div>

          <div className="space-y-6">
            <div className="bg-amber-50 border border-amber-200 rounded-lg p-6">
              <p className="text-gray-700 mb-3 font-medium">The plugin automatically creates player profiles when you import tournaments. Each player gets:</p>
              <div className="grid grid-cols-1 md:grid-cols-2 gap-3">
                <div className="flex items-center gap-2 text-gray-700">
                  <CheckCircle className="w-4 h-4 text-amber-600" />
                  Complete tournament history
                </div>
                <div className="flex items-center gap-2 text-gray-700">
                  <CheckCircle className="w-4 h-4 text-amber-600" />
                  Lifetime statistics and achievements
                </div>
                <div className="flex items-center gap-2 text-gray-700">
                  <CheckCircle className="w-4 h-4 text-amber-600" />
                  Best finishes and biggest winnings
                </div>
                <div className="flex items-center gap-2 text-gray-700">
                  <CheckCircle className="w-4 h-4 text-amber-600" />
                  ROI analytics and net profit tracking
                </div>
              </div>
            </div>

            <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
              <div className="bg-white rounded-lg p-6 border border-amber-200">
                <h3 className="text-lg font-semibold text-navy mb-3">Viewing Player Profiles</h3>
                <ol className="space-y-1 text-gray-700">
                  <li>Go to <strong>Poker Import → Players</strong></li>
                  <li>Browse the player directory or search for specific players</li>
                  <li>Click any player name to view their complete profile</li>
                </ol>
              </div>

              <div className="bg-white rounded-lg p-6 border border-amber-200">
                <h3 className="text-lg font-semibold text-navy mb-3">Player Statistics</h3>
                <p className="text-gray-700 mb-2">Each player profile includes:</p>
                <ul className="space-y-1 text-sm text-gray-700">
                  <li>• <strong>Total Tournaments:</strong> Events played</li>
                  <li>• <strong>Total Winnings:</strong> Career earnings</li>
                  <li>• <strong>Average Finish:</strong> Typical finishing position</li>
                  <li>• <strong>Best Finish:</strong> Highest placement</li>
                  <li>• <strong>Cashes:</strong> Times finishing in the money</li>
                  <li>• <strong>Wins:</strong> Tournament victories</li>
                </ul>
              </div>
            </div>

            <div className="bg-white rounded-lg p-6 border border-amber-200">
              <h3 className="text-lg font-semibold text-navy mb-3">Editing Player Details</h3>
              <ol className="space-y-1 text-gray-700">
                <li>Go to <strong>Poker Import → Players</strong></li>
                <li>Find the player and click <strong>Edit</strong></li>
                <li>Update display name, profile picture, bio, or contact information</li>
                <li>Click <strong>Update</strong> to save changes</li>
              </ol>
            </div>
          </div>
        </div>
      </section>

      {/* Series & Seasons */}
      <section id="series" className="mb-12 scroll-mt-20">
        <div className="border-l-4 border-teal-500 bg-teal-50/50 rounded-r-xl p-8">
          <div className="flex items-center gap-4 mb-6">
            <div className="w-14 h-14 bg-teal-500 rounded-xl flex items-center justify-center flex-shrink-0">
              <TrendingUp className="w-7 h-7 text-white" />
            </div>
            <div>
              <h2 className="text-3xl font-bold text-navy mb-1">5. Series & Seasons</h2>
              <p className="text-gray-600 mb-0">Organize tournaments into series and seasons</p>
            </div>
          </div>

          <div className="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
            <div className="bg-white rounded-lg p-6 border border-teal-200">
              <h3 className="text-lg font-semibold text-navy mb-3">Tournament Series</h3>
              <p className="text-gray-700 mb-2">Series are related events that are part of a larger competition:</p>
              <ul className="space-y-1 text-sm text-gray-700">
                <li>• Examples: "Summer Championship Series," "Weekly Thursday Tournament"</li>
                <li>• Share common branding and structure</li>
                <li>• Have series-wide standings and prizes</li>
              </ul>
            </div>

            <div className="bg-white rounded-lg p-6 border border-teal-200">
              <h3 className="text-lg font-semibold text-navy mb-3">Seasons</h3>
              <p className="text-gray-700 mb-2">Seasons are time-based groupings (typically yearly):</p>
              <ul className="space-y-1 text-sm text-gray-700">
                <li>• Examples: "2024 Season," "Fall 2023"</li>
                <li>• Help track annual statistics and rankings</li>
                <li>• Useful for yearly awards and recognition</li>
              </ul>
            </div>
          </div>

          <div className="space-y-6">
            <div className="bg-white rounded-lg p-6 border border-teal-200">
              <h3 className="text-lg font-semibold text-navy mb-3">Creating a Tournament Series</h3>
              <ol className="space-y-1 text-gray-700">
                <li>Go to <strong>Poker Import → Series</strong></li>
                <li>Click <strong>Add New Series</strong></li>
                <li>Enter series name, description, and dates</li>
                <li>Add series rules and prize information</li>
                <li>Click <strong>Publish</strong></li>
              </ol>
              <p className="mt-3 text-sm text-gray-600 italic">Alternatively, create a series during tournament import by selecting "Create New Series."</p>
            </div>

            <div className="bg-white rounded-lg p-6 border border-teal-200">
              <h3 className="text-lg font-semibold text-navy mb-3">Series Standings</h3>
              <p className="text-gray-700 mb-2">The plugin automatically calculates series standings based on:</p>
              <ul className="space-y-1 text-gray-700">
                <li>• Points earned across all series tournaments</li>
                <li>• Total winnings in series events</li>
                <li>• Number of tournaments played (consistency)</li>
              </ul>
            </div>
          </div>
        </div>
      </section>

      {/* Displaying Results */}
      <section id="display" className="mb-12 scroll-mt-20">
        <div className="border-l-4 border-indigo-500 bg-indigo-50/50 rounded-r-xl p-8">
          <div className="flex items-center gap-4 mb-6">
            <div className="w-14 h-14 bg-indigo-500 rounded-xl flex items-center justify-center flex-shrink-0">
              <Monitor className="w-7 h-7 text-white" />
            </div>
            <div>
              <h2 className="text-3xl font-bold text-navy mb-1">6. Displaying Results</h2>
              <p className="text-gray-600 mb-0">Show tournament results on your website</p>
            </div>
          </div>

          <div className="bg-indigo-50 border border-indigo-200 rounded-lg p-6 mb-6">
            <p className="text-gray-700 mb-3 font-medium">Tournament results are displayed automatically on dedicated pages:</p>
            <div className="grid grid-cols-1 md:grid-cols-2 gap-3">
              <div className="bg-white rounded-lg p-4 border border-indigo-100">
                <code className="text-sm text-indigo-600">/tournaments/tournament-name/</code>
                <p className="text-xs text-gray-600 mt-1">Individual Tournaments</p>
              </div>
              <div className="bg-white rounded-lg p-4 border border-indigo-100">
                <code className="text-sm text-indigo-600">/tournaments/</code>
                <p className="text-xs text-gray-600 mt-1">Tournament Archive</p>
              </div>
              <div className="bg-white rounded-lg p-4 border border-indigo-100">
                <code className="text-sm text-indigo-600">/tournament-series/series-name/</code>
                <p className="text-xs text-gray-600 mt-1">Series Pages</p>
              </div>
              <div className="bg-white rounded-lg p-4 border border-indigo-100">
                <code className="text-sm text-indigo-600">/players/player-name/</code>
                <p className="text-xs text-gray-600 mt-1">Player Profiles</p>
              </div>
            </div>
          </div>

          <div className="bg-white rounded-lg p-6 border border-indigo-200">
            <h3 className="text-lg font-semibold text-navy mb-3">Adding to Navigation Menus</h3>
            <ol className="space-y-1 text-gray-700">
              <li>Go to <strong>Appearance → Menus</strong></li>
              <li>Add tournament pages to your menu</li>
              <li>Use "Tournaments" for the archive page</li>
              <li>Add specific tournaments or series as needed</li>
            </ol>
          </div>
        </div>
      </section>

      {/* Shortcodes */}
      <section id="shortcodes" className="mb-12 scroll-mt-20">
        <div className="border-l-4 border-cyan-500 bg-cyan-50/50 rounded-r-xl p-8">
          <div className="flex items-center gap-4 mb-6">
            <div className="w-14 h-14 bg-cyan-500 rounded-xl flex items-center justify-center flex-shrink-0">
              <Code className="w-7 h-7 text-white" />
            </div>
            <div>
              <h2 className="text-3xl font-bold text-navy mb-1">7. Shortcodes Reference</h2>
              <p className="text-gray-600 mb-0">Display tournament data anywhere on your site</p>
            </div>
          </div>

          <div className="grid grid-cols-1 gap-4">
            <div className="bg-gray-900 rounded-lg p-5 border border-gray-700">
              <div className="flex items-start justify-between mb-2">
                <div className="flex-1">
                  <code className="text-green-400 font-mono text-sm">[tournament_results id="123"]</code>
                  <p className="text-gray-300 text-sm mt-2">Display specific tournament results</p>
                </div>
              </div>
              <div className="mt-3 text-xs text-gray-400">
                Options: <code className="text-cyan-400">show_details="yes"</code> | <code className="text-cyan-400">show_players="yes"</code> | <code className="text-cyan-400">show_statistics="yes"</code>
              </div>
            </div>

            <div className="bg-gray-900 rounded-lg p-5 border border-gray-700">
              <code className="text-green-400 font-mono text-sm">[tournament_series id="456"]</code>
              <p className="text-gray-300 text-sm mt-2">Show all tournaments in a series with standings and overall results</p>
            </div>

            <div className="bg-gray-900 rounded-lg p-5 border border-gray-700">
              <code className="text-green-400 font-mono text-sm">[player_profile name="John Doe"]</code>
              <p className="text-gray-300 text-sm mt-2">Display a player's tournament history and statistics</p>
            </div>

            <div className="bg-gray-900 rounded-lg p-5 border border-gray-700">
              <code className="text-green-400 font-mono text-sm">[poker_tournaments limit="10"]</code>
              <p className="text-gray-300 text-sm mt-2">Show your most recent tournaments in a list format</p>
            </div>

            <div className="bg-gray-900 rounded-lg p-5 border border-gray-700">
              <code className="text-green-400 font-mono text-sm">[poker_leaderboard season="2024" limit="25"]</code>
              <p className="text-gray-300 text-sm mt-2">Show the top players for a specific season or all-time</p>
            </div>

            <div className="bg-gray-900 rounded-lg p-5 border border-gray-700">
              <code className="text-green-400 font-mono text-sm">[series_tabs id="123"]</code>
              <p className="text-gray-300 text-sm mt-2">Complete tabbed interface with overview, results, statistics, and players</p>
            </div>

            <div className="bg-gray-900 rounded-lg p-5 border border-gray-700">
              <code className="text-green-400 font-mono text-sm">[season_tabs id="456"]</code>
              <p className="text-gray-300 text-sm mt-2">Complete tabbed interface for seasons with automatic calculations</p>
            </div>
          </div>
        </div>
      </section>

      {/* Customization */}
      <section id="customization" className="mb-12 scroll-mt-20">
        <div className="border-l-4 border-pink-500 bg-pink-50/50 rounded-r-xl p-8">
          <div className="flex items-center gap-4 mb-6">
            <div className="w-14 h-14 bg-pink-500 rounded-xl flex items-center justify-center flex-shrink-0">
              <Palette className="w-7 h-7 text-white" />
            </div>
            <div>
              <h2 className="text-3xl font-bold text-navy mb-1">8. Customization</h2>
              <p className="text-gray-600 mb-0">Customize colors, styles, and appearance</p>
            </div>
          </div>

          <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div className="bg-white rounded-lg p-6 border border-pink-200">
              <h3 className="text-lg font-semibold text-navy mb-3">Colors and Branding</h3>
              <ol className="space-y-1 text-gray-700">
                <li>Go to <strong>Poker Import → Settings → Display</strong></li>
                <li>Customize primary color, secondary color, and font styles</li>
                <li>Upload your venue or league logo</li>
                <li>Click <strong>Save Changes</strong></li>
              </ol>
            </div>

            <div className="bg-white rounded-lg p-6 border border-pink-200">
              <h3 className="text-lg font-semibold text-navy mb-3">Custom CSS</h3>
              <p className="text-gray-700 mb-2">Add custom CSS to override default styles:</p>
              <ol className="space-y-1 text-gray-700">
                <li>Go to <strong>Appearance → Customize → Additional CSS</strong></li>
                <li>Add your custom styles</li>
                <li>Click <strong>Publish</strong></li>
              </ol>
            </div>
          </div>

          <div className="bg-gray-900 rounded-lg p-5 border border-gray-700 mt-6">
            <p className="text-gray-300 text-sm mb-3">Example CSS:</p>
            <pre className="text-green-400 font-mono text-xs overflow-x-auto"><code>{`/* Change tournament title color */
.tournament-title {
  color: #your-color-hex;
}

/* Adjust results table styling */
.tournament-results-table {
  border: 2px solid #your-color-hex;
}`}</code></pre>
          </div>
        </div>
      </section>

      {/* Troubleshooting */}
      <section id="troubleshooting" className="mb-12 scroll-mt-20">
        <div className="border-l-4 border-orange-500 bg-orange-50/50 rounded-r-xl p-8">
          <div className="flex items-center gap-4 mb-6">
            <div className="w-14 h-14 bg-orange-500 rounded-xl flex items-center justify-center flex-shrink-0">
              <AlertCircle className="w-7 h-7 text-white" />
            </div>
            <div>
              <h2 className="text-3xl font-bold text-navy mb-1">9. Troubleshooting</h2>
              <p className="text-gray-600 mb-0">Solutions for common issues</p>
            </div>
          </div>

          <div className="space-y-4">
            <div className="bg-white rounded-lg border border-orange-200 overflow-hidden">
              <div className="bg-orange-100 px-6 py-4 border-b border-orange-200">
                <h3 className="text-lg font-semibold text-navy flex items-center gap-2">
                  <AlertCircle className="w-5 h-5 text-orange-600" />
                  "Invalid File Format" Error
                </h3>
              </div>
              <div className="p-6">
                <p className="font-medium text-gray-700 mb-3">Solutions:</p>
                <ul className="space-y-2 text-gray-700">
                  <li>• Ensure you're exporting from Tournament Director as <strong>JavaScript (.tdt)</strong></li>
                  <li>• Check that the file hasn't been renamed or corrupted</li>
                  <li>• Try re-exporting the file from Tournament Director</li>
                  <li>• Verify file size is under 10MB</li>
                </ul>
              </div>
            </div>

            <div className="bg-white rounded-lg border border-orange-200 overflow-hidden">
              <div className="bg-orange-100 px-6 py-4 border-b border-orange-200">
                <h3 className="text-lg font-semibold text-navy flex items-center gap-2">
                  <AlertCircle className="w-5 h-5 text-orange-600" />
                  "Upload Failed" Error
                </h3>
              </div>
              <div className="p-6">
                <p className="font-medium text-gray-700 mb-3">Solutions:</p>
                <ol className="space-y-2 text-gray-700">
                  <li>Check PHP upload limits in <strong>Poker Import → System Info</strong></li>
                  <li>Contact your hosting provider to increase upload_max_filesize if needed</li>
                  <li>Try a different browser or clear browser cache</li>
                </ol>
              </div>
            </div>

            <div className="bg-white rounded-lg border border-orange-200 overflow-hidden">
              <div className="bg-orange-100 px-6 py-4 border-b border-orange-200">
                <h3 className="text-lg font-semibold text-navy flex items-center gap-2">
                  <AlertCircle className="w-5 h-5 text-orange-600" />
                  Shortcode Not Working
                </h3>
              </div>
              <div className="p-6">
                <p className="font-medium text-gray-700 mb-3">Common fixes:</p>
                <ul className="space-y-2 text-gray-700">
                  <li>• Verify correct shortcode syntax (check for extra spaces or quotes)</li>
                  <li>• Ensure tournament ID is correct</li>
                  <li>• Make sure you're using the Text editor, not Visual editor</li>
                  <li>• Test with a different shortcode to verify plugin is working</li>
                </ul>
              </div>
            </div>

            <div className="bg-white rounded-lg border border-orange-200 overflow-hidden">
              <div className="bg-orange-100 px-6 py-4 border-b border-orange-200">
                <h3 className="text-lg font-semibold text-navy flex items-center gap-2">
                  <AlertCircle className="w-5 h-5 text-orange-600" />
                  Slow Loading Times
                </h3>
              </div>
              <div className="p-6">
                <p className="font-medium text-gray-700 mb-3">Optimization tips:</p>
                <ul className="space-y-2 text-gray-700">
                  <li>• Enable caching in <strong>Poker Import → Settings → Performance</strong></li>
                  <li>• Limit number of tournaments shown per page</li>
                  <li>• Use pagination for large player lists</li>
                  <li>• Optimize images if using player photos</li>
                </ul>
              </div>
            </div>
          </div>
        </div>
      </section>

      {/* FAQ */}
      <section id="faq" className="mb-12 scroll-mt-20">
        <div className="border-l-4 border-emerald-500 bg-emerald-50/50 rounded-r-xl p-8">
          <div className="flex items-center gap-4 mb-6">
            <div className="w-14 h-14 bg-emerald-500 rounded-xl flex items-center justify-center flex-shrink-0">
              <HelpCircle className="w-7 h-7 text-white" />
            </div>
            <div>
              <h2 className="text-3xl font-bold text-navy mb-1">10. Frequently Asked Questions</h2>
              <p className="text-gray-600 mb-0">Quick answers to common questions</p>
            </div>
          </div>

          <div className="space-y-3">
            {[
              {
                q: "Can I import tournaments from other poker software?",
                a: "Currently, the plugin only supports Tournament Director (.tdt) files. Support for other formats may be added in future versions."
              },
              {
                q: "How many tournaments can I store?",
                a: "There's no built-in limit. You can store thousands of tournaments, though performance may vary based on your hosting plan."
              },
              {
                q: "Can I export my tournament data?",
                a: "Yes, you can export tournament data in CSV format from the admin dashboard."
              },
              {
                q: "Is the plugin mobile-friendly?",
                a: "Yes, all tournament displays are fully responsive and work on all devices."
              },
              {
                q: "Can I edit tournament results after importing?",
                a: "Yes, you can manually edit player results, positions, and winnings. The plugin will automatically recalculate statistics."
              },
              {
                q: "What happens if I import the same tournament twice?",
                a: "The plugin will warn you about duplicates and let you choose whether to update the existing tournament or create a new one."
              },
              {
                q: "Can I use this plugin with any WordPress theme?",
                a: "The plugin is designed to work with any WordPress theme that follows WordPress coding standards."
              },
              {
                q: "What are the server requirements?",
                a: "WordPress 6.0+, PHP 8.0+, and MySQL 5.7+. Most modern hosting plans meet these requirements."
              }
            ].map((item, index) => (
              <details key={index} className="bg-white rounded-lg border border-emerald-200 overflow-hidden group">
                <summary className="px-6 py-4 cursor-pointer hover:bg-emerald-50 transition-colors flex items-center justify-between">
                  <span className="font-semibold text-navy pr-4">{item.q}</span>
                  <ChevronRight className="w-5 h-5 text-emerald-600 transform group-open:rotate-90 transition-transform flex-shrink-0" />
                </summary>
                <div className="px-6 py-4 bg-emerald-50/30 border-t border-emerald-100">
                  <p className="text-gray-700 mb-0">{item.a}</p>
                </div>
              </details>
            ))}
          </div>
        </div>
      </section>

      {/* Support */}
      <div className="bg-gradient-to-br from-primary/10 to-navy/10 rounded-xl p-8 border border-primary/20 not-prose">
        <h3 className="text-2xl font-bold text-navy mb-6">Need More Help?</h3>
        <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
          <div className="bg-white rounded-lg p-6 border border-gray-200">
            <h4 className="font-semibold text-navy mb-4 flex items-center gap-2">
              <Info className="w-5 h-5 text-primary" />
              Support Resources
            </h4>
            <ul className="space-y-3">
              <li><a href="https://wordpress.org/support/plugin/poker-tournament-import" target="_blank" rel="noopener noreferrer" className="text-primary hover:underline flex items-center gap-2">
                <ChevronRight className="w-4 h-4" />
                WordPress Support Forum
              </a></li>
              <li><a href="https://github.com/hkhard/tdwpimport/issues" target="_blank" rel="noopener noreferrer" className="text-primary hover:underline flex items-center gap-2">
                <ChevronRight className="w-4 h-4" />
                GitHub Issues
              </a></li>
              <li><a href="https://github.com/hkhard/tdwpimport" target="_blank" rel="noopener noreferrer" className="text-primary hover:underline flex items-center gap-2">
                <ChevronRight className="w-4 h-4" />
                GitHub Repository
              </a></li>
            </ul>
          </div>
          <div className="bg-white rounded-lg p-6 border border-gray-200">
            <h4 className="font-semibold text-navy mb-4 flex items-center gap-2">
              <Code className="w-5 h-5 text-primary" />
              Documentation
            </h4>
            <ul className="space-y-3">
              <li><Link href="/docs" className="text-primary hover:underline flex items-center gap-2">
                <ChevronRight className="w-4 h-4" />
                Main Documentation
              </Link></li>
              <li><Link href="/changelog" className="text-primary hover:underline flex items-center gap-2">
                <ChevronRight className="w-4 h-4" />
                Changelog
              </Link></li>
              <li><a href="https://wordpress.org/plugins/poker-tournament-import/" target="_blank" rel="noopener noreferrer" className="text-primary hover:underline flex items-center gap-2">
                <ChevronRight className="w-4 h-4" />
                WordPress.org Plugin Page
              </a></li>
            </ul>
          </div>
        </div>
      </div>
    </DocLayout>
  )
}
