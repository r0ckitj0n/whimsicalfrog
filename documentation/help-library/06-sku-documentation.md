# WhimsicalFrog SKU & ID Documentation

## 🏷️ Complete SKU & ID Methodology

### **Primary SKU Format**
```
WF-[CATEGORY]-[NUMBER]
```
**Examples:** `WF-TS-001`, `WF-TU-002`, `WF-AR-003`

### **Enhanced SKU Format (Optional)**
```
WF-[CATEGORY]-[GENDER]-[SIZE]-[COLOR]-[NUMBER]
```
**Example:** `WF-TS-M-L-BLK-001` = WhimsicalFrog T-Shirt, Men's Large, Black, #001

---

## 📋 Category Codes

| Category | Code | Example SKU |
|----------|------|-------------|
| T-Shirts | TS | WF-TS-001 |
| Tumblers | TU | WF-TU-001 |
| Artwork | AR | WF-AR-001 |
| Sublimation | SU | WF-SU-001 |
| Window Wraps | WW | WF-WW-001 |
| General | GEN | WF-GEN-001 |

---

## 🆔 Complete ID System

### **Customer IDs**
**Format:** `[MonthLetter][Day][SequenceNumber]`
- **Example:** `F30004` = June 30th, 4th customer
- **Month Codes:** A=Jan, B=Feb, C=Mar, D=Apr, E=May, F=Jun, G=Jul, H=Aug, I=Sep, J=Oct, K=Nov, L=Dec

### **Order IDs** ✅ **Recently Fixed**
**Format:** `[CustomerNum][MonthLetter][Day][ShippingCode][SequenceNum]`
- **Example:** `17F30P75` = Customer #17, June 30th, Pickup, Sequence #75
- **Recent Fix:** Replaced random numbers with sequence-based system to eliminate duplicate key violations
- **Shipping Codes:**
  - P = Customer Pickup
  - L = Local Delivery  
  - U = USPS
  - F = FedEx
  - X = UPS

### **Order Item IDs**
**Format:** `OI[SequentialNumber]`
- **Examples:** `OI0000000001`, `OI0000000042`
- **Sequential numbering ensures uniqueness**

---

## 🗄️ Database Architecture

### **Primary Tables**
- **`items`** - Main inventory (SKU as primary key)
- **`item_images`** - Images linked via SKU
- **`order_items`** - Order line items (references SKU)
- **`orders`** - Order headers

### **Migration History**
- ✅ **Phase 1:** Eliminated dual itemId/SKU system
- ✅ **Phase 2:** Migrated "products" → "items" terminology
- ✅ **Phase 3:** Fixed order ID generation (sequence-based)
- ✅ **Phase 4:** Implemented global color/size management
- ✅ **Current:** Pure SKU-only architecture

---

## 🛠️ API Endpoints

### **SKU Generation**
```
GET /api/next_sku.php?cat=[CATEGORY]
GET /api/next_sku.php?cat=T-Shirts&gender=M&size=L&color=Black&enhanced=true
```

### **Item Management**
```
GET /api/get_items.php
GET /api/get_item_images.php?sku=[SKU]
POST /api/update-inventory-field.php
```

### **Order Management**
```
POST /api/add-order.php (✅ Fixed duplicate issue)
GET /api/get_order.php?id=[ORDER_ID]
```

---

## 🔧 Recent Critical Fixes

### **Order ID Duplicate Fix (Latest)**
**Problem:** Users getting "SQLSTATE[23000]: Integrity constraint violation: 1062 Duplicate entry '17F30P38'" 

**Solution:** Replaced `rand(1, 99)` with sequence-based generation:
```php
// OLD: Random number causing duplicates
$randomNum = str_pad(rand(1, 99), 2, '0', STR_PAD_LEFT);

// NEW: Sequence-based ensuring uniqueness
$maxOrderStmt = $pdo->prepare("SELECT id FROM orders WHERE id LIKE ? ORDER BY id DESC LIMIT 1");
$maxOrderStmt->execute([$orderPrefix . '%']);
$maxOrderId = $maxOrderStmt->fetchColumn();
$nextSequence = $currentSequence + 1;
```

**Result:** Order IDs now generate sequentially: 17F30P75 → 17F30P76 → 17F30P77

---

## 📊 System Statistics

Access live statistics via Admin Settings > System Reference:
- Total items with image counts
- Order counts and recent activity
- Active categories
- Database health

---

## 🎨 Enhanced Features

### **Global Color & Size Management**
- Centralized color/size definitions
- Hierarchical structure for variations
- Integration with enhanced SKU generation

### **AI Integration**
- Automatic SKU suggestions based on item analysis
- Enhanced marketing and pricing APIs
- Image analysis for product categorization

### **Admin Interface**
- Real-time SKU preview in category management
- Comprehensive system documentation in admin settings
- Live data updates and statistics

---

## 🔍 Quick Reference

**View Documentation:** Admin Settings → System Reference  
**Generate SKU:** Use next_sku.php API or admin inventory interface  
**Check System Health:** System Reference modal shows live statistics  
**Debug Issues:** All APIs include comprehensive error logging

---

*Last Updated: June 30, 2025 - Added order ID sequence fix and enhanced documentation* 