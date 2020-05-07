<?php namespace Pckg\Parser\Source;

use Pckg\Parser\Driver\AbstractDriver;
use Pckg\Parser\Driver\Curl;
use Pckg\Parser\Driver\DriverInterface;
use Pckg\Parser\Search\ResultInterface;
use Pckg\Parser\Search\SearchInterface;

abstract class AbstractHttpSource extends AbstractSource implements HttpSourceInterface
{

    /**
     * @var string
     */
    protected $driver = Curl::class;

    /**
     * @var
     */
    protected $driverObject;

    /**
     * @var int
     */
    protected $subPages = 2;

    /**
     * @var string
     */
    protected $base;

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

        if ($value && substr($value, 0, 1) !== '/') {
            $value = '/' . $value;
        }

        return $this->base . $value;
    }

    public function startIndex()
    {
        $url = $this->buildIndexUrl();

        $pageData = [
            'source' => $this->getKey(),
            'url' => $url,
            'status' => 'created',
        ];
        $page = $this->getSearch()->createPage($pageData);
        $this->setPage($page);

        /**
         * Run it.
         */
        $this->beforeIndexParse();
        $this->processIndexParse($url);
        $this->afterIndexParse();
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
        } catch (\Throwable $e) {
            $this->trigger('parse.exception', $e);
            $this->page->updateStatus('error');
            return;
        }

        if (!$listings) {
            $this->trigger('debug', 'No listings found');
            return;
        }

        try {
            /**
             * Check pagination.
             */
            $nextPage = ($this->page->getPage() ?? 1) + 1;
            if ($this->shouldContinueToNextPage($nextPage)) {
                $newUrl = $this->buildIndexUrl($nextPage);
                if ($url !== $newUrl) {
                    $this->trigger('debug', 'Continuing with next page');
                    $this->page = $this->page->clone(['page' => $nextPage, 'url' => $newUrl]);
                    $this->processIndexParse($url); // recursive call
                } else {
                    $this->trigger('debug', 'Same sub-page URL, skipping');
                }
            } else {
                $this->trigger('debug', 'Finished with sub-pages');
            }
        } catch (\Throwable $e) {
            $this->trigger('parse.exception', $e);
        }
    }

    public function processListingParse(ResultInterface $result)
    {
        $result->updateStatus('parsing');

        $props = $this->getDriver()->getListingProps($result->getUrl());

        $this->page->processListing($result, $props);
    }

    public function firewall($url)
    {
        d('firewall: ' . $url);
        $this->getDriver()->getClient()->get($url);
    }
    
}