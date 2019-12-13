<?php namespace Pckg\Parser\Source;

use Pckg\Parser\Driver\AbstractDriver;
use Pckg\Parser\Driver\Curl;
use Pckg\Parser\Driver\DriverInterface;
use Pckg\Parser\Search;
use Pckg\Parser\Search\ResultInterface;
use Pckg\Parser\Search\SearchInterface;
use Pckg\Parser\SearchSource;

abstract class AbstractSource
{

    /**
     * @var string
     */
    protected $type = 'listed';

    /**
     * @var string
     */
    protected $driver = Curl::class;

    /**
     * @var \Pckg\Parser\Search\PageInterface|null
     */
    protected $searchSource;

    /**
     * @var SearchInterface
     */
    protected $search;

    /**
     * @var int
     */
    protected $subPages = 5;

    /**
     * @return AbstractDriver|DriverInterface
     */
    public function getDriver()
    {
        $driver = $this->driver;

        return new $driver($this);
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
     * @return mixed|SearchInterface
     */
    public function getSearch()
    {
        return $this->search;
    }

    /**
     * @return Search\PageInterface|null
     */
    public function getSearchSource()
    {
        return $this->searchSource;
    }

    /**
     * @param array $listings
     *
     * @return \Pckg\Collection
     */
    public function addScore(array $listings)
    {
        /**
         * Update status in database.
         */
        $this->searchSource->updateStatus('matching');

        $matched = collect($listings)->map(function($props) {
            /**
             * Trim all, add score and match.
             */
            $props = collect($props)->trim()->all();
            $props['match'] = $this->searchSource->isMatch($props, [], $by);
            $props['by'] = $by;
            $props['score'] = $this->getSearch()->getScore($props);

            return $props;
        });

        $this->searchSource->updateStatus('matched');

        return $matched;
    }

    /**
     * @var string
     */
    protected $key = 'abstract';

    /**
     * @param SearchInterface $search
     */
    public function processIndexParse()
    {
        /**
         * Build info.
         */
        $url = $this->buildIndexUrl();

        /**
         * Tell the system we have something to parse.
         */
        $this->searchSource = $this->search->createSource($this->key, $url);

        try {
            /**
             * Get initial listings.
             * Search additional page processing in same process.
             */
            $this->getDriver()->getListings($this, $url, function(DriverInterface $driver, $listings, ...$params) {
                /**
                 * Filter listings and add score.
                 */
                $listings = $this->addScore($listings);

                /**
                 * Push to queue.
                 */
                $this->searchSource->processListings($listings->all());
                $this->processIndexPagination($driver, 2, ...$params);
                $this->afterIndexParse(...$params);
            });
        } catch (\Throwable $e) {
            $this->searchSource->updateStatus('error');
            d('EXCEPTION [indexing] ' . exception($e));
        }
    }

    /**
     * @param DriverInterface $driver
     * @param                 $page
     * @param mixed           ...$params
     */
    public function processIndexPagination(DriverInterface $driver, $page, ...$params)
    {
        // $holder = $params[0]; // holder is html or RemoteWebDriver
        $url = $this->buildIndexUrl($page);

        try {
            /**
             * Clone search source for new page.
             */
            $this->searchSource = $this->searchSource->clone($page, $url);

            $driver->getListings($this, $url, function(DriverInterface $driver, $listings, ...$params) use ($page) {
                /**
                 * First process.
                 */
                $listings = $this->addScore($listings);
                $this->searchSource->processListings($listings->all());

                if (!$listings->all() || !$this->shouldContinueToNextPage($page)) {
                    return;
                }

                /**
                 * Continue with next page.
                 */
                $this->processIndexPagination($driver, $page + 1, ...$params);
            });
        } catch (\Throwable $e) {
            $searchSource->setAndSave(['status' => 'error']);
            d('error parsing pagination', exception($e));
        }
    }

    public function processListingParse(ResultInterface $result, $url)
    {
        $result->updateStatus('parsing');

        return $this->getDriver()->getListingProps($this, $url);
    }

    public function shouldContinueToNextPage($page)
    {
        /**
         * Limit number of parsed (sub)pages.
         */
        if ($this->subPages && $page >= $this->subPages) {
            d('soft limit, more than 5 pages');

            return false;
        }

        return true;
    }

    /**
     * @param SearchInterface $search
     */
    public function afterIndexParse(...$params)
    {
        /**
         * This is where our index or search page was parsed and we are trying to parse additional sources.
         * Sources that would like to be searched over Google SERPs API are parsed here.
         */
    }

    public function firewall($selenium, $url)
    {
        $selenium->get($url);
    }

    /**
     * @param array $props
     * @param array $meta
     *
     * @return mixed
     */
    public function getScore(array $props = [], array $meta = [])
    {
        return $this->getSearchSource()->getScore($props, $meta);
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
        return $this->getSearchSource()->isMatch($props, $meta, $by);
    }

}