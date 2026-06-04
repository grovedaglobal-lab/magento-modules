<?php
namespace Vendor\Ads\Ui\Component\Listing\Column;

use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Framework\View\Element\UiComponentFactory;
use Magento\Ui\Component\Listing\Columns\Column;
use Magento\Framework\UrlInterface;

class KeywordActions extends Column
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
                if (isset($item['query_id'])) {
                    $item[$name]['view'] = [
                        'href' => $this->urlBuilder->getUrl('vendor_ads/keyword/details', ['query_id' => $item['query_id']]),
                        'label' => __('View Details')
                    ];
                    $item[$name]['manage_bids'] = [
                        'href' => $this->urlBuilder->getUrl('vendor_ads/bid/index', ['query_id' => $item['query_id']]),
                        'label' => __('Manage Bids')
                    ];
                    $enabled = isset($item['is_ads_enabled']) && $item['is_ads_enabled'];
                    $item[$name]['toggle'] = [
                        'href' => $this->urlBuilder->getUrl('vendor_ads/keyword/toggle', [
                            'query_id' => $item['query_id'],
                            'active' => $enabled ? 0 : 1
                        ]),
                        'label' => $enabled ? __('Disable Ads') : __('Enable Ads')
                    ];
                }
            }
        }
        return $dataSource;
    }
}
