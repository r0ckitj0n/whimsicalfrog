#!/bin/bash
# PM2 cheatsheet for WhimsicalFrog Vite dev server (wf-vite)
set -e
cd "$(dirname "$0")/../.."

cat <<'TXT'
WhimsicalFrog PM2 Cheatsheet (wf-vite)
-------------------------------------
Start app:
  npm run pm2:start

Restart app:
  npm run pm2:restart

Stop app:
  npm run pm2:stop

Status:
  npm run pm2:status

Logs (live):
  npm run pm2:logs

Persist process list (after changes):
  npm run pm2:save

Enable startup on login/boot (run once):
  npm run pm2:startup
  # then run the printed sudo command once, and finally:
  npm run pm2:save

Manual health check:
  curl -sI http://localhost:5176/@vite/client
  lsof -nP -iTCP:5176 -sTCP:LISTEN

Troubleshooting:
  - If @vite/client is not 200 OK, check logs:
      npm run pm2:logs
  - Free port 5176 if stuck:
      lsof -t -nP -iTCP:5176 -sTCP:LISTEN | xargs -r kill -9
  - Ensure hot file:
      printf 'http://localhost:5176' > hot
TXT
