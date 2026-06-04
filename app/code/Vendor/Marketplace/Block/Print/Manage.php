<?php
namespace Vendor\Marketplace\Block\Print;

use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Vendor\Marketplace\Model\VendorFactory;
use Vendor\Marketplace\Model\VendorProfileFactory;
use Magento\Customer\Model\Session as CustomerSession;

class Manage extends Template
{
    protected $_currentVendor;
    protected $_currentProfile;
    protected $vendorFactory;
    protected $vendorProfileFactory;
    protected $customerSession;

    public function __construct(
        Context $context,
        VendorFactory $vendorFactory,
        VendorProfileFactory $vendorProfileFactory,
        CustomerSession $customerSession,
        array $data = []
    ) {
        $this->vendorFactory = $vendorFactory;
        $this->vendorProfileFactory = $vendorProfileFactory;
        $this->customerSession = $customerSession;
        parent::__construct($context, $data);
    }

    protected function _prepareLayout()
    {
        $this->pageConfig->getTitle()->set(__('Manage Print Settings'));
        return parent::_prepareLayout();
    }

    public function getVendor()
    {
        if (!$this->_currentVendor) {
            $customerId = $this->customerSession->getCustomerId();
            if ($customerId) {
                // Assuming vendor exists for customer, or fallback
                $this->_currentVendor = $this->vendorFactory->create()->load($customerId, 'customer_id');
            }
        }
        return $this->_currentVendor;
    }

    public function getProfile()
    {
        if (!$this->_currentProfile) {
            $vendor = $this->getVendor();
            if ($vendor && $vendor->getId()) {
                $this->_currentProfile = $this->vendorProfileFactory->create()->load($vendor->getId(), 'vendor_id');
            }
        }
        return $this->_currentProfile;
    }

    public function getAuthorizedName()
    {
        $profile = $this->getProfile();
        return $profile ? $profile->getData('authorized_name') : '';
    }

    public function getSignatureUrl()
    {
        $profile = $this->getProfile();
        if ($profile && $profile->getData('signature')) {
            $mediaUrl = $this->_storeManager->getStore()->getBaseUrl(\Magento\Framework\UrlInterface::URL_TYPE_MEDIA);
            return $mediaUrl . 'vendor/signature/' . ltrim($profile->getData('signature'), '/');
        }
        return '';
    }

    public function getSaveUrl()
    {
        return $this->getUrl('marketplace/print/save');
    }
}
