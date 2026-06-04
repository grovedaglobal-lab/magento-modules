<?php
namespace Vendor\Ads\Model;

use Magento\Framework\Model\AbstractModel;
use Vendor\Ads\Api\Data\SearchLinkInterface;

class SearchLink extends AbstractModel implements SearchLinkInterface
{
    protected function _construct()
    {
        $this->_init(\Vendor\Ads\Model\ResourceModel\SearchLink::class);
    }

    public function getQueryId()
    {
        return $this->getData(self::QUERY_ID);
    }

    public function setQueryId($queryId)
    {
        return $this->setData(self::QUERY_ID, $queryId);
    }

    public function getIsAdsEnabled()
    {
        return $this->getData(self::IS_ADS_ENABLED);
    }

    public function setIsAdsEnabled($isAdsEnabled)
    {
        return $this->setData(self::IS_ADS_ENABLED, $isAdsEnabled);
    }
}
