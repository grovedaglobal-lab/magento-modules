<?php
namespace Vendor\Marketplace\Model\Source;

use Magento\Customer\Model\ResourceModel\Customer\CollectionFactory;
use Magento\Framework\Data\OptionSourceInterface;

class Customer implements OptionSourceInterface
{
    /**
     * @var CollectionFactory
     */
    protected $customerCollectionFactory;

    /**
     * @param CollectionFactory $customerCollectionFactory
     */
    public function __construct(
        CollectionFactory $customerCollectionFactory
    ) {
        $this->customerCollectionFactory = $customerCollectionFactory;
    }

    /**
     * Get customer options
     *
     * @return array
     */
    public function toOptionArray()
    {
        $options = [
            ['value' => '', 'label' => __('-- Please Select --')]
        ];

        $collection = $this->customerCollectionFactory->create();
        $collection->addAttributeToSelect(['firstname', 'lastname', 'email']);

        foreach ($collection as $customer) {
            $label = sprintf(
                'ID: %s - %s %s (%s)',
                $customer->getId(),
                $customer->getFirstname(),
                $customer->getLastname(),
                $customer->getEmail()
            );

            $options[] = [
                'value' => $customer->getId(),
                'label' => $label
            ];
        }

        return $options;
    }
}
