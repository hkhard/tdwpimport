'use client'

import { Download, PlayCircle, TrendingUp } from 'lucide-react'
import Image from 'next/image'

export default function Hero() {
  return (
    <section className="pt-32 pb-20 px-4 bg-gradient-to-br from-navy via-navy-light to-primary-dark text-white overflow-hidden">
      <div className="container mx-auto">
        <div className="grid grid-cols-1 lg:grid-cols-2 gap-12 items-center">
          {/* Left Column - Copy */}
          <div className="space-y-8">
            <div className="inline-block">
              <div className="bg-white/10 backdrop-blur-sm border border-white/20 rounded-full px-4 py-2 text-sm font-medium">
                <span className="flex items-center space-x-2">
                  <TrendingUp size={16} className="text-gold" />
                  <span>Trusted by 500+ tournament organizers</span>
                </span>
              </div>
            </div>

            <h1 className="text-5xl md:text-6xl lg:text-7xl font-bold leading-tight">
              Publish Tournament Results
              <span className="block text-gold mt-2">90% Faster</span>
            </h1>

            <p className="text-xl text-gray-200 leading-relaxed">
              Import poker tournament results from Tournament Director (.tdt files) to WordPress automatically.
              Eliminate manual data entry with 100% accuracy.
            </p>

            {/* Stats */}
            <div className="grid grid-cols-3 gap-4 py-6">
              <div>
                <div className="text-3xl font-bold text-gold">90%</div>
                <div className="text-sm text-gray-300">Time Saved</div>
              </div>
              <div>
                <div className="text-3xl font-bold text-gold">100%</div>
                <div className="text-sm text-gray-300">Accuracy</div>
              </div>
              <div>
                <div className="text-3xl font-bold text-gold">500+</div>
                <div className="text-sm text-gray-300">Installs</div>
              </div>
            </div>

            {/* CTAs */}
            <div className="flex flex-col sm:flex-row gap-4">
              <a
                href="#download"
                className="bg-gold hover:bg-gold-dark text-white font-semibold py-4 px-8 rounded-lg transition-all duration-200 transform hover:scale-105 shadow-lg hover:shadow-xl flex items-center justify-center space-x-2"
              >
                <Download size={20} />
                <span>Download Free Plugin</span>
              </a>
              <a
                href="#demo"
                className="bg-white/10 hover:bg-white/20 backdrop-blur-sm border-2 border-white/30 text-white font-semibold py-4 px-8 rounded-lg transition-all duration-200 flex items-center justify-center space-x-2"
              >
                <PlayCircle size={20} />
                <span>Watch Demo</span>
              </a>
            </div>

            {/* Trust Indicators */}
            <div className="flex items-center space-x-6 text-sm text-gray-300">
              <div className="flex items-center space-x-1">
                <span className="text-gold">★★★★★</span>
                <span>4.8/5 Rating</span>
              </div>
              <div>WordPress 6.0+</div>
              <div>PHP 8.0+</div>
            </div>
          </div>

          {/* Right Column - Dashboard Preview */}
          <div className="relative">
            <div className="relative z-10 rounded-xl overflow-hidden shadow-2xl border-4 border-white/20">
              <div className="bg-white p-4">
                <div className="bg-gradient-to-br from-gray-100 to-gray-200 rounded-lg overflow-hidden relative flex items-center justify-center">
                  <Image
                    src="/assets/images/banner.png"
                    alt="Tournament Dashboard Preview"
                    width={800}
                    height={600}
                    className="max-w-full h-auto object-contain"
                    sizes="(max-width: 768px) 100vw, 50vw"
                    priority
                  />
                </div>
              </div>
            </div>

            {/* Floating Elements */}
            <div className="absolute -top-4 -right-4 bg-success text-white rounded-lg p-4 shadow-xl z-20 animate-bounce">
              <div className="text-sm font-semibold">+15 Players</div>
              <div className="text-xs">Imported</div>
            </div>

            <div className="absolute -bottom-4 -left-4 bg-primary text-white rounded-lg p-4 shadow-xl z-20">
              <div className="text-sm font-semibold">$3,000</div>
              <div className="text-xs">Prize Pool</div>
            </div>

            {/* Background Decoration */}
            <div className="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-96 h-96 bg-gold/20 rounded-full blur-3xl -z-10"></div>
          </div>
        </div>
      </div>
    </section>
  )
}
