# WhimsicalFrog

Welcome to the WhimsicalFrog codebase! 🎉

All up-to-date documentation now lives in:

```
documentation/WHIMSICALFROG_FULL_DOCUMENTATION.md
```

That single document replaces the numerous legacy markdown files that previously described various subsystems. Those files have been archived for reference but should *not* be relied upon.

Quick links:
* [Full Documentation](documentation/WHIMSICALFROG_FULL_DOCUMENTATION.md)
* [Contribution Guide](documentation/WHIMSICALFROG_FULL_DOCUMENTATION.md#11-contribution-guide)
* [Local Development Setup](documentation/WHIMSICALFROG_FULL_DOCUMENTATION.md#4-local-development)

## Front-end Quick Start (Vite)

```bash
# Install JS dependencies
npm install

# Start Vite dev server
npm run dev
```

The dev server runs on [http://localhost:5176](http://localhost:5176) and hot-reloads whenever you edit files under `js/`, `src/`, or `css/`.

To generate the production bundle used in `dist/`, run:

```bash
npm run build
```

## Full Stack Dev Servers (PHP + Vite)

To start both the PHP dev server (http://localhost:8080) and the Vite dev server (http://localhost:5176) together, run:

```bash
./scripts/restart_servers.sh
```

## Quick Commands (wf)

You can also use the `wf` helper for common operations:

```bash
# Start servers with monitoring
./wf start

# Check status
./wf status

# View recent logs
./wf logs

# Stop servers and monitor
./wf stop
```

The `wf` wrapper uses the scripts under `scripts/` and keeps process names consistent.

Enjoy coding! 🐸
