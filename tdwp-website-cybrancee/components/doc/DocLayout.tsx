import Link from 'next/link'
import { Home, Book, FileText, Code } from 'lucide-react'

interface DocLayoutProps {
  children: React.ReactNode
  title: string
  description?: string
}

export default function DocLayout({ children, title, description }: DocLayoutProps) {
  return (
    <div className="min-h-screen bg-gray-50">
      {/* Breadcrumb */}
      <div className="bg-white border-b border-gray-200">
        <div className="container mx-auto px-4 py-4">
          <nav className="flex items-center space-x-2 text-sm text-gray-600">
            <Link href="/" className="hover:text-primary transition-colors flex items-center">
              <Home size={16} className="mr-1" />
              Home
            </Link>
            <span>/</span>
            <span className="text-navy font-medium">{title}</span>
          </nav>
        </div>
      </div>

      {/* Header */}
      <div className="bg-gradient-to-br from-navy via-navy-light to-primary-dark text-white py-12">
        <div className="container mx-auto px-4">
          <h1 className="text-4xl md:text-5xl font-bold mb-4">{title}</h1>
          {description && (
            <p className="text-xl text-gray-200 max-w-3xl">{description}</p>
          )}
        </div>
      </div>

      {/* Content */}
      <div className="container mx-auto px-4 py-12">
        <div className="grid grid-cols-1 lg:grid-cols-4 gap-8">
          {/* Sidebar Navigation */}
          <aside className="lg:col-span-1">
            <div className="bg-white rounded-lg shadow-sm p-6 sticky top-4">
              <h3 className="font-semibold text-navy mb-4">Documentation</h3>
              <nav className="space-y-2">
                <Link
                  href="/docs"
                  className="flex items-center space-x-2 px-3 py-2 rounded-lg hover:bg-gray-100 transition-colors text-gray-700 hover:text-navy"
                >
                  <Book size={18} />
                  <span>Documentation</span>
                </Link>
                <Link
                  href="/user-manual"
                  className="flex items-center space-x-2 px-3 py-2 rounded-lg hover:bg-gray-100 transition-colors text-gray-700 hover:text-navy"
                >
                  <FileText size={18} />
                  <span>User Manual</span>
                </Link>
                <Link
                  href="/changelog"
                  className="flex items-center space-x-2 px-3 py-2 rounded-lg hover:bg-gray-100 transition-colors text-gray-700 hover:text-navy"
                >
                  <Code size={18} />
                  <span>Changelog</span>
                </Link>
              </nav>

              <div className="mt-6 pt-6 border-t border-gray-200">
                <h4 className="text-sm font-semibold text-gray-700 mb-3">Quick Links</h4>
                <div className="space-y-2 text-sm">
                  <a
                    href="https://wordpress.org/plugins/poker-tournament-import/"
                    className="block text-primary hover:underline"
                    target="_blank"
                    rel="noopener noreferrer"
                  >
                    WordPress.org
                  </a>
                  <a
                    href="https://github.com/hkhard/tdwpimport"
                    className="block text-primary hover:underline"
                    target="_blank"
                    rel="noopener noreferrer"
                  >
                    GitHub Repository
                  </a>
                  <a
                    href="https://wordpress.org/support/plugin/poker-tournament-import"
                    className="block text-primary hover:underline"
                    target="_blank"
                    rel="noopener noreferrer"
                  >
                    Support Forum
                  </a>
                </div>
              </div>

              <div className="mt-6 pt-6 border-t border-gray-200">
                <div className="text-sm text-gray-600">
                  <div className="font-semibold text-navy mb-1">Current Version</div>
                  <div className="text-2xl font-bold text-primary">2.9.22</div>
                  <div className="mt-2 text-xs">
                    WordPress 6.0+<br />
                    PHP 8.0+
                  </div>
                </div>
              </div>
            </div>
          </aside>

          {/* Main Content */}
          <main className="lg:col-span-3">
            <div className="bg-white rounded-lg shadow-sm p-8 prose prose-lg max-w-none
              prose-headings:text-navy
              prose-h1:text-4xl prose-h1:font-bold prose-h1:mb-6
              prose-h2:text-3xl prose-h2:font-semibold prose-h2:mt-12 prose-h2:mb-4 prose-h2:border-b prose-h2:border-gray-200 prose-h2:pb-2
              prose-h3:text-2xl prose-h3:font-semibold prose-h3:mt-8 prose-h3:mb-3
              prose-h4:text-xl prose-h4:font-semibold prose-h4:mt-6 prose-h4:mb-2
              prose-p:text-gray-700 prose-p:leading-relaxed
              prose-a:text-primary prose-a:no-underline hover:prose-a:underline
              prose-strong:text-navy prose-strong:font-semibold
              prose-code:text-primary prose-code:bg-gray-100 prose-code:px-1 prose-code:py-0.5 prose-code:rounded prose-code:before:content-[''] prose-code:after:content-['']
              prose-pre:bg-gray-900 prose-pre:text-gray-100
              prose-ul:list-disc prose-ul:ml-6
              prose-ol:list-decimal prose-ol:ml-6
              prose-li:text-gray-700
              prose-blockquote:border-l-4 prose-blockquote:border-primary prose-blockquote:pl-4 prose-blockquote:italic
              prose-img:rounded-lg prose-img:shadow-md
              prose-table:border-collapse prose-table:w-full
              prose-th:bg-gray-100 prose-th:p-3 prose-th:text-left prose-th:font-semibold
              prose-td:border prose-td:border-gray-300 prose-td:p-3
            ">
              {children}
            </div>
          </main>
        </div>
      </div>
    </div>
  )
}
