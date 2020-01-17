<?php namespace Pckg\Parser\Source;

use Pckg\Collection;
use Pckg\Concept\Event\Dispatcher;
use Pckg\Parser\Driver\AbstractDriver;
use Pckg\Parser\Driver\Curl;
use Pckg\Parser\Driver\DriverInterface;
use Pckg\Parser\Node\NodeInterface;
use Pckg\Parser\Search;
use Pckg\Parser\Search\PageInterface;
use Pckg\Parser\Search\ResultInterface;
use Pckg\Parser\Search\SearchInterface;
use Pckg\Parser\SearchSource;
use Pckg\Parser\SkipException;

abstract class AbstractSource implements SourceInterface
{

    /**
     * @var string
     */
    protected $type = 'listed';

    /**
     * @var string
     */
    protected $driver = Curl::class;

    protected $driverObject;

    /**
     * @var PageInterface|null
     */
    protected $page;

    /**
     * @var SearchInterface
     */
    protected $search;

    /**
     * @var int
     */
    protected $subPages = 5;

    /**
     * @var string
     */
    protected $base;

    /**
     * @var Dispatcher
     */
    protected $dispatcher;

    /**
     * @var array
     */
    protected $capabilities = [];

    /**
     * AbstractSource constructor.
     *
     * @param Dispatcher $dispatcher
     */
    public function __construct(Dispatcher $dispatcher = null)
    {
        $this->dispatcher = $dispatcher ?? new Dispatcher();
    }

    /**
     * @return Dispatcher
     */
    public function getDispatcher()
    {
        return $this->dispatcher;
    }

    /**
     * @return string
     */
    public function getBaseUrl()
    {
        return $this->base;
    }

    public function makeBaseUrl($value)
    {
        if (strpos($value, 'https://') === 0 || strpos($value, 'http://') === 0) {
            return $value;
        }

        return $this->base . $value;
    }

    /**
     * @param mixed $capability
     *
     * @return bool
     */
    public function hasCapability($capability)
    {
        if (!is_array($capability)) {
            $capability = [$capability];
        }

        foreach ($capability as $singleCapability) {
            if (!in_array($singleCapability, $this->capabilities)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return AbstractDriver|DriverInterface
     */
    public function getDriver()
    {
        if ($this->driverObject) {
            return $this->driverObject;
        }

        $driver = $this->driver;

        return $this->driverObject = new $driver($this);
    }

    /**
     * @param null $message
     *
     * @throws SkipException
     */
    public function skipItem($message = null)
    {
        throw new SkipException($message ?? 'Skipping item (missmatch?)');
    }

    /**
     * @param SearchInterface $search
     *
     * @return $this
     */
    public function setSearch(SearchInterface $search)
    {
        $this->search = $search;

        return $this;
    }

    /**
     * @param PageInterface $page
     *
     * @return $this
     */
    public function setPage(PageInterface $page)
    {
        $this->page = $page;

        return $this;
    }

    /**
     * @return mixed|SearchInterface
     */
    public function getSearch()
    {
        return $this->search;
    }

    /**
     * @return PageInterface|null
     */
    public function getPage()
    {
        return $this->page;
    }

    /**
     * @var string
     */
    protected $key = 'abstract';

    /**
     * @param SearchInterface $search
     */
    public function processIndexParse($url = null)
    {
        /**
         * Build info.
         */
        if (!$url) {
            $url = $this->buildIndexUrl();
        }

        /**
         * Tell the system we have something to parse.
         */
        $this->page = $this->search->createPage(['source' => $this->key, 'url' => $url, 'status' => 'created']);

        try {
            /**
             * Get initial listings.
             * Search additional page processing in same process.
             */
            $this->page->updateStatus('processing');
            $this->getDriver()->getListings($url, function(array $listings, ...$params) {
                $this->page->processListings($listings);
                $this->processIndexPagination(2, null, ...$params);
                $this->afterIndexParse($listings, ...$params);
            });
        } catch (\Throwable $e) {
            $this->page->updateStatus('error');
            d('EXCEPTION [indexing] ' . exception($e));
        }
    }

    /**
     * @param DriverInterface $driver
     * @param                 $page
     * @param mixed           ...$params
     */
    public function processIndexPagination($page, callable $then = null, ...$params)
    {
        if (!$this->shouldContinueToNextPage($page)) {
            return;
        }

        $url = $this->buildIndexUrl($page);

        try {
            /**
             * Clone search source for new page.
             */
            $this->page = $this->page->clone(['page' => $page, 'url' => $url, 'status' => 'created']);

            $this->page->updateStatus('processing');
            $driver->getListings($url, function($listings, ...$params) use ($page, $then) {
                $this->page->processListings($listings);
                if ($then) {
                    $then($listings);
                }

                if (!$listings || !$this->shouldContinueToNextPage($page)) {
                    return;
                }

                /**
                 * Continue with next page.
                 */
                $this->processIndexPagination($page + 1, $then, ...$params);
            });
        } catch (\Throwable $e) {
            $this->page->updateStatus('error');
            d('error parsing pagination', exception($e));
        }
    }

    public function processListingParse(ResultInterface $result, $url)
    {
        $result->updateStatus('parsing');

        return $this->getDriver()->getListingProps($url);
    }

    public function afterListingParse($driver, $listing, ...$params)
    {

    }

    public function shouldContinueToNextPage($page)
    {
        /**
         * Limit number of parsed (sub)pages.
         */
        if ($this->subPages && $page >= $this->subPages) {
            d('soft limit, more than ' . $this->subPages . ' pages');

            return false;
        }

        return true;
    }

    /**
     * @param SearchInterface $search
     */
    public function afterIndexParse(array $listings, ...$params)
    {
        /**
         * This is where our index or search page was parsed and we are trying to parse additional sources.
         * Sources that would like to be searched over Google SERPs API are parsed here.
         */
    }

    public function firewall($url)
    {
        d('firewall: ' . $url);
        $this->getDriver()->getClient()->get($url);
    }

    /**
     * @param array $props
     * @param array $meta
     *
     * @return mixed
     */
    public function getScore(array $props = [], array $meta = [])
    {
        return $this->getPage()->getScore($props, $meta);
    }

    /**
     * @param SearchSource $searchSource
     * @param array        $props
     * @param array        $meta
     *
     * @return mixed
     */
    public function isMatch(array $props = [], array $meta = [], &$by = null)
    {
        return $this->getPage()->isMatch($props, $meta, $by);
    }

    /**
     * @param NodeInterface $node
     * @param               $props
     * @param array         $keys
     * @param callable      $matcher
     */
    protected function eachParse(NodeInterface $node, &$props, array $keys, callable $matcher)
    {
        foreach ($keys as $label => $slug) {
            try {
                $value = $matcher($node, $label);
                if (trim($value)) {
                    $props[$slug] = trim($value);

                    return true;
                }
            } catch (\Throwable $e) {
                d('exception eachParse', exception($e));
            }
        }
    }

    public function copySearchProps($keys, &$props)
    {
        foreach ($keys as $key) {
            $props[$key] = $this->getSearch()->getDataProp($key);
        }
    }

}