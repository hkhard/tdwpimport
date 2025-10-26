import Link from 'next/link'
import { Check, Download, Github } from 'lucide-react'

export default function Pricing() {
  return (
    <section id="pricing" className="py-20 px-4 bg-gray-50">
      <div className="container mx-auto">
        {/* Section Header */}
        <div className="text-center mb-16">
          <h2 className="section-heading">Simple, Transparent Pricing</h2>
          <p className="text-xl text-gray-600 max-w-2xl mx-auto">
            Free forever. Open source. No hidden costs.
          </p>
        </div>

        {/* Pricing Card */}
        <div className="max-w-4xl mx-auto">
          <div className="bg-white rounded-2xl shadow-2xl overflow-hidden">
            <div className="grid grid-cols-1 lg:grid-cols-2">
              {/* Free Plan */}
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
                  href="#download"
                  className="w-full bg-gold hover:bg-gold-dark text-white font-semibold py-4 px-8 rounded-lg transition-all duration-200 transform hover:scale-105 shadow-lg hover:shadow-xl flex items-center justify-center space-x-2"
                >
                  <Download size={20} />
                  <span>Download Now</span>
                </a>
              </div>

              {/* Support Options */}
              <div className="p-12 bg-white">
                <div className="inline-block bg-navy/10 text-navy rounded-full px-4 py-2 text-sm font-medium mb-4">
                  SUPPORT & SERVICES
                </div>
                <h3 className="text-3xl font-bold text-navy mb-4">Optional Add-Ons</h3>
                <p className="text-gray-600 mb-8">
                  While the plugin is free, we offer optional services for those who need them
                </p>

                <div className="space-y-6">
                  <div className="border border-gray-200 rounded-lg p-6">
                    <h4 className="font-bold text-navy mb-2">Priority Support</h4>
                    <p className="text-gray-600 text-sm mb-3">
                      Email support with 24-hour response time
                    </p>
                    <div className="text-2xl font-bold text-primary">$49/month</div>
                  </div>

                  <div className="border border-gray-200 rounded-lg p-6">
                    <h4 className="font-bold text-navy mb-2">Custom Development</h4>
                    <p className="text-gray-600 text-sm mb-3">
                      Custom features and integrations for your site
                    </p>
                    <div className="text-2xl font-bold text-primary">Contact us</div>
                  </div>

                  <div className="border border-gray-200 rounded-lg p-6">
                    <h4 className="font-bold text-navy mb-2">Setup & Configuration</h4>
                    <p className="text-gray-600 text-sm mb-3">
                      We'll install and configure everything for you
                    </p>
                    <div className="text-2xl font-bold text-primary">$149 one-time</div>
                  </div>
                </div>

                <div className="mt-8 p-6 bg-navy/5 rounded-lg">
                  <div className="flex items-center space-x-3 mb-2">
                    <Github size={24} className="text-navy" />
                    <h4 className="font-bold text-navy">Open Source</h4>
                  </div>
                  <p className="text-sm text-gray-600">
                    The plugin is open source under GPL v2. View code, contribute, or fork on GitHub.
                  </p>
                  <a
                    href="https://github.com/hkhard/tdwpimport"
                    className="inline-flex items-center space-x-2 text-primary font-semibold mt-3 hover:underline"
                  >
                    <span>View on GitHub</span>
                    <span>→</span>
                  </a>
                </div>
              </div>
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
            {' '}or{' '}
            <Link href="/contact" className="text-primary font-semibold hover:underline">
              contact us
            </Link>
          </p>
        </div>
      </div>
    </section>
  )
}
