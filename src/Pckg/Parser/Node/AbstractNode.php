<?php namespace Pckg\Parser\Node;

use Facebook\WebDriver\Remote\RemoteWebDriver;
use Pckg\Parser\Node;
use PHPHtmlParser\Dom\HtmlNode;
use Pckg\Parser\Node\NodeInterface;

/**
 * Class Node
 *
 * @package Pckg\Parser
 */
abstract class AbstractNode implements Node\NodeInterface
{

    /**
     * @var RemoteWebDriver|HtmlNode
     */
    protected $node;

    /**
     * Node constructor.
     *
     * @param $node
     */
    public function __construct($node)
    {
        $this->node = $node;
    }

    /**
     * @return RemoteWebDriver|HtmlNode
     */
    public function getNode()
    {
        return $this->node;
    }

    /**
     * @return string
     */
    public function getOuterHtml()
    {
        $tag = strtolower($this->getTagName());

        return '<' . $tag . '>' . $this->getInnerHtml() . '</' . $tag . '>';
    }

}