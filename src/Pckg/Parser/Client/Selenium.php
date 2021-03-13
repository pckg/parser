<?php

namespace Pckg\Parser\Client;

use Facebook\WebDriver\WebDriverBy;
use Facebook\WebDriver\WebDriverExpectedCondition;
use Facebook\WebDriver\WebDriverKeys;
use Pckg\Parser\Driver\SeleniumFactory;

class Selenium implements Headless
{

    /**
     * @var \Facebook\WebDriver\Remote\RemoteWebDriver
     */
    protected $client;

    public function __construct($proxy)
    {
        $this->client = SeleniumFactory::getSeleniumClient(null, $proxy);
    }

    public function close()
    {
        $this->client->quit();
        $this->client = null;
    }

    public function getCurrentURL()
    {
        return $this->client->getCurrentURL();
    }

    public function findElements($cssSelector)
    {
        return $this->client->findElements(WebDriverBy::cssSelector($cssSelector));
    }

    public function findElement($cssSelector)
    {
        return $this->client->findElement(WebDriverBy::cssSelector($cssSelector));
    }

    public function takeScreenshot()
    {
        try {
            $screenshot = 'selenium/' . ($this->key ?? sluggify(get_class($this))) . '-' . date('Y-m-d-H-i-s') . '-' . sha1(microtime()) . '.png';
            $this->client->takeScreenshot(path('uploads') . $screenshot);
            return $screenshot;
        } catch (\Throwable $e) {
            error_log("Selenium: Error taking screenshot - " . exception($e));
        }
    }

    public function get($url)
    {
        $this->client->get($url);
    }

    public function wait(int $seconds, $interval = 333)
    {
        $this->client->wait($seconds, $interval);
        sleep(5);
    }

    public function waitClickable($selector)
    {
        $this->client->wait(5, 333)
            ->until(
                WebDriverExpectedCondition::elementToBeClickable(WebDriverBy::cssSelector($selector)),
                'Waited selector not found: ' . $selector
            );
    }

    public function enterInput($selector, $value)
    {
        d('entering', $value, 'into', $selector);
        $this->client->findElement(WebDriverBy::cssSelector($selector))->clear()->sendKeys($value);
    }

    public function sendKeys($selector, $keys)
    {
        $keys = [
                'enter' => WebDriverKeys::ENTER
            ][$keys] ?? null;
        if (!$keys) {
            d('no keys to send');
            return;
        }
        d('sending', $keys, 'to', $selector);
        $this->client->findElement(WebDriverBy::cssSelector($selector))->sendKeys($keys);
    }

    public function click($selector, $wait = true)
    {
        if ($wait) {
            $this->waitClickable($selector);
        }

        $this->client->findElement(WebDriverBy::cssSelector($selector))->click();
    }

    public function executeScript($script)
    {
        $this->client->executeScript($script);
    }
}
