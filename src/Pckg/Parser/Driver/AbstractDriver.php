<?php namespace Pckg\Parser\Driver;

use Facebook\WebDriver\Exception\InvalidSelectorException;
use Facebook\WebDriver\Remote\RemoteWebDriver;
use Pckg\Concept\Event\Dispatcher;
use Pckg\Parser\Driver\DriverInterface;
use Pckg\Parser\Node\CurlNode;
use Pckg\Parser\Node\NodeInterface;
use Pckg\Parser\SkipException;
use Pckg\Parser\Source\SourceInterface;
use PHPHtmlParser\Dom;

/**
 * Class AbstractDriver
 *
 * @package Pckg\Parser\Driver
 */
abstract class AbstractDriver implements DriverInterface
{

    /**
     * @deprecated
     */
    use HttpDriver;

    protected $node = CurlNode::class;

    /**
     * @var SourceInterface
     */
    protected $source;

    /**
     * @var RemoteWebDriver|Dom
     */
    protected $client;

    public function open()
    {
        return $this;
    }

    public function close()
    {
        return $this;
    }

    public function __construct(SourceInterface $source = null)
    {
        $this->source = $source;
    }

    /**
     * @return RemoteWebDriver|mixed|Dom
     */
    public function getClient()
    {
        return $this->client;
    }

    /**
     * @return Dispatcher
     */
    public function getDispatcher()
    {
        if (!$this->source) {
            return null;
        }
        
        return $this->source->getDispatcher();
    }

    /**
     * @param string $event
     * @param        $data
     *
     * @return Dispatcher|null
     */
    public function trigger(string $event, $data)
    {
        if (!($dispatcher = $this->getDispatcher())) {
            return null;
        }

        return $dispatcher->trigger($event, $data);
    }

    /**
     * @param      $selector
     * @param bool $found
     */
    public function found($selector, $found)
    {
        $this->trigger('node.' . ($found ? 'found' : 'notFound'), trim($selector));

        return !!$found;
    }

}