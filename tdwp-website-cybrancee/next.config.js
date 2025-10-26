/** @type {import('next').NextConfig} */
const nextConfig = {
  basePath: '/tdwpimport',
  assetPrefix: '/tdwpimport',
  output: 'standalone',
  images: {
    domains: [],
    unoptimized: true,
  },
}

module.exports = nextConfig
