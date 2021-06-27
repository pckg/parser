<?php

namespace Pckg\Parser\Client;

use Facebook\WebDriver\WebDriverBy;
use Facebook\WebDriver\WebDriverExpectedCondition;

interface Headless
{

    public function close();

    public function getCurrentURL();

    public function findElements($cssSelector);

    public function findElement($cssSelector);

    public function takeScreenshot();

    public function get($url);

    public function wait(int $seconds);

    public function waitClickable($selector);

    public function enterInput($selector, $value);

    public function sendKeys($selector, $keys);

    public function click($selector, $wait = true);

    public function getCookies();

    public function executeScript($script);

    public function switchToFrame($frame);

    public function switchToDefault();

    public function setCookies(array $cookies, array $domains);
}
