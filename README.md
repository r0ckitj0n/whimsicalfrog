# WhimsicalFrog

Documentation and legacy code were archived to `backups/cleanup_2025_11_26` on Wed Nov 26 20:18:57 EST 2025.

## Secret Scanning

- Local pre-commit scanning runs via `gitleaks`.
- Install the binary with `brew install gitleaks` (recommended), or use Docker as fallback.
- Run a full repo scan manually with `npm run secrets:scan`.

## Branch Protection (Required Secret Scan)

- Protect `main` and require status check `Secret Scan / gitleaks` before merge.
- Keep "Require branches to be up to date before merging" enabled.
- Do not allow force pushes or branch deletions on `main`.

## PR-Only Main Workflow

- Do not push directly to `main`.
- Open a pull request from a feature branch and merge only after required checks pass.
- Keep branch protection configured with:
  - required pull request reviews
  - required status check `Secret Scan / gitleaks`
  - no force-push and no delete on `main`

