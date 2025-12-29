import DocLayout from '@/components/doc/DocLayout'
import { Upload, Users, TrendingUp, Award, Database, Zap, Code, BookOpen, FileText, Github } from 'lucide-react'
import Link from 'next/link'

export const metadata = {
  title: 'Documentation - TD WP Import',
  description: 'Complete documentation for the Poker Tournament Import WordPress plugin',
}

export default function DocsPage() {
  const features = [
    {
      icon: Upload,
      title: 'Import .tdt Files',
      description: 'Tournament Director v3.7.2+ file support with automatic parsing',
    },
    {
      icon: Users,
      title: 'Player Management',
      description: 'Automatic player profiles with statistics and tournament history',
    },
    {
      icon: TrendingUp,
      title: 'Series & Seasons',
      description: 'Track tournament series with automatic standings calculation',
    },
    {
      icon: Award,
      title: 'Points System',
      description: 'Customizable formula engine with 145+ variables',
    },
    {
      icon: Database,
      title: 'Analytics Dashboard',
      description: 'Pre-calculated statistics for lightning-fast performance',
    },
    {
      icon: Zap,
      title: 'Bulk Import',
      description: 'Upload multiple tournaments simultaneously (v2.9.0+)',
    },
  ]

  const shortcodes = [
    {
      code: '[tournament_results id="123"]',
      description: 'Display specific tournament results',
    },
    {
      code: '[tournament_series id="456"]',
      description: 'Show series overview',
    },
    {
      code: '[player_profile name="John Doe"]',
      description: 'Display player profile',
    },
    {
      code: '[series_tabs id="123"]',
      description: 'Complete tabbed interface for series',
    },
    {
      code: '[season_tabs id="456"]',
      description: 'Complete tabbed interface for seasons',
    },
  ]

  return (
    <DocLayout
      title="Documentation"
      description="Complete guide for the Poker Tournament Import WordPress plugin"
    >
      {/* Quick Start */}
      <section>
        <h2>Quick Start</h2>
        <p>
          Poker Tournament Import automates the publishing of poker tournament results from Tournament Director software.
          Reduce manual data entry by 90% while maintaining 100% accuracy.
        </p>

        <div className="bg-blue-50 border-l-4 border-primary p-6 my-6 rounded-r-lg">
          <h3 className="text-xl font-semibold text-navy mt-0">Installation</h3>
          <ol className="mb-0">
            <li>Download from <a href="https://wordpress.org/plugins/poker-tournament-import/" target="_blank" rel="noopener noreferrer">WordPress.org</a></li>
            <li>Upload via <strong>Plugins → Add New → Upload</strong></li>
            <li>Activate and configure settings</li>
            <li>Import your first tournament</li>
          </ol>
        </div>

        <div className="bg-green-50 border-l-4 border-success p-6 my-6 rounded-r-lg">
          <h3 className="text-xl font-semibold text-navy mt-0">Your First Import</h3>
          <ol className="mb-0">
            <li>Export .tdt file from Tournament Director</li>
            <li>Go to <strong>Poker Import → Import Tournament</strong></li>
            <li>Upload your .tdt file</li>
            <li>Review parsed data and click <strong>Import</strong></li>
          </ol>
        </div>
      </section>

      {/* Key Features */}
      <section>
        <h2>Key Features</h2>
        <div className="grid grid-cols-1 md:grid-cols-2 gap-6 not-prose">
          {features.map((feature, index) => {
            const Icon = feature.icon
            return (
              <div key={index} className="bg-gray-50 rounded-lg p-6 border border-gray-200">
                <Icon className="w-10 h-10 text-primary mb-3" />
                <h3 className="text-xl font-semibold text-navy mb-2">{feature.title}</h3>
                <p className="text-gray-700 mb-0">{feature.description}</p>
              </div>
            )
          })}
        </div>
      </section>

      {/* Shortcodes Reference */}
      <section>
        <h2>Shortcodes Reference</h2>
        <p>Use these shortcodes to display tournament data anywhere on your site:</p>

        <div className="space-y-4 not-prose">
          {shortcodes.map((item, index) => (
            <div key={index} className="bg-gray-900 text-gray-100 p-4 rounded-lg">
              <code className="text-green-400 font-mono">{item.code}</code>
              <p className="text-gray-300 mt-2 mb-0 text-sm">{item.description}</p>
            </div>
          ))}
        </div>

        <div className="mt-6">
          <Link href="/user-manual" className="inline-flex items-center text-primary hover:underline font-semibold">
            View complete shortcode documentation →
          </Link>
        </div>
      </section>

      {/* Documentation Sections */}
      <section>
        <h2>Documentation Sections</h2>

        <div className="grid grid-cols-1 md:grid-cols-3 gap-6 not-prose">
          <Link href="/user-manual" className="group">
            <div className="bg-white border-2 border-gray-200 rounded-lg p-6 hover:border-primary transition-all">
              <BookOpen className="w-12 h-12 text-primary mb-4" />
              <h3 className="text-xl font-semibold text-navy group-hover:text-primary transition-colors">User Manual</h3>
              <p className="text-gray-700">Complete usage guide with step-by-step instructions</p>
            </div>
          </Link>

          <Link href="/changelog" className="group">
            <div className="bg-white border-2 border-gray-200 rounded-lg p-6 hover:border-primary transition-all">
              <FileText className="w-12 h-12 text-primary mb-4" />
              <h3 className="text-xl font-semibold text-navy group-hover:text-primary transition-colors">Changelog</h3>
              <p className="text-gray-700">Version history and release notes</p>
            </div>
          </Link>

          <a href="https://github.com/hkhard/tdwpimport" target="_blank" rel="noopener noreferrer" className="group">
            <div className="bg-white border-2 border-gray-200 rounded-lg p-6 hover:border-primary transition-all">
              <Github className="w-12 h-12 text-primary mb-4" />
              <h3 className="text-xl font-semibold text-navy group-hover:text-primary transition-colors">GitHub</h3>
              <p className="text-gray-700">Source code and developer resources</p>
            </div>
          </a>
        </div>
      </section>

      {/* Technical Requirements */}
      <section>
        <h2>Technical Requirements</h2>
        <div className="bg-gray-50 rounded-lg p-6 border border-gray-200">
          <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
              <h3 className="text-lg font-semibold text-navy mt-0">Server Requirements</h3>
              <ul className="mb-0">
                <li>WordPress 6.0 or higher</li>
                <li>PHP 8.0 or higher</li>
                <li>MySQL 5.7+ or MariaDB 10.2+</li>
                <li>PHP extensions: mbstring, xml, json</li>
              </ul>
            </div>
            <div>
              <h3 className="text-lg font-semibold text-navy mt-0">Tournament Director</h3>
              <ul className="mb-0">
                <li>Tournament Director v3.7.2 or higher</li>
                <li>Export format: JavaScript (.tdt)</li>
                <li>Maximum file size: 10MB</li>
              </ul>
            </div>
          </div>
        </div>
      </section>

      {/* Support */}
      <section>
        <h2>Support & Community</h2>
        <div className="grid grid-cols-1 md:grid-cols-2 gap-6 not-prose">
          <div className="bg-blue-50 border border-blue-200 rounded-lg p-6">
            <h3 className="text-lg font-semibold text-navy mb-3">Need Help?</h3>
            <ul className="space-y-2">
              <li>
                <a href="https://wordpress.org/support/plugin/poker-tournament-import" target="_blank" rel="noopener noreferrer" className="text-primary hover:underline">
                  WordPress Support Forum
                </a>
              </li>
              <li>
                <a href="https://github.com/hkhard/tdwpimport/issues" target="_blank" rel="noopener noreferrer" className="text-primary hover:underline">
                  Report Issues on GitHub
                </a>
              </li>
              <li>
                <Link href="/user-manual" className="text-primary hover:underline">
                  Browse User Manual
                </Link>
              </li>
            </ul>
          </div>

          <div className="bg-green-50 border border-green-200 rounded-lg p-6">
            <h3 className="text-lg font-semibold text-navy mb-3">Version Info</h3>
            <div className="space-y-2">
              <div>
                <span className="font-semibold">Current Version:</span> 2.9.22
              </div>
              <div>
                <span className="font-semibold">License:</span> GPLv2 or later
              </div>
              <div>
                <Link href="/changelog" className="text-primary hover:underline inline-block mt-2">
                  View Complete Changelog →
                </Link>
              </div>
            </div>
          </div>
        </div>
      </section>
    </DocLayout>
  )
}
