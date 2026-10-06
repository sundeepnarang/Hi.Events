---
name: docker-deployment
description: Procedures and runbooks for deploying and updating the HiEvents all-in-one Docker container on production servers
---

# All-in-One Docker Server Deployment

Guidelines and runbooks for updating the production server running the `all-in-one` Docker container.

## Standard Update Workflow

When deploying new commits from `SoSChangesV2`:

```bash
# 1. Fetch and pull latest changes
git pull origin SoSChangesV2

# 2. Rebuild with --no-cache to prevent Docker layer cache from serving stale frontend builds
docker compose --env-file .env build --no-cache all-in-one

# 3. Recreate and restart containers in background
docker compose --env-file .env up -d --force-recreate all-in-one

# 4. Follow startup logs to verify migrations and startup scripts completed
docker compose logs -f all-in-one
```

## Why Changes Sometimes Do Not Appear

1. **Docker Multi-Stage Build Cache:**
   - In `Dockerfile.all-in-one`, `yarn build` runs in the `node-frontend` stage.
   - If Docker caches this layer, `--build` alone will not compile new frontend changes.
   - **Fix:** Always include `--no-cache` on `docker compose build`.

2. **Browser Asset Caching:**
   - Browser caching can serve older JS/CSS bundles.
   - **Fix:** Perform a hard refresh (`Cmd+Shift+R` or `Ctrl+F5`) or test in an Incognito window.

3. **Local Server Git Conflicts:**
   - If server-specific changes exist in `.env` or `docker-compose.yml`, keep them unstaged or stashed:
   ```bash
   git stash
   git pull origin SoSChangesV2
   git stash pop
   ```
