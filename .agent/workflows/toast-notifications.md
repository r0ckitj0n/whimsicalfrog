---
description: Toast notification preference for all user feedback
---
# Toast Notification Standard

## Rule
**Always use toast notifications (`window.WFToast`) for all user feedback across the site.** Do not use inline notification states or alert boxes unless specifically requested.

## Usage Pattern
```typescript
// Success feedback
if (window.WFToast) window.WFToast.success('Operation completed!');

// Error feedback
if (window.WFToast) window.WFToast.error('Operation failed');

// Info feedback
if (window.WFToast) window.WFToast.info('Processing...');
```

## Why
- Consistent UX across all admin modals and pages
- Non-intrusive notifications that don't require dismissal
- User preference explicitly stated
