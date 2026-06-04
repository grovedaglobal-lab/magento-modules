# Implementation Guide: Integrating Alternative Package Size Methods

This guide shows how to integrate each alternative method into your Magento storefront.

---

## Method 1: Pre-configured Dropdown (Already Implemented)

### Current Implementation
File: `app/code/Vendor/Marketplace/Setup/Patch/Data/AddPackageSizeAttribute.php`

### In Product Form
```html
<!-- vendor/marketplace/view/frontend/templates/product/edit.phtml -->

<div class="fieldset">
    <label for="package_size"><?php echo __('Package Size'); ?></label>
    <select id="package_size" name="package_size" class="required-entry">
        <option value="">-- Select Size --</option>
        <?php foreach ($block->getPackageSizeOptions() as $option): ?>
            <option value="<?php echo $option['value']; ?>">
                <?php echo $option['label']; ?>
            </option>
        <?php endforeach; ?>
    </select>
</div>
```

### Block Helper
```php
// Add to Block/Product/Edit.php

public function getPackageSizeOptions()
{
    return $this->packageSizeManager->getAllOptions();
}
```

---

## Method 2: Request Form Integration

### Step 1: Add "Request Size" Link to Product Form

File: `view/frontend/templates/product/edit.phtml`

```html
<div class="package-size-section">
    <h3><?php echo __('Package Size'); ?></h3>
    
    <!-- Existing dropdown -->
    <div class="field">
        <label><?php echo __('Select from list'); ?></label>
        <select id="package_size" name="package_size">
            <!-- options -->
        </select>
    </div>
    
    <!-- NEW: Request custom size -->
    <div class="request-size-box">
        <p><?php echo __('Don\'t see your size?'); ?></p>
        <a href="<?php echo $block->getUrl('vendor/product/request-package-size'); ?>" 
           class="btn btn-link">
            <?php echo __('Request a Custom Size'); ?>
        </a>
    </div>
    
    <!-- Display vendor's pending requests -->
    <div class="pending-requests">
        <h4><?php echo __('Your Package Size Requests'); ?></h4>
        <?php if (count($block->getVendorRequests()) > 0): ?>
            <table>
                <thead>
                    <tr>
                        <th><?php echo __('Size'); ?></th>
                        <th><?php echo __('Status'); ?></th>
                        <th><?php echo __('Requested'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($block->getVendorRequests() as $request): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($request['package_size']); ?></td>
                            <td>
                                <span class="status-<?php echo $request['status']; ?>">
                                    <?php echo ucfirst($request['status']); ?>
                                </span>
                            </td>
                            <td><?php echo $block->formatDate($request['created_at']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p><?php echo __('No pending requests'); ?></p>
        <?php endif; ?>
    </div>
</div>

<style>
.request-size-box {
    background: #f5f5f5;
    padding: 10px;
    margin-top: 10px;
    border-left: 3px solid #007bff;
}

.pending-requests {
    margin-top: 20px;
}

.status-pending {
    background: #fff3cd;
    padding: 2px 6px;
    border-radius: 3px;
}

.status-approved {
    background: #d4edda;
    padding: 2px 6px;
    border-radius: 3px;
}

.status-rejected {
    background: #f8d7da;
    padding: 2px 6px;
    border-radius: 3px;
}
</style>
```

### Step 2: Create Request Form Template

File: `view/frontend/templates/product/request-package-size.phtml`

```html
<?php /** @var \Vendor\Marketplace\Block\Product\RequestPackageSize $block */ ?>

<div class="request-package-size-form">
    <h1><?php echo __('Request Package Size'); ?></h1>
    
    <p><?php echo __('Submit a request for a custom package size. Our administrators will review your request and approve or provide feedback within 2-3 business days.'); ?></p>
    
    <form method="POST" action="<?php echo $block->getFormActionUrl(); ?>">
        <?php echo $block->getBlockHtml('formkey'); ?>
        
        <fieldset class="fieldset">
            <legend><span><?php echo __('Package Size Details'); ?></span></legend>
            
            <!-- Package Size Input -->
            <div class="field required">
                <label for="package_size"><?php echo __('Desired Package Size'); ?></label>
                <input 
                    type="text" 
                    id="package_size" 
                    name="package_size" 
                    class="form-control required-entry"
                    placeholder="<?php echo __('e.g., 3.5kg, 175ml, Pack of 15'); ?>"
                    required
                />
                <p class="help-text"><?php echo __('Examples: 500g, 2L, Pack of 12, Small, XL'); ?></p>
            </div>
            
            <!-- Category (optional suggestion) -->
            <div class="field">
                <label for="category"><?php echo __('Product Category (Optional)'); ?></label>
                <select id="category" name="category" class="form-control">
                    <option value="">-- Select Category --</option>
                    <option value="food">Food</option>
                    <option value="beverages">Beverages</option>
                    <option value="cosmetics">Cosmetics</option>
                    <option value="apparel">Apparel</option>
                    <option value="electronics">Electronics</option>
                    <option value="supplements">Supplements</option>
                    <option value="household">Household</option>
                    <option value="office">Office Supplies</option>
                </select>
            </div>
            
            <!-- Reason -->
            <div class="field">
                <label for="reason"><?php echo __('Reason for Request'); ?></label>
                <textarea 
                    id="reason" 
                    name="reason" 
                    class="form-control"
                    rows="4"
                    placeholder="<?php echo __('Why do you need this size? Are you creating a new product type or line?'); ?>"
                ></textarea>
            </div>
        </fieldset>
        
        <!-- Suggested sizes for selected category -->
        <div id="suggested-sizes" style="display: none;">
            <fieldset class="fieldset">
                <legend><span><?php echo __('Suggested Sizes'); ?></span></legend>
                <div id="suggestions-list"></div>
            </fieldset>
        </div>
        
        <!-- Submit Button -->
        <div class="actions">
            <button type="submit" class="btn btn-primary">
                <?php echo __('Submit Request'); ?>
            </button>
            <a href="<?php echo $block->getBackUrl(); ?>" class="btn btn-secondary">
                <?php echo __('Cancel'); ?>
            </a>
        </div>
    </form>
</div>

<script>
    require(['jquery'], function($) {
        const suggestions = <?php echo json_encode($block->getCategorySuggestions()); ?>;
        
        $('#category').on('change', function() {
            const category = $(this).val();
            if (category && suggestions[category]) {
                let html = '<div class="suggestion-list">';
                suggestions[category].forEach(function(size) {
                    html += '<span class="suggestion-chip">' + size + '</span>';
                });
                html += '</div>';
                $('#suggestions-list').html(html);
                $('#suggested-sizes').show();
                
                // Click to fill
                $('.suggestion-chip').on('click', function() {
                    $('#package_size').val($(this).text());
                });
            } else {
                $('#suggested-sizes').hide();
            }
        });
    });
</script>

<style>
.suggestion-chip {
    display: inline-block;
    background: #e3f2fd;
    border: 1px solid #90caf9;
    padding: 6px 12px;
    margin: 4px;
    cursor: pointer;
    border-radius: 20px;
    transition: all 0.2s;
}

.suggestion-chip:hover {
    background: #90caf9;
    color: white;
}

.help-text {
    color: #666;
    font-size: 0.9em;
    margin-top: 4px;
}
</style>
```

### Step 3: Create Request Block

File: `Block/Product/RequestPackageSize.php`

```php
<?php

namespace Vendor\Marketplace\Block\Product;

use Magento\Framework\View\Element\Template;
use Vendor\Marketplace\Model\Product\Attribute\PackageSizeSuggestions;
use Vendor\Marketplace\Model\ResourceModel\PackageSizeRequest\CollectionFactory;

class RequestPackageSize extends Template
{
    protected $suggestions;
    protected $requestsFactory;
    protected $vendorRegistry;

    public function __construct(
        Template\Context $context,
        PackageSizeSuggestions $suggestions,
        CollectionFactory $requestsFactory,
        \Vendor\Marketplace\Model\VendorRegistry $vendorRegistry,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->suggestions = $suggestions;
        $this->requestsFactory = $requestsFactory;
        $this->vendorRegistry = $vendorRegistry;
    }

    public function getCategorySuggestions()
    {
        return $this->suggestions->getAllCategorySuggestions();
    }

    public function getFormActionUrl()
    {
        return $this->getUrl('vendor/product/save-request-package-size');
    }

    public function getBackUrl()
    {
        return $this->getUrl('vendor/product/');
    }
}
```

### Step 4: Create Controller

File: `Controller/Product/SaveRequestPackageSize.php`

```php
<?php

namespace Vendor\Marketplace\Controller\Product;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Vendor\Marketplace\Model\PackageSizeRequestFactory;
use Vendor\Marketplace\Model\ResourceModel\PackageSizeRequest as RequestResource;

class SaveRequestPackageSize extends Action
{
    protected $requestFactory;
    protected $requestResource;
    protected $vendorRegistry;

    public function __construct(
        Context $context,
        PackageSizeRequestFactory $requestFactory,
        RequestResource $requestResource,
        \Vendor\Marketplace\Model\VendorRegistry $vendorRegistry
    ) {
        parent::__construct($context);
        $this->requestFactory = $requestFactory;
        $this->requestResource = $requestResource;
        $this->vendorRegistry = $vendorRegistry;
    }

    public function execute()
    {
        if (!$this->getRequest()->isPost()) {
            return $this->resultRedirectFactory->create()->setPath('vendor/product/');
        }

        try {
            $vendorId = $this->vendorRegistry->getCurrentVendorId();
            if (!$vendorId) {
                throw new \Exception(__('You must be logged in as a vendor.'));
            }

            $packageSize = trim((string)$this->getRequest()->getPost('package_size'));
            $reason = trim((string)$this->getRequest()->getPost('reason', ''));

            if (empty($packageSize)) {
                throw new \Exception(__('Package size is required.'));
            }

            if (strlen($packageSize) > 255) {
                throw new \Exception(__('Package size must be 255 characters or less.'));
            }

            // Check for duplicates
            $collection = $this->requestFactory->create()->getCollection()
                ->addFieldToFilter('vendor_id', $vendorId)
                ->addFieldToFilter('package_size', $packageSize)
                ->addFieldToFilter('status', 'pending');

            if ($collection->getSize() > 0) {
                throw new \Exception(__('You already have a pending request for "%1".', $packageSize));
            }

            // Create request
            $model = $this->requestFactory->create();
            $model->setVendorId($vendorId);
            $model->setPackageSize($packageSize);
            $model->setReason($reason);
            $model->setStatus('pending');
            
            $this->requestResource->save($model);

            $this->messageManager->addSuccessMessage(
                __('Your package size request "%1" has been submitted. You will receive an email notification once it\'s reviewed.', $packageSize)
            );

            return $this->resultRedirectFactory->create()->setPath('vendor/product/');

        } catch (\Exception $e) {
            $this->messageManager->addErrorMessage(__('Error: ' . $e->getMessage()));
            return $this->resultRedirectFactory->create()->setPath('vendor/product/request-package-size');
        }
    }
}
```

---

## Method 3: REST API Integration

### Step 1: Register API Routes

File: `etc/webapi.xml`

```xml
<?xml version="1.0"?>
<routes xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:noNamespaceSchemaLocation="urn:magento:module:Magento_Webapi:etc/webapi.xsd">
    
    <!-- Request a new package size -->
    <route url="/V1/vendor/package-size/request" method="POST">
        <service class="Vendor\Marketplace\Api\VendorPackageSizeManagementInterface" method="requestPackageSize"/>
        <resources>
            <resource ref="Vendor_Marketplace::manage_products"/>
        </resources>
    </route>
    
    <!-- Get vendor's requests -->
    <route url="/V1/vendor/package-size/requests" method="GET">
        <service class="Vendor\Marketplace\Api\VendorPackageSizeManagementInterface" method="getMyRequests"/>
        <resources>
            <resource ref="Vendor_Marketplace::manage_products"/>
        </resources>
    </route>
    
    <!-- Get available sizes -->
    <route url="/V1/vendor/package-size/available" method="GET">
        <service class="Vendor\Marketplace\Api\VendorPackageSizeManagementInterface" method="getAvailableSizes"/>
        <resources>
            <resource ref="Vendor_Marketplace::manage_products"/>
        </resources>
    </route>
</routes>
```

### Step 2: Update Dependency Injection

File: `etc/di.xml` (Add this section if not already present)

```xml
<config xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:noNamespaceSchemaLocation="urn:magento:framework:ObjectManager/etc/config.xsd">
    
    <preference for="Vendor\Marketplace\Api\VendorPackageSizeManagementInterface" 
                type="Vendor\Marketplace\Model\VendorPackageSizeManagement"/>
    
</config>
```

### Usage Examples

```bash
# Request a size
curl -X POST https://example.com/rest/V1/vendor/package-size/request \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9..." \
  -d '{
    "package_size": "3.5kg",
    "reason": "Bulk rice packages"
  }'

# Get pending requests
curl -X GET "https://example.com/rest/V1/vendor/package-size/requests?status=pending" \
  -H "Authorization: Bearer eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9..."

# Get available sizes
curl -X GET https://example.com/rest/V1/vendor/package-size/available \
  -H "Authorization: Bearer eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9..."
```

---

## Method 4: Custom Text Field (Fallback)

### Add to Product Form

File: `view/frontend/templates/product/edit.phtml`

```html
<div class="field">
    <label for="package_size"><?php echo __('Package Size'); ?></label>
    
    <!-- Standard selection -->
    <select id="package_size" name="package_size" class="form-control">
        <option value="">-- Select Size --</option>
        <?php foreach ($block->getPackageSizeOptions() as $option): ?>
            <option value="<?php echo $option['value']; ?>">
                <?php echo $option['label']; ?>
            </option>
        <?php endforeach; ?>
    </select>
</div>

<!-- NEW: Custom fallback field -->
<div class="field">
    <label for="custom_package_size">
        <?php echo __('Or Enter Custom Size'); ?>
        <span class="note"><?php echo __('(if size not in list above)'); ?></span>
    </label>
    <input 
        type="text" 
        id="custom_package_size" 
        name="custom_package_size"
        class="form-control"
        placeholder="<?php echo __('e.g., Custom 3.5kg box'); ?>"
        maxlength="255"
    />
    <p class="help-text"><?php echo __('This will be specific to this product only.'); ?></p>
</div>

<script>
require(['jquery'], function($) {
    // When user selects dropdown, clear custom
    $('#package_size').on('change', function() {
        if ($(this).val()) {
            $('#custom_package_size').val('');
        }
    });
    
    // When user enters custom, clear selection
    $('#custom_package_size').on('input', function() {
        if ($(this).val()) {
            $('#package_size').val('');
        }
    });
});
</script>
```

---

## Method 5: Category Suggestions Integration

### Add to Product Form

File: `view/frontend/templates/product/edit.phtml`

```html
<div class="package-size-section">
    <!-- Category Selection -->
    <div class="field">
        <label for="product_category" class="required">
            <?php echo __('Product Category'); ?>
        </label>
        <select id="product_category" name="category_id" class="form-control required-entry" required>
            <option value="">-- Select Category --</option>
            <?php foreach ($block->getCategoryOptions() as $category): ?>
                <option value="<?php echo $category['id']; ?>">
                    <?php echo $category['name']; ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    
    <!-- Suggestions appear here -->
    <div id="size-suggestions" style="display: none;">
        <div class="suggestion-container">
            <h4><?php echo __('Suggested Sizes for '); ?><span id="category-name"></span></h4>
            <div id="suggestions-list" class="suggestion-items"></div>
        </div>
    </div>
    
    <!-- Package Size Selection -->
    <div class="field required">
        <label for="package_size"><?php echo __('Package Size'); ?></label>
        <select id="package_size" name="package_size" class="form-control required-entry" required>
            <option value="">-- Select Size --</option>
        </select>
    </div>
</div>

<script>
require(['jquery', 'mage/url'], function($, url) {
    const suggestions = <?php echo json_encode($block->getAllSuggestions()); ?>;
    
    $('#product_category').on('change', function() {
        const categoryId = $(this).val();
        const categoryName = $(this).find('option:selected').text();
        
        if (!categoryId) {
            $('#size-suggestions').hide();
            return;
        }
        
        // Get suggestions for this category
        const suggested = getSuggestionsForCategory(categoryId);
        
        if (suggested && suggested.length > 0) {
            // Update dropdown with suggestions first
            let html = '<option value="">-- Select Size --</option>';
            suggested.forEach(function(size) {
                html += '<option value="' + size + '">' + size + '</option>';
            });
            
            // Add all other options
            const allOptions = $('body').data('all_sizes') || [];
            allOptions.forEach(function(size) {
                if (!suggested.includes(size)) {
                    html += '<option value="' + size + '">' + size + '</option>';
                }
            });
            
            $('#package_size').html(html);
            
            // Show suggestions
            let suggestionHtml = '';
            suggested.forEach(function(size) {
                suggestionHtml += '<span class="suggestion-chip" data-size="' + size + '">' + size + '</span>';
            });
            $('#suggestions-list').html(suggestionHtml);
            $('#category-name').text(categoryName);
            $('#size-suggestions').show();
            
            // Click to select
            $('.suggestion-chip').on('click', function() {
                const size = $(this).data('size');
                $('#package_size').val(size).change();
                $(this).addClass('selected');
            });
        } else {
            $('#size-suggestions').hide();
        }
    });
    
    function getSuggestionsForCategory(categoryId) {
        // You would load this from server or have it embedded
        return suggestions[categoryId] || [];
    }
});
</script>

<style>
.suggestion-container {
    background: #f5f5f5;
    padding: 15px;
    margin: 15px 0;
    border-radius: 4px;
    border-left: 3px solid #007bff;
}

.suggestion-items {
    margin-top: 10px;
}

.suggestion-chip {
    display: inline-block;
    background: #e3f2fd;
    border: 1px solid #90caf9;
    padding: 6px 12px;
    margin: 4px;
    cursor: pointer;
    border-radius: 20px;
    transition: all 0.2s;
}

.suggestion-chip:hover {
    background: #90caf9;
    color: white;
}

.suggestion-chip.selected {
    background: #1976d2;
    color: white;
    border-color: #1976d2;
}
</style>
```

---

## Complete Implementation Checklist

### Phase 1: Pre-configured Dropdown (Done)
- [x] AddPackageSizeAttribute.php patch
- [x] PackageSizeManager service
- [x] Basic form field in template

### Phase 2: Category Suggestions
- [ ] Integrate PackageSizeSuggestions in block
- [ ] Update form template with suggestions
- [ ] Add JavaScript for suggestion selection
- [ ] Create CSS for suggestion chips

### Phase 3: Request Form
- [ ] Create PackageSizeRequest model
- [ ] Create database schema patch
- [ ] Create Request form template
- [ ] Create RequestPackageSize block
- [ ] Create SaveRequestPackageSize controller
- [ ] Add to product edit template

### Phase 4: REST API
- [ ] Create VendorPackageSizeManagementInterface
- [ ] Create VendorPackageSizeManagement implementation
- [ ] Register routes in webapi.xml
- [ ] Update di.xml
- [ ] Test API endpoints

### Phase 5: Custom Text (Optional)
- [ ] Add custom_package_size attribute
- [ ] Update template with fallback field
- [ ] Add JavaScript for mutual exclusivity

---

## Testing Each Method

### Method 1: Dropdown
```
1. Load product edit form
2. Verify dropdown shows all options
3. Select size and save
4. Verify product has size attribute
```

### Method 2: Request Form
```
1. Click "Request Package Size"
2. Fill form and submit
3. Check database has entry with status=pending
4. Verify admin sees in request grid
5. Admin approves
6. Vendor sees dropdown now has new size
```

### Method 3: API
```
1. Get vendor token
2. POST /V1/vendor/package-size/request {size: "3.5kg"}
3. GET /V1/vendor/package-size/requests
4. Verify response has request data
```

### Method 4: Custom Text
```
1. Load product edit form
2. Enter text in custom field
3. Save product
4. Verify custom value stored in database
```

### Method 5: Suggestions
```
1. Select category in product form
2. Verify suggestions appear
3. Click suggestion - verify it populates dropdown
4. Save with suggested size
```

---

## Common Issues & Solutions

### Issue: Suggestions don't appear
**Solution:** Ensure `getAllSuggestions()` is available in block and category mapping is correct

### Issue: Request validation fails
**Solution:** Check vendor ID is correctly retrieved from `VendorRegistry`

### Issue: API returns 404
**Solution:** Verify `webapi.xml` routes are correct and module is enabled

### Issue: Dropdown is empty
**Solution:** Check `AddPackageSizeAttribute` patch was executed. Run: `php bin/magento setup:upgrade`

### Issue: Custom field not saving
**Solution:** Ensure `custom_package_size` attribute is created and in product form's save logic

---

That's the complete integration guide for all 5 methods!
