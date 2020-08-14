<?php namespace Pckg\Parser\Node;

use PHPHtmlParser\Dom\Node\HtmlNode;

/**
 * Class CurlNode
 *
 * @package Pckg\Parser\Node
 */
class CurlNode extends AbstractNode implements NodeInterface
{

    /**
     * @return string
     */
    public function getInnerHtml()
    {
        return $this->node->innerHtml;
    }

    /**
     * @return string
     */
    public function getInnerText()
    {
        return strip_tags($this->getInnerHtml());
    }

    /**
     * @return string
     */
    public function getTagName()
    {
        return $this->node->getTag()->name();
    }

    /**
     * @return \Pckg\Collection
     */
    public function getChildren()
    {
        return collect($this->node->getChildren())->map(function($node) {
            return new CurlNode($node);
        });
    }

    /**
     * @param      $selector
     * @param null $nth
     *
     * @return \Pckg\Collection|\Pckg\Parser\Node\AbstractNode\PHPHtmlParser\Dom\AbstractNode|NodeInterface
     * @throws \PHPHtmlParser\Exceptions\ChildNotFoundException
     */
    public function find($selector, $nth = null)
    {
        if ($nth > 0 || $nth === 0) {
            $find = $this->node->find($selector, $nth);
            if (!$find) {
                return null;
            }

            return new CurlNode($find, $this->selector . ' ' . $selector);
        }

        return collect($this->node->find($selector, null))->map(function(HtmlNode $node) use ($selector) {
            return new CurlNode($node, $this->selector . ' ' . $selector);
        });
    }

    /**
     * @param $name
     *
     * @return string|null
     */
    public function getAttribute($name)
    {
        return $this->node->getAttribute($name);
    }

    /**
     * @return \PHPHtmlParser\Dom\AbstractNode
     * @throws \PHPHtmlParser\Exceptions\ChildNotFoundException
     * @throws \PHPHtmlParser\Exceptions\ParentNotFoundException
     */
    public function nextSibling()
    {
        return new CurlNode($this->node->nextSibling());
    }

    /**
     * @return \Pckg\Parser\Node\\PHPHtmlParser\Dom\AbstractNode
     * @throws \PHPHtmlParser\Exceptions\ChildNotFoundException
     * @throws \PHPHtmlParser\Exceptions\ParentNotFoundException
     */
    public function previousSibling()
    {
        return new CurlNode($this->node->previousSibling());
    }

    /**
     * @return CurlNode|\Pckg\Parser\Node\NodeInterface
     */
    public function parent()
    {
        return new CurlNode($this->node->getParent());
    }

}