<?php
namespace Vendor\Ads\Service;

use Magento\Search\Model\ResourceModel\SynonymGroup\CollectionFactory as SynonymGroupCollectionFactory;

class SynonymResolver
{
    protected $synonymCollectionFactory;

    public function __construct(SynonymGroupCollectionFactory $synonymCollectionFactory)
    {
        $this->synonymCollectionFactory = $synonymCollectionFactory;
    }

    /**
     * Get all synonyms for a given query text
     * 
     * @param string $queryText
     * @return string[]
     */
    public function getSynonyms($queryText)
    {
        $collection = $this->synonymCollectionFactory->create();
        $collection->addFieldToFilter('synonyms', ['like' => '%' . $queryText . '%']);
        
        $synonyms = [$queryText];
        foreach ($collection as $group) {
            $list = explode(',', $group->getSynonyms());
            foreach ($list as $syn) {
                $syn = trim($syn);
                if ($syn && !in_array($syn, $synonyms)) {
                    $synonyms[] = $syn;
                }
            }
        }
        
        return $synonyms;
    }
}
