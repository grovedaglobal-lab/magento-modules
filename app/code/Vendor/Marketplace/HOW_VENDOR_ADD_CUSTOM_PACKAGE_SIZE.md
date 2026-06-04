# How Vendor Can Add Custom Package Size Values

## Quick Answer

Vendors have **4 ways** to add custom package size values for their products:

---

## Method 1: 🎯 Quick & Simple - Use Pre-configured Dropdown (Default)

**Easiest Option**

```
Vendor creates a simple product and fills:

Product Name:       Coffee 100g
SKU:                COFFEE-100G
Price:              $5.00

Package Size:       [100g ▼]  ← Select from dropdown
                    
Status:             Enabled
[Save Product]
```

**Available Options (30+):**
- Weights: 100g, 250g, 500g, 1kg, 2kg, 5kg, 10kg, 25kg, 50kg
- Volumes: 100ml, 250ml, 500ml, 750ml, 1L, 1.5L, 2L, 5L, 10L
- Counts: Pack of 6, 12, 24, 50, 100
- Sizes: XS, S, M, L, XL, XXL
- Generic: Single, Bulk

**When to Use:**
- ✅ Most products (95% of cases)
- ✅ When size is in standard list
- ✅ No waiting for approval

---

## Method 2: 💬 Get Custom Size Approved - Request System

**When You Need a Unique Size**

### Step 1: Request the Size
```
Marketplace Dashboard → My Products → Request Package Size

┌──────────────────────────────────────────┐
│ Request New Package Size                 │
│                                          │
│ Desired Package Size:                    │
│ [3.5kg______________]                    │
│ (e.g., 500g, 2L, Pack of 15)             │
│                                          │
│ Product Category:                        │
│ [Select Category ▼]                      │
│ - Food                                   │
│ - Beverages                              │
│ - Cosmetics                              │
│ - Apparel                                │
│                                          │
│ Reason for Request:                      │
│ [_________________________]               │
│ Need for bulk premium coffee packages    │
│                                          │
│ [Request This Size]  [Cancel]            │
└──────────────────────────────────────────┘
```

### Step 2: Wait for Admin Approval
Admins see all requests in:
```
Admin Panel → Marketplace → Package Size Requests

Status shows as:
  ⏳ Pending — Waiting for review
```

### Step 3: Size Gets Added
Once approved:
```
✅ Admin approves "3.5kg"

Your Dashboard:
  Status: Approved ✓

Next Product Edit:
  Package Size: [3.5kg ▼]  ← Now available!
```

**Process Timeline:**
- Submit request: Immediate
- Admin reviews: 2-3 business days
- Gets approved: Immediately available
- Appears in dropdown: Yes, for all vendors

**Best For:**
- ✅ Specialty sizes (3.5kg, 175ml, Pack of 8)
- ✅ Niche products
- ✅ When you want standardization (size for all)

**Form Examples:**

```
Request: "3.5kg"
Reason: "Selling premium coffee in bulk packages"
Status: ⏳ Pending

Request: "175ml"
Reason: "Custom bottle size for artisan oils"
Status: ✅ Approved

Request: "Pack of 8"
Reason: "Standard case pack for our business"
Status: ❌ Rejected (Admin suggested "Pack of 6" exists)
```

---

## Method 3: ✏️ Enter Custom Text - No Approval Needed

**For One-Off Custom Sizes**

### When Creating Product

```
Marketplace → Add New Product

Product Name:           Custom Coffee Blend
SKU:                    COFFEE-CUSTOM

Package Size Options:

☐ Use Standard Size
   [Select Size ▼]
   
OR

☑ Use Custom Size
   [Enter custom text]
   [Best seller supply - 3.5kg custom box]

[Save Product]
```

### How It Works

**Example 1: Custom Size for Single Product**
```
Product A: "Custom 3.5kg Premium Pack"
├─ Stored as: custom_package_size = "Premium 3.5kg"
├─ Not in global dropdown
├─ Only shows for this product
└─ No admin approval needed
```

**Example 2: Multiple Custom Sizes**
```
Product B: "Bulk Supply 100 pieces"
├─ custom_package_size = "Box of 100 units"

Product C: "Office Supplies Pack"
├─ custom_package_size = "Carton (12 smaller boxes)"
```

**Database Storage:**
```sql
-- Standard size (in dropdown)
catalog_product_entity_int
WHERE attribute_id = 142 (package_size)
AND value = 100 (option ID for "100g")

-- Custom size (free text)
catalog_product_entity_varchar
WHERE attribute_id = 143 (custom_package_size)
AND value = "Premium 3.5kg seal-pack"
```

**When to Use:**
- ✅ One-time/unique sizes
- ✅ Marketing-specific sizes ("Best Seller Bundle")
- ✅ Don't need global availability
- ✅ Quick entry (no approval)
- ❌ Not useful for configurable products

---

## Method 4: 🔌 API Integration - For Integrations

**For ERP/Automation Systems**

### Request a New Size via API

```bash
curl -X POST https://store.com/rest/V1/vendor/package-size/request \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -d '{
    "package_size": "3.5kg",
    "reason": "Bulk rice packages"
  }'
```

**Response:**
```json
{
  "status": "pending",
  "message": "Request submitted successfully",
  "package_size": "3.5kg",
  "request_id": 1234,
  "created_at": "2026-02-10T10:30:00Z"
}
```

### Check Request Status

```bash
curl -X GET https://store.com/rest/V1/vendor/package-size/requests \
  -H "Authorization: Bearer YOUR_TOKEN"
```

**Response:**
```json
[
  {
    "id": 1234,
    "package_size": "3.5kg",
    "reason": "Bulk rice packages",
    "status": "pending",
    "requested_at": "2026-02-10T10:30:00Z"
  },
  {
    "id": 1235,
    "package_size": "175ml",
    "reason": "Custom bottles",
    "status": "approved",
    "approved_at": "2026-02-11T14:00:00Z"
  }
]
```

**When to Use:**
- ✅ Automated bulk product uploads
- ✅ ERP system integration
- ✅ Sync with warehouse systems
- ✅ Programmatic product creation

---

## Comparison: Which Method to Use?

```
METRIC              METHOD 1      METHOD 2        METHOD 3      METHOD 4
                    Dropdown      Request Form    Custom Text   API
─────────────────────────────────────────────────────────────────────────
Speed               ⚡ Instant     🐢 2-3 days    ⚡ Instant     ⚡ Instant
Approval Needed?    ❌ No          ✅ Yes          ❌ No          ✅ Yes
Global Available?   ✅ Yes         ✅ Yes          ❌ No          ✅ Yes
For Config Product? ✅ Yes         ✅ Yes          ❌ No          ✅ Yes
Standard?           ✅ Yes         ✅ Yes          ❌ No          ✅ Yes
UI/Manual?          ✅ Easy        ✅ Easy         ✅ Easy        ❌ Code
Tech Required?      ❌ No          ❌ No           ❌ No          ✅ Yes
```

---

## Step-by-Step: Request a Custom Size

### For Vendor (You)

**Step 1: Identify Missing Size**
```
Creating product "Premium Coffee Blend"
Package Size dropdown has: 100g, 250g, 500g, 1kg, 2kg...
Need: 3.5kg ← NOT IN LIST
```

**Step 2: Click "Request Package Size"**
```
Marketplace → My Products
[+ Add New Product] [Request Package Size] [My Requests]
```

**Step 3: Fill Request Form**
```
┌─────────────────────────────────────────┐
│ REQUEST PACKAGE SIZE                    │
│                                         │
│ Package Size: *                         │
│ [3.5kg              ]                   │
│                                         │
│ Category: (optional)                    │
│ [Food ▼]                                │
│ → Suggested sizes: 100g, 500g, 1kg, 2kg│
│                                         │
│ Reason: (optional)                      │
│ [____________                           │
│ Premium coffee needs custom 3.5kg size  │
│ ____________]                           │
│                                         │
│        [Request] [Cancel]               │
└─────────────────────────────────────────┘
```

**Step 4: Track Request Status**
```
Your Package Size Requests

SIZE      REASON                   STATUS      REQUESTED
─────────────────────────────────────────────────────────
3.5kg     Premium coffee blend     ⏳ Pending  2 days ago
175ml     Artisan oil bottles      ✅ Approved 1 week ago
Pack of 8 Standard case pack       ❌ Rejected Suggested Pack of 6
```

**Step 5: Use When Approved**
```
Once approved → Size appears in dropdown
Next product edit:

Package Size: [3.5kg ▼]  ← NEW! Now available

Can create configurable products with this size!
```

---

## For Configurable Products

### Creating a Bundle with Custom Sizes

**Scenario:**
```
Want to create: Coffee Bundle Configurable Product
With variants: 100g, 250g, 500g, 1kg, 2kg, 3.5kg

But 3.5kg is not in dropdown...
```

**Solution:**

**Option A: Request First**
```
1. Request "3.5kg" via form
2. Wait for approval (2-3 days)
3. Once approved, create configurable product
4. Select all 6 sizes (including new 3.5kg)
```

**Option B: Request via API**
```
1. POST /V1/vendor/package-size/request (3.5kg)
2. Poll /V1/vendor/package-size/requests until approved
3. GET /V1/vendor/package-size/available
4. Create configurable with all available sizes
```

**Option C: Use Standard Sizes Only**
```
1. Create configurable with: 100g, 250g, 500g, 1kg, 2kg
2. Skip 3.5kg for now
3. Later: Request 3.5kg, create new variant product
```

**Option D: Don't Use Custom Text**
```
Custom text sizes (Method 3) CANNOT be used in:
- Configurable products (need to be in dropdown)
- Layered navigation
- Swatches
- Compare feature

❌ Won't work for "3.5kg" if it's only custom text
✅ Request approval to add to global dropdown
```

---

## Real-World Examples

### Example 1: Food Vendor

```
Vendor: "Organic Foods CoOp"

Products Created:
✅ Rice 100g (uses Package Size: 100g)
✅ Rice 250g (uses Package Size: 250g)
✅ Rice 500g (uses Package Size: 500g)
✅ Rice 1kg (uses Package Size: 1kg)

Customer: "Do you have 3.5kg?"
↓
Request: "3.5kg" with reason "Bulk customers prefer 3.5kg"
↓
Status: Pending (admin reviewing)
↓
3 days later: ✅ Approved!
↓
Now create:
✅ Rice 3.5kg (new! uses Package Size: 3.5kg)

Can also create Configurable:
Rice Bundle (configurable)
├─ 100g variant
├─ 250g variant
├─ 500g variant
├─ 1kg variant
├─ 3.5kg variant  ← Uses newly approved size!
└─ 5kg variant
```

### Example 2: Cosmetics Vendor

```
Vendor: "Beauty Boutique"

Products:
✅ Face Cream 50ml (custom_package_size)
✅ Face Cream 100ml (Package Size: 100ml)
✅ Face Cream 250ml (Package Size: 250ml)

Special Edition:
✅ Face Cream - Holiday Gift Set 3-pack (custom_package_size: "3 x 50ml")
   (Doesn't need approval, just marketing copy)

New size needed:
Request: "500ml" 
Reason: "Customers ask for larger size"
Status: ✅ Approved

Now:
✅ Face Cream 500ml (uses Package Size: 500ml)
↓
Can create configurable:
Face Cream Line (configurable)
├─ 50ml variant (custom)
├─ 100ml variant
├─ 250ml variant
├─ 500ml variant  ← Uses newly approved
```

---

## Troubleshooting

**Q: I requested "3.5kg" but it's still pending. Can I use it now?**
A: No, not for standard/configurable products. You can:
- Use custom text field for single product
- Request approval (wait 2-3 days)
- Use existing sizes from dropdown
- Check if similar size exists ("3kg" or "5kg")

**Q: Can I use custom text in dropdown for configurable products?**
A: No, configurable products require sizes from the global dropdown.
You must request approval to add size to dropdown first.

**Q: How long does approval take?**
A: 2-3 business days. Admin checks:
- Formatting validity
- No duplicates
- Reasonable value
- Adds request to global options when approved

**Q: Can multiple vendors request same size?**
A: Yes. If 2 vendors request "3.5kg", admin approves once.
Both vendors get access immediately.

**Q: What if admin rejects my request?**
A: You'll see rejection reason, e.g.:
- "Similar size exists: 5kg is available"
- "Invalid format, try: 3kg or 3.5"
- "Not appropriate for this category"

Can then:
- Resubmit with better formatting
- Use suggested alternative
- Use custom text for single product only

**Q: Can I delete a request?**
A: Yes, if still pending. Once approved, size is global.

---

## Best Practices

### ✅ DO

- ✅ Use pre-configured dropdown first (95% of cases)
- ✅ Request sizes that will be used multiple times
- ✅ Be specific in request reason
- ✅ Use consistent formatting (e.g., "3.5kg" not "3.5 kilograms")
- ✅ Group similar requests (e.g., request "175ml" and "250ml" together)

### ❌ DON'T

- ❌ Request duplicates (admin will reject)
- ❌ Request invalid formats
- ❌ Use custom text for configurable products
- ❌ Request sizes that don't match your category
- ❌ Request sizes with marketing copy

---

## Summary

| Method | Speed | Approval | Global | Configurable | Best For |
|--------|-------|----------|--------|--------------|----------|
| **1. Dropdown** | Instant | No | Yes | Yes | Most products |
| **2. Request** | 2-3 days | Yes | Yes | Yes | Unique sizes |
| **3. Custom Text** | Instant | No | No | No | Marketing copy |
| **4. API** | Instant | Yes | Yes | Yes | Automation |

**Start with Method 1** (dropdown). Only use Method 2 if you need a truly custom size that doesn't exist. Methods 3 & 4 are for specific use cases.
