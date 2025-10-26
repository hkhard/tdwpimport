# Deploy to Cybrancee - Static HTML

Ultra-simple deployment for PHP/Apache hosting.

## What You Get

Pure HTML/CSS/JavaScript files. No Node.js or PHP needed on server.

## 3-Step Deployment

### 1. Build (if not done)
```bash
npm install
npm run build
```
Creates `out/` directory.

### 2. Upload
Upload **contents of `out/` directory** to:
```
/httpdocs/tdwpimport/
```

Via FTP, SFTP, or Plesk File Manager.

### 3. Visit
```
https://nikielhard.se/tdwpimport
```

## Files to Upload

From `out/` directory:
- `index.html` ✓
- `404.html` ✓
- `.htaccess` ✓ (important!)
- `_next/` folder ✓
- `assets/` folder ✓
- `index.txt` ✓

Total: ~1.5 MB

## Requirements

- Apache with mod_rewrite
- That's it!

## Troubleshooting

**404 errors**: Upload `.htaccess` file
**No images**: Upload `assets/` folder
**No styles**: Upload `_next/` folder

See **STATIC_DEPLOYMENT.md** for detailed guide.

---

Done! 🎉
