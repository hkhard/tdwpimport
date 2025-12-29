import DocLayout from '@/components/doc/DocLayout'
import { Package, AlertCircle, Bug, Zap, Shield, Sparkles } from 'lucide-react'

export const metadata = {
  title: 'Changelog - TD WP Import',
  description: 'Version history and release notes for the Poker Tournament Import plugin',
}

export default function ChangelogPage() {
  return (
    <DocLayout
      title="Changelog"
      description="Complete version history and release notes"
    >
      {/* Current Version Banner */}
      <div className="bg-gradient-to-r from-primary/20 to-navy/20 rounded-lg p-8 mb-8 not-prose">
        <div className="flex items-center justify-between flex-wrap gap-4">
          <div>
            <div className="flex items-center gap-3 mb-2">
              <Package className="w-8 h-8 text-primary" />
              <h3 className="text-2xl font-bold text-navy">Current Version</h3>
            </div>
            <p className="text-3xl font-bold text-primary">2.9.22</p>
            <p className="text-gray-700 mt-1">Released: October 26, 2025</p>
          </div>
          <div>
            <a
              href="https://wordpress.org/plugins/poker-tournament-import/"
              target="_blank"
              rel="noopener noreferrer"
              className="inline-block bg-primary hover:bg-primary-dark text-white font-semibold px-6 py-3 rounded-lg transition-colors"
            >
              Download Latest
            </a>
          </div>
        </div>
      </div>

      {/* Legend */}
      <div className="bg-gray-50 rounded-lg p-6 mb-8 not-prose">
        <h3 className="text-lg font-semibold text-navy mb-4">Release Types</h3>
        <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
          <div className="flex items-center gap-2">
            <Sparkles className="w-5 h-5 text-purple-600" />
            <span className="text-sm"><strong>NEW:</strong> New features</span>
          </div>
          <div className="flex items-center gap-2">
            <Zap className="w-5 h-5 text-yellow-600" />
            <span className="text-sm"><strong>IMPROVED:</strong> Enhancements</span>
          </div>
          <div className="flex items-center gap-2">
            <Bug className="w-5 h-5 text-red-600" />
            <span className="text-sm"><strong>FIXED:</strong> Bug fixes</span>
          </div>
        </div>
      </div>

      {/* Version 2.9.x - WordPress.org Compliance */}
      <section>
        <h2>Version 2.9.x - WordPress.org Compliance & Bulk Import</h2>

        {/* v2.9.22 */}
        <div className="border-l-4 border-primary pl-6 mb-8">
          <h3 className="flex items-center gap-2">
            <span className="text-primary">2.9.22</span>
            <span className="text-sm text-gray-600">October 26, 2025</span>
          </h3>
          <ul>
            <li><Bug className="w-4 h-4 inline mr-1 text-red-600" /><strong>Fixed:</strong> Translator comments moved to same line as sprintf() for Plugin Check compliance</li>
            <li><Shield className="w-4 h-4 inline mr-1 text-blue-600" /><strong>Code quality:</strong> WordPress i18n standards compliance</li>
          </ul>
        </div>

        {/* v2.9.21 */}
        <div className="border-l-4 border-gray-300 pl-6 mb-8">
          <h3 className="flex items-center gap-2">
            <span>2.9.21</span>
            <span className="text-sm text-gray-600">October 26, 2025</span>
          </h3>
          <ul>
            <li><Bug className="w-4 h-4 inline mr-1 text-red-600" /><strong>Fixed:</strong> All phpcs:ignore comments moved to correct position</li>
            <li><Shield className="w-4 h-4 inline mr-1 text-blue-600" /><strong>Code quality:</strong> All suppressions properly placed for Plugin Check compliance</li>
          </ul>
        </div>

        {/* v2.9.20 */}
        <div className="border-l-4 border-gray-300 pl-6 mb-8">
          <h3 className="flex items-center gap-2">
            <span>2.9.20</span>
            <span className="text-sm text-gray-600">October 26, 2025</span>
          </h3>
          <ul>
            <li><Bug className="w-4 h-4 inline mr-1 text-red-600" /><strong>Fixed:</strong> 5 WordPress.DB.PreparedSQL.NotPrepared errors</li>
            <li><Bug className="w-4 h-4 inline mr-1 text-red-600" /><strong>Fixed:</strong> 11 file operation warnings (fopen/fclose/fread/rename/rmdir/unlink)</li>
            <li><Shield className="w-4 h-4 inline mr-1 text-blue-600" /><strong>Code quality:</strong> Plugin Check compliant - all critical errors resolved</li>
          </ul>
        </div>

        {/* v2.9.15 */}
        <div className="border-l-4 border-gray-300 pl-6 mb-8">
          <h3 className="flex items-center gap-2">
            <span>2.9.15</span>
            <span className="text-sm text-gray-600">October 26, 2025</span>
          </h3>
          <ul>
            <li><Shield className="w-4 h-4 inline mr-1 text-blue-600" /><strong>WordPress.org compliance:</strong> Applied tdwp_ prefix throughout codebase</li>
            <li><Bug className="w-4 h-4 inline mr-1 text-red-600" /><strong>Fixed:</strong> All options now use tdwp_ prefix (poker_ → tdwp_)</li>
            <li><Zap className="w-4 h-4 inline mr-1 text-yellow-600" /><strong>Migration:</strong> Automatic migration of old options to new prefix</li>
            <li><Shield className="w-4 h-4 inline mr-1 text-blue-600" /><strong>Backward compatibility:</strong> Old shortcode names still work</li>
          </ul>
        </div>

        {/* v2.9.0 */}
        <div className="border-l-4 border-purple-500 pl-6 mb-8">
          <h3 className="flex items-center gap-2">
            <span className="text-purple-600">2.9.0</span>
            <span className="text-sm text-gray-600">January 23, 2025</span>
          </h3>
          <ul>
            <li><Sparkles className="w-4 h-4 inline mr-1 text-purple-600" /><strong>NEW:</strong> Bulk import functionality - Upload multiple .tdt files simultaneously</li>
            <li><Sparkles className="w-4 h-4 inline mr-1 text-purple-600" /><strong>NEW:</strong> Real-time progress tracking with per-file status indicators</li>
            <li><Sparkles className="w-4 h-4 inline mr-1 text-purple-600" /><strong>NEW:</strong> Intelligent duplicate detection using file hash</li>
            <li><Sparkles className="w-4 h-4 inline mr-1 text-purple-600" /><strong>NEW:</strong> Batch management with resume capability</li>
            <li><Sparkles className="w-4 h-4 inline mr-1 text-purple-600" /><strong>NEW:</strong> Two new database tables (wp_poker_import_batches, wp_poker_import_batch_files)</li>
            <li><Zap className="w-4 h-4 inline mr-1 text-yellow-600" /><strong>Improved:</strong> Import workflow now scales to 20+ files reliably</li>
          </ul>
        </div>
      </section>

      {/* Version 2.8.x - Ranking & Display Improvements */}
      <section>
        <h2>Version 2.8.x - Ranking System & UI Improvements</h2>

        {/* v2.8.14 */}
        <div className="border-l-4 border-gray-300 pl-6 mb-8">
          <h3 className="flex items-center gap-2">
            <span>2.8.14</span>
            <span className="text-sm text-gray-600">October 21, 2025</span>
          </h3>
          <ul>
            <li><Bug className="w-4 h-4 inline mr-1 text-red-600" /><strong>Fixed:</strong> "Avg Players/Event" now counts unique physical players per tournament</li>
            <li><Zap className="w-4 h-4 inline mr-1 text-yellow-600" /><strong>Improved:</strong> More accurate metric shows actual participation instead of total entries</li>
          </ul>
        </div>

        {/* v2.8.0 */}
        <div className="border-l-4 border-purple-500 pl-6 mb-8">
          <h3 className="flex items-center gap-2">
            <span className="text-purple-600">2.8.0</span>
            <span className="text-sm text-gray-600">October 20, 2025</span>
          </h3>
          <ul>
            <li><Sparkles className="w-4 h-4 inline mr-1 text-purple-600" /><strong>COMPLETE RANKING SYSTEM REWRITE:</strong> Rebuy-aware algorithm</li>
            <li><Bug className="w-4 h-4 inline mr-1 text-red-600" /><strong>CRITICAL FIX:</strong> Rankings now handle rebuys correctly</li>
            <li><Sparkles className="w-4 h-4 inline mr-1 text-purple-600" /><strong>NEW:</strong> Prize validation and bubble calculation</li>
            <li><Zap className="w-4 h-4 inline mr-1 text-yellow-600" /><strong>Technical:</strong> Uses latest elimination timestamp per unique player</li>
          </ul>
        </div>
      </section>

      {/* Version 2.7.x - Hit Counting & GameHistory */}
      <section>
        <h2>Version 2.7.x - Hit Counting & GameHistory Extraction</h2>

        {/* v2.7.2 */}
        <div className="border-l-4 border-primary pl-6 mb-8">
          <h3 className="flex items-center gap-2">
            <span className="text-primary">2.7.2</span>
            <span className="text-sm text-gray-600">October 20, 2025</span>
          </h3>
          <ul>
            <li><Bug className="w-4 h-4 inline mr-1 text-red-600" /><strong>CRITICAL FIX:</strong> Hits now display in public dashboard</li>
            <li><Bug className="w-4 h-4 inline mr-1 text-red-600" /><strong>Fixed:</strong> insert_tournament_players() now includes hits field</li>
            <li><Zap className="w-4 h-4 inline mr-1 text-yellow-600" /><strong>Solution:</strong> Use "Repair Player Data" button to update existing tournaments</li>
          </ul>
        </div>

        {/* v2.7.1 */}
        <div className="border-l-4 border-gray-300 pl-6 mb-8">
          <h3 className="flex items-center gap-2">
            <span>2.7.1</span>
            <span className="text-sm text-gray-600">October 20, 2025</span>
          </h3>
          <ul>
            <li><Sparkles className="w-4 h-4 inline mr-1 text-purple-600" /><strong>NEW:</strong> FullCreditHit configuration support</li>
            <li><Sparkles className="w-4 h-4 inline mr-1 text-purple-600" /><strong>NEW:</strong> WordPress admin setting for hit counting method</li>
            <li><Zap className="w-4 h-4 inline mr-1 text-yellow-600" /><strong>Enhanced:</strong> Hybrid hit counting logic with WordPress override</li>
          </ul>
        </div>
      </section>

      {/* Version 2.6.x - UI/UX & Formula Management */}
      <section>
        <h2>Version 2.6.x - UI/UX Improvements</h2>

        {/* v2.6.1 */}
        <div className="border-l-4 border-primary pl-6 mb-8">
          <h3 className="flex items-center gap-2">
            <span className="text-primary">2.6.1</span>
            <span className="text-sm text-gray-600">October 17, 2025</span>
          </h3>
          <ul>
            <li><Bug className="w-4 h-4 inline mr-1 text-red-600" /><strong>CRITICAL FIX:</strong> 404 errors on menu items resolved</li>
            <li><Bug className="w-4 h-4 inline mr-1 text-red-600" /><strong>Fixed:</strong> Menu race condition with hook priority adjustment</li>
            <li><Zap className="w-4 h-4 inline mr-1 text-yellow-600" /><strong>Improved:</strong> Formula Manager moved to Poker Import menu</li>
            <li><Sparkles className="w-4 h-4 inline mr-1 text-purple-600" /><strong>NEW:</strong> Debug mode toggle in admin settings</li>
          </ul>
        </div>
      </section>

      {/* Version 2.4.x - ROI & Prize Extraction */}
      <section>
        <h2>Version 2.4.x - ROI Analytics & Prize Extraction</h2>

        {/* v2.4.39 */}
        <div className="border-l-4 border-primary pl-6 mb-8">
          <h3 className="flex items-center gap-2">
            <span className="text-primary">2.4.39</span>
            <span className="text-sm text-gray-600">October 16, 2025</span>
          </h3>
          <ul>
            <li><Bug className="w-4 h-4 inline mr-1 text-red-600" /><strong>CRITICAL BUGFIX:</strong> Prizes not extracted from modern .tdt files</li>
            <li><Bug className="w-4 h-4 inline mr-1 text-red-600" /><strong>Fixed:</strong> GamePrizes wrapper extraction in domain mapper</li>
            <li><Zap className="w-4 h-4 inline mr-1 text-yellow-600" /><strong>Enhanced:</strong> Multi-format prize extraction (modern and legacy)</li>
          </ul>
        </div>

        {/* v2.4.36 */}
        <div className="border-l-4 border-gray-300 pl-6 mb-8">
          <h3 className="flex items-center gap-2">
            <span>2.4.36</span>
            <span className="text-sm text-gray-600">October 16, 2025</span>
          </h3>
          <ul>
            <li><Bug className="w-4 h-4 inline mr-1 text-red-600" /><strong>CRITICAL BUGFIX:</strong> ROI calculation accuracy - Per-buyin fee lookup</li>
            <li><Bug className="w-4 h-4 inline mr-1 text-red-600" /><strong>Fixed:</strong> Accurate per-entry cost calculation with FeeProfile lookups</li>
            <li><Sparkles className="w-4 h-4 inline mr-1 text-purple-600" /><strong>NEW:</strong> Automatic ROI table population for new imports</li>
          </ul>
        </div>
      </section>

      {/* Older Versions Link */}
      <div className="bg-gray-50 rounded-lg p-8 my-8 not-prose">
        <h3 className="text-xl font-semibold text-navy mb-4">Older Versions</h3>
        <p className="text-gray-700 mb-4">
          For a complete version history including versions 2.3.x, 2.2.x, 2.1.x, and earlier,
          view the complete changelog in the plugin's readme.txt file.
        </p>
        <a
          href="https://plugins.svn.wordpress.org/poker-tournament-import/trunk/readme.txt"
          target="_blank"
          rel="noopener noreferrer"
          className="inline-block bg-navy hover:bg-navy-dark text-white font-semibold px-6 py-3 rounded-lg transition-colors"
        >
          View Complete Changelog
        </a>
      </div>

      {/* Download CTA */}
      <div className="bg-gradient-to-r from-primary/20 to-navy/20 rounded-lg p-8 my-8 not-prose">
        <h3 className="text-2xl font-bold text-navy mb-4">Ready to Get Started?</h3>
        <p className="text-gray-700 mb-6">
          Download the latest version of Poker Tournament Import and start publishing your tournament results today.
        </p>
        <div className="flex flex-wrap gap-4">
          <a
            href="https://wordpress.org/plugins/poker-tournament-import/"
            target="_blank"
            rel="noopener noreferrer"
            className="inline-block bg-primary hover:bg-primary-dark text-white font-semibold px-8 py-3 rounded-lg transition-colors"
          >
            Download from WordPress.org
          </a>
          <a
            href="https://github.com/hkhard/tdwpimport"
            target="_blank"
            rel="noopener noreferrer"
            className="inline-block bg-navy hover:bg-navy-dark text-white font-semibold px-8 py-3 rounded-lg transition-colors"
          >
            View on GitHub
          </a>
        </div>
      </div>
    </DocLayout>
  )
}
