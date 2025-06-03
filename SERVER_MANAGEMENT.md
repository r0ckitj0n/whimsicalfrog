# WhimsicalFrog – Server Management Guide
_(SERVER_MANAGEMENT.md)_

Welcome to the WhimsicalFrog server handbook.  
This document explains everything you need to keep your local development environment healthy and online.

---

## 1 . What Servers Are Running & Why?

| Port | Process | Purpose | Start Command |
|------|---------|---------|---------------|
| **3000** | Node.js (Express) – `server.js` | Serves REST API endpoints and talks to Google Sheets | `npm start` |
| **8000** | PHP built-in server | Serves the website (`index.php`, etc.) | `php -S localhost:8000 -t .` |

These two processes are all you need: one for data, one for pages.

---

## 2 . Starting, Stopping & Restarting

All routine tasks are wrapped in `server_monitor.sh`.

| Action | Command |
|--------|---------|
| **Start both servers** | `./server_monitor.sh start` |
| **Stop both servers** | `./server_monitor.sh stop` |
| **Restart both servers** | `./server_monitor.sh restart` |
| **Quick status check** | `./server_monitor.sh status` |

Shortcut: run `./start_servers.sh` for a one-shot “start & show status” workflow (ideal for double-click).

---

## 3 . Checking Server Status

`./server_monitor.sh status` prints:

- ✅/✗ for API (3000) and PHP (8000)  
- Direct access URLs  
- Cron instructions (see below)

---

## 4 . Automatic Monitoring

A lightweight watchdog checks every 5 minutes and restarts anything that fails.

```cron
*/5 * * * * /Users/jongraves/Documents/Websites/WhimsicalFrog/server_monitor.sh monitor >> /Users/jongraves/Documents/Websites/WhimsicalFrog/monitor.log 2>&1
```

How it works:

1. Tests that each port is listening.  
2. Hits a health URL (`/api/products` for Node, `/` for PHP).  
3. If either test fails it kills and restarts the offending server.  
4. Writes a timestamped entry to `monitor.log`.

_No extra daemons or packages required – it’s just Bash + cron._

---

## 5 . Accessing the Site & API

| Resource | URL |
|----------|-----|
| Website (PHP) | http://localhost:8000 |
| API – Products | http://localhost:3000/api/products |
| API – Users | http://localhost:3000/api/users |
| API – Inventory | http://localhost:3000/api/inventory |
| API – Product Groups | http://localhost:3000/api/product-groups |

---

## 6 . Troubleshooting Tips

| Symptom | Fix |
|---------|-----|
| “Site can’t be reached” on port 8000 | `./server_monitor.sh start` or `restart` |
| API returns `ECONNREFUSED` | Ensure Node server running: `./server_monitor.sh status` |
| Cron not running | `crontab -l` – confirm entry exists |
| Port already in use | `lsof -i :3000,8000` then `kill <PID>` and `./server_monitor.sh restart` |
| Logs growing large | Truncate with `> filename.log` |

---

## 7 . Log File Locations

| File | Purpose |
|------|---------|
| `monitor.log` | All monitoring actions & restarts |
| `server.log` | Stdout/stderr from Node.js (`npm start`) |
| `php_server.log` | Stdout/stderr from PHP server |
| `cron` system log | Last-resort debugging (`grep CRON /var/log/system.log` on macOS) |

---

## 8 . Disabling Automatic Monitoring

Option A – temporary  
```bash
crontab -l > mycron.bak        # backup
crontab -r                     # removes ALL cron jobs for current user
```

Option B – edit single line  
```bash
crontab -e                     # comment out the server_monitor entry with #
```

To restore:  
```bash
crontab mycron.bak
```

---

### Quick Reference

```bash
# Start everything
./start_servers.sh         # or ./server_monitor.sh start

# Manual health check
./server_monitor.sh status

# Stop everything
./server_monitor.sh stop
```

Happy hacking! 🐸
