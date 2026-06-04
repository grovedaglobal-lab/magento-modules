<?php
namespace Vendor\Marketplace\Ui\Component\Listing\Column;

use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Framework\View\Element\UiComponentFactory;
use Magento\Ui\Component\Listing\Columns\Column;
use Magento\Framework\Pricing\PriceCurrencyInterface;

class SubCommissions extends Column
{
    /**
     * @var PriceCurrencyInterface
     */
    protected $priceCurrency;

    /**
     * @param ContextInterface $context
     * @param UiComponentFactory $uiComponentFactory
     * @param PriceCurrencyInterface $priceCurrency
     * @param array $components
     * @param array $data
     */
    public function __construct(
        ContextInterface $context,
        UiComponentFactory $uiComponentFactory,
        PriceCurrencyInterface $priceCurrency,
        array $components = [],
        array $data = []
    ) {
        $this->priceCurrency = $priceCurrency;
        parent::__construct($context, $uiComponentFactory, $components, $data);
    }

    /**
     * Prepare Data Source
     *
     * @param array $dataSource
     * @return array
     */
    public function prepareDataSource(array $dataSource)
    {
        if (isset($dataSource['data']['items'])) {
            foreach ($dataSource['data']['items'] as &$item) {
                if (!empty($item['ranges_data'])) {
                    $ranges = explode('|', $item['ranges_data']);
                    $html = '<div class="sub-commissions-list" style="font-size: 11px; line-height: 1.4;">';
                    foreach ($ranges as $range) {
                        $parts = explode(':', $range);
                        if (count($parts) < 3)
                            continue;

                        list($min, $max, $percent) = $parts;
                        $minPrice = $this->priceCurrency->format($min, false);
                        $maxPrice = $max !== '' ? $this->priceCurrency->format($max, false) : __('∞');

                        $html .= '<div style="margin-bottom: 2px; border-bottom: 1px solid #f0f0f0; padding-bottom: 2px;">';
                        $html .= '<span style="color: #666;">' . __('Min') . ':</span> ' . $minPrice . ' | ';
                        $html .= '<span style="color: #666;">' . __('Max') . ':</span> ' . $maxPrice . ' | ';
                        $html .= '<span style="color: #666;">' . __('%') . ':</span> <span style="font-weight: bold; color: #eb5202;">' . $percent . '%</span>';
                        $html .= '</div>';
                    }
                    $html .= '</div>';

                    $item['commission_percent'] = $html;
                    $item['min_price'] = '-';
                    $item['max_price'] = '-';
                }
            }
        }

        return $dataSource;
    }
}
