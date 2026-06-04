<?php
namespace Tax\IndianGST\Controller\Adminhtml\System\Config;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\App\ResourceConnection;

class ColumnList extends Action
{
    /**
     * @var JsonFactory
     */
    protected $resultJsonFactory;

    /**
     * @var ResourceConnection
     */
    protected $resource;

    /**
     * @param Context $context
     * @param JsonFactory $resultJsonFactory
     * @param ResourceConnection $resource
     */
    public function __construct(
        Context $context,
        JsonFactory $resultJsonFactory,
        ResourceConnection $resource
    ) {
        parent::__construct($context);
        $this->resultJsonFactory = $resultJsonFactory;
        $this->resource = $resource;
    }

    /**
     * @return \Magento\Framework\Controller\Result\Json
     */
    public function execute()
    {
        $result = $this->resultJsonFactory->create();
        $tableName = $this->getRequest()->getParam('table');

        $options = [];
        if ($tableName) {
            try {
                $connection = $this->resource->getConnection();
                if ($connection->isTableExists($tableName)) {
                    $describe = $connection->describeTable($tableName);
                    foreach ($describe as $columnName => $data) {
                        $options[] = ['value' => $columnName, 'label' => $columnName];
                    }
                    usort($options, function ($a, $b) {
                        return strcmp($a['label'], $b['label']);
                    });
                }
            } catch (\Exception $e) {
                // error
            }
        }

        array_unshift($options, ['value' => '', 'label' => __('-- Select Column --')]);

        return $result->setData($options);
    }

    /**
     * @return bool
     */
    protected function _isAllowed()
    {
        return $this->_authorization->isAllowed('Tax_IndianGST::config');
    }
}
