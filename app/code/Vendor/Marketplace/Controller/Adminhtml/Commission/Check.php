<?php
namespace Vendor\Marketplace\Controller\Adminhtml\Commission;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Vendor\Marketplace\Model\ResourceModel\VendorCommission\CollectionFactory;

class Check extends Action
{
    protected $collectionFactory;

    public function __construct(
        Context $context,
        CollectionFactory $collectionFactory
    ) {
        parent::__construct($context);
        $this->collectionFactory = $collectionFactory;
    }

    public function execute()
    {
        $collection = $this->collectionFactory->create();
        echo "<h1>Commission Records Debug</h1>";
        echo "SQL: " . $collection->getSelect()->__toString() . "<br><br>";
        
        $count = $collection->getSize();
        echo "Count: " . $count . "<br><br>";

        if ($count > 0) {
            echo "<table border='1'><tr>";
            $first = $collection->getFirstItem();
            foreach ($first->getData() as $key => $val) {
                echo "<th>$key</th>";
            }
            echo "</tr>";
            
            foreach ($collection as $item) {
                echo "<tr>";
                foreach ($item->getData() as $key => $val) {
                    echo "<td>$val</td>";
                }
                echo "</tr>";
            }
            echo "</table>";
        } else {
            echo "No records found in DB collection.";
        }
        exit;
    }

    protected function _isAllowed()
    {
        return true;
    }
}
