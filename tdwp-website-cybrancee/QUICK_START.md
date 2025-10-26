# Quick Start - Deploy to Cybrancee/Plesk

## What's Configured

- **Base Path**: `/tdwpimport` (runs in subfolder)
- **Asset Prefix**: `/tdwpimport` (all assets use subfolder)
- **Output Mode**: `standalone` (optimized for Node.js hosting)
- **Image Optimization**: Disabled (works better on shared hosting)
- **Version**: 2.9.14 (latest stable)
- **Features**: Updated with bulk import capabilities

## 5-Minute Deployment

### 1. Build (if not already done)
```bash
npm install
npm run build
```

### 2. Upload to Server
Upload these to `/httpdocs/tdwpimport/`:
- `.next/` directory
- `public/` directory
- `package.json`
- `next.config.js`

### 3. Configure in Plesk
- Enable Node.js (v18+)
- Set app root: `/httpdocs/tdwpimport`
- Set startup: `.next/standalone/server.js`
- Click "Enable" and "Restart"

### 4. Test
Visit: `https://nikielhard.se/tdwpimport`

## Files Included

- Website source code with basePath configured
- Next.js 14 with App Router
- Tailwind CSS styling
- Updated content for v2.9.14
- Bulk import feature highlighted
- All internal links updated for subfolder

## Key Changes from Original

1. **Configuration**:
   - Added `basePath: '/tdwpimport'`
   - Added `assetPrefix: '/tdwpimport'`
   - Changed output to `standalone`
   - Disabled image optimization

2. **Routing**:
   - All relative links now use Next.js `<Link>` component
   - Hash links (#features, etc) work correctly
   - External links unchanged

3. **Content Updates**:
   - Version updated to 2.9.14
   - Bulk import feature highlighted
   - Hero description mentions bulk import
   - Features list updated with NEW tag

## Development

Run locally on subfolder:
```bash
npm run dev
# Visit: http://localhost:3000/tdwpimport
```

Build for production:
```bash
npm run build
npm start
# Visit: http://localhost:3000/tdwpimport
```

## Troubleshooting

**404 on subfolder**: Check basePath in next.config.js
**Images not loading**: Verify public/ folder uploaded
**App won't start**: Check Node.js version (need 18+)

See PLESK_DEPLOYMENT.md for detailed instructions.
