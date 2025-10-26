# Static HTML Deployment Guide for Cybrancee

Simple deployment guide for the static HTML export version of TD WP Import website.

## Overview

This site uses Next.js static export - pure HTML/CSS/JavaScript files that work on any hosting. No Node.js or PHP processing required.

**Deployment Path**: `/tdwpimport` subfolder
**Full URL**: `https://nikielhard.se/tdwpimport`

## 🚀 Quick Deployment (5 Minutes)

### Step 1: Build Static Site (if not already done)

```bash
cd /Users/hkh/dev/tdwpimport/tdwp-website-cybrancee
npm install
npm run build
```

This creates the `out/` directory with all static files.

### Step 2: Upload to Cybrancee

Upload **contents** of `out/` directory to `/httpdocs/tdwpimport/` on your server.

**Files to upload:**
```
out/
├── index.html           (homepage)
├── 404.html             (error page)
├── index.txt            (metadata)
├── .htaccess            (Apache config)
├── _next/               (JS/CSS bundles)
└── assets/              (images)
```

### Step 3: Upload Methods

#### Option A: FTP/SFTP (Recommended)

1. Connect to Cybrancee via FTP/SFTP
2. Navigate to `/httpdocs/`
3. Create `tdwpimport` directory if it doesn't exist
4. Upload all files from `out/` into `/httpdocs/tdwpimport/`
5. Ensure `.htaccess` is uploaded (hidden file)

#### Option B: Plesk File Manager

1. Log into Plesk control panel
2. Go to File Manager
3. Navigate to `/httpdocs/`
4. Create `tdwpimport` folder
5. Upload files using Plesk upload feature
6. Or upload as ZIP and extract

#### Option C: Command Line (if SSH available)

```bash
# On your local machine, create archive
cd /Users/hkh/dev/tdwpimport/tdwp-website-cybrancee
tar -czf tdwpimport-static.tar.gz -C out/ .

# Upload to server
scp tdwpimport-static.tar.gz user@server:/httpdocs/tdwpimport/

# SSH into server and extract
ssh user@server
cd /httpdocs/tdwpimport
tar -xzf tdwpimport-static.tar.gz
rm tdwpimport-static.tar.gz
```

### Step 4: Verify Deployment

Visit: `https://nikielhard.se/tdwpimport`

**Check:**
- ✅ Homepage loads correctly
- ✅ Images display properly
- ✅ Navigation works (hash links like #features)
- ✅ Styles applied correctly
- ✅ Mobile responsive

## 📋 File Checklist

**Required files in `/httpdocs/tdwpimport/`:**
- ✅ `index.html` (homepage)
- ✅ `404.html` (error page)
- ✅ `.htaccess` (Apache config - **important!**)
- ✅ `_next/` directory (JavaScript/CSS bundles)
- ✅ `assets/` directory (images)
- ✅ `index.txt` (optional metadata)

**Total size**: ~1.5 MB

## 🔧 Server Requirements

**Minimal requirements:**
- Apache web server (or nginx)
- `.htaccess` support (mod_rewrite)
- No Node.js needed ✓
- No PHP processing needed ✓
- No database needed ✓

**Recommended Apache modules:**
- mod_rewrite (URL routing)
- mod_deflate (compression)
- mod_expires (caching)
- mod_headers (security headers)

## 🌐 How It Works

1. **Static Pre-rendering**: All pages pre-built at compile time
2. **Client-side Routing**: React handles navigation via JavaScript
3. **No Server Runtime**: Just serves HTML/CSS/JS files
4. **Apache Routing**: `.htaccess` routes all requests to index.html
5. **React Hydration**: JavaScript makes page interactive

## 🔄 Updating the Site

### Update Process

1. **Make changes** to source code locally
2. **Rebuild**:
   ```bash
   npm run build
   ```
3. **Upload** new `out/` contents to server
4. **Clear browser cache** or use hard refresh (Cmd+Shift+R / Ctrl+Shift+R)

### What to Upload When Updating

**Full update** (content or style changes):
- Upload entire `out/` directory

**Quick update** (content only):
- Upload `index.html`
- Upload `_next/static/` directory

## 🐛 Troubleshooting

### 404 Errors / Page Not Found

**Problem**: Links don't work, getting 404 errors

**Solutions:**
1. Check `.htaccess` file uploaded
2. Verify mod_rewrite enabled in Apache
3. Check file permissions (644 for files, 755 for directories)
4. Ensure RewriteBase matches your path: `/tdwpimport/`

### Images Not Loading

**Problem**: Broken image links

**Solutions:**
1. Verify `assets/` directory uploaded completely
2. Check file paths in browser console
3. Confirm `assetPrefix: '/tdwpimport'` in next.config.js
4. Check file permissions on images

### Styles Not Applying

**Problem**: Page looks unstyled

**Solutions:**
1. Verify `_next/` directory uploaded completely
2. Check browser console for CSS loading errors
3. Clear browser cache
4. Verify `.htaccess` allows CSS file serving

### Blank Page

**Problem**: White screen, nothing displays

**Solutions:**
1. Open browser console - check for JavaScript errors
2. Verify all files in `_next/static/chunks/` uploaded
3. Check file permissions
4. Try hard refresh (Cmd+Shift+R)

### Hash Links Not Working (#features, #pricing)

**Problem**: Clicking #features doesn't scroll

**Solution**:
- This should work automatically
- Check JavaScript loaded (browser console)
- Verify React hydration completed

## 🔒 Security Notes

The `.htaccess` file includes:
- Security headers (X-Frame-Options, etc.)
- Directory browsing disabled
- File type restrictions

**Additional security** (optional):
- Add SSL certificate in Plesk (Let's Encrypt free)
- Enable HTTPS redirect in `.htaccess`
- Add Content Security Policy headers

## ⚡ Performance Optimization

### Already Optimized

- ✅ Static pre-rendering (fast first paint)
- ✅ Code splitting (smaller bundles)
- ✅ Image optimization disabled (better for hosting)
- ✅ Compression via .htaccess
- ✅ Browser caching configured

### Additional Optimization

**Enable Gzip** (if not working):
```apache
# Add to .htaccess
<IfModule mod_deflate.c>
  AddOutputFilterByType DEFLATE text/html text/css application/javascript
</IfModule>
```

**Leverage Browser Caching**:
Already configured in `.htaccess` - 1 year for images, 1 month for CSS/JS

**CDN** (optional):
- Use Cloudflare for free CDN
- Add domain to Cloudflare
- Enable caching and minification

## 📊 Monitoring

### Check Site Health

**Tools:**
- Google PageSpeed Insights: https://pagespeed.web.dev/
- GTmetrix: https://gtmetrix.com/
- WebPageTest: https://www.webpagetest.org/

**Monitor:**
- Page load times
- Resource loading
- Mobile performance
- Core Web Vitals

### Server Monitoring

**In Plesk:**
- Check bandwidth usage
- Monitor disk space
- Review access logs
- Check error logs

## 🆚 Comparison: Static vs Node.js

| Feature | Static Export | Node.js |
|---------|--------------|---------|
| Server Runtime | None needed | Requires Node.js |
| Hosting Compatibility | Any server | Specific setup |
| Performance | Fast (pre-rendered) | Fast (dynamic) |
| Deployment | Simple FTP | Complex config |
| Updates | Re-build & upload | Code deploy + restart |
| Cost | Cheapest | More expensive |
| Dynamic Features | Client-side only | Server + client |

**For this marketing site**: Static export is perfect! ✓

## 📁 Directory Structure on Server

```
/httpdocs/
└── tdwpimport/
    ├── index.html              # Homepage
    ├── 404.html                # Error page
    ├── .htaccess               # Apache config
    ├── index.txt               # Metadata
    ├── _next/                  # Build assets
    │   ├── static/
    │   │   ├── chunks/         # JavaScript bundles
    │   │   ├── css/            # Stylesheets
    │   │   └── media/          # Fonts, etc.
    │   └── ...
    └── assets/                 # Public assets
        └── images/
            └── banner.png
```

## 💡 Tips

1. **Test locally first**: Use `npx serve out` to test static export
2. **Version control**: Keep build artifacts in git (or document build process)
3. **Backup**: Keep copy of `out/` directory before updates
4. **Cache busting**: Next.js auto-adds hashes to filenames
5. **Monitor**: Check site after deployment

## 🔗 Resources

- **Next.js Static Export**: https://nextjs.org/docs/app/building-your-application/deploying/static-exports
- **Apache mod_rewrite**: https://httpd.apache.org/docs/current/mod/mod_rewrite.html
- **Cybrancee Support**: https://cybrancee.com/support

## 📞 Support

**Website deployment issues:**
- Check this guide's troubleshooting section
- Contact Cybrancee support for hosting questions
- Check Apache error logs in Plesk

**Plugin questions:**
- WordPress.org support forum
- GitHub repository issues

---

**🎉 Ready to Deploy!** Just upload the `out/` directory contents and you're live.
