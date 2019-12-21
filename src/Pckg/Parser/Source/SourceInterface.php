<?php namespace Pckg\Parser\Source;

use Pckg\Collection;
use Pckg\Concept\Event\Dispatcher;
use Pckg\Parser\Driver\DriverInterface;
use Pckg\Parser\Search\PageInterface;
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
     * @return Dispatcher
     */
    public function getDispatcher();

    /**
     * @param mixed $capability
     *
     * @return boolean
     */
    public function hasCapability($capability);

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
    public function processIndexParse($url = null);

    public function afterIndexParse(array $listings, ...$props);

    public function processIndexPagination($page, callable $then, ...$params);

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
     * @param       $driver
     * @param       $listing
     * @param mixed ...$props
     *
     * @return mixed
     */
    public function afterListingParse($driver, $listing, ...$props);

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
     * @return PageInterface
     */
    public function setPage(PageInterface $page);

    /**
     * @return SourceInterface
     */
    public function getPage();

}