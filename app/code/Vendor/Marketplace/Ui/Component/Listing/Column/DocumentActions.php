<?php
namespace Vendor\Marketplace\Ui\Component\Listing\Column;

use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Framework\View\Element\UiComponentFactory;
use Magento\Ui\Component\Listing\Columns\Column;
use Magento\Framework\UrlInterface;

class DocumentActions extends Column
{
    const URL_PATH_EDIT = 'vendor_marketplace/document/edit';
    const URL_PATH_DELETE = 'vendor_marketplace/document/delete';
    const URL_PATH_DOWNLOAD = 'vendor_marketplace/document/download';
    const URL_PATH_APPROVE = 'vendor_marketplace/document/approve';
    const URL_PATH_REJECT = 'vendor_marketplace/document/reject';

    protected $_urlBuilder;

    public function __construct(
        ContextInterface $context,
        UiComponentFactory $uiComponentFactory,
        UrlInterface $urlBuilder,
        array $components = [],
        array $data = []
    ) {
        $this->_urlBuilder = $urlBuilder;
        parent::__construct($context, $uiComponentFactory, $components, $data);
    }

    public function prepareDataSource(array $dataSource)
    {
        if (isset($dataSource['data']['items'])) {
            foreach ($dataSource['data']['items'] as &$item) {
                // Action button is now handled per-document in the list
                $item[$this->getData('name')] = [];
            }
        }

        return $dataSource;
    }
}
