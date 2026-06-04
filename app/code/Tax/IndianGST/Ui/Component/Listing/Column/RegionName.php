<?php
namespace Tax\IndianGST\Ui\Component\Listing\Column;

use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Framework\View\Element\UiComponentFactory;
use Magento\Ui\Component\Listing\Columns\Column;
use Magento\Directory\Model\RegionFactory;

class RegionName extends Column
{
    /**
     * @var RegionFactory
     */
    protected $regionFactory;

    public function __construct(
        ContextInterface $context,
        UiComponentFactory $uiComponentFactory,
        RegionFactory $regionFactory,
        array $components = [],
        array $data = []
    ) {
        $this->regionFactory = $regionFactory;
        parent::__construct($context, $uiComponentFactory, $components, $data);
    }

    public function prepareDataSource(array $dataSource)
    {
        if (isset($dataSource['data']['items'])) {
            // Bulk load regions to avoid loop queries if possible, 
            // but for simple grid straightforward load is fine for now or caching.
            foreach ($dataSource['data']['items'] as & $item) {
                if (isset($item['region_id']) && $item['region_id'] > 0) {
                    $region = $this->regionFactory->create()->load($item['region_id']);
                    if ($region->getId()) {
                        // Display Code (e.g., GJ) or Name (e.g., Gujarat)
                        $item[$this->getData('name')] = $region->getName() . ' (' . $region->getCode() . ')';
                    }
                }
            }
        }
        return $dataSource;
    }
}
