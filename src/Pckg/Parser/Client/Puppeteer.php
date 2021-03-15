<?php

namespace Pckg\Parser\Client;

use Facebook\WebDriver\WebDriverBy;
use Facebook\WebDriver\WebDriverKeys;
use Nesk\Puphpeteer\Resources\Browser;
use Nesk\Puphpeteer\Resources\Page;
use Nesk\Rialto\Data\JsFunction;
use Pckg\Parser\Driver\SeleniumFactory;

class Puppeteer implements Headless
{

    /**
     * @var Browser
     */
    protected $client;

    /**
     * @var Page
     */
    protected $page;

    public function __construct($proxy = null)
    {
        $puppeteer = new \Nesk\Puphpeteer\Puppeteer([
            'idle_timeout' => 240,
            'read_timeout' => 240,
        ]);
        $args = [
            '--no-sandbox',
            '--disable-setuid-sandbox',
            '--disable-dev-shm-usage',
            '--window-size=1920,1080',
        ];
        if ($proxy) {
            $args[] = '--proxy-server=http://' . $proxy;
        }
        $this->client = $puppeteer->launch([
            'headless' => true,
            'ignoreHTTPSErrors' => true,
            'args' => $args
        ]);
    }

    public function close()
    {
        return $this->try(function () {
            $this->client->close();
            $this->client = null;
            $this->page = null;
            context()->unbind(Page::class);
        });
    }

    public function getCurrentURL()
    {
        return $this->try(function () {
            return $this->page->url();
        });
    }

    public function findElements($cssSelector)
    {
        return $this->try(function () use ($cssSelector) {
            return $this->page->tryCatch->querySelectorAll($cssSelector);
        });
    }

    public function findElement($cssSelector)
    {
        return $this->try(function () use ($cssSelector) {
            return $this->page->tryCatch->querySelector($cssSelector);
        });
    }

    public function takeScreenshot()
    {
        try {
            $screenshot = 'selenium/' . ($this->key ?? sluggify(get_class($this))) . '-' . date('Y-m-d-H-i-s') . '-' . sha1(microtime()) . '.png';
            $this->page->tryCatch->screenshot(['path' => path('uploads') . $screenshot]);
        } catch (\Throwable $e) {
            error_log("Puppeteer: Error taking screenshot - " . exception($e));
        }
    }

    public function get($url)
    {
        return $this->try(function () use ($url) {
            $this->page = $this->client->newPage();
            context()->bind(Page::class, $this->page);
            $this->page->goto($url);
            $this->page->evaluate((new JsFunction())->body(SeleniumFactory::getProtectionScript()));
        });
    }

    public function wait(int $seconds)
    {
        sleep(5);
    }

    public function waitClickable($selector)
    {
        return $this->try(function () use ($selector) {
            $this->page->tryCatch->waitForSelector($selector, ['timeout' => 5000]);
        });
    }

    public function enterInput($selector, $value)
    {

        return $this->try(function () use ($selector, $value) {
            $this->page->tryCatch->type($selector, '');
            $this->page->tryCatch->type($selector, $value);
        });
    }

    public function sendKeys($selector, $keys)
    {
        return $this->try(function () use ($selector, $keys) {
            $this->tryCatch->findElement($selector)->press($keys);
            //$this->page->tryCatch->type($selector, $keys);
            // or page.keyboard.press('Enter');
        });
    }

    public function click($selector, $wait = true)
    {
        return $this->try(function () use ($selector) {
            $this->page->tryCatch->click($selector);
        });
    }

    public function try(callable $task)
    {
        try {
            $response = $task();
            return $response;
        } catch (\Throwable $e) {
            error_log('EXCEPTIION: ' . exception($e));
        }
    }
}
