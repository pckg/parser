<?php namespace Pckg\Parser\Driver;

use Facebook\WebDriver\Chrome\ChromeOptions;
use Facebook\WebDriver\Remote\DesiredCapabilities;
use Facebook\WebDriver\Remote\RemoteWebDriver;
use Facebook\WebDriver\Remote\WebDriverCapabilityType;

class SeleniumFactory
{

    protected static $host = 'http://selenium-hub:4444/wd/hub';

    /**
     * @return RemoteWebDriver
     */
    public static function getSeleniumClient($host = null, $proxy = null)
    {
        if (!$host) {
            $host = static::$host;
        }
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
                                   //'--headless',
                                   '--window-size=1280,1024',
                                   //'headless',
                                   'start-maximized',
                                   'disable-infobar',
                                   '--lang=en_US',
                                   '--user-agent=' . $agent,
                               ]);
        $capabilities->setCapability(ChromeOptions::CAPABILITY, $options);
        //$capabilities->setCapability('idleTimeout', 10); // zalenium
        //$capabilities->setCapability('screenResolution', '360x720'); // zalenium
        //$capabilities->setCapability('redordVideo', true); // zalenium

        /**
         * Select random proxy.
         */
        if ($proxy) {
            $capabilities->setCapability(WebDriverCapabilityType::PROXY, [
                'proxyType' => 'manual',
                'httpProxy' => 'http://' . $proxy,
                'sslProxy'  => 'http://' . $proxy,
            ]);
        }

        d('creating web driver');
        try {
            $webdriver = RemoteWebDriver::create($host, $capabilities, 10000);
        } catch (\Throwable $e) {
            d(exception($e), get_class($e));
            throw $e;
        }
        d('web driver created');

        $webdriver->executeScript(static::getProtectionScript());

        return $webdriver;
    }

    public static function getProtectionScript()
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

}