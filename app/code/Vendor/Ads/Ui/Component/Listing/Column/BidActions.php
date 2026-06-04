<?php
namespace Vendor\Ads\Ui\Component\Listing\Column;

use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Framework\View\Element\UiComponentFactory;
use Magento\Ui\Component\Listing\Columns\Column;
use Magento\Framework\UrlInterface;

class BidActions extends Column
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
                if (isset($item['bid_id'])) {
                    $item[$name]['view'] = [
                        'href' => $this->urlBuilder->getUrl('vendor_ads/bid/details', ['bid_id' => $item['bid_id']]),
                        'label' => __('View Details')
                    ];
                    $isActive = isset($item['is_active']) && $item['is_active'];
                    $item[$name]['toggle'] = [
                        'href' => $this->urlBuilder->getUrl('vendor_ads/bid/toggle', [
                            'bid_id' => $item['bid_id'],
                            'active' => $isActive ? 0 : 1
                        ]),
                        'label' => $isActive ? __('Pause') : __('Resume'),
                        'confirm' => [
                            'title' => $isActive ? __('Pause Bid') : __('Resume Bid'),
                            'message' => $isActive ? __('Are you sure you want to pause this bid?') : __('Are you sure you want to resume this bid?')
                        ]
                    ];
                    $item[$name]['cancel'] = [
                        'href' => $this->urlBuilder->getUrl('vendor_ads/bid/cancel', ['bid_id' => $item['bid_id']]),
                        'label' => __('Cancel'),
                        'confirm' => [
                            'title' => __('Cancel Bid'),
                            'message' => __('Are you sure you want to cancel this bid? This action cannot be undone.')
                        ]
                    ];
                }
            }
        }
        return $dataSource;
    }
}
