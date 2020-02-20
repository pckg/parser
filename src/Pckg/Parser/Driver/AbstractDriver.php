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
        return $this->source->getDispatcher();
    }

    public function trigger(string $event, $data)
    {
        return $this->getDispatcher()->trigger($event, $data);
    }

}