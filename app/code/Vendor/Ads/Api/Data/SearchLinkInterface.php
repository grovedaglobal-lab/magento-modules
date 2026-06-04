<?php
namespace Vendor\Ads\Api\Data;

interface SearchLinkInterface
{
    const ID = 'id';
    const QUERY_ID = 'query_id';
    const IS_ADS_ENABLED = 'is_ads_enabled';

    /**
     * @return int|null
     */
    public function getId();

    /**
     * @param int $id
     * @return $this
     */
    public function setId($id);

    /**
     * @return int
     */
    public function getQueryId();

    /**
     * @param int $queryId
     * @return $this
     */
    public function setQueryId($queryId);

    /**
     * @return int
     */
    public function getIsAdsEnabled();

    /**
     * @param int $isAdsEnabled
     * @return $this
     */
    public function setIsAdsEnabled($isAdsEnabled);
}
