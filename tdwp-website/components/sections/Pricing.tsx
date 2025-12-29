import { Check, Download, Github } from 'lucide-react'

export default function Pricing() {
  return (
    <section id="download" className="py-20 px-4 bg-gray-50">
      <div className="container mx-auto">
        {/* Section Header */}
        <div className="text-center mb-16">
          <h2 className="section-heading">Free & Open Source</h2>
          <p className="text-xl text-gray-600 max-w-2xl mx-auto">
            Free forever. Open source. No hidden costs. Community supported.
          </p>
        </div>

        {/* Pricing Card */}
        <div className="max-w-3xl mx-auto">
          <div className="bg-white rounded-2xl shadow-2xl overflow-hidden">
            <div className="p-12 bg-gradient-to-br from-primary to-primary-dark text-white">
              <div className="inline-block bg-white/20 backdrop-blur-sm rounded-full px-4 py-2 text-sm font-medium mb-4">
                OPEN SOURCE
              </div>
              <h3 className="text-4xl font-bold mb-4">Free Forever</h3>
              <div className="text-5xl font-bold mb-2">$0</div>
              <p className="text-gray-200 mb-8">
                Complete plugin with all features. No limitations.
              </p>

              <ul className="space-y-4 mb-8">
                {[
                  'Unlimited tournaments',
                  'Unlimited players',
                  'Complete .tdt parsing',
                  'Player statistics & ROI',
                  'Series & season tracking',
                  'Formula engine',
                  'All shortcodes',
                  'Mobile responsive',
                  'SEO optimized',
                  'Regular updates',
                  'Community support',
                  'GPL v2 license',
                ].map((feature, index) => (
                  <li key={index} className="flex items-center space-x-3">
                    <Check size={20} className="text-gold flex-shrink-0" />
                    <span>{feature}</span>
                  </li>
                ))}
              </ul>

              <a
                href="https://wordpress.org/plugins/poker-tournament-import/"
                className="w-full bg-gold hover:bg-gold-dark text-white font-semibold py-4 px-8 rounded-lg transition-all duration-200 transform hover:scale-105 shadow-lg hover:shadow-xl flex items-center justify-center space-x-2"
              >
                <Download size={20} />
                <span>Download Now</span>
              </a>
            </div>

            <div className="p-8 bg-white">
              <div className="flex items-center space-x-3 mb-4">
                <Github size={24} className="text-navy" />
                <h4 className="font-bold text-navy text-xl">Open Source</h4>
              </div>
              <p className="text-gray-600 mb-4">
                The plugin is open source under GPL v2. View code, contribute, or fork on GitHub.
              </p>
              <a
                href="https://github.com/hkhard/tdwpimport"
                className="inline-flex items-center space-x-2 text-primary font-semibold hover:underline"
              >
                <span>View on GitHub</span>
                <span>→</span>
              </a>
            </div>
          </div>
        </div>

        {/* FAQ Link */}
        <div className="text-center mt-12">
          <p className="text-gray-600">
            Have questions?{' '}
            <a href="#faq" className="text-primary font-semibold hover:underline">
              Check our FAQ
            </a>
          </p>
        </div>
      </div>
    </section>
  )
}
