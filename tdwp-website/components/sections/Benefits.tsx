import { Clock, Target, Shield, TrendingUp, Heart, Zap } from 'lucide-react'

export default function Benefits() {
  const benefits = [
    {
      icon: Clock,
      title: '90% Time Savings',
      description: 'Reduce manual data entry from hours to seconds with automated .tdt file parsing.',
      stat: '3 hours → 30 seconds',
    },
    {
      icon: Target,
      title: '100% Accuracy',
      description: 'Eliminate manual entry errors with direct data extraction from Tournament Director.',
      stat: 'Zero errors',
    },
    {
      icon: TrendingUp,
      title: 'Player Engagement',
      description: 'Automatic player profiles and statistics keep your community engaged and coming back.',
      stat: '+150% engagement',
    },
    {
      icon: Shield,
      title: 'WordPress Security',
      description: 'Built following WordPress security best practices with nonce verification and prepared statements.',
      stat: 'GDPR compliant',
    },
    {
      icon: Heart,
      title: 'Professional Results',
      description: 'Beautiful, mobile-responsive tournament displays that work with any WordPress theme.',
      stat: '4.8/5 rating',
    },
    {
      icon: Zap,
      title: 'Lightning Performance',
      description: 'Optimized database queries and caching ensure instant page loads even with thousands of tournaments.',
      stat: '<1s load time',
    },
  ]

  return (
    <section className="py-20 px-4 bg-white">
      <div className="container mx-auto">
        {/* Section Header */}
        <div className="text-center mb-16">
          <h2 className="section-heading">Why Tournament Directors Choose TD WP Import</h2>
          <p className="text-xl text-gray-600 max-w-3xl mx-auto">
            Join hundreds of poker tournament organizers who have streamlined their operations
          </p>
        </div>

        {/* Benefits Grid */}
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
          {benefits.map((benefit, index) => {
            const Icon = benefit.icon
            return (
              <div
                key={index}
                className="relative p-8 rounded-xl bg-gradient-to-br from-gray-50 to-white border-2 border-gray-100 hover:border-primary/50 hover:shadow-xl transition-all duration-300 group"
              >
                <div className="flex items-start space-x-4">
                  <div className="bg-primary/10 text-primary w-12 h-12 rounded-lg flex items-center justify-center flex-shrink-0 group-hover:scale-110 transition-transform">
                    <Icon size={24} />
                  </div>
                  <div>
                    <h3 className="text-xl font-bold text-navy mb-2">
                      {benefit.title}
                    </h3>
                    <p className="text-gray-600 text-sm mb-3">
                      {benefit.description}
                    </p>
                    <div className="inline-block bg-gold/20 text-gold-dark text-xs font-semibold px-3 py-1 rounded-full">
                      {benefit.stat}
                    </div>
                  </div>
                </div>
              </div>
            )
          })}
        </div>

        {/* Social Proof */}
        <div className="mt-16 text-center">
          <p className="text-gray-500 text-sm mb-4">TRUSTED BY LEADING POKER ORGANIZATIONS</p>
          <div className="flex flex-wrap justify-center items-center gap-12 opacity-60">
            {['Poker League 1', 'Casino 2', 'Club 3', 'Series 4'].map((org, i) => (
              <div key={i} className="text-2xl font-bold text-gray-400">{org}</div>
            ))}
          </div>
        </div>
      </div>
    </section>
  )
}
