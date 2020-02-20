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

    public function trigger(string $event, $data)
    {
        $this->getDispatcher()->trigger($event, $data);
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
     * @return string
     */
    public function getDriverClass()
    {
        return $this->driver;
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
     * @return string
     */
    public function getKey()
    {
        return $this->key;
    }

    /**
     * @param SearchInterface $search
     */
    public function processIndexParse($url)
    {
        try {
            /**
             * Get initial listings.
             * Search additional page processing in same process.
             */
            $this->page->updateStatus('processing');

            /**
             * Fetch and parse listings with specified driver.
             */
            $listings = $this->getDriver()->getListings($url);

            /**
             * Save listings to the page.
             */
            $this->trigger('parse.log', 'Processing listings');
            $this->page->processListings($listings);
            $this->trigger('parse.log', 'Listings processed');

            /**
             * Check pagination.
             */
            $nextPage = ($this->page->getPage() ?? 1) + 1;
            if ($listings && $this->shouldContinueToNextPage($nextPage)) {
                $this->trigger('debug', 'Continuing with next page');
                $url = $this->buildIndexUrl($nextPage);
                $this->page = $this->page->clone(['page' => $nextPage, 'url' => $url]);
                $this->processIndexParse($url); // recursive call
            } else {
                $this->trigger('debug', 'Finished with sub-pages');
            }

        } catch (\Throwable $e) {
            $this->trigger('parse.exception', $e);
            $this->page->updateStatus('error');
        }
    }

    public function processListingParse(ResultInterface $result)
    {
        $result->updateStatus('parsing');

        $props = $this->getDriver()->getListingProps($result->getUrl());

        $this->page->processListing($result, $props);
    }

    public function afterListingParse($driver, $listing, ...$params)
    {

    }

    public function shouldContinueToNextPage($page)
    {
        /**
         * Limit number of parsed (sub)pages.
         */
        if ($this->subPages && $page < $this->subPages) {
            return true;
        }

        return false;
    }

    /**
     * @param SearchInterface $search
     */
    public function beforeIndexParse()
    {
        /**
         * This is where search is set and we can validate it.
         */
    }

    /**
     * @param SearchInterface $search
     */
    public function afterIndexParse()
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