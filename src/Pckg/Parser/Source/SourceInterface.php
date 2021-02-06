<?php

namespace Pckg\Parser\Source;

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
     * @return PageInterface
     */
    public function getPage();

    /**
     * @return ResultInterface
     */
    public function setResult(ResultInterface $result);

    /**
     * @return ResultInterface
     */
    public function getResult();

    /**
     * @return mixed
     */
    public function startIndex();
}
