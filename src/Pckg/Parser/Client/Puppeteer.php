<?php

namespace Pckg\Parser\Client;

use Facebook\WebDriver\WebDriverBy;
use Facebook\WebDriver\WebDriverKeys;
use Nesk\Puphpeteer\Resources\Browser;
use Nesk\Puphpeteer\Resources\Page;
use Nesk\Rialto\Data\JsFunction;
use Pckg\Parser\Driver\SeleniumFactory;

class Puppeteer extends AbstractClient implements Headless
{

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
        /**
         * @var Browser
         */
        $this->client = $puppeteer->launch([
            'headless' => true,
            'ignoreHTTPSErrors' => true,
            'defaultViewport' => null,
            'args' => $args
        ]);
        $this->executeScript(SeleniumFactory::getProtectionScript());
    }

    public function getCookies()
    {
        return collect($this->page->cookies())->map(
            function ($cookie) {
                return $cookie->toArray();
            }
        );
    }

    public function setCookies(array $cookies, array $domains)
    {
        // TODO: Implement setCookies() method.
    }

    public function close()
    {
        return $this->try(function () {
            if ($this->client) {
                $this->client->close();
                $this->client = null;
            }
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
            return $this->tryCatch()->querySelectorAll($cssSelector);
        });
    }

    public function findElement($cssSelector)
    {
        return $this->try(function () use ($cssSelector) {
            return $this->tryCatch()->querySelector($cssSelector);
        });
    }

    public function takeScreenshot()
    {
        try {
            $screenshot = 'selenium/' .     sha1(microtime()) . '.png';
            $this->tryCatch()->screenshot(['path' => path('uploads') . $screenshot]);
        } catch (\Throwable $e) {
            error_log("Puppeteer: Error taking screenshot - " . exception($e));
        }
    }

    public function get($url)
    {
        return $this->try(function () use ($url) {
            $this->page = $this->client->newPage();
            context()->bind(Page::class, $this->page);
            $this->page->setViewport([
                'width' => 1920,
                'height' => 1080,
            ]);
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
            $this->tryCatch()->waitForSelector($selector, ['timeout' => 5000]);
        });
    }

    public function enterInput($selector, $value)
    {

        return $this->try(function () use ($selector, $value) {
            $this->tryCatch()->type($selector, '');
            $this->tryCatch()->type($selector, $value);
        });
    }

    public function sendKeys($selector, $keys)
    {
        return $this->try(function () use ($selector, $keys) {
            $this->tryCatch()->findElement($selector)->press($keys);
            //$this->tryCatch()->type($selector, $keys);
            // or page.keyboard.press('Enter');
        });
    }

    public function click($selector, $wait = true)
    {
        return $this->try(function () use ($selector) {
            $this->tryCatch()->click($selector);
        });
    }

    public function executeScript($script)
    {
        return $this->try(function () use ($script) {
            $this->tryCatch()->evaluate((new JsFunction())->body($script));
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

    public function tryCatch()
    {
        return $this->page->tryCatch;
    }

    public function switchToFrame($frame)
    {
        return $frame->contentFrame();
    }

    public function switchToDefault()
    {
        return $this;
    }
}
