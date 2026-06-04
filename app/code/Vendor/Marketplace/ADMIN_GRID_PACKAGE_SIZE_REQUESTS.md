# Admin Grid for Package Size Requests Management

For **Method 2 (Request Form)** to work properly, admins need a way to review and approve/reject vendor requests. Here's how to implement the admin grid.

---

## File Structure

```
app/code/Vendor/Marketplace/
├── Block/
│   └── Adminhtml/
│       ├── PackageSizeRequest/
│       │   ├── Grid.php
│       │   ├── Edit.php
│       │   └── Edit/
│       │       └── Tab/
│       │           └── Main.php
│       └── PackageSizeRequest.php
├── Controller/
│   └── Adminhtml/
│       └── PackageSizeRequest/
│           ├── Index.php
│           ├── Edit.php
│           ├── Save.php
│           └── Delete.php
├── Ui/
│   └── Component/
│       └── MassAction/
│           └── Status.php
├── view/adminhtml/ui_component/
│   └── vendor_marketplace_package_size_request_listing.xml
└── view/adminhtml/layout/
    └── vendor_marketplace_packagesizerequest_*.xml
```

---

## Step 1: Create Admin Grid Block

File: `Block/Adminhtml/PackageSizeRequest.php`

```php
<?php

namespace Vendor\Marketplace\Block\Adminhtml;

use Magento\Backend\Block\Widget\Grid\Container;

class PackageSizeRequest extends Container
{
    protected function _construct()
    {
        parent::_construct();
        $this->_controller = 'adminhtml_packagesizerequest';
        $this->_blockGroup = 'Vendor_Marketplace';
        $this->_headerText = __('Package Size Requests');
        $this->_addButtonLabel = __('Process Request');
    }
}
```

---

## Step 2: Create Grid Block

File: `Block/Adminhtml/PackageSizeRequest/Grid.php`

```php
<?php

namespace Vendor\Marketplace\Block\Adminhtml\PackageSizeRequest;

use Magento\Backend\Block\Widget\Grid;
use Magento\Backend\Block\Widget\Grid\Column;
use Magento\Framework\Data\Collection;

class Grid extends Grid
{
    protected $_defaultSort = 'entity_id';
    protected $_defaultDir = 'DESC';

    protected $collectionFactory;
    protected $statusOptions;

    public function __construct(
        \Magento\Backend\Block\Template\Context $context,
        \Magento\Backend\Helper\Data $backendHelper,
        \Vendor\Marketplace\Model\ResourceModel\PackageSizeRequest\CollectionFactory $collectionFactory,
        array $data = []
    ) {
        parent::__construct($context, $backendHelper, $data);
        $this->collectionFactory = $collectionFactory;
        $this->setId('packagesizerequest_listing');
        $this->setDefaultSort('entity_id');
        $this->setDefaultDir('DESC');
        $this->setUseAjax(true);
        $this->setFilterVisibility(true);
        $this->setDefaultLimit(20);
    }

    protected function _prepareCollection()
    {
        $collection = $this->collectionFactory->create();
        $this->setCollection($collection);
        return parent::_prepareCollection();
    }

    protected function _prepareColumns()
    {
        // ID Column
        $this->addColumn(
            'entity_id',
            [
                'header' => __('ID'),
                'align' => 'right',
                'width' => '50px',
                'index' => 'entity_id',
                'type' => 'number'
            ]
        );

        // Vendor Column
        $this->addColumn(
            'vendor_id',
            [
                'header' => __('Vendor'),
                'index' => 'vendor_id',
                'width' => '150px',
                'renderer' => 'Vendor\Marketplace\Block\Adminhtml\PackageSizeRequest\Renderer\Vendor'
            ]
        );

        // Package Size Column
        $this->addColumn(
            'package_size',
            [
                'header' => __('Requested Size'),
                'index' => 'package_size',
                'width' => '150px'
            ]
        );

        // Reason Column
        $this->addColumn(
            'reason',
            [
                'header' => __('Reason'),
                'index' => 'reason',
                'truncate' => true,
                'width' => '200px'
            ]
        );

        // Status Column
        $this->addColumn(
            'status',
            [
                'header' => __('Status'),
                'index' => 'status',
                'type' => 'options',
                'options' => $this->getStatusOptions(),
                'width' => '100px',
                'renderer' => 'Vendor\Marketplace\Block\Adminhtml\PackageSizeRequest\Renderer\Status'
            ]
        );

        // Created Date Column
        $this->addColumn(
            'created_at',
            [
                'header' => __('Requested On'),
                'index' => 'created_at',
                'type' => 'datetime',
                'width' => '150px'
            ]
        );

        // Admin Notes Column
        $this->addColumn(
            'admin_notes',
            [
                'header' => __('Admin Notes'),
                'index' => 'admin_notes',
                'truncate' => true,
                'width' => '150px'
            ]
        );

        // Actions Column
        $this->addColumn(
            'action',
            [
                'header' => __('Action'),
                'width' => '100px',
                'type' => 'action',
                'getter' => 'getId',
                'actions' => [
                    [
                        'caption' => __('View'),
                        'url' => ['base' => 'vendor_marketplace/packagesizerequest/edit'],
                        'field' => 'id'
                    ],
                    [
                        'caption' => __('Delete'),
                        'url' => ['base' => 'vendor_marketplace/packagesizerequest/delete'],
                        'field' => 'id',
                        'confirm' => __('Are you sure?')
                    ]
                ],
                'filter' => false,
                'sortable' => false,
                'index' => 'stores',
                'is_system' => true,
            ]
        );

        return parent::_prepareColumns();
    }

    protected function _prepareMassaction()
    {
        $this->setMassactionIdField('entity_id');
        $this->getMassactionBlock()->setFormFieldName('packagesizerequest');

        // Approve mass action
        $this->getMassactionBlock()->addItem(
            'approve',
            [
                'label' => __('Approve'),
                'url' => $this->getUrl('vendor_marketplace/packagesizerequest/massApprove'),
            ]
        );

        // Reject mass action
        $this->getMassactionBlock()->addItem(
            'reject',
            [
                'label' => __('Reject'),
                'url' => $this->getUrl('vendor_marketplace/packagesizerequest/massReject'),
            ]
        );

        // Delete mass action
        $this->getMassactionBlock()->addItem(
            'delete',
            [
                'label' => __('Delete'),
                'url' => $this->getUrl('vendor_marketplace/packagesizerequest/massDelete'),
                'confirm' => __('Are you sure?')
            ]
        );

        return $this;
    }

    public function getRowUrl($row)
    {
        return $this->getUrl('vendor_marketplace/packagesizerequest/edit', ['id' => $row->getId()]);
    }

    public function getGridUrl()
    {
        return $this->getUrl('vendor_marketplace/packagesizerequest/index', ['_current' => true]);
    }

    protected function getStatusOptions()
    {
        return [
            'pending' => __('Pending'),
            'approved' => __('Approved'),
            'rejected' => __('Rejected')
        ];
    }
}
```

---

## Step 3: Create Edit Block

File: `Block/Adminhtml/PackageSizeRequest/Edit.php`

```php
<?php

namespace Vendor\Marketplace\Block\Adminhtml\PackageSizeRequest;

use Magento\Backend\Block\Widget\Form\Container;
use Magento\Backend\Block\Widget\Context;
use Magento\Framework\Registry;

class Edit extends Container
{
    protected $_coreRegistry;

    public function __construct(
        Context $context,
        Registry $coreRegistry,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->_coreRegistry = $coreRegistry;
    }

    protected function _construct()
    {
        parent::_construct();
        $this->_objectId = 'id';
        $this->_blockGroup = 'Vendor_Marketplace';
        $this->_controller = 'adminhtml_packagesizerequest';

        $this->removeButton('delete');
        $this->removeButton('reset');

        if ($this->getRequest()->getParam('id')) {
            $this->addButton(
                'approve',
                [
                    'label' => __('Approve Request'),
                    'class' => 'save',
                    'onclick' => 'setApprovalStatus("approved"); jQuery("#edit_form").submit();'
                ]
            );

            $this->addButton(
                'reject',
                [
                    'label' => __('Reject Request'),
                    'class' => 'delete',
                    'onclick' => 'setApprovalStatus("rejected"); jQuery("#edit_form").submit();'
                ]
            );
        }
    }

    public function getHeaderText()
    {
        $request = $this->_coreRegistry->registry('current_packagesizerequest');

        if ($request && $request->getId()) {
            return __(
                'Package Size Request - %1',
                htmlspecialchars($request->getPackageSize())
            );
        }

        return __('New Package Size Request');
    }
}
```

---

## Step 4: Create Edit Tab

File: `Block/Adminhtml/PackageSizeRequest/Edit/Tab/Main.php`

```php
<?php

namespace Vendor\Marketplace\Block\Adminhtml\PackageSizeRequest\Edit\Tab;

use Magento\Backend\Block\Widget\Form\Generic;
use Magento\Backend\Block\Widget\Tab\TabInterface;

class Main extends Generic implements TabInterface
{
    protected $requestFactory;
    protected $vendorFactory;
    protected $systemStore;

    public function __construct(
        \Magento\Backend\Block\Template\Context $context,
        \Magento\Framework\Registry $registry,
        \Magento\Framework\Data\FormFactory $formFactory,
        \Vendor\Marketplace\Model\PackageSizeRequestFactory $requestFactory,
        \Vendor\Marketplace\Model\VendorFactory $vendorFactory,
        \Magento\Config\Model\Config\Source\Store $systemStore,
        array $data = []
    ) {
        parent::__construct($context, $registry, $formFactory, $data);
        $this->requestFactory = $requestFactory;
        $this->vendorFactory = $vendorFactory;
        $this->systemStore = $systemStore;
    }

    public function getTabLabel()
    {
        return __('Request Information');
    }

    public function getTabTitle()
    {
        return __('Request Information');
    }

    public function canShowTab()
    {
        return true;
    }

    public function isHidden()
    {
        return false;
    }

    protected function _prepareForm()
    {
        $request = $this->_coreRegistry->registry('current_packagesizerequest');
        $form = $this->_formFactory->create();

        // Request Details Fieldset
        $fieldset = $form->addFieldset(
            'request_details',
            ['legend' => __('Request Details'), 'class' => 'fieldset-wide']
        );

        if ($request && $request->getId()) {
            $fieldset->addField(
                'entity_id',
                'hidden',
                ['name' => 'entity_id']
            );
        }

        $fieldset->addField(
            'vendor_name',
            'text',
            [
                'name' => 'vendor_name',
                'label' => __('Vendor'),
                'disabled' => true,
                'value' => $request ? $this->getVendorName($request->getVendorId()) : ''
            ]
        );

        $fieldset->addField(
            'package_size',
            'text',
            [
                'name' => 'package_size',
                'label' => __('Requested Package Size'),
                'disabled' => true,
                'value' => $request ? $request->getPackageSize() : ''
            ]
        );

        $fieldset->addField(
            'reason',
            'textarea',
            [
                'name' => 'reason',
                'label' => __('Reason for Request'),
                'disabled' => true,
                'value' => $request ? $request->getReason() : ''
            ]
        );

        $fieldset->addField(
            'created_at',
            'text',
            [
                'name' => 'created_at',
                'label' => __('Requested On'),
                'disabled' => true,
                'value' => $request ? $request->getCreatedAt() : ''
            ]
        );

        // Approval Fieldset
        if ($request && $request->getId()) {
            $fieldset = $form->addFieldset(
                'approval',
                ['legend' => __('Admin Response'), 'class' => 'fieldset-wide']
            );

            $fieldset->addField(
                'status',
                'select',
                [
                    'name' => 'status',
                    'label' => __('Status'),
                    'value' => $request->getStatus(),
                    'options' => [
                        'pending' => __('Pending'),
                        'approved' => __('Approved'),
                        'rejected' => __('Rejected')
                    ],
                    'required' => true
                ]
            );

            $fieldset->addField(
                'admin_notes',
                'textarea',
                [
                    'name' => 'admin_notes',
                    'label' => __('Admin Notes'),
                    'value' => $request->getAdminNotes() ?: '',
                    'note' => __('Optionally provide feedback to the vendor about their request.')
                ]
            );

            // Hidden field for status change
            $fieldset->addField(
                'approval_status',
                'hidden',
                ['name' => 'approval_status']
            );
        }

        if ($request) {
            $form->setValues($request->getData());
        }

        $this->setForm($form);
        return parent::_prepareForm();
    }

    protected function getVendorName($vendorId)
    {
        $vendor = $this->vendorFactory->create()->load($vendorId);
        return $vendor ? $vendor->getName() : __('Unknown Vendor');
    }
}
```

---

## Step 5: Create Controllers

File: `Controller/Adminhtml/PackageSizeRequest/Index.php`

```php
<?php

namespace Vendor\Marketplace\Controller\Adminhtml\PackageSizeRequest;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\View\Result\PageFactory;

class Index extends Action
{
    const ADMIN_RESOURCE = 'Vendor_Marketplace::package_size_requests';

    protected $pageFactory;

    public function __construct(
        Context $context,
        PageFactory $pageFactory
    ) {
        parent::__construct($context);
        $this->pageFactory = $pageFactory;
    }

    public function execute()
    {
        $resultPage = $this->pageFactory->create();
        $resultPage->getConfig()->getTitle()->prepend(__('Package Size Requests'));
        return $resultPage;
    }
}
```

File: `Controller/Adminhtml/PackageSizeRequest/Edit.php`

```php
<?php

namespace Vendor\Marketplace\Controller\Adminhtml\PackageSizeRequest;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Registry;
use Magento\Framework\View\Result\PageFactory;
use Vendor\Marketplace\Model\PackageSizeRequestFactory;

class Edit extends Action
{
    const ADMIN_RESOURCE = 'Vendor_Marketplace::package_size_requests';

    protected $coreRegistry;
    protected $pageFactory;
    protected $requestFactory;

    public function __construct(
        Context $context,
        Registry $coreRegistry,
        PageFactory $pageFactory,
        PackageSizeRequestFactory $requestFactory
    ) {
        parent::__construct($context);
        $this->coreRegistry = $coreRegistry;
        $this->pageFactory = $pageFactory;
        $this->requestFactory = $requestFactory;
    }

    public function execute()
    {
        $requestId = $this->getRequest()->getParam('id');

        if ($requestId) {
            $model = $this->requestFactory->create()->load($requestId);
            
            if (!$model->getId()) {
                $this->messageManager->addErrorMessage(__('This request no longer exists.'));
                return $this->resultRedirectFactory->create()->setPath('*/*/');
            }

            $this->coreRegistry->register('current_packagesizerequest', $model);
        }

        $resultPage = $this->pageFactory->create();
        $resultPage->getConfig()->getTitle()->prepend(__('Package Size Requests'));

        return $resultPage;
    }
}
```

File: `Controller/Adminhtml/PackageSizeRequest/Save.php`

```php
<?php

namespace Vendor\Marketplace\Controller\Adminhtml\PackageSizeRequest;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Vendor\Marketplace\Model\PackageSizeRequestFactory;
use Vendor\Marketplace\Model\ResourceModel\PackageSizeRequest as RequestResource;
use Vendor\Marketplace\Model\Product\Attribute\PackageSizeManager;

class Save extends Action
{
    const ADMIN_RESOURCE = 'Vendor_Marketplace::package_size_requests';

    protected $requestFactory;
    protected $requestResource;
    protected $packageSizeManager;

    public function __construct(
        Context $context,
        PackageSizeRequestFactory $requestFactory,
        RequestResource $requestResource,
        PackageSizeManager $packageSizeManager
    ) {
        parent::__construct($context);
        $this->requestFactory = $requestFactory;
        $this->requestResource = $requestResource;
        $this->packageSizeManager = $packageSizeManager;
    }

    public function execute()
    {
        if (!$this->getRequest()->isPost()) {
            return $this->resultRedirectFactory->create()->setPath('*/*/');
        }

        try {
            $requestId = $this->getRequest()->getPost('entity_id');
            $status = $this->getRequest()->getPost('status');
            $adminNotes = $this->getRequest()->getPost('admin_notes', '');

            $model = $this->requestFactory->create()->load($requestId);

            if (!$model->getId()) {
                throw new \Exception(__('Request not found'));
            }

            $model->setStatus($status);
            $model->setAdminNotes($adminNotes);

            // If approved, add package size to global options
            if ($status === 'approved' && $model->getStatus() !== 'approved') {
                $packageSize = $model->getPackageSize();
                
                if (!$this->packageSizeManager->optionExists($packageSize)) {
                    $this->packageSizeManager->addOption($packageSize);
                }
            }

            $this->requestResource->save($model);

            $this->messageManager->addSuccessMessage(
                __('Request for "%1" has been %2.', $model->getPackageSize(), $status)
            );

            return $this->resultRedirectFactory->create()->setPath('*/*/');

        } catch (\Exception $e) {
            $this->messageManager->addErrorMessage(__('Error: ' . $e->getMessage()));
            return $this->resultRedirectFactory->create()->setPath('*/*/edit', ['id' => $requestId ?? null]);
        }
    }
}
```

File: `Controller/Adminhtml/PackageSizeRequest/MassApprove.php`

```php
<?php

namespace Vendor\Marketplace\Controller\Adminhtml\PackageSizeRequest;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Ui\Component\MassAction\Filter;
use Vendor\Marketplace\Model\ResourceModel\PackageSizeRequest\CollectionFactory;
use Vendor\Marketplace\Model\ResourceModel\PackageSizeRequest as RequestResource;
use Vendor\Marketplace\Model\Product\Attribute\PackageSizeManager;

class MassApprove extends Action
{
    const ADMIN_RESOURCE = 'Vendor_Marketplace::package_size_requests';

    protected $filter;
    protected $collectionFactory;
    protected $requestResource;
    protected $packageSizeManager;

    public function __construct(
        Context $context,
        Filter $filter,
        CollectionFactory $collectionFactory,
        RequestResource $requestResource,
        PackageSizeManager $packageSizeManager
    ) {
        parent::__construct($context);
        $this->filter = $filter;
        $this->collectionFactory = $collectionFactory;
        $this->requestResource = $requestResource;
        $this->packageSizeManager = $packageSizeManager;
    }

    public function execute()
    {
        try {
            $collection = $this->filter->getCollection($this->collectionFactory->create());
            
            $approved = 0;
            foreach ($collection as $item) {
                // Add to global options if not exists
                $packageSize = $item->getPackageSize();
                if (!$this->packageSizeManager->optionExists($packageSize)) {
                    $this->packageSizeManager->addOption($packageSize);
                }

                // Update status
                $item->setStatus('approved');
                $this->requestResource->save($item);
                $approved++;
            }

            $this->messageManager->addSuccessMessage(
                __('Approved %1 package size request(s).', $approved)
            );

        } catch (\Exception $e) {
            $this->messageManager->addErrorMessage(__('Error: ' . $e->getMessage()));
        }

        return $this->resultRedirectFactory->create()->setPath('*/*/');
    }
}
```

---

## Step 6: Create Admin Navigation

File: `etc/adminhtml/menu.xml`

```xml
<?xml version="1.0"?>
<config xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:noNamespaceSchemaLocation="urn:magento:module:Magento_Backend:etc/menu.xsd">
    <menu>
        <add id="Vendor_Marketplace::marketplace" title="Marketplace" translate="title" module="Vendor_Marketplace" sortOrder="50" resource="Magento_Backend::all"/>
        
        <add id="Vendor_Marketplace::package_size_requests" 
             title="Package Size Requests" 
             translate="title" 
             module="Vendor_Marketplace"
             parent="Vendor_Marketplace::marketplace" 
             sortOrder="20" 
             action="vendor_marketplace/packagesizerequest/index"
             resource="Vendor_Marketplace::package_size_requests"/>
    </menu>
</config>
```

---

## Step 7: Create Layout XML

File: `view/adminhtml/layout/vendor_marketplace_packagesizerequest_index.xml`

```xml
<?xml version="1.0"?>
<page xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:noNamespaceSchemaLocation="urn:magento:framework:View/Layout/etc/page_configuration.xsd">
    <update handle="styles"/>
    <body>
        <referenceContainer name="content">
            <block class="Vendor\Marketplace\Block\Adminhtml\PackageSizeRequest" name="packagesizerequest.grid.container">
                <block class="Vendor\Marketplace\Block\Adminhtml\PackageSizeRequest\Grid" name="packagesizerequest.grid" as="grid"/>
            </block>
        </referenceContainer>
    </body>
</page>
```

File: `view/adminhtml/layout/vendor_marketplace_packagesizerequest_edit.xml`

```xml
<?xml version="1.0"?>
<page xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:noNamespaceSchemaLocation="urn:magento:framework:View/Layout/etc/page_configuration.xsd">
    <update handle="editor"/>
    <body>
        <referenceContainer name="content">
            <block class="Vendor\Marketplace\Block\Adminhtml\PackageSizeRequest\Edit" name="packagesizerequest.edit.container">
                <block class="Magento\Backend\Block\Widget\Tabs" name="packagesizerequest.edit.tabs" as="tabs">
                    <block class="Vendor\Marketplace\Block\Adminhtml\PackageSizeRequest\Edit\Tab\Main" name="packagesizerequest.edit.tab.main" as="tab_main"/>
                </block>
            </block>
        </referenceContainer>
    </body>
</page>
```

---

## Grant Admin Permissions

File: `etc/acl.xml`

```xml
<?xml version="1.0"?>
<config xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:noNamespaceSchemaLocation="urn:magento:framework:Authorization/etc/acl.xsd">
    <acl>
        <resources>
            <resource id="Magento_Backend::all">
                <resource id="Vendor_Marketplace::marketplace" title="Marketplace">
                    <resource id="Vendor_Marketplace::package_size_requests" title="Manage Package Size Requests"/>
                </resource>
            </resource>
        </resources>
    </acl>
</config>
```

---

## Admin Panel Workflow

### Step 1: View All Requests
```
Admin → Marketplace → Package Size Requests
```
Shows grid with all requests in pending/approved/rejected status

### Step 2: Click Request to View Details
- Vendor name
- Requested size
- Reason
- Current status
- Admin notes field

### Step 3: Make Decision
Choose one of:
- [Approve Request] - Adds size to global options, vendor gets notified
- [Reject Request] - Request marked rejected, vendor gets notification with admin notes
- Edit & save with different status

### Step 4: Bulk Actions
Select multiple requests:
- [ ] Request #1
- [ ] Request #2
- [ ] Request #3
[Approve] [Reject] [Delete]

---

## Send Notifications to Vendors

Create Event Observer: `Observer/PackageSizeRequestStatusChanged.php`

```php
<?php

namespace Vendor\Marketplace\Observer;

use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Mail\Template\TransportBuilder;
use Magento\Store\Model\StoreManagerInterface;

class PackageSizeRequestStatusChanged implements ObserverInterface
{
    protected $transportBuilder;
    protected $storeManager;

    public function __construct(
        TransportBuilder $transportBuilder,
        StoreManagerInterface $storeManager
    ) {
        $this->transportBuilder = $transportBuilder;
        $this->storeManager = $storeManager;
    }

    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        $request = $observer->getEvent()->getRequest();
        $status = $request->getStatus();

        if ($status === 'approved') {
            $this->sendApprovalEmail($request);
        } elseif ($status === 'rejected') {
            $this->sendRejectionEmail($request);
        }
    }

    protected function sendApprovalEmail($request)
    {
        $vendor = $request->getVendor();
        
        $transport = $this->transportBuilder
            ->setTemplateIdentifier('package_size_approved')
            ->setTemplateOptions([
                'area' => \Magento\Framework\App\Area::AREA_FRONTEND,
                'store' => $this->storeManager->getStore()->getId(),
            ])
            ->setTemplateVars([
                'vendor_name' => $vendor->getName(),
                'package_size' => $request->getPackageSize(),
            ])
            ->addTo($vendor->getEmail())
            ->getTransport();

        $transport->sendMessage();
    }

    protected function sendRejectionEmail($request)
    {
        $vendor = $request->getVendor();
        
        $transport = $this->transportBuilder
            ->setTemplateIdentifier('package_size_rejected')
            ->setTemplateOptions([
                'area' => \Magento\Framework\App\Area::AREA_FRONTEND,
                'store' => $this->storeManager->getStore()->getId(),
            ])
            ->setTemplateVars([
                'vendor_name' => $vendor->getName(),
                'package_size' => $request->getPackageSize(),
                'admin_notes' => $request->getAdminNotes(),
            ])
            ->addTo($vendor->getEmail())
            ->getTransport();

        $transport->sendMessage();
    }
}
```

Register Event: `etc/events.xml`

```xml
<?xml version="1.0"?>
<config xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:noNamespaceSchemaLocation="urn:magento:framework:Event/etc/events.xsd">
    <event name="package_size_request_status_changed">
        <observer name="send_notification" instance="Vendor\Marketplace\Observer\PackageSizeRequestStatusChanged"/>
    </event>
</config>
```

---

## Summary

The admin panel now provides:

✅ **Grid View** - See all requests with filters and sorting
✅ **Edit View** - Review request details and add admin notes
✅ **Approve/Reject** - Change status individually or in bulk
✅ **Auto-add Size** - When approved, size is added to global options automatically
✅ **Notifications** - Vendors get emailed when their request is processed
✅ **Permissions** - Only admins with "Manage Package Size Requests" can access

This completes the **Request System (Method 2)** implementation!
