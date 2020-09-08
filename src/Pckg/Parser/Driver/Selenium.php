<?php namespace Pckg\Parser\Driver;

use Facebook\WebDriver\Chrome\ChromeOptions;
use Facebook\WebDriver\Remote\DesiredCapabilities;
use Facebook\WebDriver\Remote\RemoteWebDriver;
use Facebook\WebDriver\Remote\RemoteWebElement;
use Facebook\WebDriver\Remote\WebDriverCapabilityType;
use Facebook\WebDriver\WebDriverBy;
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

    /**
     * @return $this|\Pckg\Parser\Driver\AbstractDriver
     * @throws \Throwable
     */
    public function open()
    {
        $proxy = $this->getHttpProxy();

        $this->client = SeleniumFactory::getSeleniumClient(null, $proxy);

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
            $this->client->quit();
            $this->client = null;
        }

        return parent::close();
    }

    /**
     * @return RemoteWebDriver
     */
    private function getSeleniumClient()
    {
        if ($this->client) {
            return $this->client;
        }

        $proxy = $this->getHttpProxy();

        return $this->client = SeleniumFactory::getSeleniumClient(null, $proxy);
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
        $selenium = $this->getSeleniumClient();
        $this->trigger('page.status', 'initiated');
        try {
            $this->source->firewall($url);

            try {
                $this->trigger('page.status', 'parsing');
                $listings = $this->getListingsFromIndex();
                $this->trigger('page.status', 'parsed');
            } catch (\Throwable $e) {
                $this->trigger('page.status', 'error');
            }

            return $listings;
        } catch (\Throwable $e) {
            $this->trigger('parse.exception', $e);
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
        $selenium = $this->getSeleniumClient();
        $allListings = $selenium->findElements(WebDriverBy::cssSelector($listingsSelector));

        /**
         * Collect all listings.
         */
        $listings = collect($allListings);
        d('located listings', $listings->count(), $listingsSelector);

        /**
         * Parse them.
         */
        return $listings->map(function ($node) use ($selectors, $listingsSelector) {
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

                d($props);

                return $props;
            } catch (\Throwable $e) {
                $this->trigger('parse.exception', $e);
            }
        })->removeEmpty()->rekey()->all();
    }

    public function autoParseListing(&$props)
    {
        $structure = $this->source->getListingStructure();
        $htmlNode = $this->makeNode($this->getClient()->findElement(WebDriverBy::cssSelector('html')));
        foreach ($structure as $selector => $details) {
            try {
                $this->processSectionByStructure($htmlNode, $selector, $details, $props);
            } catch (\Throwable $e) {
                $this->trigger('parse.exception', $e);
            }
        }

        $this->source->afterListingParse($this->getClient(), $props);
    }

    /**
     * @param              $url
     */
    public function getListingProps(string $url)
    {
        $selenium = $this->getSeleniumClient();

        try {
            $props = [];
            $selenium->get($url);
            $selenium->wait(5);
            sleep(5);
            $this->takeScreenshot($selenium);
            $this->autoParseListing($props);
        } catch (\Throwable $e) {
            $this->trigger('parse.exception', $e);
        }

        return $props;
    }

    /**
     * @param $section RemoteWebElement
     * @param $attribute
     *
     * @return mixed
     */
    public function getElementAttribute($section, string $attribute)
    {
        return $section->getAttribute($attribute);
    }

    /**
     * @param $section RemoteWebElement
     *
     * @return mixed
     */
    public function getElementInnerHtml($section)
    {
        return $section->getText();
    }

    /**
     * @param $section RemoteWebElement
     * @param $css
     *
     * @return mixed
     */
    public function getElementByCss($section, string $css)
    {
        return $section->findElement(WebDriverBy::cssSelector($css));
    }

    /**
     * @param $section RemoteWebElement
     * @param $css
     *
     * @return mixed
     */
    public function getElementsByCss($section, string $css)
    {
        return $section->findElements(WebDriverBy::cssSelector($css));
    }

    /**
     * @param $section RemoteWebElement
     * @param $xpath
     *
     * @return mixed
     */
    public function getElementByXpath($section, string $xpath)
    {
        return $section->findElement(WebDriverBy::xpath('.//' . $xpath));
    }

}