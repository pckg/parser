<?php

namespace Pckg\Parser\Node;

use Nesk\Puphpeteer\Resources\ElementHandle;
use Nesk\Puphpeteer\Resources\Page;
use Nesk\Rialto\Data\JsFunction;
use Pckg\Collection;
use Pckg\Parser\Driver\SeleniumFactory;
use Pckg\Parser\Node\AbstractNode;
use Pckg\Parser\Node\NodeInterface;

class PuppeteerNode extends AbstractNode implements NodeInterface
{

    /**
     * @var ElementHandle
     */
    protected $node;

    /**
     * @return Page
     * @throws \Exception
     */
    protected function getPage()
    {
        return context()->get(Page::class)->tryCatch;
    }

    protected function evaluateElement($body)
    {
        return $this->getPage()->evaluate(JsFunction::createWithParameters(['element'])->body($body), $this->node);
    }

    /**
     * @return string
     */
    public function getInnerHtml()
    {
        return $this->evaluateElement('return element.innerHTML');
    }

    /**
     * @return string
     */
    public function getInnerText()
    {
        return $this->evaluateElement('return element.innerText');
    }

    /**
     * @return string
     */
    public function getTagName()
    {
        return $this->evaluateElement('return element.tagName');
    }

    /**
     * @return \Pckg\Collection
     */
    public function getChildren()
    {
        return collect($this->evaluateElement('return element.children'))
            ->map(function ($node) {
                return new PuppeteerNode($node);
            });
    }

    /**
     * @param      $selector
     * @param null $nth
     *
     * @return mixed|PuppeteerNode|Collection|null
     */
    public function find($selector, $nth = null)
    {
        $elements = $this->node->tryCatch->querySelectorAll($selector);
        if ($nth > 0 || $nth === 0) {
            $find = $elements[$nth] ?? null;
            if (!$find) {
                return null;
            }

            return new PuppeteerNode($find);
        }

        return collect($elements)->map(function ($node) {
            return new PuppeteerNode($node);
        });
    }

    /**
     * @param $name
     *
     * @return string|null
     */
    public function getAttribute($name)
    {
        return $this->evaluateElement('return (element.attributes[' . json_encode($name) . '] || {}).value || \'\'');
    }

    protected function nodeOrNull($node)
    {
        if (!$node) {
            return null;
        }
        return new PuppeteerNode($node);
    }

    /**
     * @return AbstractNode
     * @throws \PHPHtmlParser\Exceptions\ChildNotFoundException
     * @throws \PHPHtmlParser\Exceptions\ParentNotFoundException
     */
    public function nextSibling()
    {
        return $this->nodeOrNull($this->evaluateElement('return element.nextElementSibling'));
    }

    /**
     * @return AbstractNode
     * @throws \PHPHtmlParser\Exceptions\ChildNotFoundException
     * @throws \PHPHtmlParser\Exceptions\ParentNotFoundException
     */
    public function previousSibling()
    {
        return $this->nodeOrNull($this->evaluateElement('return element.previousElementSibling'));
    }

    /**
     * @return Puppe
     */
    public function parent()
    {
        return $this->nodeOrNull($this->evaluateElement('return element.parentNode'));
    }
}
