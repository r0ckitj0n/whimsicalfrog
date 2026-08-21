# Branch Protection: Secret Scan Required

Use these settings to ensure no PR can merge unless the secret scanner passes.

## Target Branch

- `main`

## Required Settings

1. Go to GitHub repository settings.
2. Open `Branches` and edit the protection rule for `main`.
3. Enable `Require a pull request before merging`.
4. Keep **Require approvals at 0**. This is a one-person repo: the PR author cannot approve their own pull request, and Cloud Agent tokens cannot submit GitHub reviews. File review happens in the Cloud Agent conversation; GitHub review gates would block every merge.
5. Enable `Require status checks to pass before merging`.
6. Require this check:
   - `Secret Scan / gitleaks`
7. Enable `Require branches to be up to date before merging`.
8. Keep these disabled:
   - `Require approvals` (or set required reviewers to `0`)
   - `Allow force pushes`
   - `Allow deletions`

## Verification

- Open any PR and confirm `Secret Scan / gitleaks` appears in required checks.
- Confirm merge is blocked when the check fails.
