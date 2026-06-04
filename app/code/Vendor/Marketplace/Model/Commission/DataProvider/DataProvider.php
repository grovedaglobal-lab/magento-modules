<?php
namespace Vendor\Marketplace\Model\Commission\DataProvider;

use Vendor\Marketplace\Model\ResourceModel\VendorCommission\CollectionFactory;
use Magento\Framework\App\Request\DataPersistorInterface;
use Magento\Ui\DataProvider\AbstractDataProvider;

class DataProvider extends AbstractDataProvider
{
    /**
     * @var \Vendor\Marketplace\Model\ResourceModel\VendorCommission\Collection
     */
    protected $collection;

    /**
     * @var CollectionFactory
     */
    protected $collectionFactory;

    /**
     * @var DataPersistorInterface
     */
    protected $dataPersistor;

    /**
     * @var array
     */
    protected $loadedData;

    /**
     * @param string $name
     * @param string $primaryFieldName
     * @param string $requestFieldName
     * @param CollectionFactory $collectionFactory
     * @param DataPersistorInterface $dataPersistor
     * @param array $meta
     * @param array $data
     */
    public function __construct(
        $name,
        $primaryFieldName,
        $requestFieldName,
        CollectionFactory $collectionFactory,
        DataPersistorInterface $dataPersistor,
        array $meta = [],
        array $data = []
    ) {
        $this->collection = $collectionFactory->create();
        $this->collectionFactory = $collectionFactory;
        $this->dataPersistor = $dataPersistor;
        parent::__construct($name, $primaryFieldName, $requestFieldName, $meta, $data);
    }

    /**
     * Get data
     *
     * @return array
     */
    public function getData()
    {
        if (isset($this->loadedData)) {
            return $this->loadedData;
        }
        $this->loadedData = [];
        $items = $this->collection->getItems();
        foreach ($items as $model) {
            $data = $model->getData();
            $categoryId = $model->getCategoryId();

            if ($categoryId) {
                // Load all ranges for this category to populate dynamic rows
                $rangeCollection = $this->collectionFactory->create();
                $rangeCollection->addFieldToFilter('category_id', $categoryId);
                $rangeCollection->setOrder('min_price', 'ASC');

                $ranges = [];
                $position = 0;
                foreach ($rangeCollection as $rangeModel) {
                    $rangeData = $rangeModel->getData();
                    $rangeData['position'] = $position++;
                    $ranges[] = $rangeData;
                }
                $data['price_ranges']['price_ranges'] = $ranges;

                // Debug: Log the loaded data for this item
                file_put_contents(BP . '/var/log/commission_load.log', date('Y-m-d H:i:s') . " Loaded ID " . $model->getId() . ": " . print_r($data, true) . "\n", FILE_APPEND);
            }

            $this->loadedData[$model->getId()] = $data;
        }
        $data = $this->dataPersistor->get('vendor_marketplace_commission');

        if (!empty($data)) {
            $model = $this->collection->getNewEmptyItem();
            $model->setData($data);
            $this->loadedData[$model->getId()] = $model->getData();
            $this->dataPersistor->clear('vendor_marketplace_commission');
        }

        return $this->loadedData;
    }
}
