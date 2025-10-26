# TD WP Import Website - Static HTML for Cybrancee

Marketing website for Tournament Director WordPress Import plugin. Static HTML export for simple deployment on any hosting. Runs in `/tdwpimport` subfolder at nikielhard.se.

## 🎯 What's Different in This Version

**Static HTML export for PHP/Apache hosting:**
- ✅ Base path set to `/tdwpimport` (runs in subfolder)
- ✅ Pure HTML/CSS/JS - no Node.js or PHP needed
- ✅ Works on any hosting (Apache, nginx, etc.)
- ✅ All internal links updated for subfolder routing
- ✅ Content updated to v2.9.14 with bulk import features
- ✅ Includes `.htaccess` for Apache

**See deployment guides:**
- 📘 **DEPLOY.md** - 3-step quick deploy
- 📗 **STATIC_DEPLOYMENT.md** - Complete guide with troubleshooting

## 🚀 Quick Start

```bash
# Install dependencies
npm install

# Run development server
npm run dev

# Build for production
npm run build

# Start production server
npm start
```

Visit [http://localhost:3000/tdwpimport](http://localhost:3000/tdwpimport) to see the website (note the `/tdwpimport` path).

## 📁 Project Structure

```
tdwp-website/
├── app/
│   ├── layout.tsx          # Root layout with SEO metadata
│   ├── page.tsx            # Homepage
│   └── globals.css         # Global styles & Tailwind
├── components/
│   ├── layout/
│   │   ├── Header.tsx      # Navigation header
│   │   └── Footer.tsx      # Site footer
│   └── sections/
│       ├── Hero.tsx        # Hero section with CTA
│       ├── Features.tsx    # Feature grid
│       ├── HowItWorks.tsx  # 3-step process
│       ├── Benefits.tsx    # Benefits showcase
│       ├── Testimonials.tsx # User testimonials
│       ├── Pricing.tsx     # Pricing & plans
│       ├── FAQ.tsx         # FAQ accordion
│       └── CTA.tsx         # Final download CTA
├── public/
│   └── images/            # Static images & assets
├── package.json
├── tailwind.config.ts
├── tsconfig.json
└── next.config.js
```

## 🎨 Design System

### Color Palette
- **Primary Blue**: `#3498db` (from dashboard gradient)
- **Navy**: `#1e3a52` (dark backgrounds)
- **Gold**: `#f39c12` (CTAs and accents)
- **Success Green**: `#28a745`

### Typography
- Font: Inter (Google Fonts)
- Headings: Bold, 2xl-7xl
- Body: Regular, base-xl

### Components
- Responsive grid layouts (1/2/3 columns)
- Card components with hover effects
- Button variants (primary/secondary)
- Section headings with consistent spacing

## 🔧 Key Features

### SEO Optimization
- Next.js Metadata API for title/description
- Open Graph tags for social sharing
- Twitter Card metadata
- Semantic HTML structure
- Structured data ready

### Performance
- Next.js 14 App Router
- Static generation where possible
- Optimized images with Next.js Image
- Tailwind CSS for minimal bundle size
- Component-level code splitting

### Accessibility
- Semantic HTML5 elements
- ARIA labels on interactive elements
- Keyboard navigation support
- Color contrast compliance (WCAG AA)
- Focus indicators

### Mobile First
- Responsive breakpoints (sm/md/lg/xl)
- Touch-friendly navigation
- Mobile-optimized layouts
- Hamburger menu for small screens

## 📝 Content Strategy

### Target Audience
1. Tournament Directors - need fast publishing
2. Poker Room Managers - manage multiple series
3. League Organizers - track season standings
4. WordPress Site Owners - run poker sites

### Key Messages
- **90% Time Savings** - from hours to seconds
- **100% Accuracy** - eliminate manual errors
- **Professional Results** - beautiful displays
- **Free Forever** - no hidden costs

### SEO Keywords
- "wordpress poker tournament plugin"
- "tournament director wordpress"
- "poker results wordpress"
- "tdt file import wordpress"
- "poker tournament management"

## 🚢 Deployment

### Vercel (Recommended)
```bash
# Install Vercel CLI
npm i -g vercel

# Deploy
vercel

# Deploy to production
vercel --prod
```

### Netlify
1. Push code to GitHub
2. Connect repo to Netlify
3. Build command: `npm run build`
4. Publish directory: `.next`

### Custom Server
```bash
# Build
npm run build

# Start
npm start

# Or use PM2
pm2 start npm --name "tdwp-website" -- start
```

## 🔗 Environment Variables

Create `.env.local` for local development:

```env
# Site URL
NEXT_PUBLIC_SITE_URL=https://tdwpimport.com

# Analytics (optional)
NEXT_PUBLIC_GA_ID=G-XXXXXXXXXX

# Contact Form (optional)
CONTACT_FORM_API=your_api_endpoint
```

## 📊 Analytics Setup

### Google Analytics 4
Add to `app/layout.tsx`:
```tsx
import Script from 'next/script'

// Add in <head>
<Script
  src={`https://www.googletagmanager.com/gtag/js?id=${process.env.NEXT_PUBLIC_GA_ID}`}
  strategy="afterInteractive"
/>
```

### Vercel Analytics
```bash
npm install @vercel/analytics
```

Add to `app/layout.tsx`:
```tsx
import { Analytics } from '@vercel/analytics/react'

// Add in <body>
<Analytics />
```

## 🎯 Conversion Optimization

### Primary CTAs
- Hero download button (above fold)
- Feature section CTA
- Pricing download button
- Final CTA section

### Secondary CTAs
- View demo/docs
- GitHub link
- Support forum

### Trust Signals
- 500+ installations stat
- 4.8/5 star rating
- User testimonials
- WordPress.org badge

## 🧪 Testing

### Manual Testing Checklist
- [ ] All links work (internal and external)
- [ ] Mobile responsive on all devices
- [ ] Forms submit correctly
- [ ] Images load properly
- [ ] No console errors
- [ ] Fast load times (<3s)
- [ ] SEO metadata present
- [ ] Accessibility audit passes

### Automated Testing
```bash
# Lighthouse audit
npm run build
npm start
# Run Lighthouse in Chrome DevTools

# TypeScript type checking
npx tsc --noEmit
```

## 📚 Additional Resources

- [Next.js Documentation](https://nextjs.org/docs)
- [Tailwind CSS Docs](https://tailwindcss.com/docs)
- [Vercel Deployment](https://vercel.com/docs)
- [WordPress Plugin Repo](https://wordpress.org/plugins/poker-tournament-import/)

## 🤝 Contributing

This website is part of the TD WP Import project. For issues or improvements:
1. Open issue on [GitHub](https://github.com/hkhard/tdwpimport/issues)
2. Fork and create PR
3. Follow existing code style

## 📄 License

Same as TD WP Import plugin - GPL v2 or later

---

**Made with ❤️ for the poker community**
