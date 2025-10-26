'use client'

import { Star } from 'lucide-react'

export default function Testimonials() {
  const testimonials = [
    {
      name: 'Marcus H',
      role: 'Tournament Director',
      organization: 'Swedish Poker League',
      quote: 'This plugin has been a game-changer for our poker league. What used to take me 3 hours of manual entry now takes 30 seconds. The automatic player statistics and series tracking are incredible.',
      rating: 5,
      avatar: '🎯',
    },
    {
      name: 'Fredrik Y',
      role: 'Poker Room Manager',
      organization: 'ORF Poker Series',
      quote: 'The formula engine is incredibly powerful. We run complex point systems across our tournament series, and this plugin handles it all perfectly. The data accuracy is flawless.',
      rating: 5,
      avatar: '♠️',
    },
    {
      name: 'Joakim H',
      role: 'League Organizer',
      organization: 'Koffsta Poker Club',
      quote: 'Our players love being able to see their tournament history and statistics. Player engagement has increased significantly since we started using this plugin. Highly recommended!',
      rating: 5,
      avatar: '🏆',
    },
  ]

  return (
    <section className="py-20 px-4 bg-gradient-to-br from-navy via-navy-light to-primary-dark text-white">
      <div className="container mx-auto">
        {/* Section Header */}
        <div className="text-center mb-16">
          <h2 className="text-4xl md:text-5xl font-bold mb-6">What Tournament Directors Say</h2>
          <p className="text-xl text-gray-200 max-w-2xl mx-auto">
            Real feedback from poker tournament organizers using TD WP Import
          </p>
        </div>

        {/* Testimonials Grid */}
        <div className="grid grid-cols-1 md:grid-cols-3 gap-8">
          {testimonials.map((testimonial, index) => (
            <div
              key={index}
              className="bg-white/10 backdrop-blur-sm rounded-xl p-8 border border-white/20 hover:bg-white/15 transition-all duration-300"
            >
              {/* Stars */}
              <div className="flex space-x-1 mb-4">
                {[...Array(testimonial.rating)].map((_, i) => (
                  <Star key={i} size={20} className="fill-gold text-gold" />
                ))}
              </div>

              {/* Quote */}
              <p className="text-gray-200 italic leading-relaxed mb-6">
                "{testimonial.quote}"
              </p>

              {/* Author */}
              <div className="flex items-center space-x-4">
                <div className="w-12 h-12 rounded-full bg-white/20 flex items-center justify-center text-2xl">
                  {testimonial.avatar}
                </div>
                <div>
                  <div className="font-bold text-white">{testimonial.name}</div>
                  <div className="text-sm text-gray-300">{testimonial.role}</div>
                  <div className="text-xs text-gray-400">{testimonial.organization}</div>
                </div>
              </div>
            </div>
          ))}
        </div>

        {/* Overall Rating */}
        <div className="mt-16 text-center">
          <div className="inline-block bg-white/10 backdrop-blur-sm border border-white/20 rounded-xl p-8">
            <div className="flex items-center justify-center space-x-2 mb-3">
              {[...Array(5)].map((_, i) => (
                <Star key={i} size={32} className="fill-gold text-gold" />
              ))}
            </div>
            <div className="text-4xl font-bold text-white mb-2">4.8 / 5.0</div>
            <div className="text-gray-300">Based on 150+ reviews</div>
          </div>
        </div>
      </div>
    </section>
  )
}
