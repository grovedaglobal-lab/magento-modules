<?php
namespace Vendor\Marketplace\Controller\Shipping;

use Magento\Framework\App\Action\HttpPostActionInterface;
use Vendor\Marketplace\Controller\AbstractVendor;
use Magento\Framework\App\Action\Context;
use Vendor\Marketplace\Model\Session\VendorSession;
use Vendor\Marketplace\Model\ResourceModel\VendorProfile\CollectionFactory as ProfileCollectionFactory;
use Vendor\Marketplace\Model\ResourceModel\ShippingRate\CollectionFactory as RateCollectionFactory;
use Vendor\Marketplace\Model\ShippingRateFactory;
use Magento\Framework\File\UploaderFactory;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Filesystem;
use Magento\Framework\App\Filesystem\DirectoryList;

class Save extends AbstractVendor implements HttpPostActionInterface
{
    /**
     * @var ProfileCollectionFactory
     */
    protected $_profileCollectionFactory;

    /**
     * @var RateCollectionFactory
     */
    protected $_rateCollectionFactory;

    /**
     * @var ShippingRateFactory
     */
    protected $_rateFactory;

    /**
     * @var UploaderFactory
     */
    protected $_uploaderFactory;

    /**
     * @var Filesystem
     */
    protected $_filesystem;

    /**
     * @param Context $context
     * @param VendorSession $vendorSession
     * @param ProfileCollectionFactory $profileCollectionFactory
     * @param RateCollectionFactory $rateCollectionFactory
     * @param ShippingRateFactory $rateFactory
     * @param UploaderFactory $uploaderFactory
     * @param Filesystem $filesystem
     */
    public function __construct(
        Context $context,
        VendorSession $vendorSession,
        ProfileCollectionFactory $profileCollectionFactory,
        RateCollectionFactory $rateCollectionFactory,
        ShippingRateFactory $rateFactory,
        UploaderFactory $uploaderFactory,
        Filesystem $filesystem
    ) {
        $this->_profileCollectionFactory = $profileCollectionFactory;
        $this->_rateCollectionFactory = $rateCollectionFactory;
        $this->_rateFactory = $rateFactory;
        $this->_uploaderFactory = $uploaderFactory;
        $this->_filesystem = $filesystem;
        parent::__construct($context, $vendorSession);
    }

    /**
     * @return \Magento\Framework\Controller\Result\Redirect
     */
    public function execute()
    {
        $redirect = $this->resultRedirectFactory->create();
        $vendorId = $this->_vendorSession->getVendorId();
        $data = $this->getRequest()->getPostValue();

        try {
            // 1. Save Profile Settings
            $profileCollection = $this->_profileCollectionFactory->create();
            $profileCollection->addFieldToFilter('vendor_id', $vendorId);
            $profile = $profileCollection->getFirstItem();

            if ($profile->getId()) {
                $profile->setShippingSource($data['shipping_source']);
                $profile->setShippingTaxClass($data['shipping_tax_class']);
                $profile->setFreeShippingAmount($data['free_shipping_amount']);
                $profile->save();
            }

            // 2. Handle Custom Rates Grid
            if (isset($data['rates']) && is_array($data['rates'])) {
                $this->saveManualRates($vendorId, $data['rates']);
            }

            // 3. Handle CSV Upload (Optional - Overwrites if present)
            if (!empty($_FILES['shipping_rates_csv']['name'])) {
                $this->importRates($vendorId);
            }

            $this->messageManager->addSuccessMessage(__('Shipping settings saved successfully.'));
        } catch (\Exception $e) {
            $this->messageManager->addErrorMessage(__('Error saving settings: %1', $e->getMessage()));
        }

        return $redirect->setPath('marketplace/shipping/index');
    }

    /**
     * @param int $vendorId
     * @return void
     */
    protected function importRates($vendorId)
    {
        $uploader = $this->_uploaderFactory->create(['fileId' => 'shipping_rates_csv']);
        $uploader->setAllowedExtensions(['csv']);
        $uploader->setAllowRenameFiles(true);
        $path = $this->_filesystem->getDirectoryRead(DirectoryList::VAR_DIR)->getAbsolutePath('tmp/vendor_shipping');
        $result = $uploader->save($path);

        $filePath = $path . '/' . $result['file'];
        if (($handle = fopen($filePath, "r")) !== FALSE) {
            // First clear existing rates for this vendor
            $rateCollection = $this->_rateCollectionFactory->create();
            $rateCollection->addFieldToFilter('vendor_id', $vendorId);
            foreach ($rateCollection as $rate) {
                $rate->delete();
            }

            $header = fgetcsv($handle, 1000, ","); // Skip header
            while (($row = fgetcsv($handle, 1000, ",")) !== FALSE) {
                if (count($row) < 6)
                    continue;

                $rateModel = $this->_rateFactory->create();
                $rateModel->setData([
                    'vendor_id' => $vendorId,
                    'country_id' => $row[0],
                    'region_id' => $row[1],
                    'zip_code' => $row[2],
                    'weight_from' => $row[3],
                    'weight_to' => $row[4],
                    'price' => $row[5]
                ]);
                $rateModel->save();
            }
            fclose($handle);
        }
    }

    /**
     * @param int $vendorId
     * @param array $rates
     */
    protected function saveManualRates($vendorId, array $rates)
    {
        // 1. Delete existing rates for this vendor (simplest validation strategy)
        // Alternatively, we could do more complex diffing, but full replace is safer for sync.
        // NOTE: If we want to keep CSV rates AND Grid rates, we should probably differentiate them,
        // but typically a user manages one source of truth.
        // For now, let's assume valid grid data REPLACES all previous rates to avoid duplicates.
        $collection = $this->_rateCollectionFactory->create();
        $collection->addFieldToFilter('vendor_id', $vendorId);
        $collection->walk('delete');

        // 2. Insert new rates
        foreach ($rates as $rateData) {
            if (empty($rateData['country_id']) || !isset($rateData['price'])) {
                continue;
            }

            $rateModel = $this->_rateFactory->create();
            $rateModel->setVendorId($vendorId);
            $rateModel->setCountryId($rateData['country_id']);
            $rateModel->setRegionId(isset($rateData['region_id']) ? $rateData['region_id'] : 0);
            $rateModel->setZipCode(isset($rateData['zip_code']) ? $rateData['zip_code'] : null);
            $rateModel->setWeightFrom(isset($rateData['weight_from']) ? $rateData['weight_from'] : 0);
            $rateModel->setWeightTo(isset($rateData['weight_to']) ? $rateData['weight_to'] : 10000);
            $rateModel->setPrice($rateData['price']);
            $rateModel->save();
        }
    }
}
