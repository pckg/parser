<?php

namespace Pckg\Parser\Driver;

use Facebook\WebDriver\Remote\RemoteWebDriver;
use Pckg\Parser\Node\NodeInterface;
use Pckg\Parser\ParserInterface;
use Pckg\Parser\Source\SourceInterface;
use PHPHtmlParser\Dom;

/**
 * Interface DriverInterface
 *
 * @package Pckg\Parser\Driver
 */
interface DriverInterface
{

    /**
     * @return RemoteWebDriver|mixed|Dom
     */
    public function getClient();

    /**
     * @param SourceInterface $parser
     * @param string          $url
     * @param callable|null   $then
     *
     * @return array
     */
    public function getListings(string $url);

    /**
     * @param string $url
     *
     * @return array
     */
    public function getListingProps(string $url);
}
