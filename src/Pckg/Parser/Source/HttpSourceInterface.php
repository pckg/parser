<?php

namespace Pckg\Parser\Source;

use Pckg\Parser\Driver\AbstractDriver;
use Pckg\Parser\Driver\Curl;
use Pckg\Parser\Driver\DriverInterface;
use Pckg\Parser\Driver\Selenium;
use Pckg\Parser\Search\ResultInterface;
use Pckg\Parser\Search\SearchInterface;

interface HttpSourceInterface
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
    public function processIndexParse($url);

    /**
     * @return array
     */
    public function getListingStructure();

    /**
     * @param SearchResult $result
     *
     * @return array
     */
    public function processListingParse(ResultInterface $result);

    /**
     * @param       $driver
     * @param       $listing
     * @param mixed ...$props
     *
     * @return mixed
     */
    public function afterListingParse($driver, $listing, ...$props);

    /**
     * @return AbstractDriver|DriverInterface|Curl|Selenium
     */
    public function getDriver();
}
