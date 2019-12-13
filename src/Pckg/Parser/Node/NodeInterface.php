<?php namespace Pckg\Parser\Node;

use Pckg\Collection;

/**
 * Interface NodeInterface
 *
 * @package Pckg\Parser
 */
interface NodeInterface
{

    /**
     * @return string|null
     */
    public function getInnerHtml();

    /**
     * @return string|null
     */
    public function getInnerText();

    /**
     * @return string
     */
    public function getTagName();

    /**
     * @return Collection
     */
    public function getChildren();

    /**
     * @param      $selector
     * @param null $nth
     *
     * @return mixed|Collection|NodeInterface|null
     */
    public function find($selector, $nth = null);

    /**
     * @param $attribute
     *
     * @return mixed|string|null
     */
    public function getAttribute($attribute);

    /**
     * @return NodeInterface
     */
    public function nextSibling();

    /**
     * @return NodeInterface
     */
    public function previousSibling();

    /**
     * @return NodeInterface
     */
    public function parent();

}