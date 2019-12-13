<?php namespace Pckg\Parser\Driver;

use Pckg\Parser\Node\NodeInterface;
use Pckg\Parser\ParserInterface;
use Pckg\Parser\Source\SourceInterface;

/**
 * Interface DriverInterface
 *
 * @package Pckg\Parser\Driver
 */
interface DriverInterface
{

    /**
     * @param ParserInterface $parser
     * @param string          $url
     * @param callable|null   $then
     *
     * @return array
     */
    public function getListings(SourceInterface $parser, string $url, callable $then = null);

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