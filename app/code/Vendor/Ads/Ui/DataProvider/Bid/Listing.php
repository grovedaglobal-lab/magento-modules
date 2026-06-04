<?php
namespace Vendor\Ads\Ui\DataProvider\Bid;

use Magento\Framework\View\Element\UiComponent\DataProvider\DataProvider;

class Listing extends DataProvider
{
    /** @var \Vendor\Ads\Api\VendorResolverInterface */
    protected $vendorResolver;

    public function __construct(
        $name,
        $primaryFieldName,
        $requestFieldName,
        \Magento\Framework\Api\Search\ReportingInterface $reporting,
        \Magento\Framework\Api\Search\SearchCriteriaBuilder $searchCriteriaBuilder,
        \Magento\Framework\App\RequestInterface $request,
        \Magento\Framework\Api\FilterBuilder $filterBuilder,
        \Vendor\Ads\Api\VendorResolverInterface $vendorResolver,
        array $meta = [],
        array $data = []
    ) {
        parent::__construct($name, $primaryFieldName, $requestFieldName, $reporting, $searchCriteriaBuilder, $request, $filterBuilder, $meta, $data);
        $this->vendorResolver = $vendorResolver;
    }

    public function getData()
    {
        $data = parent::getData();
        if (isset($data['items'])) {
            foreach ($data['items'] as &$item) {
                if (empty($item['vendor_name']) || $item['vendor_name'] == 'N/A') {
                    $item['vendor_name'] = $this->vendorResolver->getVendorName((int)$item['vendor_id']) ?: 'Vendor #' . $item['vendor_id'];
                }
            }
        }
        return $data;
    }
}
