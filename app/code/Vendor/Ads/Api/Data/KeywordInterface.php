<?php
namespace Vendor\Ads\Api\Data;

interface KeywordInterface
{
    const KEYWORD_ID = 'keyword_id';
    const KEYWORD = 'keyword';

    public function getKeywordId();
    public function setKeywordId($keywordId);
    public function getKeyword();
    public function setKeyword($keyword);
}
