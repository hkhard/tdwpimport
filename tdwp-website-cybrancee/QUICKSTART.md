# 🚀 Quick Start Guide - TD WP Import Website

Get your marketing website running in under 5 minutes!

## Step 1: Install Dependencies ⚡

```bash
cd /Users/hkh/dev/tdwpimport/tdwp-website
npm install
```

This will install:
- Next.js 14
- React 18
- Tailwind CSS
- Lucide React icons
- Framer Motion
- TypeScript

## Step 2: Run Development Server 🖥️

```bash
npm run dev
```

Open [http://localhost:3000](http://localhost:3000) in your browser.

You should see:
- ✅ Hero section with "Publish Tournament Results 90% Faster"
- ✅ Features grid with 12 features
- ✅ How It Works (3 steps)
- ✅ Benefits showcase
- ✅ Testimonials
- ✅ Pricing section
- ✅ FAQ accordion
- ✅ Download CTA

## Step 3: Customize Content 📝

### Update Plugin Version
Edit `components/sections/CTA.tsx`:
```tsx
<span className="font-semibold text-white">Current Version:</span> 2.6.6
```

### Update Stats
Edit `components/sections/Hero.tsx`:
```tsx
<div className="text-3xl font-bold text-gold">500+</div>
<div className="text-sm text-gray-300">Installs</div>
```

### Update Links
Edit `components/layout/Footer.tsx` and `components/sections/CTA.tsx` with your actual URLs.

## Step 4: Add Real Images 🖼️

Copy banner and icons to `/public/images/`:
```bash
cp /Users/hkh/dev/tdwpimport/assets/* /Users/hkh/dev/tdwpimport/tdwp-website/public/images/
```

Update Hero section to use real dashboard image:
```tsx
<Image
  src="/images/banner.png"
  alt="Tournament Dashboard"
  width={1200}
  height={630}
/>
```

## Step 5: Build for Production 🏗️

```bash
npm run build
```

This creates an optimized production build in `.next/` directory.

Test production build locally:
```bash
npm start
```

## Step 6: Deploy to Vercel 🌐

### One-Command Deploy
```bash
npx vercel
```

Follow the prompts:
1. Set up and deploy? **Y**
2. Which scope? **Your account**
3. Link to existing project? **N**
4. Project name? **tdwp-import-website**
5. Directory? **./  (press Enter)**

Your site will be live at a temporary URL like `tdwp-import-website.vercel.app`

### Deploy to Production
```bash
npx vercel --prod
```

## Step 7: Configure Custom Domain 🌍

1. Go to Vercel dashboard
2. Click on your project
3. Go to "Settings" → "Domains"
4. Add domain: `tdwpimport.com`
5. Add DNS records shown by Vercel

## 🎯 What You Get

### Pages
- **Homepage** - Complete marketing site
- **All sections** - Hero, Features, How It Works, Benefits, Testimonials, Pricing, FAQ, CTA

### Features
- 📱 **Mobile Responsive** - Works on all devices
- ⚡ **Lightning Fast** - Next.js optimization
- 🎨 **Brand Aligned** - Matches plugin colors
- 🔍 **SEO Ready** - Meta tags, Open Graph
- ♿ **Accessible** - WCAG AA compliant
- 🚀 **Performance** - Lighthouse optimized

### Components
- `Header` - Navigation with mobile menu
- `Footer` - Links and social icons
- `Hero` - Eye-catching headline and CTA
- `Features` - 12 feature cards
- `HowItWorks` - 3-step process
- `Benefits` - 6 benefits with stats
- `Testimonials` - User reviews
- `Pricing` - Free plan + support options
- `FAQ` - 10 questions answered
- `CTA` - Download buttons

## 📊 Verify Everything Works

### Checklist
- [ ] Site loads at http://localhost:3000
- [ ] All sections visible and styled
- [ ] Mobile menu works (resize browser)
- [ ] All links clickable (may go to #)
- [ ] No console errors
- [ ] Images load (if added)
- [ ] Smooth scrolling works
- [ ] Build completes without errors

### Test Build
```bash
npm run build
npm start
# Visit http://localhost:3000
```

Should see:
```
✓ Compiled successfully
✓ Generating static pages (8/8)
✓ Finalizing page optimization
```

## 🛠️ Common Issues

### Port Already in Use
```bash
# Kill process on port 3000
npx kill-port 3000

# Or use different port
PORT=3001 npm run dev
```

### Module Not Found
```bash
# Clean install
rm -rf node_modules package-lock.json
npm install
```

### Build Errors
```bash
# Check TypeScript
npx tsc --noEmit

# Clear cache
rm -rf .next
npm run build
```

## 📚 Next Steps

1. **Customize Content** - Update copy to match your needs
2. **Add Images** - Replace placeholder with real screenshots
3. **Set Up Analytics** - Add Google Analytics ID
4. **Configure SEO** - Update metadata in `app/layout.tsx`
5. **Deploy** - Push to production with Vercel
6. **Monitor** - Track performance and conversions

## 🆘 Need Help?

- **Next.js Issues**: [nextjs.org/docs](https://nextjs.org/docs)
- **Deployment**: See `DEPLOYMENT.md`
- **Full Guide**: See `README.md`
- **Summary**: See `WEBSITE_SUMMARY.md`

## 🎉 You're All Set!

Your professional marketing website is ready to convert visitors into plugin users.

**Remember**: The website is optimized for:
- 90% time savings messaging
- Clear value proposition
- Multiple CTAs
- Social proof
- SEO ranking
- Fast performance

**Happy Launching! 🚀**
