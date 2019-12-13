<?php namespace Pckg\Parser\Pagination;

use Pckg\Parser\Source\AbstractSource;
use Pckg\Parser\Driver\DriverInterface;
use Pckg\Parser\Search\PageInterface;

class SeleniumComputed
{

    public function getNumPages($driver, $selenium)
    {
        $links = collect($driver->getElementsByCss($selenium, 'ul.pagination li'));
        $lastLink = $driver->getElementByCss($links->last(), 'a');
        $lastHref = $driver->getElementAttribute($lastLink, 'href');
        $exploded = explode('&pg=', $lastHref);

        return explode('&', $exploded[0])[0];
    }

    public function process(PageInterface $searchSource, AbstractSource $parser, ...$params)
    {

        /**
         * @var $driver   DriverInterface
         * @var $selenium \RemoteWebDriver
         */
        [$driver, $selenium] = $params;

        /**
         * Find pagination element and navigate to the last page.
         */
        $numPages = $this->getNumPages($driver, $selenium);
        $i = 2;

        $pages = [];
        while ($i <= $numPages && $i < 3) {
            try {
                /**
                 * Wait for page to load.
                 * Then parse it.
                 */
                $url = $parser->buildIndexUrl($i);

                /**
                 * Clone search source for new page.
                 */
                $searchSource = $searchSource->saveAs([
                                                          'parent_id' => $searchSource->id,
                                                          'data'      => null,
                                                          'url'       => $url,
                                                      ]);
                $selenium->get($url);
                sleep(3);
                $listings = $driver->getListingsFromIndex($selenium, $parser->getIndexStructure());
                $listings = $parser->addScore($listings, $searchSource);
                $searchSource->processListings($listings->all());
            } catch (\Throwable $e) {
                d('error parsing page', exception($e));
            }
            $i++;
        }
        d('pages', $pages);
    }

}