<?php
namespace Vendor\Marketplace\Model\Config\Source;

use Magento\Eav\Model\Entity\Attribute\Source\AbstractSource;
use Vendor\Marketplace\Model\ResourceModel\Vendor\CollectionFactory as VendorCollectionFactory;
use Vendor\Marketplace\Model\ResourceModel\VendorProfile\CollectionFactory as VendorProfileCollectionFactory;

class VendorList extends AbstractSource
{
    /**
     * @var VendorCollectionFactory
     */
    protected $vendorCollectionFactory;

    /**
     * @var VendorProfileCollectionFactory
     */
    protected $vendorProfileCollectionFactory;

    /**
     * @param VendorCollectionFactory $vendorCollectionFactory
     * @param VendorProfileCollectionFactory $vendorProfileCollectionFactory
     */
    public function __construct(
        VendorCollectionFactory $vendorCollectionFactory,
        VendorProfileCollectionFactory $vendorProfileCollectionFactory
    ) {
        $this->vendorCollectionFactory = $vendorCollectionFactory;
        $this->vendorProfileCollectionFactory = $vendorProfileCollectionFactory;
    }

    /**
     * GetAllOptions
     *
     * @return array
     */
    public function getAllOptions()
    {
        if ($this->_options === null) {
            $this->_options = [['label' => __('Select Vendor'), 'value' => '']];

            // Get Vendors
            $collection = $this->vendorCollectionFactory->create();
            
            // We need to join profile to get the name
            // Assuming vendor_entity has entity_id and vendor_profile has vendor_id and shop_name
            // We can actually just iterate and load profile, but that's n+1.
            // Better to join. The Vendor ResourceModel usually links to vendor_entity table.
            
            // Let's manually join or use the profile collection if it's easier.
            // Profile collection has vendor_id.
            
            $profileCollection = $this->vendorProfileCollectionFactory->create();
            $profileCollection->addFieldToSelect(['vendor_id', 'shop_name']);
            
            // We should probably check if vendor is active in vendor_entity table if needed, 
            // but for now listing all profiles with names is good.
            
            foreach ($profileCollection as $profile) {
                $vendorId = $profile->getVendorId();
                $shopName = $profile->getShopName();
                $label = $vendorId . ' - ' . ($shopName ?: 'Unknown');
                
                $this->_options[] = [
                    'label' => $label,
                    'value' => $vendorId
                ];
            }
        }
        return $this->_options;
    }
}
