<?php

namespace Pckg\Parser\Driver;

use Facebook\WebDriver\Chrome\ChromeOptions;
use Facebook\WebDriver\Remote\DesiredCapabilities;
use Facebook\WebDriver\Remote\RemoteWebDriver;
use Facebook\WebDriver\Remote\RemoteWebElement;
use Facebook\WebDriver\Remote\WebDriverCapabilityType;
use Facebook\WebDriver\WebDriverBy;
use Nesk\Puphpeteer\Resources\Browser;
use Pckg\Parser\Node\PuppeteerNode;
use Pckg\Parser\Node\SeleniumNode;
use Pckg\Parser\SkipException;
use Pckg\Parser\Source\AbstractSource;
use Pckg\Parser\ParserInterface;
use Pckg\Parser\Driver\AbstractDriver;
use Pckg\Parser\Driver\DriverInterface;
use Pckg\Parser\Source\SourceInterface;

class Puppeteer extends Selenium implements DriverInterface
{
    protected $node = PuppeteerNode::class;

    protected $clientClass = \Pckg\Parser\Client\Puppeteer::class;
}
