<?php namespace Pckg\Parser\Source;

use Pckg\Parser\Driver\DriverInterface;
use Pckg\Parser\Search\ResultInterface;
use Pckg\Parser\Search\SearchInterface;
use Pckg\Parser\SearchResult;

/**
 * Interface SourceInterface
 *
 * @package Pckg\Parser
 */
interface SourceInterface
{

    /**
     * @param SearchInterface $search
     *
     * @return string
     */
    public function buildIndexUrl($page = null);

    /**
     * Define HTML structure to get list.
     *
     * @return array
     */
    public function getIndexStructure();

    /**
     * @return array
     */
    public function processIndexParse();

    /**
     * @return array
     */
    public function getListingStructure();

    /**
     * @param SearchResult $result
     * @param              $url
     *
     * @return array
     */
    public function processListingParse(ResultInterface $result, $url);

    /**
     * @return DriverInterface
     */
    public function getDriver();

    /**
     * @return SourceInterface
     */
    public function setSearch(SearchInterface $search);

    /**
     * @return SearchInterface
     */
    public function getSearch();

    /**
     * @return SourceInterface
     */
    public function getSearchSource();

}