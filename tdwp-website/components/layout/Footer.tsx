import Link from 'next/link'
import { Github, Twitter, Mail, Heart } from 'lucide-react'

export default function Footer() {
  const currentYear = new Date().getFullYear()

  return (
    <footer className="bg-navy text-white">
      <div className="container mx-auto px-4 py-12">
        <div className="grid grid-cols-1 md:grid-cols-4 gap-8">
          {/* Brand */}
          <div>
            <div className="flex items-center space-x-2 mb-4">
              <div className="w-10 h-10 bg-gradient-to-br from-primary to-primary-dark rounded-lg flex items-center justify-center">
                <span className="text-white font-bold text-xl">TD</span>
              </div>
              <span className="font-bold text-xl">WP Import</span>
            </div>
            <p className="text-gray-300 text-sm">
              Professional poker tournament results publishing for WordPress.
            </p>
          </div>

          {/* Product */}
          <div>
            <h4 className="font-semibold mb-4">Product</h4>
            <ul className="space-y-2 text-sm">
              <li><a href="#features" className="text-gray-300 hover:text-white transition-colors">Features</a></li>
              <li><a href="#pricing" className="text-gray-300 hover:text-white transition-colors">Pricing</a></li>
              <li><a href="#download" className="text-gray-300 hover:text-white transition-colors">Download</a></li>
              <li><a href="/changelog" className="text-gray-300 hover:text-white transition-colors">Changelog</a></li>
            </ul>
          </div>

          {/* Resources */}
          <div>
            <h4 className="font-semibold mb-4">Resources</h4>
            <ul className="space-y-2 text-sm">
              <li><a href="/docs" className="text-gray-300 hover:text-white transition-colors">Documentation</a></li>
              <li><a href="/user-manual" className="text-gray-300 hover:text-white transition-colors">User Manual</a></li>
              <li><a href="#faq" className="text-gray-300 hover:text-white transition-colors">FAQ</a></li>
              <li><a href="https://github.com/hkhard/tdwpimport" className="text-gray-300 hover:text-white transition-colors">GitHub</a></li>
            </ul>
          </div>

          {/* Support */}
          <div>
            <h4 className="font-semibold mb-4">Support</h4>
            <ul className="space-y-2 text-sm">
              <li><a href="https://wordpress.org/support/plugin/poker-tournament-import" className="text-gray-300 hover:text-white transition-colors">Support Forum</a></li>
              <li><a href="https://github.com/hkhard/tdwpimport/issues" className="text-gray-300 hover:text-white transition-colors">Report Issue</a></li>
              <li><a href="/contact" className="text-gray-300 hover:text-white transition-colors">Contact</a></li>
            </ul>
          </div>
        </div>

        {/* Bottom Bar */}
        <div className="border-t border-gray-700 mt-12 pt-8 flex flex-col md:flex-row items-center justify-between">
          <p className="text-sm text-gray-300 mb-4 md:mb-0">
            © {currentYear} TD WP Import. Made with <Heart size={14} className="inline text-red-500" /> for the poker community.
          </p>
          <div className="flex items-center space-x-4">
            <a href="https://github.com/hkhard/tdwpimport" className="text-gray-300 hover:text-white transition-colors">
              <Github size={20} />
            </a>
            <a href="https://twitter.com/tdwpimport" className="text-gray-300 hover:text-white transition-colors">
              <Twitter size={20} />
            </a>
            <a href="mailto:support@tdwpimport.com" className="text-gray-300 hover:text-white transition-colors">
              <Mail size={20} />
            </a>
          </div>
        </div>
      </div>
    </footer>
  )
}
