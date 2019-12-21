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

    public function __construct(SourceInterface $source)
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

    /**
     * @param $node
     * @param $getter string
     * @param $setter string|callable|array
     * @param $props  array
     *
     * @throws \Exception
     * @throws \Throwable
     */
    public function processSectionByStructure(NodeInterface $node, $getter, $setter, &$props)
    {
        try {
            /**
             * Check for foreach.
             */
            $value = null;
            $match = false;
            if (strpos($getter, 'each:') === 0) {
                $selector = substr($getter, 5);
                $nodes = $node->find($selector);
                $nodes->each(function(NodeInterface $node) use ($setter, &$props) {
                    try {
                        $this->processSectionByStructure($node, '&', $setter, $props);
                    } catch (SkipException $e) {
                        throw $e;
                    } catch (\Throwable $e) {
                        d('exception each node', exception($e));
                    }
                });

                return;
            }

            /**
             * Normal string getter.
             */
            if (strpos($getter, 'attr:') === 0) {
                $value = $node->getAttribute(substr($getter, 5));
                $match = true;
            } /*elseif (strpos($getter, 'xpath:') === 0) {
                $value = $this->makeNode($this->getElementByXpath($node, substr($getter, 6)));
                $match = true;
            } */ elseif ($getter === 'innerHtml') {
                $value = $node->getInnerHtml();
                $match = true;
            } elseif ($getter === 'innerText') {
                $value = $node->getInnerText();
                $match = true;
            } elseif ($getter === '&') {
                if (is_array($setter)) {
                    foreach ($setter as $k => $v) {
                        $this->processSectionByStructure($node, $k, $v, $props);
                    }

                    return;
                }
                $value = $node; // no need to double wrap a node
                $match = true;
            }

            /**
             * Set when value was found.
             */
            if ($value) {
                if (is_only_callable($setter)) {
                    $setter($value, $props);

                    return;
                }

                $props[$setter] = $value;

                return;
            } elseif ($match) {
                return;
            }

            /**
             * Actual selector was passed.
             */
            $section = null;
            try {
                $section = $node->find($getter, 0);
                if (!$section) {
                    d('no section ' . $getter);

                    return null;
                }
            } catch (SkipException $e) {
                throw $e;
            } catch (\Throwable $e) {
                throw new \Exception('No section ' . $getter);
            }

            /**
             * Pass to callback when final.
             */
            if (is_only_callable($setter)) {
                $setter($section, $props);

                return;
            }

            /**
             * Set as prop when setter.
             */
            if (is_string($setter)) {
                $props[$setter] = $section->getInnerHtml();

                return;
            }

            /**
             * Loop through definition.
             */
            foreach ($setter as $get => $set) {
                try {
                    $this->processSectionByStructure($section, $get, $set, $props);
                } catch (SkipException $e) {
                    throw $e;
                } catch (\Throwable $e) {
                    d(get_class($e),
                      'EXCEPTION [parsing index listing field] ' . $getter . ' ' . $get . ' ' . exception($e));
                }
            }
        } catch (InvalidSelectorException $e) {
            d('invalid selector ' . $getter);
        } catch (SkipException $e) {
            throw $e;
        } catch (\Throwable $e) {
            d("in abstract drivr", exception($e), get_class($e));
            //throw $e;
        }
    }

    /**
     * @return mixed|void
     */
    public function getHttpProxy()
    {
        $disabled = config('scintilla.proxy.disabled', false);
        if ($disabled) {
            return;
        }
        $proxies = config('scintilla.proxy.servers', []);
        if (!$proxies) {
            return;
        }

        return $proxies[array_rand($proxies)];
    }

    /**
     * @param $node
     *
     * @return null|\Pckg\Parser\Node\NodeInterface
     */
    public function makeNode($node)
    {
        if (!$node) {
            return null;
        }

        $nodeProxy = $this->node;

        return new $nodeProxy($node);
    }

}