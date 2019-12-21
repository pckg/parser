<?php namespace Pckg\Parser\Driver;

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

    public function open();

    public function close();

    /**
     * @param SourceInterface $parser
     * @param string          $url
     * @param callable|null   $then
     *
     * @return array
     */
    public function getListings(string $url, callable $then = null);

    /**
     * @param string $url
     *
     * @return array
     */
    public function getListingProps(string $url, callable $then = null);

    /**
     * @param $driver
     * @param $props
     *
     * @return mixed
     */
    public function autoParseListing(&$props);

    /**
     * @param        $section
     * @param string $attribute
     *
     * @return string
     */
    public function getElementAttribute($section, string $attribute);

    /**
     * @param $section
     *
     * @return string
     */
    public function getElementInnerHtml($section);

    /**
     * @param        $section
     * @param string $css
     *
     * @return mixed
     */
    public function getElementByCss($section, string $css);

    /**
     * @param        $section
     * @param string $css
     *
     * @return array
     */
    public function getElementsByCss($section, string $css);

    /**
     * @param        $section
     * @param string $xpath
     *
     * @return mixed
     */
    public function getElementByXpath($section, string $xpath);

    /**
     * @param $node
     *
     * @return \Pckg\Parser\Node\NodeInterface
     */
    public function makeNode($node);

}