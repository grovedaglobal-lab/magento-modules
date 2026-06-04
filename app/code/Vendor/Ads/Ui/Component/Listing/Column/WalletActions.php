<?php
namespace Vendor\Ads\Ui\Component\Listing\Column;

use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Framework\View\Element\UiComponentFactory;
use Magento\Ui\Component\Listing\Columns\Column;
use Magento\Framework\UrlInterface;

class WalletActions extends Column
{
    protected $urlBuilder;

    public function __construct(
        ContextInterface $context,
        UiComponentFactory $uiComponentFactory,
        UrlInterface $urlBuilder,
        array $components = [],
        array $data = []
    ) {
        parent::__construct($context, $uiComponentFactory, $components, $data);
        $this->urlBuilder = $urlBuilder;
    }

    public function prepareDataSource(array $dataSource)
    {
        if (isset($dataSource['data']['items'])) {
            foreach ($dataSource['data']['items'] as & $item) {
                $name = $this->getData('name');
                if (isset($item['vendor_id'])) {
                    $item[$name]['add_balance'] = [
                        'href' => $this->urlBuilder->getUrl('vendor_ads/wallet/adjust', ['vendor_id' => $item['vendor_id'], 'type' => 'add']),
                        'label' => __('Add Balance')
                    ];
                    $item[$name]['deduct_balance'] = [
                        'href' => $this->urlBuilder->getUrl('vendor_ads/wallet/adjust', ['vendor_id' => $item['vendor_id'], 'type' => 'deduct']),
                        'label' => __('Deduct Balance')
                    ];
                    $item[$name]['view_transactions'] = [
                        'href' => $this->urlBuilder->getUrl('vendor_ads/wallet/transactions', ['vendor_id' => $item['vendor_id']]),
                        'label' => __('View Transactions')
                    ];
                }
            }
        }
        return $dataSource;
    }
}
