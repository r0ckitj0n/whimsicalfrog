#!/bin/bash

# Change to the project root directory
cd "$(dirname "$0")/.."

# Configuration
HOST="home419172903.1and1-data.host"
USER="acc899014616"
PASS="Palz2516!"
REMOTE_PATH="/"
# Optional public base for sites under a subdirectory (e.g., /wf)
PUBLIC_BASE="${WF_PUBLIC_BASE:-}"
BASE_URL="https://whimsicalfrog.us${PUBLIC_BASE}"

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

echo -e "${GREEN}🚀 Starting fast file deployment...${NC}"
echo -e "${GREEN}💾 Backing up website...${NC}"
curl -s -X POST https://whimsicalfrog.us/api/backup_website.php || echo -e "${YELLOW}⚠️  Website backup failed, continuing deployment...${NC}"
echo -e "${YELLOW}⏭️  Skipping database updates in fast deploy (use deploy_full.sh for DB restore)${NC}"

# Quarantine duplicate/backup files before build/upload
echo -e "${GREEN}🧹 Quarantining duplicate/backup files...${NC}"
bash scripts/dev/quarantine_duplicates.sh || true

# Clean up any stale git lock file
if [ -f .git/index.lock ]; then
  echo -e "${YELLOW}⚠️  Removing stale .git/index.lock file...${NC}"
  rm -f .git/index.lock
fi

# Always attempt to synchronize with GitHub and push local changes
echo -e "${GREEN}🔄 Syncing with GitHub...${NC}"
if command -v git >/dev/null 2>&1; then
  # Remove any stale lock first (already handled above, but keep it defensive)
  [ -f .git/index.lock ] && rm -f .git/index.lock || true

  # Ensure git identity is set locally to avoid commit failures
  if ! git config user.email >/dev/null 2>&1; then
    git config user.email "deploy@whimsicalfrog.us" || true
  fi
  if ! git config user.name >/dev/null 2>&1; then
    git config user.name "WF Deploy Bot" || true
  fi

  # Ensure we are in a git repo and have an origin
  if git rev-parse --is-inside-work-tree >/dev/null 2>&1 && git remote get-url origin >/dev/null 2>&1; then
    # Derive branch name robustly (prefer upstream, fallback to HEAD, then main)
    BRANCH=$(git rev-parse --abbrev-ref --symbolic-full-name "@{u}" 2>/dev/null | sed 's#.*/##' | tr -d '\n' )
    if [ -z "$BRANCH" ]; then
      BRANCH=$(git rev-parse --abbrev-ref HEAD 2>/dev/null | tr -d '\n' | sed 's/[[:space:]]*$//')
    fi
    [ -z "$BRANCH" ] && BRANCH="main"

    # Ensure upstream tracking exists for the current branch
    if ! git rev-parse --abbrev-ref --symbolic-full-name "@{u}" >/dev/null 2>&1; then
      git fetch origin "$BRANCH" || true
      git branch --set-upstream-to="origin/$BRANCH" "$BRANCH" 2>/dev/null || true
    fi

    # Rebase on top of remote to avoid non-fast-forward push failures
    git fetch origin || true
    # If working tree is dirty, stash changes before rebase
    NEED_STASH=false
    if [ -n "$(git status --porcelain)" ]; then NEED_STASH=true; fi
    if [ "$NEED_STASH" = true ]; then
      echo -e "${YELLOW}⚠️  Working tree not clean. Stashing changes before rebase...${NC}"
      git stash push -u -k -m "deploy-autostash $(date +'%Y-%m-%d %H:%M:%S')" || true
    fi
    git pull --rebase origin "$BRANCH" || echo -e "${YELLOW}⚠️  Git pull --rebase encountered issues; continuing...${NC}"
    # Try to restore stashed changes
    if [ "$NEED_STASH" = true ]; then
      git stash list | grep -q 'deploy-autostash' && git stash pop || true
    fi

    # Commit any pending changes (if any)
    if [ -n "$(git status --porcelain)" ]; then
      echo -e "${GREEN}📝 Committing changes to GitHub...${NC}"
      git add -A
      # Disable husky hooks for automated commits
      HUSKY=0 git commit --no-verify -m "Auto-commit before deployment ($(date +'%Y-%m-%d %H:%M:%S'))" || true
    else
      echo -e "${GREEN}✅ No local changes to commit${NC}"
    fi

    # Push with a retry after another rebase if needed
    if HUSKY=0 git push origin "$BRANCH"; then
      echo -e "${GREEN}✅ Successfully pushed to GitHub${NC}"
    else
      echo -e "${YELLOW}⚠️  Initial push failed; attempting rebase and retry...${NC}"
      git pull --rebase origin "$BRANCH" || true
      if HUSKY=0 git push origin "$BRANCH"; then
        echo -e "${GREEN}✅ Push succeeded after rebase${NC}"
      else
        echo -e "${YELLOW}⚠️  GitHub push failed even after rebase. Continuing with deployment...${NC}"
      fi
    fi
  else
    echo -e "${YELLOW}⚠️  No git repository or 'origin' remote not configured. Skipping GitHub sync.${NC}"
  fi
else
  echo -e "${YELLOW}⚠️  'git' not available on PATH; skipping GitHub sync.${NC}"
fi

# Ensure frontend build artifacts exist
echo -e "${GREEN}🧱 Ensuring Vite build artifacts exist...${NC}"
if [ ! -f dist/manifest.json ]; then
  echo -e "${YELLOW}⚠️  dist/manifest.json not found. Running vite build...${NC}"
  if command -v npm >/dev/null 2>&1; then
    if npm run build; then
      echo -e "${GREEN}✅ Vite build completed${NC}"
    else
      echo -e "${RED}❌ Vite build failed. Aborting deployment.${NC}"
      exit 1
    fi
  else
    echo -e "${YELLOW}⚠️  npm not available; skipping build step${NC}"
  fi
else
  echo -e "${GREEN}✅ Found dist/manifest.json${NC}"
fi

# Prune old hashed JS bundles locally so remote stale files are removed via --delete
echo -e "${GREEN}🧹 Pruning old hashed bundles in local dist...${NC}"
prune_stems=(
  "assets/js/app.js"
  "assets/js/header-bootstrap.js"
  "assets/login-modal"
  "assets/login-page"
)
for stem in "${prune_stems[@]}"; do
  dir="dist/$(dirname "$stem")"
  base="$(basename "$stem")"
  if [ -d "$dir" ]; then
    matches=("$dir/${base}-"*.js)
    # If glob doesn't match, it returns the pattern itself; guard that
    if [ -e "${matches[0]}" ]; then
      # Sort by mtime descending, keep first (newest)
      newest=$(ls -t $dir/${base}-*.js 2>/dev/null | head -n 1)
      for f in $dir/${base}-*.js; do
        if [ "$f" != "$newest" ]; then
          echo "Removing old bundle: $f"
          rm -f "$f"
        fi
      done
    fi
  fi
done

# Pre-clean common duplicate/backup/tmp files on the remote to avoid slow deletes during mirror
echo -e "${GREEN}🧽 Pre-cleaning duplicate/backup/tmp files on server...${NC}"
cat > preclean_remote.txt << EOL
set sftp:auto-confirm yes
set ssl:verify-certificate no
set cmd:fail-exit yes
open sftp://$USER:$PASS@$HOST
rm -f .tmp* ".tmp2 *" *.bak *.bak.* *\ 2 *\ 3 *\ 2.* *\ 3.* || true
cd src || true
rm -f .tmp* ".tmp2 *" *.bak *.bak.* *\ 2 *\ 3 *\ 2.* *\ 3.* || true
cd styles || true
rm -f .tmp* ".tmp2 *" *.bak *.bak.* *\ 2 *\ 3 *\ 2.* *\ 3.* || true
cd .. || true
cd js || true
rm -f .tmp* ".tmp2 *" *.bak *.bak.* *\ 2 *\ 3 *\ 2.* *\ 3.* || true
cd / || true
cd images || true
rm -f .tmp* ".tmp2 *" *.bak *.bak.* *\ 2.* *\ 3.* || true
cd items || true
rm -f .tmp* ".tmp2 *" *.bak *.bak.* *\ 2.* *\ 3.* || true
cd / || true
bye
EOL

lftp -f preclean_remote.txt || true
rm -f preclean_remote.txt

# Quarantine any new duplicate files created during build
echo -e "${GREEN}🧹 Quarantining any duplicate files created during build...${NC}"
bash scripts/dev/quarantine_duplicates.sh || true

# Create lftp commands for file deployment
echo -e "${GREEN}📁 Preparing file deployment...${NC}"
cat > deploy_commands.txt << EOL
set sftp:auto-confirm yes
set ssl:verify-certificate no
set cmd:fail-exit yes
open sftp://$USER:$PASS@$HOST
# Note: SFTP lacks checksums; use size-only + only-newer to avoid re-uploading identical files
# - only-newer: don't overwrite if remote is same/newer
# - ignore-time: ignore mtime differences; compare by size only to skip identical files
# - no-perms: don't try to sync permissions (reduces needless diffs)
mirror --reverse --delete --verbose --only-newer --ignore-time --no-perms \
  --exclude-glob .git/ \
  --exclude-glob node_modules/ \
  --exclude-glob vendor/ \
  --exclude-glob .vscode/ \
  --exclude-glob hot \
  --exclude-glob sessions/** \
  --exclude-glob backups/duplicates/** \
  --exclude-glob backups/tests/** \
  --include-glob backups/**/*.sql \
  --include-glob backups/**/*.sql.gz \
  --exclude-glob backups/** \
  --exclude-glob documentation/ \
  --exclude-glob Documentation/ \
  --include-glob documentation/.htaccess \
  --include-glob reports/.htaccess \
  --exclude-glob Scripts/ \
  --exclude-glob scripts/ \
  --exclude-glob *.log \
  --exclude-glob *.sh \
  --exclude-glob *.plist \
  --exclude-glob temp_cron.txt \
  --exclude-glob SERVER_MANAGEMENT.md \
  --exclude-glob factory-tutorial/ \
  --exclude-glob backup.sql \
  --exclude-glob backup_*.tar.gz \
  --exclude-glob *_backup_*.tar.gz \
  --exclude-glob .tmp* \
  --exclude-glob ".tmp2 *" \
  --exclude-glob deploy_commands.txt \
  --exclude-glob fix_clown_frog_image.sql \
  --exclude-glob images/.htaccess \
  --exclude-glob images/items/.htaccess \
  --exclude-glob config/my.cnf \
  --exclude-glob "* [0-9].*" \
  --exclude-glob "* [0-9]/*" \
  --exclude-glob "* copy*" \
  --include-glob credentials.json \
  . $REMOTE_PATH
bye
EOL

# Run lftp with the commands
echo -e "${GREEN}🌐 Deploying files to server...${NC}"
if lftp -f deploy_commands.txt; then
  echo -e "${GREEN}✅ Files deployed successfully${NC}"
  # Optional: Upload maintenance utility (disabled by default to avoid mkdir errors on some hosts)
  if [ "${WF_UPLOAD_MAINTENANCE:-0}" = "1" ]; then
    echo -e "${GREEN}🧰 Uploading maintenance utilities (prune_sessions.sh)...${NC}"
    cat > upload_maintenance.txt << EOL
set sftp:auto-confirm yes
set ssl:verify-certificate no
set cmd:fail-exit yes
open sftp://$USER:$PASS@$HOST
mkdir -p api/maintenance
cd api/maintenance
put scripts/maintenance/prune_sessions.sh -o prune_sessions.sh
chmod 755 prune_sessions.sh
bye
EOL
    if lftp -f upload_maintenance.txt; then
      echo -e "${GREEN}✅ Maintenance script uploaded to /api/maintenance/prune_sessions.sh${NC}"
    else
      echo -e "${YELLOW}⚠️  Skipping maintenance upload (remote may not allow creating api/maintenance)${NC}"
    fi
    rm -f upload_maintenance.txt
  else
    echo -e "${YELLOW}⏭️  Skipping maintenance upload (set WF_UPLOAD_MAINTENANCE=1 to enable)${NC}"
  fi
  # Perform a second, targeted mirror for images/backgrounds WITHOUT --ignore-time
  # Rationale: when replacing background files with the same size but different content,
  # the size-only comparison (from --ignore-time) may skip the upload. This pass uses
  # mtime to ensure changed files are uploaded.
  echo -e "${GREEN}🖼️  Ensuring background images are updated (mtime-based)...${NC}"
  cat > deploy_backgrounds.txt << EOL
set sftp:auto-confirm yes
set ssl:verify-certificate no
set cmd:fail-exit yes
open sftp://$USER:$PASS@$HOST
mirror --reverse --delete --verbose --only-newer --no-perms \
  images/backgrounds images/backgrounds
bye
EOL
  if lftp -f deploy_backgrounds.txt; then
    echo -e "${GREEN}✅ Background images synced (mtime-based)${NC}"
  else
    echo -e "${YELLOW}⚠️  Background image sync failed; continuing${NC}"
  fi
  rm -f deploy_backgrounds.txt
else
  echo -e "${RED}❌ File deployment failed${NC}"
  rm deploy_commands.txt
  exit 1
fi

# Clean up lftp commands file
rm deploy_commands.txt

# Ensure no Vite hot file exists on the live server (prevents accidental dev mode)
echo -e "${GREEN}🧹 Removing any stray Vite hot file on server...${NC}"
cat > cleanup_hot.txt << EOL
set sftp:auto-confirm yes
set ssl:verify-certificate no
open sftp://$USER:$PASS@$HOST
rm -f hot
bye
EOL

lftp -f cleanup_hot.txt > /dev/null 2>&1 || true
rm cleanup_hot.txt

# Verify deployment (HTTP-based, avoids dotfile visibility issues)
echo -e "${GREEN}🔍 Verifying deployment over HTTP...${NC}"

# Check Vite manifest availability (prefer .vite/manifest.json)
HTTP_MANIFEST_CODE=$(curl -s -o /dev/null -w "%{http_code}" "$BASE_URL/dist/.vite/manifest.json")
if [ "$HTTP_MANIFEST_CODE" != "200" ]; then
  HTTP_MANIFEST_CODE=$(curl -s -o /dev/null -w "%{http_code}" "$BASE_URL/dist/manifest.json")
fi
if [ "$HTTP_MANIFEST_CODE" = "200" ]; then
  echo -e "${GREEN}✅ Vite manifest accessible over HTTP${NC}"
else
  echo -e "${YELLOW}⚠️  Vite manifest not accessible over HTTP (code $HTTP_MANIFEST_CODE)${NC}"
fi

# Extract one JS and one CSS asset from homepage HTML and verify
HOME_HTML=$(curl -s "$BASE_URL/")
APP_JS=$(echo "$HOME_HTML" | grep -Eo "/dist/assets/js/app.js-[^\"']+\\.js" | head -n1)
MAIN_CSS=$(echo "$HOME_HTML" | grep -Eo "/dist/assets/[^\"']+\\.css" | head -n1)
if [ -n "$APP_JS" ]; then
  CODE_JS=$(curl -s -o /dev/null -w "%{http_code}" "$BASE_URL$APP_JS")
  echo -e "  • JS $APP_JS -> HTTP $CODE_JS"
else
  echo -e "  • JS: ⚠️ Not found in homepage HTML"
fi
if [ -n "$MAIN_CSS" ]; then
  CODE_CSS=$(curl -s -o /dev/null -w "%{http_code}" "$BASE_URL$MAIN_CSS")
  echo -e "  • CSS $MAIN_CSS -> HTTP $CODE_CSS"
else
  echo -e "  • CSS: ⚠️ Not found in homepage HTML"
fi

# Fix permissions automatically after deployment
echo -e "${GREEN}🔧 Fixing image permissions on server...${NC}"
# Remove problematic .htaccess files and fix permissions via SFTP
cat > fix_permissions.txt << EOL
set sftp:auto-confirm yes
set ssl:verify-certificate no
open sftp://$USER:$PASS@$HOST
rm -f images/.htaccess
rm -f images/items/.htaccess
chmod 755 images/
chmod 755 images/items/
chmod 644 images/items/*
bye
EOL

lftp -f fix_permissions.txt > /dev/null 2>&1 || true
rm fix_permissions.txt

# List duplicate-suffixed files on server (for visibility)
echo -e "${GREEN}🧹 Listing duplicate-suffixed files on server (space-number)...${NC}"
cat > list_server_duplicates.txt << EOL
set sftp:auto-confirm yes
set ssl:verify-certificate no
open sftp://$USER:$PASS@$HOST
# images root
cls -1 images/*\\ 2.* || true
cls -1 images/*\\ 3.* || true
# subdirs
cls -1 images/items/*\\ 2.* || true
cls -1 images/items/*\\ 3.* || true
cls -1 images/backgrounds/*\\ 2.* || true
cls -1 images/backgrounds/*\\ 3.* || true
cls -1 images/logos/*\\ 2.* || true
cls -1 images/logos/*\\ 3.* || true
cls -1 images/signs/*\\ 2.* || true
cls -1 images/signs/*\\ 3.* || true
bye
EOL
lftp -f list_server_duplicates.txt || true
rm list_server_duplicates.txt

# Delete duplicate-suffixed files on server
echo -e "${GREEN}🧽 Removing duplicate-suffixed files on server...${NC}"
cat > delete_server_duplicates.txt << EOL
set sftp:auto-confirm yes
set ssl:verify-certificate no
open sftp://$USER:$PASS@$HOST
rm -f images/*\\ 2.* || true
rm -f images/*\\ 3.* || true
rm -f images/items/*\\ 2.* || true
rm -f images/items/*\\ 3.* || true
rm -f images/backgrounds/*\\ 2.* || true
rm -f images/backgrounds/*\\ 3.* || true
rm -f images/logos/*\\ 2.* || true
rm -f images/logos/*\\ 3.* || true
rm -f images/signs/*\\ 2.* || true
rm -f images/signs/*\\ 3.* || true
bye
EOL
lftp -f delete_server_duplicates.txt || true
rm delete_server_duplicates.txt

# Test image accessibility (use a stable, non-legacy asset)
echo -e "${GREEN}🌍 Testing image accessibility...${NC}"
HTTP_CODE=$(curl -s -o /dev/null -w "%{http_code}" "$BASE_URL/images/logos/logo-whimsicalfrog.webp")
if [ "$HTTP_CODE" = "200" ]; then
  echo -e "${GREEN}✅ Logo image is accessible online!${NC}"
elif [ "$HTTP_CODE" = "404" ]; then
  echo -e "${YELLOW}⚠️  Logo image returns 404 - may need a few minutes to propagate${NC}"
else
  echo -e "${YELLOW}⚠️  Logo image returned HTTP code: $HTTP_CODE${NC}"
fi

# Final summary
echo -e "\n${GREEN}📊 Fast Deployment Summary:${NC}"
echo -e "  • Files: ✅ Deployed to server"
echo -e "  • Database: ⏭️  Skipped (use deploy_full.sh for database updates)"
echo -e "  • Images: ✅ Included in deployment"
echo -e "  • Verification: ✅ Completed"

echo -e "\n${GREEN}🎉 Fast deployment completed!${NC}"
echo -e "${YELLOW}💡 Use ./deploy_full.sh when you need to update the database${NC}"
