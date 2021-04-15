<?php

namespace Pckg\Parser\Node;

use Facebook\WebDriver\Remote\RemoteWebDriver;
use Pckg\Parser\Node;
use PHPHtmlParser\Dom\Node\HtmlNode;
use Pckg\Parser\Node\NodeInterface;
use Twig\Node\TextNode;

/**
 * Class Node
 *
 * @package Pckg\Parser
 */
abstract class AbstractNode implements Node\NodeInterface
{

    /**
     * @var RemoteWebDriver|HtmlNode|TextNode
     */
    protected $node;

    /**
     * @var string
     */
    protected $selector;

    /**
     * Node constructor.
     *
     * @param $node
     */
    public function __construct($node, $selector = null)
    {
        if (!$node) {
            $node = '';
            //throw new \Exception('Node cannot be empty?');
        }

        $this->node = $node;
        $this->selector = $selector;
    }

    /**
     * @return RemoteWebDriver|HtmlNode
     */
    public function getNode()
    {
        return $this->node;
    }

    /**
     * @return string|null
     */
    public function getSelector()
    {
        return $this->selector;
    }

    /**
     * @return string
     */
    public function getOuterHtml()
    {
        $tag = strtolower($this->getTagName());

        return '<' . $tag . '>' . $this->getInnerHtml() . '</' . $tag . '>';
    }

    /**
     * @param $class
     *
     * @return bool
     */
    public function hasClass($class)
    {
        return stringify($this->getAttribute('class'))->explodeToCollection(' ')->has($class);
    }

    /**
     * @return bool
     */
    public function isTextNode()
    {
        return $this->getTagName() === 'text';
    }
}
