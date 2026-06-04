<?php
namespace Vendor\Ads\Model;

use Magento\Framework\Model\AbstractModel;
use Vendor\Ads\Api\Data\KeywordInterface;

class Keyword extends AbstractModel implements KeywordInterface
{
    protected function _construct()
    {
        $this->_init(\Vendor\Ads\Model\ResourceModel\Keyword::class);
    }

    public function getKeywordId()
    {
        return $this->getData(self::KEYWORD_ID);
    }

    public function setKeywordId($keywordId)
    {
        return $this->setData(self::KEYWORD_ID, $keywordId);
    }

    public function getKeyword()
    {
        return $this->getData(self::KEYWORD);
    }

    public function setKeyword($keyword)
    {
        return $this->setData(self::KEYWORD, $keyword);
    }
}
