import { Upload, CheckCircle, Sparkles } from 'lucide-react'

export default function HowItWorks() {
  const steps = [
    {
      icon: Upload,
      title: 'Export from Tournament Director',
      description: 'Open your tournament in TD, go to File → Export, and save as JavaScript (.tdt) format.',
      color: 'from-primary to-primary-dark',
    },
    {
      icon: CheckCircle,
      title: 'Upload to WordPress',
      description: 'In WordPress admin, go to Poker Import → Import Tournament and upload your .tdt file.',
      color: 'from-success to-green-700',
    },
    {
      icon: Sparkles,
      title: 'Publish Results Instantly',
      description: 'The plugin automatically extracts all data, creates player profiles, calculates points, and publishes results.',
      color: 'from-gold to-gold-dark',
    },
  ]

  return (
    <section id="how-it-works" className="py-20 px-4 bg-white">
      <div className="container mx-auto">
        {/* Section Header */}
        <div className="text-center mb-16">
          <h2 className="section-heading">How It Works</h2>
          <p className="text-xl text-gray-600 max-w-2xl mx-auto">
            Three simple steps to publish professional tournament results
          </p>
        </div>

        {/* Steps */}
        <div className="grid grid-cols-1 md:grid-cols-3 gap-8 relative">
          {steps.map((step, index) => {
            const Icon = step.icon
            return (
              <div key={index} className="relative">
                {/* Connector Line (desktop only) */}
                {index < steps.length - 1 && (
                  <div className="hidden md:block absolute top-16 left-1/2 w-full h-0.5 bg-gradient-to-r from-gray-300 to-gray-300 z-0"></div>
                )}

                {/* Step Card */}
                <div className="relative z-10 text-center">
                  <div className="flex justify-center mb-6">
                    <div className={`bg-gradient-to-br ${step.color} w-32 h-32 rounded-full flex items-center justify-center shadow-xl`}>
                      <Icon size={48} className="text-white" />
                    </div>
                  </div>

                  <div className="bg-white/60 backdrop-blur-sm rounded-lg p-6">
                    <div className="inline-block bg-navy text-white rounded-full w-8 h-8 flex items-center justify-center font-bold mb-4">
                      {index + 1}
                    </div>
                    <h3 className="text-2xl font-bold text-navy mb-3">
                      {step.title}
                    </h3>
                    <p className="text-gray-600 leading-relaxed">
                      {step.description}
                    </p>
                  </div>
                </div>
              </div>
            )
          })}
        </div>

        {/* Time Savings Highlight */}
        <div className="mt-16 bg-gradient-to-r from-primary/10 to-gold/10 rounded-2xl p-8 text-center">
          <div className="max-w-3xl mx-auto">
            <h3 className="text-3xl font-bold text-navy mb-4">
              From Hours to Minutes
            </h3>
            <p className="text-xl text-gray-700 mb-6">
              What used to take 2-3 hours of manual data entry now takes less than 30 seconds
            </p>
            <div className="flex justify-center items-center space-x-8">
              <div>
                <div className="text-5xl font-bold text-red-600 line-through">3hrs</div>
                <div className="text-sm text-gray-600 mt-2">Manual Entry</div>
              </div>
              <div className="text-4xl text-gray-400">→</div>
              <div>
                <div className="text-5xl font-bold text-success">30s</div>
                <div className="text-sm text-gray-600 mt-2">With Plugin</div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>
  )
}
