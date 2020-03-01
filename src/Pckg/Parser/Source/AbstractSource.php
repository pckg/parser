<?php namespace Pckg\Parser\Source;

use Pckg\Collection;
use Pckg\Concept\Event\Dispatcher;
use Pckg\Parser\Driver\AbstractDriver;
use Pckg\Parser\Driver\Curl;
use Pckg\Parser\Driver\DriverInterface;
use Pckg\Parser\Node\NodeInterface;
use Pckg\Parser\Search;
use Pckg\Parser\Search\PageInterface;
use Pckg\Parser\Search\ResultInterface;
use Pckg\Parser\Search\SearchInterface;
use Pckg\Parser\SearchSource;
use Pckg\Parser\SkipException;

abstract class AbstractSource implements SourceInterface
{

    /**
     * @var string
     */
    protected $type = 'listed';

    /**
     * @var string
     */
    protected $key = 'abstract';

    /**
     * @var PageInterface|null
     */
    protected $page;

    /**
     * @var SearchInterface
     */
    protected $search;

    /**
     * @var Dispatcher
     */
    protected $dispatcher;

    /**
     * @var array
     */
    protected $capabilities = [];

    /**
     * AbstractSource constructor.
     *
     * @param Dispatcher $dispatcher
     */
    public function __construct(Dispatcher $dispatcher = null)
    {
        $this->dispatcher = $dispatcher ?? new Dispatcher();
    }

    /**
     * @return Dispatcher
     */
    public function getDispatcher()
    {
        return $this->dispatcher;
    }

    public function trigger(string $event, $data)
    {
        $this->getDispatcher()->trigger($event, $data);
    }

    /**
     * @param mixed $capability
     *
     * @return bool
     */
    public function hasCapability($capability)
    {
        if (!is_array($capability)) {
            $capability = [$capability];
        }

        foreach ($capability as $singleCapability) {
            if (!in_array($singleCapability, $this->capabilities)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param null $message
     *
     * @throws SkipException
     */
    public function skipItem($message = null)
    {
        throw new SkipException($message ?? 'Skipping item (missmatch?)');
    }

    /**
     * @param SearchInterface $search
     *
     * @return $this
     */
    public function setSearch(SearchInterface $search)
    {
        $this->search = $search;

        return $this;
    }

    /**
     * @param PageInterface $page
     *
     * @return $this
     */
    public function setPage(PageInterface $page)
    {
        $this->page = $page;

        return $this;
    }

    /**
     * @return mixed|SearchInterface
     */
    public function getSearch()
    {
        return $this->search;
    }

    /**
     * @return PageInterface|null
     */
    public function getPage()
    {
        return $this->page;
    }

    /**
     * @return string
     */
    public function getKey()
    {
        return $this->key;
    }

    public function afterListingParse($driver, $listing, ...$params)
    {

    }

    public function shouldContinueToNextPage($page)
    {
        /**
         * Limit number of parsed (sub)pages.
         */
        if ($this->subPages && $page < $this->subPages) {
            return true;
        }

        return false;
    }

    /**
     * @param SearchInterface $search
     */
    public function beforeIndexParse()
    {
        /**
         * This is where search is set and we can validate it.
         */
    }

    /**
     * @param SearchInterface $search
     */
    public function afterIndexParse()
    {
        /**
         * This is where our index or search page was parsed and we are trying to parse additional sources.
         * Sources that would like to be searched over Google SERPs API are parsed here.
         */
    }

    /**
     * @param NodeInterface $node
     * @param               $props
     * @param array         $keys
     * @param callable      $matcher
     */
    protected function eachParse(NodeInterface $node, &$props, array $keys, callable $matcher)
    {
        foreach ($keys as $label => $slug) {
            try {
                $value = $matcher($node, $label);
                if (trim($value)) {
                    $props[$slug] = trim($value);

                    return true;
                }
            } catch (\Throwable $e) {
                d('exception eachParse', exception($e));
            }
        }
    }

    public function copySearchProps($keys, &$props)
    {
        foreach ($keys as $key) {
            $props[$key] = $this->getSearch()->getDataProp($key);
        }
    }

}