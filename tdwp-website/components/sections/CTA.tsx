import { Download, Github, BookOpen } from 'lucide-react'

export default function CTA() {
  return (
    <section id="download" className="py-20 px-4 bg-gradient-to-br from-navy via-navy-light to-primary-dark text-white">
      <div className="container mx-auto max-w-4xl text-center">
        <h2 className="text-4xl md:text-5xl font-bold mb-6">
          Ready to Transform Your Tournament Publishing?
        </h2>
        <p className="text-xl text-gray-200 mb-12 max-w-2xl mx-auto">
          Join hundreds of tournament directors who have reduced their workload by 90% while providing better player experiences
        </p>

        {/* Primary CTA */}
        <div className="flex flex-col sm:flex-row gap-4 justify-center mb-12">
          <a
            href="https://wordpress.org/plugins/poker-tournament-import/"
            className="bg-gold hover:bg-gold-dark text-white font-semibold py-4 px-10 rounded-lg transition-all duration-200 transform hover:scale-105 shadow-lg hover:shadow-xl flex items-center justify-center space-x-3 text-lg"
          >
            <Download size={24} />
            <span>Download from WordPress.org</span>
          </a>
          <a
            href="https://github.com/hkhard/tdwpimport"
            className="bg-white/10 hover:bg-white/20 backdrop-blur-sm border-2 border-white/30 text-white font-semibold py-4 px-10 rounded-lg transition-all duration-200 flex items-center justify-center space-x-3 text-lg"
          >
            <Github size={24} />
            <span>View on GitHub</span>
          </a>
        </div>

        {/* Quick Links */}
        <div className="grid grid-cols-1 md:grid-cols-3 gap-6 max-w-3xl mx-auto">
          <a
            href="/docs"
            className="bg-white/10 backdrop-blur-sm border border-white/20 rounded-lg p-6 hover:bg-white/15 transition-all group"
          >
            <BookOpen size={32} className="text-gold mx-auto mb-3 group-hover:scale-110 transition-transform" />
            <div className="font-semibold mb-1">Documentation</div>
            <div className="text-sm text-gray-300">Complete user guide</div>
          </a>

          <a
            href="/user-manual"
            className="bg-white/10 backdrop-blur-sm border border-white/20 rounded-lg p-6 hover:bg-white/15 transition-all group"
          >
            <div className="text-3xl mx-auto mb-3 group-hover:scale-110 transition-transform">📖</div>
            <div className="font-semibold mb-1">User Manual</div>
            <div className="text-sm text-gray-300">Step-by-step tutorials</div>
          </a>

          <a
            href="/changelog"
            className="bg-white/10 backdrop-blur-sm border border-white/20 rounded-lg p-6 hover:bg-white/15 transition-all group"
          >
            <div className="text-3xl mx-auto mb-3 group-hover:scale-110 transition-transform">📝</div>
            <div className="font-semibold mb-1">Changelog</div>
            <div className="text-sm text-gray-300">Latest updates</div>
          </a>
        </div>

        {/* Version & Stats */}
        <div className="mt-12 pt-8 border-t border-white/20">
          <div className="flex flex-wrap justify-center items-center gap-8 text-sm text-gray-300">
            <div>
              <span className="font-semibold text-white">Current Version:</span> 2.6.6
            </div>
            <div>
              <span className="font-semibold text-white">500+</span> Active Installations
            </div>
            <div>
              <span className="font-semibold text-white">4.8/5</span> Rating
            </div>
            <div>
              <span className="font-semibold text-white">GPL v2</span> License
            </div>
          </div>
        </div>
      </div>
    </section>
  )
}
