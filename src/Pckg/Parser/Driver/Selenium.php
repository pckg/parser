<?php

namespace Pckg\Parser\Driver;

use Facebook\WebDriver\Chrome\ChromeOptions;
use Facebook\WebDriver\Remote\DesiredCapabilities;
use Facebook\WebDriver\Remote\RemoteWebDriver;
use Facebook\WebDriver\Remote\RemoteWebElement;
use Facebook\WebDriver\Remote\WebDriverCapabilityType;
use Facebook\WebDriver\WebDriverBy;
use Pckg\Parser\Client\Headless;
use Pckg\Parser\Node\SeleniumNode;
use Pckg\Parser\SkipException;
use Pckg\Parser\Source\AbstractSource;
use Pckg\Parser\ParserInterface;
use Pckg\Parser\Driver\AbstractDriver;
use Pckg\Parser\Driver\DriverInterface;
use Pckg\Parser\Source\SourceInterface;
use Scintilla\Parser\Utils\SeleniumHelper;

class Selenium extends AbstractDriver implements DriverInterface
{
    use SeleniumHelper;

    protected $node = SeleniumNode::class;

    protected $clientClass = \Pckg\Parser\Client\Selenium::class;

    /**
     * @return $this|\Pckg\Parser\Driver\AbstractDriver
     * @throws \Throwable
     */
    public function open()
    {
        $proxy = $this->getHttpProxy();

        if ($this->client) {
            $this->close();
        }

        /**
         *
         */
        $clientClass = $this->clientClass;
        $this->client = new $clientClass($proxy);

        return $this;
    }

    /**
     * Close unclosed connections.
     */
    public function __destruct()
    {
        $this->close();
    }

    /**
     * @return \Pckg\Parser\Driver\AbstractDriver
     */
    public function close()
    {
        if ($this->client) {
            $this->client->close();
            $this->client = null;
        }

        return parent::close();
    }

    /**
     * @return \Pckg\Parser\Client\Selenium|Headless
     */
    public function getClient()
    {
        if ($this->client) {
            return $this->client;
        }

        $this->open();

        return $this->client;
    }

    /**
     * @param ParserInterface $parser
     * @param string $url
     * @param callable|null $then
     *
     * @return array
     */
    public function getListings(string $url)
    {
        $this->trigger('page.status', 'initiating');
        $client = $this->getClient();
        $this->trigger('page.status', 'initiated');
        try {
            $this->source->firewall($url);
            $this->source->getPage()->getPageRecord()->setAndSave(['url' => $client->getCurrentURL()]);

            $listings = [];
            $this->trigger('page.status', 'parsing');
            try {
                $listings = $this->getListingsFromIndex();
            } catch (\Throwable $e) {
                d(exception($e));
                $this->trigger('parse.exception', $e);
                $this->trigger('page.status', 'error');
                $this->takeScreenshot();

                return [];
            }

            $this->trigger('page.status', 'parsed');

            /**
             * Collect screenshot when there are no listings.
             * Also collect HTML for debugging?
             */
            if (!$listings) {
                $client->takeScreenshot();
            }

            return $listings;
        } catch (\Throwable $e) {
            $this->trigger('parse.exception', $e);
            $this->trigger('page.status', 'error');
            $this->takeScreenshot();
        }

        return [];
    }

    /**
     * @param RemoteWebDriver $selenium
     * @param array $structure
     *
     * @return array
     */
    public function getListingsFromIndex()
    {
        $structure = $this->source->getIndexStructure();
        $selector = array_keys($structure)[0];
        $selectors = $structure[$selector];
        $listingsSelector = array_keys($structure)[0];

        /**
         * We do not close the client since it should already be initiated.
         */
        $client = $this->getClient();
        $allListings = $client->findElements($listingsSelector);

        /**
         * Collect all listings.
         */
        $listings = collect($allListings);

        /**
         * Parse them.
         */
        $listings = $listings->map(function ($node) use ($selectors, $listingsSelector) {
            try {
                $props = [];

                foreach ($selectors as $selector => $details) {
                    try {
                        $this->processSectionByStructure($this->makeNode($node, $listingsSelector), $selector, $details, $props);
                    } catch (SkipException $e) {
                        throw $e;
                    } catch (\Throwable $e) {
                        d('EXCEPTION [parsing index listing selector] ' . $selector . ' ' . exception($e));
                    }
                }

                return $props;
            } catch (\Throwable $e) {
                $this->trigger('parse.exception', $e);
            }
        })->removeEmpty()->rekey()->all();

        return $listings;
    }

    /**
     * @param              $url
     */
    public function getListingProps(string $url)
    {
        $client = $this->getClient();

        try {
            $props = [];
            $client->get($url);
            $client->wait(5);

            $this->autoParseListing($props);
        } catch (\Throwable $e) {
            $this->trigger('parse.exception', $e);
            $this->takeScreenshot();
        }

        return $props;
    }

    public function autoParseListing(&$props)
    {
        $structure = $this->source->getListingStructure();
        $htmlNode = $this->makeNode($this->getClient()->findElement('html'));
        foreach ($structure as $selector => $details) {
            try {
                $this->processSectionByStructure($htmlNode, $selector, $details, $props);
            } catch (\Throwable $e) {
                $this->trigger('parse.exception', $e);
            }
        }

        $this->source->afterListingParse($this->getClient(), $props);
    }
}
