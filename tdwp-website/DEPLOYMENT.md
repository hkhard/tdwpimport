# Deployment Guide - TD WP Import Website

Complete guide for deploying the TD WP Import marketing website.

## 📋 Pre-Deployment Checklist

### Content Review
- [ ] All copy is accurate and up-to-date
- [ ] Plugin version number is current (v2.6.6)
- [ ] All links are tested and working
- [ ] Images are optimized (<200KB each)
- [ ] Contact information is correct

### Technical Review
- [ ] No TypeScript errors (`npx tsc --noEmit`)
- [ ] No console errors in browser
- [ ] Mobile responsive on all breakpoints
- [ ] All CTAs functional
- [ ] Forms submit successfully
- [ ] Analytics configured

### SEO Review
- [ ] Meta titles (<60 characters)
- [ ] Meta descriptions (<160 characters)
- [ ] Open Graph images present
- [ ] Sitemap generated
- [ ] Robots.txt configured

## 🚀 Deployment Options

### Option 1: Vercel (Recommended)

**Why Vercel?**
- Built by Next.js creators
- Zero-config deployment
- Automatic HTTPS
- Global CDN
- Free hobby plan

**Setup Steps:**

1. **Install Vercel CLI**
```bash
npm i -g vercel
```

2. **Login to Vercel**
```bash
vercel login
```

3. **Deploy to Preview**
```bash
cd /Users/hkh/dev/tdwpimport/tdwp-website
vercel
```

4. **Deploy to Production**
```bash
vercel --prod
```

5. **Configure Domain**
- Go to Vercel dashboard
- Add custom domain: tdwpimport.com
- Add DNS records:
  - A record: 76.76.21.21
  - CNAME: cname.vercel-dns.com

**Environment Variables:**
```
NEXT_PUBLIC_SITE_URL=https://tdwpimport.com
NEXT_PUBLIC_GA_ID=G-XXXXXXXXXX
```

### Option 2: Netlify

**Setup Steps:**

1. **Connect GitHub Repo**
- Push code to GitHub
- Sign in to Netlify
- Click "New site from Git"
- Select your repository

2. **Build Settings**
```
Build command: npm run build
Publish directory: .next
```

3. **Environment Variables**
Add in Netlify dashboard:
```
NEXT_PUBLIC_SITE_URL=https://tdwpimport.com
```

4. **Deploy**
- Click "Deploy site"
- Configure custom domain

### Option 3: Custom VPS (Advanced)

**Requirements:**
- Ubuntu 22.04+ or similar
- Node.js 18+
- Nginx
- PM2

**Setup Steps:**

1. **Server Preparation**
```bash
# Update system
sudo apt update && sudo apt upgrade -y

# Install Node.js 18+
curl -fsSL https://deb.nodesource.com/setup_18.x | sudo -E bash -
sudo apt install -y nodejs

# Install PM2
sudo npm install -g pm2

# Install Nginx
sudo apt install -y nginx
```

2. **Deploy Code**
```bash
# Clone or copy project
cd /var/www
git clone https://github.com/hkhard/tdwpimport.git
cd tdwpimport/tdwp-website

# Install dependencies
npm install

# Build
npm run build
```

3. **Configure PM2**
```bash
# Start app
pm2 start npm --name "tdwp-website" -- start

# Save PM2 config
pm2 save

# Auto-start on reboot
pm2 startup
```

4. **Configure Nginx**
```nginx
# /etc/nginx/sites-available/tdwpimport.com
server {
    listen 80;
    server_name tdwpimport.com www.tdwpimport.com;

    location / {
        proxy_pass http://localhost:3000;
        proxy_http_version 1.1;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection 'upgrade';
        proxy_set_header Host $host;
        proxy_cache_bypass $http_upgrade;
    }
}
```

```bash
# Enable site
sudo ln -s /etc/nginx/sites-available/tdwpimport.com /etc/nginx/sites-enabled/

# Test config
sudo nginx -t

# Restart Nginx
sudo systemctl restart nginx
```

5. **SSL with Let's Encrypt**
```bash
# Install Certbot
sudo apt install -y certbot python3-certbot-nginx

# Get certificate
sudo certbot --nginx -d tdwpimport.com -d www.tdwpimport.com

# Auto-renewal
sudo certbot renew --dry-run
```

## 🔧 Post-Deployment Setup

### 1. DNS Configuration

**For apex domain (tdwpimport.com):**
```
Type: A
Name: @
Value: [Your server IP or Vercel IP]
TTL: 3600
```

**For www subdomain:**
```
Type: CNAME
Name: www
Value: tdwpimport.com
TTL: 3600
```

### 2. Google Analytics Setup

1. Create GA4 property at [analytics.google.com](https://analytics.google.com)
2. Get Measurement ID (G-XXXXXXXXXX)
3. Add to environment variables
4. Deploy updated site

### 3. Search Console Setup

1. Go to [search.google.com/search-console](https://search.google.com/search-console)
2. Add property for tdwpimport.com
3. Verify ownership (DNS or HTML file)
4. Submit sitemap: `https://tdwpimport.com/sitemap.xml`

### 4. Performance Monitoring

**Vercel Analytics** (if using Vercel):
```bash
npm install @vercel/analytics
```

Add to `app/layout.tsx`:
```tsx
import { Analytics } from '@vercel/analytics/react'

export default function RootLayout({ children }) {
  return (
    <html>
      <body>
        {children}
        <Analytics />
      </body>
    </html>
  )
}
```

### 5. Error Tracking

**Sentry** (optional):
```bash
npm install @sentry/nextjs
npx @sentry/wizard -i nextjs
```

## 📊 Monitoring & Maintenance

### Performance Metrics to Track
- Largest Contentful Paint (LCP) < 2.5s
- First Input Delay (FID) < 100ms
- Cumulative Layout Shift (CLS) < 0.1
- Time to First Byte (TTFB) < 600ms

### Monthly Tasks
- [ ] Check Google Analytics
- [ ] Review Search Console errors
- [ ] Update plugin version if changed
- [ ] Check broken links
- [ ] Review load times
- [ ] Monitor uptime

### Quarterly Tasks
- [ ] Update dependencies (`npm update`)
- [ ] Review SEO rankings
- [ ] Refresh testimonials
- [ ] Update screenshots/demos
- [ ] Audit accessibility

## 🔄 CI/CD Setup (Advanced)

### GitHub Actions Deployment

Create `.github/workflows/deploy.yml`:

```yaml
name: Deploy to Vercel

on:
  push:
    branches: [main]

jobs:
  deploy:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v3

      - name: Setup Node.js
        uses: actions/setup-node@v3
        with:
          node-version: '18'

      - name: Install dependencies
        run: npm ci

      - name: Build
        run: npm run build

      - name: Deploy to Vercel
        uses: amondnet/vercel-action@v25
        with:
          vercel-token: ${{ secrets.VERCEL_TOKEN }}
          vercel-org-id: ${{ secrets.VERCEL_ORG_ID }}
          vercel-project-id: ${{ secrets.VERCEL_PROJECT_ID }}
          vercel-args: '--prod'
```

## 🆘 Troubleshooting

### Build Fails
```bash
# Clear cache
rm -rf .next node_modules
npm install
npm run build
```

### Images Not Loading
- Check image paths are correct
- Verify images exist in `/public`
- Check Next.js Image config in `next.config.js`

### Slow Performance
- Enable caching in Vercel/Netlify settings
- Optimize images (use WebP, compress)
- Review bundle size with `npm run build`
- Enable Gzip/Brotli compression

### SEO Not Working
- Verify metadata in `app/layout.tsx`
- Check robots.txt allows crawling
- Submit sitemap to Search Console
- Validate structured data

## 📞 Support

For deployment issues:
- Vercel: [vercel.com/support](https://vercel.com/support)
- Netlify: [netlify.com/support](https://netlify.com/support)
- Next.js: [nextjs.org/docs](https://nextjs.org/docs)

---

**Ready to launch?** Follow the Vercel deployment steps above for the fastest deployment.
