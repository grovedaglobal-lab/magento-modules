<?php
namespace Vendor\Marketplace\Model\Source\News;

use Magento\Framework\Data\OptionSourceInterface;
use Vendor\Marketplace\Model\ResourceModel\Vendor\CollectionFactory as VendorCollectionFactory;

class Sellers implements OptionSourceInterface
{
    /**
     * @var VendorCollectionFactory
     */
    protected $vendorCollectionFactory;

    /**
     * @param VendorCollectionFactory $vendorCollectionFactory
     */
    public function __construct(
        VendorCollectionFactory $vendorCollectionFactory
    ) {
        $this->vendorCollectionFactory = $vendorCollectionFactory;
    }

    /**
     * Get options
     *
     * @return array
     */
    public function toOptionArray()
    {
        $options = [];
        $collection = $this->vendorCollectionFactory->create();

        $collection->getSelect()->join(
            ['vp' => $collection->getTable('vendor_profile')],
            'main_table.entity_id = vp.vendor_id',
            ['shop_name']
        );

        foreach ($collection as $vendor) {
            $options[] = [
                'value' => $vendor->getId(),
                'label' => $vendor->getData('shop_name') ?: $vendor->getId()
            ];
        }

        return $options;
    }
}
