'use client'

import { useState } from 'react'
import { ChevronDown } from 'lucide-react'

export default function FAQ() {
  const [openIndex, setOpenIndex] = useState<number | null>(0)

  const faqs = [
    {
      question: 'What file formats are supported?',
      answer: 'The plugin currently supports Tournament Director (.tdt) files from version 3.7.2 and later. These are JavaScript-format export files from the Tournament Director software.',
    },
    {
      question: 'Is the plugin really free?',
      answer: 'Yes! The plugin is completely free and open source under GPL v2 license. You can use it on unlimited websites with unlimited tournaments. Optional paid support services are available if you need them.',
    },
    {
      question: 'Will this work with my WordPress theme?',
      answer: 'Yes, the plugin is designed to work with any WordPress theme that follows WordPress coding standards. All tournament displays are responsive and adapt to your theme\'s styling.',
    },
    {
      question: 'How accurate are the tournament results?',
      answer: '100% accurate. The plugin extracts data directly from Tournament Director files without any manual intervention, eliminating human error completely.',
    },
    {
      question: 'Can I customize the appearance?',
      answer: 'Absolutely! You can customize colors, layouts, and templates. The plugin includes shortcodes for flexible placement, custom CSS hooks, and template override support.',
    },
    {
      question: 'Does it support player statistics?',
      answer: 'Yes! The plugin automatically creates player profiles with complete tournament history, career earnings, ROI calculations, average finishes, win rates, and more.',
    },
    {
      question: 'Can I manage tournament series?',
      answer: 'Yes, the plugin includes full series and season management with automatic standings calculation, leaderboards, and points tracking across multiple tournaments.',
    },
    {
      question: 'What are the server requirements?',
      answer: 'WordPress 6.0+, PHP 8.0+, and MySQL 5.7+ or MariaDB 10.2+. Most modern hosting plans meet these requirements. 256MB memory minimum (512MB+ recommended for large tournaments).',
    },
    {
      question: 'How do I get support?',
      answer: 'Free community support is available through the WordPress.org support forums and GitHub issues. Priority email support is available for $49/month.',
    },
    {
      question: 'Can I import multiple tournaments at once?',
      answer: 'Currently, tournaments are imported one at a time. Batch import functionality is planned for a future version based on user feedback.',
    },
  ]

  return (
    <section id="faq" className="py-20 px-4 bg-white">
      <div className="container mx-auto max-w-4xl">
        {/* Section Header */}
        <div className="text-center mb-16">
          <h2 className="section-heading">Frequently Asked Questions</h2>
          <p className="text-xl text-gray-600">
            Everything you need to know about TD WP Import
          </p>
        </div>

        {/* FAQ Accordion */}
        <div className="space-y-4">
          {faqs.map((faq, index) => (
            <div
              key={index}
              className="bg-gray-50 rounded-lg overflow-hidden border border-gray-200 hover:border-primary/50 transition-colors"
            >
              <button
                onClick={() => setOpenIndex(openIndex === index ? null : index)}
                className="w-full px-6 py-5 flex items-center justify-between text-left"
              >
                <span className="font-semibold text-navy text-lg pr-4">
                  {faq.question}
                </span>
                <ChevronDown
                  size={24}
                  className={`text-primary flex-shrink-0 transition-transform ${
                    openIndex === index ? 'rotate-180' : ''
                  }`}
                />
              </button>
              {openIndex === index && (
                <div className="px-6 pb-5 text-gray-600 leading-relaxed">
                  {faq.answer}
                </div>
              )}
            </div>
          ))}
        </div>

        {/* Contact CTA */}
        <div className="mt-12 text-center p-8 bg-gradient-to-r from-primary/10 to-gold/10 rounded-xl">
          <h3 className="text-2xl font-bold text-navy mb-3">
            Still have questions?
          </h3>
          <p className="text-gray-600 mb-6">
            We're here to help! Reach out through our support channels
          </p>
          <div className="flex flex-col sm:flex-row gap-4 justify-center">
            <a
              href="https://wordpress.org/support/plugin/poker-tournament-import"
              className="btn-secondary"
            >
              Support Forum
            </a>
            <a
              href="https://github.com/hkhard/tdwpimport/issues"
              className="btn-secondary"
            >
              GitHub Issues
            </a>
          </div>
        </div>
      </div>
    </section>
  )
}
