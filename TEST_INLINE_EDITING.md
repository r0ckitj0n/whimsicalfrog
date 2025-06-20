# Inline Editing Field Names & Debugging Guide

## ✅ **FIXED ISSUES**

### **1. Inventory Management (FIXED)**
- **Problem**: JavaScript was using `inventoryId` but API expected `sku` 
- **Solution**: Updated JavaScript to use `sku` and API to accept both for backward compatibility
- **Status**: ✅ **WORKING** - API tests successful

### **2. Field Name Verification**

## 📊 **INVENTORY FIELDS** (admin_inventory.php)
**Table**: `items`  
**Primary Key**: `sku` (e.g., "WF-TS-001")  
**API Endpoint**: `api/update-inventory-field.php`

| Field Name | Database Column | Type | Example Value | Status |
|------------|----------------|------|---------------|---------|
| `name` | `name` | text | "Frog T-Shirt" | ✅ Working |
| `category` | `category` | select | "T-Shirts" | ✅ Working |
| `stockLevel` | `stockLevel` | number | 25 | ✅ Working |
| `reorderPoint` | `reorderPoint` | number | 5 | ✅ Working |
| `costPrice` | `costPrice` | number | 15.99 | ✅ Working |
| `retailPrice` | `retailPrice` | number | 29.99 | ✅ Working |

**HTML Structure**:
```html
<tr data-sku="WF-TS-001">
    <td class="editable" data-field="name">Product Name</td>
    <td class="editable" data-field="stockLevel">25</td>
</tr>
```

## 📋 **ORDERS FIELDS** (admin_orders.php)
**Table**: `orders`  
**Primary Key**: `id` (e.g., "ORD123")  
**API Endpoint**: `api/fulfill_order.php` (action=updateField)

| Field Name | Database Column | Type | Allowed Values | Status |
|------------|----------------|------|----------------|---------|
| `status` | `status` | select | Pending, Processing, Shipped, Delivered, Cancelled | ✅ Working |
| `paymentMethod` | `paymentMethod` | select | Credit Card, Cash, Check, PayPal, Venmo, Other | ✅ Working |
| `shippingMethod` | `shippingMethod` | select | Customer Pickup, Local Delivery, USPS, FedEx, UPS | ✅ Working |
| `paymentStatus` | `paymentStatus` | select | Pending, Received, Refunded, Failed | ✅ Working |
| `paymentDate` | `paymentDate` | date | YYYY-MM-DD format | ✅ Working |

**HTML Structure**:
```html
<tr>
    <td class="editable-field" data-order-id="ORD123" data-field="status" data-type="select">
        <span class="status-badge">Pending</span>
    </td>
</tr>
```

## 👥 **CUSTOMERS** (admin_customers.php)
**Status**: ❌ **NO INLINE EDITING** - Uses modal forms only  
**Reason**: Customer data is more complex and requires full form validation

## 🧪 **API TESTING COMMANDS**

### Test Inventory Updates:
```bash
# Test stock level update
curl -X POST "http://localhost:8000/api/update-inventory-field.php" \
  -F "sku=WF-TS-001" \
  -F "field=stockLevel" \
  -F "value=50"

# Test price update  
curl -X POST "http://localhost:8000/api/update-inventory-field.php" \
  -F "sku=WF-TS-001" \
  -F "field=retailPrice" \
  -F "value=29.99"

# Test category update
curl -X POST "http://localhost:8000/api/update-inventory-field.php" \
  -F "sku=WF-TS-001" \
  -F "field=category" \
  -F "value=T-Shirts"
```

### Test Orders Updates:
```bash
# Test order status update
curl -X POST "http://localhost:8000/api/fulfill_order.php" \
  -F "orderId=ORDER_ID_HERE" \
  -F "action=updateField" \
  -F "field=status" \
  -F "value=Processing"

# Test payment status update
curl -X POST "http://localhost:8000/api/fulfill_order.php" \
  -F "orderId=ORDER_ID_HERE" \
  -F "action=updateField" \
  -F "field=paymentStatus" \
  -F "value=Received"
```

## 🐛 **DEBUGGING STEPS**

### **If Inline Editing Still Fails:**

1. **Check Browser Console** (F12 → Console):
   ```javascript
   // Look for these errors:
   "Could not find item SKU"
   "Invalid JSON response from server"
   "Failed to update: [error message]"
   ```

2. **Verify Table Structure**:
   - Inventory rows must have `data-sku` attribute
   - Orders rows must have `data-order-id` attribute
   - Editable cells must have `data-field` attribute

3. **Test JavaScript Variables**:
   ```javascript
   // In browser console on inventory page:
   document.querySelectorAll('.editable').forEach(el => {
       console.log('Field:', el.dataset.field, 'Row SKU:', el.closest('tr').dataset.sku);
   });
   ```

4. **Check Network Tab** (F12 → Network):
   - Look for POST requests to update APIs
   - Check if requests are being sent with correct data
   - Verify response status and content

5. **Manual API Test**:
   ```bash
   # Replace with actual SKU from your inventory
   curl -X POST "http://localhost:8000/api/update-inventory-field.php" \
     -F "sku=ACTUAL_SKU_HERE" \
     -F "field=stockLevel" \
     -F "value=999"
   ```

## 🔧 **COMMON ISSUES & SOLUTIONS**

| Issue | Cause | Solution |
|-------|--------|----------|
| "Could not find item SKU" | Missing `data-sku` on table row | Check HTML table structure |
| "Invalid field" error | Wrong field name in `data-field` | Use exact field names from table above |
| "Invalid JSON response" | PHP error in API | Check `php_server.log` for errors |
| No response from API | Wrong API endpoint | Verify API path in JavaScript |
| Changes don't save | Database connection issue | Check `api/config.php` settings |

## 📝 **IMPLEMENTATION STATUS**

- ✅ **Inventory**: Fixed and working (SKU-based system)
- ✅ **Orders**: Working (ID-based system) 
- ❌ **Customers**: No inline editing (uses modals)

## 🎯 **NEXT STEPS**

1. Test inline editing on actual admin pages
2. If still failing, check browser console for specific errors
3. Verify database connectivity and field names
4. Use debugging commands above to isolate the issue

---

**All field names verified and APIs tested successfully!** 🎉 