# Package Size - Quick Reference Card

## 📦 For Vendors

### Available Package Sizes (Pre-configured)

**Weight-based:**
```
100g, 250g, 500g, 750g
1kg, 2kg, 5kg, 10kg, 25kg, 50kg
```

**Volume-based:**
```
100ml, 250ml, 500ml, 750ml
1L, 2L, 5L
```

**Count-based:**
```
Pack of 6, Pack of 12, Pack of 24
Pack of 50, Pack of 100
```

**Size-based:**
```
Small, Medium, Large
Extra Large, XXL
```

**Generic:**
```
Single Unit, Bulk, Trial Size
Family Pack, Economy Pack
```

---

## ⚡ Quick Steps

### Create Size Variations

```
1. Create Simple Products (one per size)
   └─ Product 1: Rice - 1kg, Price: 50, package_size: 1kg
   └─ Product 2: Rice - 5kg, Price: 200, package_size: 5kg

2. Create Configurable Product (parent)
   └─ Name: Rice
   └─ Variation: Package Size
   └─ Link: Both simple products

3. Save → Done! ✓
```

---

## 🔧 For Administrators

### Add New Package Size

**Via CLI:**
```bash
php bin/magento vendor:attribute:add-option package_size "3kg"
php bin/magento cache:flush
php bin/magento indexer:reindex
```

**Via Admin Panel:**
```
Stores → Attributes → Product → package_size
→ Manage Options → Add New Row → "3kg" → Save
```

---

## 🎯 Best Practices

### DO ✓
- Use consistent naming: "Product - Size"
- Set package_size for all simple products
- Create configurable AFTER simple products
- Use same attribute set for all products

### DON'T ✗
- Don't manually type package sizes (select from dropdown)
- Don't skip package_size field on simple products
- Don't mix different variation attributes
- Don't make simple products visible individually

---

## 🆘 Troubleshooting

| Problem | Solution |
|---------|----------|
| **Size not in dropdown** | Contact admin to add new size |
| **Can't link products** | Ensure all have package_size set |
| **No dropdown on frontend** | Check simple products have different sizes |
| **Price not updating** | Verify each simple product has price set |

---

## 📞 Support

**Need custom size?**  
→ Contact: admin@marketplace.com  
→ Specify exact size needed (e.g., "3.5kg")  
→ Admin will add globally

---

## 🎓 Learn More

- Full Guide: See `README_PACKAGE_SIZE.md`
- Visual Guide: See `DOCS_PACKAGE_SIZE_VISUAL_GUIDE.md`
- Technical: See `Model/Product/Attribute/PackageSizeManager.php`
