<?php

namespace Pckg\Parser\Client;

use Nesk\Puphpeteer\Resources\Browser;
use Facebook\WebDriver\Remote\RemoteWebDriver;

abstract class AbstractClient
{

    /**
     * @var RemoteWebDriver|Browser
     */
    protected $client;

    public function getClient()
    {
        return $this->client;
    }
}
