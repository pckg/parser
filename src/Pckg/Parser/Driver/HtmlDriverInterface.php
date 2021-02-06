<?php

namespace Pckg\Parser\Driver;

interface HtmlDriverInterface extends DriverInterface
{

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
    public function makeNode($node, $selector = null);
}
