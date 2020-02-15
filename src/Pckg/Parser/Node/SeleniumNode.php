<?php namespace Pckg\Parser\Node;

use Facebook\WebDriver\Remote\RemoteWebDriver;
use Facebook\WebDriver\Remote\RemoteWebElement;
use Facebook\WebDriver\WebDriverBy;
use Pckg\Parser\Node\CurlNode;
use Pckg\Parser\Node\AbstractNode;
use Pckg\Parser\Node\NodeInterface;

class SeleniumNode extends AbstractNode implements NodeInterface
{

    /**
     * @var RemoteWebElement
     */
    protected $node;

    /**
     * Node constructor.
     *
     * @param $node RemoteWebElement
     */
    public function __construct(RemoteWebElement $node)
    {
        parent::__construct($node);
    }

    /**
     * @return string
     */
    public function getInnerHtml()
    {
        return $this->node->getAttribute('innerHTML');
    }

    /**
     * @return string
     */
    public function getInnerText()
    {
        return $this->node->getAttribute('innerText');
    }

    /**
     * @return string
     */
    public function getTagName()
    {
        return $this->node->getTagName();
    }

    /**
     * @return \Pckg\Collection
     */
    public function getChildren()
    {
        return collect($this->node->findElements(WebDriverBy::cssSelector('> *')));
    }

    /**
     * @param      $selector
     * @param null $nth
     *
     * @return mixed|\Pckg\Collection|CurlNode|\Pckg\Parser\Node\NodeInterface|null
     */
    public function find($selector, $nth = null)
    {
        $elements = $this->node->findElements(WebDriverBy::cssSelector($selector));
        if ($nth > 0 || $nth === 0) {
            $find = $elements[$nth] ?? null;
            if (!$find) {
                return null;
            }

            return new SeleniumNode($find);
        }

        return collect($elements)->map(function($node) {
            return new SeleniumNode($node);
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
     * @return AbstractNode
     * @throws \PHPHtmlParser\Exceptions\ChildNotFoundException
     * @throws \PHPHtmlParser\Exceptions\ParentNotFoundException
     */
    public function nextSibling()
    {
        return new SeleniumNode($this->node->findElement(WebDriverBy::xpath('following-sibling::*')));
    }

    /**
     * @return AbstractNode
     * @throws \PHPHtmlParser\Exceptions\ChildNotFoundException
     * @throws \PHPHtmlParser\Exceptions\ParentNotFoundException
     */
    public function previousSibling()
    {
        return new SeleniumNode($this->node->findElement(WebDriverBy::xpath('preceding-sibling::*')));
    }

    /**
     * @return SeleniumNode|\Pckg\Parser\Node\NodeInterface
     */
    public function parent()
    {
        return new SeleniumNode($this->node->findElement(WebDriverBy::xpath('parent::*')));
    }

}