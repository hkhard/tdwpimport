/** @type {import('next').NextConfig} */
const nextConfig = {
  basePath: '/tdwpimport',
  assetPrefix: '/tdwpimport',
  output: 'export',
  images: {
    domains: [],
    unoptimized: true,
  },
}

module.exports = nextConfig
