import {
  Upload,
  Users,
  TrendingUp,
  Award,
  Database,
  Zap,
  Shield,
  Smartphone,
  BarChart3,
  Code,
  Search,
  Settings,
} from 'lucide-react'

export default function Features() {
  const features = [
    {
      icon: Upload,
      title: 'Bulk Import (NEW)',
      description: 'Upload multiple .tdt files at once with real-time progress tracking, intelligent duplicate detection, and automatic processing.',
      color: 'text-primary',
      bg: 'bg-primary/10',
    },
    {
      icon: Users,
      title: 'Player Profiles',
      description: 'Automatic player profile creation with tournament history, statistics, and career earnings tracking.',
      color: 'text-success',
      bg: 'bg-success/10',
    },
    {
      icon: TrendingUp,
      title: 'Series Tracking',
      description: 'Manage tournament series with automatic standings calculation and season-long rankings.',
      color: 'text-gold',
      bg: 'bg-gold/10',
    },
    {
      icon: Award,
      title: 'Points Formula Engine',
      description: 'Complete Tournament Director formula support with 145+ variables and 43+ mathematical functions.',
      color: 'text-purple-600',
      bg: 'bg-purple-100',
    },
    {
      icon: Database,
      title: 'Data Mart Analytics',
      description: 'Pre-calculated statistics and data mart tables for lightning-fast dashboard performance.',
      color: 'text-navy',
      bg: 'bg-navy/10',
    },
    {
      icon: Zap,
      title: 'Lightning Fast',
      description: 'Optimized database queries, caching, and lazy loading for instant page loads.',
      color: 'text-yellow-600',
      bg: 'bg-yellow-100',
    },
    {
      icon: Shield,
      title: 'Secure & Safe',
      description: 'File validation, data sanitization, nonce verification, and prepared statements throughout.',
      color: 'text-red-600',
      bg: 'bg-red-100',
    },
    {
      icon: Smartphone,
      title: 'Mobile Responsive',
      description: 'Beautiful tournament displays that adapt perfectly to all screen sizes and devices.',
      color: 'text-blue-600',
      bg: 'bg-blue-100',
    },
    {
      icon: BarChart3,
      title: 'ROI Analytics',
      description: 'Track player profitability with total invested, gross winnings, and net profit calculations.',
      color: 'text-green-600',
      bg: 'bg-green-100',
    },
    {
      icon: Code,
      title: 'Shortcode System',
      description: 'Flexible shortcodes to display tournaments, players, series, and leaderboards anywhere.',
      color: 'text-indigo-600',
      bg: 'bg-indigo-100',
    },
    {
      icon: Search,
      title: 'SEO Optimized',
      description: 'Structured data, semantic URLs, meta tags, and search engine friendly markup.',
      color: 'text-teal-600',
      bg: 'bg-teal-100',
    },
    {
      icon: Settings,
      title: 'Highly Customizable',
      description: 'Custom templates, CSS styling, formula editor, and complete WordPress hooks integration.',
      color: 'text-orange-600',
      bg: 'bg-orange-100',
    },
  ]

  return (
    <section id="features" className="py-20 px-4 bg-gray-50">
      <div className="container mx-auto">
        {/* Section Header */}
        <div className="text-center mb-16">
          <h2 className="section-heading">Powerful Features Built for Tournament Directors</h2>
          <p className="text-xl text-gray-600 max-w-3xl mx-auto">
            Everything you need to publish professional tournament results, manage player statistics, and engage your poker community.
          </p>
        </div>

        {/* Features Grid */}
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
          {features.map((feature, index) => {
            const Icon = feature.icon
            return (
              <div
                key={index}
                className="card group hover:scale-105 transition-transform duration-300"
              >
                <div className={`${feature.bg} ${feature.color} w-14 h-14 rounded-lg flex items-center justify-center mb-4 group-hover:scale-110 transition-transform`}>
                  <Icon size={28} />
                </div>
                <h3 className="text-xl font-bold text-navy mb-3">
                  {feature.title}
                </h3>
                <p className="text-gray-600 leading-relaxed">
                  {feature.description}
                </p>
              </div>
            )
          })}
        </div>

        {/* Bottom CTA */}
        <div className="text-center mt-16">
          <a href="#download" className="btn-primary inline-flex items-center space-x-2">
            <span>Get Started Free</span>
            <span>→</span>
          </a>
        </div>
      </div>
    </section>
  )
}
