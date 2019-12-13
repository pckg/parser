<?php namespace Pckg\Parser\Driver;

use Facebook\WebDriver\Chrome\ChromeOptions;
use Facebook\WebDriver\Remote\DesiredCapabilities;
use Facebook\WebDriver\Remote\RemoteWebDriver;
use Facebook\WebDriver\Remote\RemoteWebElement;
use Facebook\WebDriver\Remote\WebDriverCapabilityType;
use Facebook\WebDriver\WebDriverBy;
use Pckg\Parser\Node\SeleniumNode;
use Pckg\Parser\Source\AbstractSource;
use Pckg\Parser\ParserInterface;
use Pckg\Parser\Driver\AbstractDriver;
use Pckg\Parser\Driver\DriverInterface;
use Pckg\Parser\Source\SourceInterface;

class Selenium extends AbstractDriver implements DriverInterface
{

    protected $node = SeleniumNode::class;

    protected $host = 'http://selenium-hub:4444/wd/hub';

    /**
     * @return RemoteWebDriver
     */
    public function getSeleniumClient($host = null)
    {
        if (!$host) {
            $host = $this->host;
        }
        $capabilities = DesiredCapabilities::chrome();
        $options = new ChromeOptions();
        $options->addArguments([
                                   '--disable-dev-shm-usage',
                                   '--whitelisted-ips',
                                   '--disable-extensions',
                                   '--no-sandbox',
                                   '--verbose',
                                   '--disable-gpu',
                                   //'--headless',
                                   '--window-size=1280,1024',
                                   //'headless',
                                   'start-maximized',
                                   'disable-infobar',
                                   '--lang=en-GB',
                                   '--user-agent=Mozilla/5.0 (X11; Linux x86_64)AppleWebKit/537.36 (KHTML, like Gecko) Chrome/64.0.3282.39 Safari/537.36',
                               ]);
        $capabilities->setCapability(ChromeOptions::CAPABILITY, $options);

        /**
         * Select random proxy.
         */
        $proxy = $this->getHttpProxy();
        if ($proxy) {
            $capabilities->setCapability(WebDriverCapabilityType::PROXY, [
                'proxyType' => 'manual',
                'httpProxy' => 'http://' . $proxy,
                'sslProxy'  => 'http://' . $proxy,
            ]);
        }

        d('creating web driver');
        try {
            $webdriver = RemoteWebDriver::create($host, $capabilities, 5000);
        } catch (\Throwable $e) {
            ddd(exception($e), get_class($e));
        }
        d('web driver created');

        $webdriver->executeScript($this->getProtectionScript());

        return $webdriver;
    }

    public function getProtectionScript()
    {
        return '
    // Pass the Webdriver Test.
    Object.defineProperty(navigator, \'webdriver\', {
      get: function() { return false; }
    });

  // Pass the Chrome Test.
    // We can mock this in as much depth as we need for the test.
    window.navigator.chrome = {
      runtime: {},
      // etc.
    };

  // Pass the Permissions Test.
    const originalQuery = window.navigator.permissions.query;
    return window.navigator.permissions.query = function(parameters){ return parameters.call(
      parameters.name === \'notifications\' ?
        Promise.resolve({ state: Notification.permission }) :
        originalQuery(parameters)
    )};

  // Pass the Plugins Length Test.
    // Overwrite the `plugins` property to use a custom getter.
    Object.defineProperty(navigator, \'plugins\', {
      // This just needs to have `length > 0` for the current test,
      // but we could mock the plugins too if necessary.
      get: function(){ return [1, 2, 3, 4, 5]; },
    });

  // Pass the Languages Test.
    // Overwrite the `plugins` property to use a custom getter.
    Object.defineProperty(navigator, \'languages\', {
      get: function() { return [\'en-US\', \'en\']; }
    });';
    }

    /**
     * @param ParserInterface $parser
     * @param string          $url
     * @param callable|null   $then
     *
     * @return array
     */
    public function getListings(SourceInterface $parser, string $url, callable $then = null)
    {
        $parser->getSearchSource()->updateStatus('initiating');
        $selenium = $this->getSeleniumClient();
        $parser->getSearchSource()->updateStatus('initiated');
        try {
            $parser->firewall($selenium, $url);

            $listings = [];
            try {
                d('taking screenshot');
                $selenium->takeScreenshot(path('uploads') . 'selenium/after-firewall-' .
                                          sluggify(microtime() . ' ' . $url) . '.png');
                d('screenshot taken');
                $parser->getSearchSource()->updateStatus('parsing');
                $listings = $this->getListingsFromIndex($selenium, $parser->getIndexStructure());
                $parser->getSearchSource()->updateStatus('parsed');
                $selenium->takeScreenshot(path('uploads') . 'selenium/after-parse-' .
                                          sluggify(microtime() . ' ' . $url) . '.png');
                d('listings', $listings);
            } catch (\Throwable $e) {
                $parser->getSearchSource()->updateStatus('error');
                $selenium->takeScreenshot(path('uploads') . 'selenium/' . sluggify(microtime() . ' ' . $url) . '.png');
                d('error gettings listings from index');
            }

            /**
             * Some pages require additional pagination parsing.
             */
            if ($listings && $then) {
                try {
                    $then($this, $listings, $selenium);
                } catch (\Throwable $e) {
                    d('error running then', exception($e));
                }
            }

            /**
             * We would also like to paginate? Probably in same session for better results?
             */
            if ($then) {
                $selenium->quit();
            }

            return $listings;
        } catch (\Throwable $e) {
            d(exception($e));
            try {
                $selenium->takeScreenshot(path('uploads') . 'selenium/' . sluggify(microtime() . ' ' . $url) . '.png');
            } catch (\Throwable $e) {

            }
            $selenium->quit();
        }
    }

    /**
     * @param RemoteWebDriver $selenium
     * @param array           $structure
     *
     * @return array
     */
    public function getListingsFromIndex(RemoteWebDriver $selenium, array $structure)
    {
        $selector = array_keys($structure)[0];
        $selectors = $structure[$selector];
        $listingsSelector = array_keys($structure)[0];

        $allListings = $selenium->findElements(WebDriverBy::cssSelector($listingsSelector));

        /**
         * Collect all listings.
         */
        $listings = collect($allListings);
        d('located listings', $listings->count(), $listingsSelector);

        /**
         * Parse them.
         */
        return $listings->map(function($node) use ($selectors) {
            try {
                $props = [];

                foreach ($selectors as $selector => $details) {
                    try {
                        $this->processSectionByStructure($this->makeNode($node), $selector, $details, $props);
                    } catch (\Throwable $e) {
                        d('EXCEPTION [parsing index listing selector] ' . $selector . ' ' . exception($e));
                    }
                }

                return $props;
            } catch (\Throwable $e) {
                d('EXCEPTION [parsing index listing]' . exception($e));
            }
        })->removeEmpty()->rekey()->all();
    }

    /**
     * @param              $url
     */
    public function getListingProps($url)
    {
        d('getting selenium client');
        $selenium = $this->getSeleniumClient();
        d('got selenium client');

        try {
            $props = [];
            d('opening url');
            $selenium->get($url);
            d('opened, sleeping');
            $selenium->takeScreenshot(path('uploads') . 'selenium/before-props-' . sluggify(microtime() . ' ' . $url) .
                                      '.png');
            d('parsing structure');
            $structure = $this->source->getListingStructure();
            foreach ($structure as $selector => $details) {
                try {
                    $this->processSectionByStructure($this->makeNode($selenium->findElement(WebDriverBy::cssSelector('body'))),
                                                     $selector, $details, $props);
                } catch (\Throwable $e) {
                    d('exception parsing node selector ', $selector, exception($e));
                }
            }
            d('parsed');

            // $this->firewall($selenium, $url);

            // parse?
            $selenium->takeScreenshot(path('uploads') . 'selenium/after-props-' . sluggify(microtime() . ' ' . $url) .
                                      '.png');

            $selenium->quit();

            return $props;
        } catch (\Throwable $e) {
            $selenium->quit();
            d("getlistingprops", exception($e));
        }
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
     * @param $xpath
     *
     * @return mixed
     */
    public function getElementByXpath($section, string $xpath)
    {
        return $section->findElement(WebDriverBy::xpath('.//' . $xpath));
    }

}