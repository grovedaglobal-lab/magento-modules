<?php
declare(strict_types=1);

namespace Vendor\Marketplace\Block\Account;

use Magento\Customer\Model\AccountManagement;
use Magento\Customer\Model\Session;
use Magento\Customer\Model\Url;
use Magento\Framework\DataObject;
use Magento\Framework\View\Element\Template;

class Register extends Template
{
    public function __construct(
        Template\Context $context,
        private readonly Session $customerSession,
        private readonly Url $customerUrl,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->_isScopePrivate = false;
    }

    public function getAction(): string
    {
        return $this->getUrl('vendor_marketplace/account/createPost');
    }

    public function getBackUrl(): string
    {
        $url = (string)$this->getData('back_url');

        return $url !== '' ? $url : $this->getUrl('marketplace/account/login');
    }

    public function getSuccessUrl(): string
    {
        return $this->getUrl('vendor_marketplace/seller/dashboard');
    }

    public function getErrorUrl(): string
    {
        return $this->getUrl('vendor_marketplace/account/create');
    }

    public function getFormData(): DataObject
    {
        $data = $this->getData('form_data');
        if ($data instanceof DataObject) {
            return $data;
        }

        $formData = $this->customerSession->getCustomerFormData(true);
        $data = new DataObject();

        if ($formData) {
            $data->addData($formData);
            $data->setCustomerData(1);
        }

        if ($data->getData('region_id')) {
            $data->setData('region_id', (int)$data->getData('region_id'));
        }

        $this->setData('form_data', $data);

        return $data;
    }

    public function getMinimumPasswordLength(): string
    {
        return (string)$this->_scopeConfig->getValue(AccountManagement::XML_PATH_MINIMUM_PASSWORD_LENGTH);
    }

    public function getRequiredCharacterClassesNumber(): string
    {
        return (string)$this->_scopeConfig->getValue(AccountManagement::XML_PATH_REQUIRED_CHARACTER_CLASSES_NUMBER);
    }
}
