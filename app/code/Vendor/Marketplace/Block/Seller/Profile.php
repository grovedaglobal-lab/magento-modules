<?php
namespace Vendor\Marketplace\Block\Seller;

use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Magento\Customer\Model\Session as CustomerSession;
use Vendor\Marketplace\Model\VendorFactory;
use Vendor\Marketplace\Model\VendorProfileFactory;

class Profile extends Template
{
    protected $customerSession;
    protected $vendorRepository;
    protected $vendorProfileFactory;
    protected $vendorDocumentFactory;
    protected $currentVendor;
    protected $currentProfile;

    public function __construct(
        Context $context,
        CustomerSession $customerSession,
        \Vendor\Marketplace\Api\VendorRepositoryInterface $vendorRepository,
        VendorProfileFactory $vendorProfileFactory,
        \Vendor\Marketplace\Model\VendorDocumentFactory $vendorDocumentFactory,
        array $data = []
    ) {
        $this->customerSession = $customerSession;
        $this->vendorRepository = $vendorRepository;
        $this->vendorProfileFactory = $vendorProfileFactory;
        $this->vendorDocumentFactory = $vendorDocumentFactory;
        parent::__construct($context, $data);
    }

    public function getVendor()
    {
        if (!$this->currentVendor) {
            $customerId = $this->customerSession->getCustomerId();
            try {
                $this->currentVendor = $this->vendorRepository->getByCustomerId($customerId);
            } catch (\Exception $e) {
                return null;
            }
        }
        return $this->currentVendor;
    }

    public function getProfile()
    {
        if (!$this->currentProfile) {
            $profile = $this->vendorProfileFactory->create();
            $vendor = $this->getVendor();

            if ($vendor && $vendor->getId()) {
                $profile->load($vendor->getId(), 'vendor_id'); // Load by vendor_id

                // DEBUG LOGGING
                $writer = new \Zend_Log_Writer_Stream(BP . '/var/log/vendor_profile_debug.log');
                $logger = new \Zend_Log();
                $logger->addWriter($writer);
                $logger->info('Frontend Profile Load:');
                $logger->info('Vendor ID: ' . $vendor->getId());
                $logger->info('Profile ID Loaded: ' . $profile->getId());

                // Backfill Logic: If profile exists but data is missing, OR if new profile
                $customer = $this->customerSession->getCustomer();

                if ($customer && $customer->getId()) {
                    // Force logging to see if we reach here
                    $logger->info('Customer Found for Backfill: ' . $customer->getId());

                    // 1. Shop Name
                    if (empty($profile->getShopName())) { // Check for empty string too
                        $shopUrlName = $vendor->getShopUrl() ? ucfirst(str_replace('-', ' ', $vendor->getShopUrl())) : '';
                        $customerName = $customer->getFirstname() . ' ' . $customer->getLastname();
                        $val = $shopUrlName ?: $customerName;
                        $profile->setShopName($val);
                        $logger->info('Backfilled Shop Name: ' . $val);
                    }

                    // 2. Email (Use getData for safety if getEmail interface is missing)
                    if (empty($profile->getData('email'))) {
                        $profile->setData('email', $customer->getEmail());
                        $logger->info('Backfilled Email: ' . $customer->getEmail());
                    }

                    // 3. Address / Phone (Try Default Billing)
                    $billingAddress = $customer->getDefaultBillingAddress();
                    if ($billingAddress) {
                        if (empty($profile->getPhone()))
                            $profile->setPhone($billingAddress->getTelephone());
                        if (empty($profile->getCity()))
                            $profile->setCity($billingAddress->getCity());
                        if (empty($profile->getZipCode()))
                            $profile->setZipCode($billingAddress->getPostcode());
                        if (empty($profile->getCountry()))
                            $profile->setCountry($billingAddress->getCountryId());
                        if (empty($profile->getState()) && $billingAddress->getRegion())
                            $profile->setState($billingAddress->getRegion());
                        if (empty($profile->getAddress())) {
                            $street = $billingAddress->getStreet();
                            if (!empty($street)) {
                                $profile->setAddress(implode(', ', $street));
                            }
                        }
                    }
                }

                // Ensure Vendor ID is set if it was a new object
                if (!$profile->getVendorId()) {
                    $profile->setVendorId($vendor->getId());
                }
            }

            $this->currentProfile = $profile;
        }
        return $this->currentProfile;
    }

    public function getVendorDocuments()
    {
        $vendor = $this->getVendor();
        if (!$vendor || !$vendor->getId()) {
            return [];
        }

        $collection = $this->vendorDocumentFactory->create()->getCollection()
            ->addFieldToFilter('vendor_id', $vendor->getId());

        return $collection;
    }

    public function getOrganicCertifications()
    {
        $documents = $this->getVendorDocuments();
        if (!$documents)
            return [];

        $filtered = [];
        foreach ($documents as $doc) {
            if ($doc->getDocumentType() === 'organic_certification') {
                $filtered[] = $doc;
            }
        }
        return $filtered;
    }

    /**
     * Get COA reports
     *
     * @return array
     */
    public function getCoaReports()
    {
        $allDocuments = $this->getVendorDocuments();
        $coaReports = [];
        foreach ($allDocuments as $document) {
            if ($document->getDocumentType() === 'coa_report') {
                $coaReports[] = $document;
            }
        }
        return $coaReports;
    }

    /**
     * Get other documents (excluding organic certifications and COA reports)
     *
     * @return array
     */
    public function getOtherDocuments()
    {
        $allDocuments = $this->getVendorDocuments();
        $otherDocuments = [];
        $specialTypes = ['organic_certification', 'coa_report'];
        foreach ($allDocuments as $document) {
            if (!in_array($document->getDocumentType(), $specialTypes)) {
                $otherDocuments[] = $document;
            }
        }
        return $otherDocuments;
    }

    public function getSaveUrl()
    {
        return $this->getUrl('vendor_marketplace/seller/profilePost');
    }

    public function getMediaUrl()
    {
        return $this->_storeManager->getStore()->getBaseUrl(\Magento\Framework\UrlInterface::URL_TYPE_MEDIA);
    }
}
