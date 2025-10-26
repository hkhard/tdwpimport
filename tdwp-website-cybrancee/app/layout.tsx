import type { Metadata } from 'next'
import { Inter } from 'next/font/google'
import './globals.css'
import Header from '@/components/layout/Header'
import Footer from '@/components/layout/Footer'

const inter = Inter({ subsets: ['latin'] })

export const metadata: Metadata = {
  title: 'TD WP Import - Tournament Director WordPress Plugin',
  description: 'Import and display poker tournament results from Tournament Director (.tdt) files on WordPress. Reduce manual data entry by 90% with 100% accuracy.',
  keywords: 'wordpress poker tournament plugin, tournament director wordpress, poker results, tdt file import, poker tournament management, wordpress plugin',
  authors: [{ name: 'TD WP Import' }],
  openGraph: {
    title: 'TD WP Import - Tournament Director WordPress Plugin',
    description: 'Import poker tournament results from Tournament Director to WordPress. 90% faster publishing with 100% accuracy.',
    type: 'website',
    url: 'https://tdwpimport.com',
    images: [
      {
        url: '/og-image.png',
        width: 1200,
        height: 630,
        alt: 'TD WP Import Dashboard',
      },
    ],
  },
  twitter: {
    card: 'summary_large_image',
    title: 'TD WP Import - Tournament Director WordPress Plugin',
    description: 'Import poker tournament results from Tournament Director to WordPress',
    images: ['/og-image.png'],
  },
}

export default function RootLayout({
  children,
}: {
  children: React.ReactNode
}) {
  return (
    <html lang="en">
      <body className={inter.className}>
        <Header />
        <main>{children}</main>
        <Footer />
      </body>
    </html>
  )
}
