# Deployment Verification Guide  
**File:** `DEPLOYMENT_VERIFICATION.md`  
**Scope:** Confirm all inventory-related fixes are working on **both local and live** environments after deployment.

---

## 1  Prerequisites
| Requirement | Local | Live |
|-------------|-------|------|
| Updated codebase (commit `26e8a84f` or later) | ✅ | ✅ |
| Database seeded with sample inventory (8 items) | ✅ | ✅ |
| API endpoint `/api/inventory.php` reachable | ✅ | ✅ |
| Admin credentials | `admin / *****` | Same |

---

## 2  Quick Smoke Tests

### 2.1 API Endpoint
```bash
# Local
curl -s http://localhost/WhimsicalFrog/api/inventory.php | jq length   # → 8
# Live
curl -s https://<your-domain>/api/inventory.php | jq length           # → 8
```
Expected: **HTTP 200** and array length **8** (or real count).

### 2.2 Forbidden Check  
```bash
curl -I https://<domain>/process_inventory_get.php | head -1
```
Expected: **HTTP 301/302** (rewritten) or **404** – NOT 403.

---

## 3  Admin UI Walk-through  

| Step | Expected on Local | Expected on Live |
|------|-------------------|------------------|
| Navigate to `/index.php?page=admin&section=inventory` | Stats populate (Items 8, Low 4, Cats 6, Value $0.00) | Same values (or prod numbers) |
| Table rows | 8 visible, alternating background, sticky green header | Same |
| Row styling | Items with `stockLevel ≤ reorderPoint` have pale yellow bg & orange text | Same |
| Controls | Search box, category dropdown, **Refresh** & **Add** buttons visible | Same |
| Console | No 403/500 errors in DevTools | Same |

---

## 4  Functional Tests

### 4.1 Search & Filter
1. Type “Tumbler” → only tumbler rows show.  
2. Select **Category = Artwork** → 1 row appears.  
3. Clear filters → all rows return.

### 4.2 CRUD Cycle
| Action | Procedure | Expected |
|--------|-----------|----------|
| **Add** | Click “Add New Item”, enter dummy data, Save | Toast: *Item added*, table +1, Total Items increments |
| **Edit** | Click pencil icon on new item, modify name, Save | Row updates immediately |
| **Delete** | Trash icon → Confirm, item removed | Toast: *Item deleted*, table –1 |

### 4.3 Low-Stock Trigger
1. Edit an item’s `stockLevel` to `0` (below `reorderPoint`).  
2. Row gains **low-stock** styling and **Low Stock** counter increments.

---

## 5  Responsive / Styling Checks
1. Resize browser to 768 px & 480 px widths.  
2. Verify:
   - Stats grid collapses (2-col then 1-col)
   - Table scrolls horizontally on mobile
   - Modals fit viewport

---

## 6  Error & Log Review
| Location | Check |
|----------|-------|
| Browser DevTools | No red errors, network calls 200 |
| Server logs (`error_log`) | No new *Inventory API* or *Database* errors |
| PHP headers | No “headers already sent” warnings |

---

## 7  Rollback / Troubleshooting
Issue | Remedy
----- | ------
API returns 403 | Confirm `.htaccess` allow-rule: `^api/.*\.php$` and fetch URLs use `/api/...`
Stats still 0 | Clear browser cache -> hard reload; verify DB credentials in `api/config.php`
Table unstyled | Ensure `css/styles.css` latest version uploaded & cache-busted
Live empty, local ok | Rerun `deploy.sh`, verify `mirror --reverse` includes `api/` and `sections/`

---

## 8  Sign-off Checklist
- [ ] API returns JSON array on **both** environments  
- [ ] Inventory admin page shows non-zero stats & rows  
- [ ] Table formatting correct across browsers  
- [ ] CRUD operations succeed and reflect immediately  
- [ ] No console or server errors  
- [ ] Mobile responsiveness validated  

If all boxes checked, inventory deployment is **approved**.  
Happy Frogging 🐸✨
