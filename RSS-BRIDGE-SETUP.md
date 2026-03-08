# RSS Bridge Setup for Curator Bot

This folder contains the configuration to deploy RSS Bridge with a custom Creator Spotlight bridge to Render.com.

## Files Created

- `Dockerfile` - Docker configuration for RSS Bridge
- `render.yaml` - Render.com deployment configuration
- `bridges/CreatorSpotlightBridge.php` - Custom bridge for Creator Spotlight profiles

## Deployment Steps

### 1. Initialize Git Repository (if not already done)

```bash
git init
git add .
git commit -m "Add RSS Bridge setup"
```

### 2. Push to GitHub

Create a new repository on GitHub, then:

```bash
git remote add origin https://github.com/YOUR-USERNAME/curator-bot-rss-bridge.git
git branch -M main
git push -u origin main
```

### 3. Deploy to Render.com

1. Go to https://render.com and sign up/login
2. Click "New +" → "Web Service"
3. Connect your GitHub repository
4. Render will auto-detect the `render.yaml` configuration
5. Click "Apply" to deploy

Your RSS Bridge will be deployed at: `https://rss-bridge-XXXX.onrender.com`

### 4. Access Your Custom Bridge

Once deployed, visit:
```
https://your-app-name.onrender.com/?action=display&bridge=CreatorSpotlight&format=Atom
```

This will generate an RSS feed from Creator Spotlight profiles!

### 5. Add to Make.com Scenario

Copy the feed URL and add it to your existing RSS feed array in the Make scenario.

## Alternative: Deploy from Render Dashboard

1. Go to https://dashboard.render.com
2. Click "New +" → "Web Service"
3. Choose "Deploy from Git repository"
4. Connect this repository
5. Render will automatically use the `render.yaml` configuration

## Customizing the Bridge

Edit `bridges/CreatorSpotlightBridge.php` to adjust:
- Which elements to scrape
- How many items to return (currently 20)
- Cache timeout (currently 1 hour)

After making changes:
```bash
git add bridges/CreatorSpotlightBridge.php
git commit -m "Update bridge configuration"
git push
```

Render will automatically redeploy!

## Testing Locally (Optional)

If you have Docker installed:

```bash
docker build -t rss-bridge .
docker run -p 8080:80 rss-bridge
```

Visit `http://localhost:8080` to test locally.

## Troubleshooting

- **Bridge not showing up**: Check logs in Render dashboard
- **No items returned**: The site may be JavaScript-heavy; consider using a different scraping approach
- **Render free tier sleeping**: First request may be slow (service wakes up automatically)

## Notes

- Render free tier services sleep after 15 minutes of inactivity
- First request after sleeping takes ~30 seconds
- This is fine for a weekly curation bot
- RSS Bridge caches results for 1 hour by default
