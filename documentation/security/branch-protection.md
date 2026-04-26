# Branch Protection: Secret Scan Required

Use these settings to ensure no PR can merge unless the secret scanner passes.

## Target Branch

- `main`

## Required Settings

1. Go to GitHub repository settings.
2. Open `Branches` and edit the protection rule for `main`.
3. Enable `Require status checks to pass before merging`.
4. Require this check:
   - `Secret Scan / gitleaks`
5. Enable `Require branches to be up to date before merging`.
6. Keep these disabled:
   - `Allow force pushes`
   - `Allow deletions`

## Verification

- Open any PR and confirm `Secret Scan / gitleaks` appears in required checks.
- Confirm merge is blocked when the check fails.
