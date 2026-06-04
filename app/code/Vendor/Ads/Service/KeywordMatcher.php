<?php
namespace Vendor\Ads\Service;

class KeywordMatcher
{
    const MATCH_TYPE_EXACT = 'exact';
    const MATCH_TYPE_PHRASE = 'phrase'; // Partial
    const MATCH_TYPE_SYNONYM = 'synonym';

    /**
     * Get match score and type
     *
     * @param string $query
     * @param string $keyword
     * @param string $typePreference (exact, phrase, synonym)
     * @return array [score, matchType]
     */
    public function getMatchDetails($query, $keyword, $typePreference = self::MATCH_TYPE_PHRASE)
    {
        $query = strtolower(trim($query));
        $keyword = strtolower(trim($keyword));

        // 1. Exact Match
        if ($query === $keyword) {
            return [1.0, self::MATCH_TYPE_EXACT];
        }

        // 2. Phrase/Partial Match
        if (strpos($query, $keyword) !== false || strpos($keyword, $query) !== false) {
            return [0.7, self::MATCH_TYPE_PHRASE];
        }

        // 3. Synonym Match
        if ($this->checkSynonym($query, $keyword)) {
            return [0.4, self::MATCH_TYPE_SYNONYM];
        }

        return [0.0, 'none'];
    }

    /**
     * Placeholder for synonym check
     */
    protected function checkSynonym($query, $keyword)
    {
        $queryWords = explode(' ', $query);
        $keywordWords = explode(' ', $keyword);
        
        $common = array_intersect($queryWords, $keywordWords);
        return count($common) > 0;
    }
}
