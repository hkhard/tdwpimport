# Plesk Deployment Guide for Cybrancee Hosting

This guide covers deploying the TD WP Import website to Cybrancee hosting using Plesk's Node.js support.

## Prerequisites

- Cybrancee web hosting account with Plesk control panel
- Node.js support enabled in Plesk (v18+ recommended)
- Domain or subdomain configured (e.g., nikielhard.se)
- SSH/FTP access to your hosting account

## Deployment Configuration

**Subdirectory Path**: `/tdwpimport`
**Full URL**: `https://nikielhard.se/tdwpimport`

The site is configured to run in a subfolder, leaving the root domain available for other content.

## Step 1: Enable Node.js in Plesk

1. Log into Plesk control panel at Cybrancee
2. Navigate to your domain (nikielhard.se)
3. Go to **"Hosting & DNS" > "Apache & nginx Settings"** or look for **"Node.js"** option
4. Enable Node.js support
5. Select Node.js version 18.x or higher
6. Click **Apply** or **Save**

## Step 2: Upload Website Files

### Option A: Using FTP/SFTP

1. Connect to your hosting via FTP/SFTP
2. Navigate to your web root (usually `/httpdocs` or `/public_html`)
3. Create directory: `tdwpimport`
4. Upload these files to `/httpdocs/tdwpimport/`:
   - `.next/` directory (entire folder from standalone build)
   - `public/` directory
   - `package.json`
   - `next.config.js`

### Option B: Using Plesk File Manager

1. Open Plesk File Manager
2. Navigate to web root directory
3. Create `tdwpimport` folder
4. Upload files using Plesk's upload feature
5. Extract if uploaded as ZIP

### Option C: Using Git (Recommended)

1. In Plesk, go to **"Git"** section
2. Click **"Clone Repository"**
3. Enter your repository URL
4. Set deployment path to `/httpdocs/tdwpimport`
5. Configure automatic deployment on push (optional)

## Step 3: Configure Node.js Application in Plesk

1. In Plesk, navigate to **"Node.js"** settings for your domain
2. Click **"Enable Node.js"** or **"Add Application"**
3. Configure the application:
   - **Application mode**: Production
   - **Application root**: `/httpdocs/tdwpimport`
   - **Application startup file**: `.next/standalone/server.js`
   - **Node.js version**: 18.x or higher
   - **Custom environment variables** (if needed):
     - `NODE_ENV=production`
     - `PORT=3000` (or as assigned by Plesk)

4. Click **"Enable"** or **"Run"**

## Step 4: Install Dependencies

Via Plesk Node.js interface or SSH:

```bash
cd /httpdocs/tdwpimport
npm install --production
```

Or use Plesk's built-in npm interface:
- Go to Node.js settings
- Click **"NPM Install"** button

## Step 5: Configure Web Server Routing

### Apache Configuration

Add to `.htaccess` in web root (`/httpdocs/.htaccess`):

```apache
# Route /tdwpimport to Node.js application
<IfModule mod_rewrite.c>
  RewriteEngine On
  RewriteBase /

  # Proxy requests to Node.js app running on port 3000
  RewriteCond %{REQUEST_URI} ^/tdwpimport
  RewriteRule ^tdwpimport/(.*)$ http://localhost:3000/tdwpimport/$1 [P,L]
</IfModule>
```

### Nginx Configuration (if using)

Add to nginx configuration:

```nginx
location /tdwpimport {
    proxy_pass http://localhost:3000;
    proxy_http_version 1.1;
    proxy_set_header Upgrade $http_upgrade;
    proxy_set_header Connection 'upgrade';
    proxy_set_header Host $host;
    proxy_cache_bypass $http_upgrade;
    proxy_set_header X-Real-IP $remote_addr;
    proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
}
```

## Step 6: Start the Application

1. In Plesk Node.js settings, click **"Restart App"** or **"Enable Node.js"**
2. Check application status - should show as "Running"
3. View logs if errors occur

## Step 7: Verify Deployment

1. Visit: `https://nikielhard.se/tdwpimport`
2. Verify all pages load correctly
3. Check that images and styles display properly
4. Test navigation between sections

## Troubleshooting

### Application Won't Start

**Check logs in Plesk:**
- Navigate to Node.js settings
- Click "Show Logs" or "Error Log"
- Review error messages

**Common issues:**
- Wrong startup file path - ensure it points to `.next/standalone/server.js`
- Missing dependencies - run `npm install` again
- Port conflicts - check if assigned port is available

### 404 Errors / Routing Issues

**Fix basePath issues:**
- Verify `next.config.js` has `basePath: '/tdwpimport'`
- Check that web server routing is configured correctly
- Ensure all internal links use Next.js `<Link>` component

### Static Assets Not Loading

**Check public folder:**
- Verify `public/` directory was uploaded
- Check that `assetPrefix: '/tdwpimport'` is in `next.config.js`
- Verify image paths in components

### Performance Issues

**Optimize for shared hosting:**
- Enable caching in `.htaccess`
- Reduce Node.js memory if needed
- Use CDN for static assets if available

## Automatic Restarts

Configure app to restart automatically if it crashes:

1. In Plesk Node.js settings
2. Enable **"Restart on failure"**
3. Set **"Restart delay"** (recommended: 10 seconds)

## Updating the Site

1. Upload new files via FTP/Git
2. Run `npm install` if dependencies changed
3. Click **"Restart App"** in Plesk Node.js settings
4. Clear browser cache and test

## Environment Variables (Optional)

Add in Plesk Node.js settings under "Environment Variables":

- `NODE_ENV=production`
- `NEXT_TELEMETRY_DISABLED=1` (disable Next.js telemetry)
- Custom variables as needed

## Monitoring

**Check application health:**
- Plesk Node.js dashboard shows status
- Monitor resource usage in Plesk
- Set up uptime monitoring (external service)

**Log locations:**
- Application logs: Plesk Node.js logs section
- Error logs: Check Plesk error log viewer
- Access logs: Available in Plesk statistics

## Support Resources

- **Cybrancee Support**: https://cybrancee.com/support
- **Plesk Node.js Docs**: https://docs.plesk.com/en-US/obsidian/administrator-guide/website-management/hosting-nodejs-applications.76652/
- **Next.js Deployment**: https://nextjs.org/docs/deployment

## Alternative: Static Export (Simpler)

If Node.js is unavailable or complex, export as static HTML:

```bash
# In next.config.js, remove 'output: standalone' and add:
output: 'export'

# Build
npm run build

# Upload contents of 'out/' directory to /httpdocs/tdwpimport/
```

Note: This removes server-side features but works on any hosting.
