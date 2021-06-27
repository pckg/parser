<?php

namespace Pckg\Parser\Source;

use Pckg\Parser\Client\Puppeteer;

trait HeadlessHelper
{

    public function getClient()
    {
        return $this->getDriver()->getClient();
    }

    /**
     * @param string $url
     * @param int|string $wait
     * @return \Pckg\Parser\Client\Selenium|Puppeteer
     */
    public function openAndWait($url, $wait = 5)
    {
        $client = $this->getClient();
        $client->get($url);

        if (is_int($wait)) {
            sleep($wait);
        } else {
            $client->waitClickable($wait);
        }

        return $client;
    }

    /**
     * @param string $selector
     * @param string $value
     * @throws \Facebook\WebDriver\Exception\NoSuchElementException
     * @throws \Facebook\WebDriver\Exception\TimeOutException
     */
    public function waitAndEnter($selector, $value)
    {
        $this->trigger('parse.log', 'Waiting for input ' . $selector);
        $this->getClient()->waitClickable($selector);

        $this->trigger('parse.log', 'Clearing + entering keyword into ' . $selector);
        $this->getClient()->enterInput($selector, $value);
    }

    /**
     * @param string $selector
     * @param string $value
     * @throws \Facebook\WebDriver\Exception\NoSuchElementException
     * @throws \Facebook\WebDriver\Exception\TimeOutException
     */
    public function sendKeys($selector, $keys)
    {
        $this->trigger('parse.log', 'Waiting for input ' . $selector);
        $this->getClient()->waitClickable($selector);

        $this->trigger('parse.log', 'Sending keys ' . $selector);
        $this->getClient()->sendKeys($selector, $keys);
    }

    /**
     * @param string $selector
     * @param string $value
     */
    public function waitAndSelect($selector, $value)
    {
        $this->clickAndWait($selector . ' option[value="' . $value . '"]');
    }

    /**
     * @param string $selector
     */
    public function clickAndWait($selector, $sleep = 5)
    {
        $this->trigger('parse.log', 'Clicking and waiting on ' . $selector);
        $this->getClient()->click($selector);
        if ($sleep) {
            sleep(5);
        }
    }

    /**
     * @param string $selector
     */
    public function waitAndClick($selector)
    {
        $this->trigger('parse.log', 'Waiting for click on ' . $selector);
        $this->getClient()->waitClickable($selector);
        $this->getClient()->click($selector);
    }

    /**
     * @param string $selector
     */
    public function submitAndWait($selector)
    {
        $this->trigger('parse.log', 'Submitting form');
        $this->getClient()->click($selector, false);

        $this->trigger('parse.log', 'Submitted, waiting');
        sleep(10);
        $this->trigger('parse.log', 'Waited, continuing');
    }

    public function submitAndWaitForIndex($selector)
    {
        $this->trigger('parse.log', 'Submitting form');
        $this->getClient()->click($selector, false);

        $indexSelector = array_keys($this->getIndexStructure())[0];
        $this->trigger('parse.log', 'Waiting for index - ' . $indexSelector);
        $this->getClient()->waitClickable($indexSelector);
        $this->trigger('parse.log', 'Index is present - ' . $indexSelector);
    }

    public function takeScreenshot()
    {
        if (!($client = $this->getClient())) {
            return;
        }

        try {
            $screenshot = $client->takeScreenshot();
            if ($screenshot) {
                $this->trigger('parse.log', 'Screenshot /storage/uploads/' . $screenshot);
            }
        } catch (\Throwable $e) {
            error_log('HeadlessHelper: Error taking screenshot:' . exception($e));
        }
    }
}
