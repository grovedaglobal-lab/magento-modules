# Vendor Configurable Product - Package Size Guide

## Overview

This guide explains how vendors can add and use package size variations for their products in the marketplace.

## What is Package Size?

The `package_size` attribute allows vendors to create product variations based on different package sizes. For example:
- Rice available in 1kg, 5kg, and 10kg packages
- Oil available in 500ml, 1L, and 5L bottles
- Supplements available in Pack of 30, Pack of 60, Pack of 90

## How It Works

### Step 1: Create Simple Products with Package Sizes

When creating a **Simple Product**, vendors can select a package size from the dropdown:

1. Go to **My Products** → **Add New Product**
2. Select **Simple Product** type
3. Fill in basic information (Name, SKU, Price)
4. In the product form, find the **"Package Size"** field
5. Select from available options:
   - **Weight-based**: 100g, 250g, 500g, 1kg, 2kg, 5kg, 10kg, 25kg, 50kg
   - **Volume-based**: 100ml, 250ml, 500ml, 1L, 2L, 5L
   - **Count-based**: Pack of 6, Pack of 12, Pack of 24, Pack of 50, Pack of 100
   - **Size-based**: Small, Medium, Large, Extra Large, XXL
   - **Generic**: Single Unit, Trial Size, Family Pack, Economy Pack

**Example:**
```
Product Name: Royal Basmati Rice - 1kg
SKU: RICE-1KG
Price: 50
Package Size: 1kg
```

```
Product Name: Royal Basmati Rice - 5kg
SKU: RICE-5KG
Price: 200
Package Size: 5kg
```

### Step 2: Create Configurable Product

After creating all simple product variations:

1. Go to **My Products** → **Add New Product**
2. Select **Configurable Product** type
3. Fill in basic information for the parent product:
   - Name: Royal Basmati Rice
   - SKU: RICE-PARENT
4. In the **"Product Configurations"** section:
   - **Select Variation Attribute**: Choose "Package Size"
   - **Select Simple Products**: Check all the simple products you created (Rice 1kg, Rice 5kg, etc.)
5. Click **Save Product**

### Step 3: Frontend Display

On the product page, customers will see:
- Parent product: "Royal Basmati Rice"
- Package Size selector (dropdown or swatches)
- Price updates when selecting different sizes
- Stock availability per size

## Available Package Sizes

### Pre-configured Options

The system comes with 30+ pre-configured package sizes:

| Category | Sizes |
|----------|-------|
| **Weight (g)** | 100g, 250g, 500g, 750g |
| **Weight (kg)** | 1kg, 2kg, 5kg, 10kg, 25kg, 50kg |
| **Volume (ml/L)** | 100ml, 250ml, 500ml, 750ml, 1L, 2L, 5L |
| **Count** | Pack of 6, 12, 24, 50, 100 |
| **Apparel** | Small, Medium, Large, Extra Large, XXL |
| **Generic** | Single Unit, Bulk, Trial Size, Family Pack, Economy Pack |

## Requesting New Package Sizes

If you need a package size that's not in the list:

### Option 1: Contact Administrator (Recommended)
1. Contact marketplace admin
2. Provide the exact package size needed (e.g., "3kg", "1.5L")
3. Admin will add it to the global list

### Option 2: Developer/Admin CLI Command
Admins can add new sizes via CLI:

```bash
php bin/magento vendor:attribute:add-option package_size "3kg"
```

Or through Admin Panel:
1. **Stores** → **Attributes** → **Product**
2. Find **package_size**
3. **Manage Options** → **Add New Option**
4. Enter new value
5. Save

## Technical Implementation

### Data Structure

```yaml
Configurable Product (Parent):
  - ID: 1001
  - SKU: RICE-PARENT
  - Name: Royal Basmati Rice
  - Type: configurable
  - Configurable Attribute: package_size
  
Associated Simple Products (Children):
  - Product 1:
      ID: 1002
      SKU: RICE-1KG
      Name: Royal Basmati Rice - 1kg
      Price: 50
      package_size: 1kg
      Visibility: Not Visible Individually
      
  - Product 2:
      ID: 1003
      SKU: RICE-5KG
      Name: Royal Basmati Rice - 5kg
      Price: 200
      package_size: 5kg
      Visibility: Not Visible Individually
```

### Magento 2 API Usage

The implementation uses Magento 2's native configurable product APIs:

```php
// Set configurable attribute
$configurableType->setUsedProductAttributeIds([attributeId], $parentProduct);

// Create option
$option = $optionFactory->create();
$option->setAttributeId($attributeId);
$option->setLabel('Package Size');

// Link child products
$extensionAttributes->setConfigurableProductOptions([$option]);
$extensionAttributes->setConfigurableProductLinks([childProductIds]);
$parentProduct->setExtensionAttributes($extensionAttributes);
```

## Best Practices

### ✅ DO

1. **Use Consistent Naming**:
   - Simple: "Product Name - Size"
   - Configurable: "Product Name"

2. **Logical SKU Structure**:
   - Simple: PRODUCT-SIZE (e.g., RICE-1KG)
   - Configurable: PRODUCT-PARENT

3. **Accurate Pricing**:
   - Set realistic prices for each size variation
   - Larger sizes typically have better unit prices

4. **Same Attribute Set**:
   - Use the same attribute set for all simple products and the configurable parent

5. **Stock Management**:
   - Set stock levels for each simple product independently

### ❌ DON'T

1. **Don't mix attribute types**:
   - Don't use both package_size and color for the same configurable
   - One configurable = One variation attribute

2. **Don't create duplicate package sizes**:
   - Check existing options before requesting new ones

3. **Don't set configurable product price**:
   - Price comes from selected simple product

4. **Don't make simple products visible**:
   - System automatically sets visibility to "Not Visible Individually"

## Troubleshooting

### Issue: Package Size not showing

**Solution**: Ensure the attribute is:
- Global scope
- Select type (dropdown)
- Added to your attribute set

### Issue: Can't link products

**Possible causes**:
1. Products don't have package_size value set
2. Products have different attribute sets
3. Products aren't simple type
4. Products don't belong to you (ownership check)

### Issue: Need custom size

**Solution**: 
1. Contact admin to add the size globally
2. Alternatively, admin can add via backend

## Command Reference

### For Administrators

**Add Package Size Option:**
```bash
php bin/magento vendor:attribute:add-option package_size "3.5kg"
```

**List All Package Sizes:**
```bash
php bin/magento catalog:product:attribute:options package_size
```

**Reindex After Changes:**
```bash
php bin/magento indexer:reindex
php bin/magento cache:clean
```

## FAQ

**Q: Can I change the package size of an existing product?**  
A: Yes, edit the simple product and select a different package size.

**Q: Can I have multiple variation attributes?**  
A: Currently, one configurable product supports one variation attribute. For multiple variations (size + color), contact admin for advanced setup.

**Q: What if my product doesn't have a size?**  
A: Use "Single Unit" or leave it as a simple product without making it configurable.

**Q: Can I delete a linked product?**  
A: Yes, but it will break the configurable product link. You'll need to edit the configurable product to update the associations.

## Support

For additional help:
- Contact: marketplace@yourstore.com
- Documentation: /vendor/docs/products
- Admin Panel: Help → Vendor Guide
