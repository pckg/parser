<?php

namespace Pckg\Parser\Driver;

use Facebook\WebDriver\Chrome\ChromeOptions;
use Facebook\WebDriver\Remote\DesiredCapabilities;
use Facebook\WebDriver\Remote\RemoteWebDriver;
use Facebook\WebDriver\Remote\WebDriverCapabilityType;
use GuzzleHttp\Client;
use GuzzleHttp\RequestOptions;
use Pckg\Framework\Helper\Retry;

class SeleniumFactory
{

    /**
     * @var string
     */
    protected static $host = 'http://selenium-hub:4444/wd/hub';

    /**
     * @return array|callable|mixed|\Pckg\Framework\Config|null
     */
    private static function getHost($host)
    {
        if ($host) {
            return $host;
        }

        return config('pckg.parser.hub.host', static::$host);
    }

    /**
     * @return RemoteWebDriver
     */
    public static function getSeleniumClient($host = null, $proxy = null)
    {
        $host = static::getHost($host);

        /**
         * Retry for 20 times in the interval of 6s to get a connection.
         */
        return (new Retry())->interval(5)
            ->retry(5)
            ->heartbeat(function () {
                dispatcher()->trigger('heartbeat');
            })
            ->make(function () use ($host, $proxy) {
                /**
                 * Emit event so apps can implement capacity limiters.
                 */
                if (false) {
                    d('retrying 5 times, 5 seconds for grid status');
                    $okay = (new Retry())->interval(5)
                        ->retry(5)
                        ->heartbeat(function () {
                            dispatcher()->trigger('heartbeat');
                        })
                        ->make(function () use ($host) {
                            $response = (new Client())->get($host . '/status', [RequestOptions::HTTP_ERRORS => false]);
                            $data = json_decode($response->getBody()->getContents(), true);
                            $hasCapacity = ($data['value']['ready'] ?? null) && in_array($data['value']['message'] ?? null, ['Selenium Grid ready.', 'Hub has capacity']);

                            if (!$hasCapacity) {
                                throw new \Exception('No capacity on Hub / Grid');
                            }

                            return true;
                        });

                    if (!$okay) {
                        throw new \Exception('Hub has no capacity after 10 retries.');
                    }
                }

                $chrome = true;
                if ($chrome) {
                    $capabilities = DesiredCapabilities::chrome();
                    $options = new ChromeOptions();
                    $agents = config('pckg.parser.agents', []);
                    $agent = $agents[array_rand($agents)];
                    $options->addArguments([
                        '--disable-dev-shm-usage',
                        '--whitelisted-ips',
                        '--disable-extensions',
                        '--no-sandbox',
                        '--verbose',
                        '--disable-gpu',
                        '--headless',
                        //'--window-size=1280,1024',
                        '--window-size=1600,1200',
                        'headless',
                        'start-maximized',
                        'disable-infobar',
                        '--lang=en_US',
                        '--user-agent=' . $agent,
                        //'--user-data-dir=selenium',
                    ]);
                    $capabilities->setCapability(ChromeOptions::CAPABILITY, $options);
                } else {
                    $capabilities = DesiredCapabilities::firefox();
                    /*$options = new FirefoxO();
                    $agents = config('pckg.parser.agents', []);
                    $agent = $agents[array_rand($agents)];
                    $options->addArguments([
                        '--disable-dev-shm-usage',
                        //'--whitelisted-ips',
                        '--disable-extensions',
                        '--no-sandbox',
                        //'--verbose',
                        //'--disable-gpu',
                        //'--headless',
                        //'--window-size=1280,1024',
                        //'headless',
                        'start-maximized',
                        'disable-infobar',
                        //'--lang=en_US',
                        //'--user-agent=' . $agent,
                    ]);
                    $capabilities->setCapability(ChromeOptions::CAPABILITY, $options);*/
                }
                //$capabilities->setCapability('idleTimeout', 10); // zalenium
                //$capabilities->setCapability('screenResolution', '360x720'); // zalenium
                //$capabilities->setCapability('recordVideo', true); // zalenium

                /**
                 * Select random proxy.
                 */
                if ($proxy) {
                    $capabilities->setCapability(WebDriverCapabilityType::PROXY, [
                        'proxyType' => 'manual',
                        'httpProxy' => 'http://' . $proxy,
                        'sslProxy' => 'http://' . $proxy,
                    ]);
                }

                /**
                 * Create webdriver.
                 */
                d('creating web driver on ' . $host);
                $webdriver = RemoteWebDriver::create($host, $capabilities, 65000, 125000);
                d('web driver created');

                /**
                 * Identify us as normal user.
                 */
                $webdriver->executeScript(static::getProtectionScript());
                d('executed un-protection');

                return $webdriver;
            });
    }

    public static function getProtectionScript()
    {
        return '
//await page.evaluateOnNewDocument(function(){
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
    });
//});
    
    window.scrollBy(0,10);
    ';
    }
}
