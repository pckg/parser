<?php

namespace Pckg\Parser\Driver;

use Facebook\WebDriver\Exception\InvalidSelectorException;
use GuzzleHttp\Client;
use Pckg\Parser\Node\NodeInterface;
use Pckg\Parser\SkipException;

trait HttpDriver
{

    /**
     * @return mixed|void
     */
    public function getHttpProxy($exclude = null)
    {
        $disabled = config('scintilla.proxy.disabled', false);
        if ($disabled) {
            return;
        }

        $source = config('scintilla.proxy.httpSource', null);
        $proxies = [];
        if ($source) {
            try {
                $response = cache(HttpDriver::class . ':getHttpProxy', function () use ($source) {
                    $client = new Client();
                    $response = $client->get($source);
                    if ($response->getStatusCode() !== 200) {
                        throw new \Exception('Proxy list HTTP code not 200');
                    }
                    return $response->getBody()->getContents();
                }, 'app', '15minutes');
                $decoded = json_decode($response, true);
                $proxies = $decoded;
            } catch (\Throwable $e) {
                $this->trigger('parse.exception', $e);
            }
        }

        if (!$proxies) {
            $proxies = config('scintilla.proxy.servers', []);
        }

        if (!$proxies) {
            return;
        }

        return collect($proxies)->filter(function ($proxy) use ($exclude) {
                return $proxy !== $exclude;
        })->random() ?? collect($proxies)->first();
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
        /**
         * Check for JSON.
         */
        if (strpos($getter, 'json:') === 0) {
            $selector = substr($getter, 5);
            $jsonNode = $node->find($selector, 0);

            /**
             * Collect data for healthcheck.
             */
            if (!$this->found($node->getSelector() . ' ' . $selector, $jsonNode)) {
                return;
            }

            try {
                $raw = $jsonNode->getInnerHtml();
                $json = json_decode($raw, true, 10, JSON_PARTIAL_OUTPUT_ON_ERROR);
                $newProps = $setter($json, $raw, $jsonNode);
                if ($newProps) {
                    $props = array_merge($props, $newProps);
                }
            } catch (SkipException $e) {
                throw $e;
            } catch (\Throwable $e) {
                $this->trigger(
                    'parse.exception',
                    new \Exception('Exception processing node selector ' . $selector, null, $e)
                );
            }

            return;
        }
        /**
         * Check for foreach.
         */
        $value = null;
        $match = false;
        if (strpos($getter, 'each:') === 0) {
            $selector = substr($getter, 5);
            $nodes = $node->find($selector);

            /**
             * Collect data for healthcheck.
             */
            if (!$this->found($node->getSelector() . ' ' . $selector, $nodes->count() > 0)) {
                return;
            }

            $nodes->each(function (NodeInterface $node, $i) use ($setter, &$props, $selector) {
                try {
                    $this->processSectionByStructure($node, '&', $setter, $props);
                } catch (SkipException $e) {
                    throw $e;
                } catch (\Throwable $e) {
                    $this->trigger(
                        'parse.exception',
                        new \Exception(
                            'Exception processing node selector ' . $selector . ' index ' . $i,
                            null,
                            $e
                        )
                    );
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
        } elseif ($getter === 'innerHtml') {
            $value = $node->getInnerHtml();
            $match = true;
        } elseif ($getter === 'innerText') {
            $value = $node->getInnerText();
            $match = true;
        } elseif ($getter === '&') {
            if (is_array($setter)) {
                foreach ($setter as $k => $v) {
                    try {
                        $this->processSectionByStructure($node, $k, $v, $props);
                    } catch (SkipException $e) {
                        throw $e;
                    } catch (\Throwable $e) {
                        $this->trigger('parse.exception', $e);
                    }
                }

                return;
            }
            $value = $node; // no need to double wrap a node
            $match = true;
        }

        /**
         * Set when value was found.
         */
        if ($match) {
            $this->found($node->getSelector() . ' ' . $getter, $value);

            if (is_only_callable($setter)) {
                $setter($value, $props);

                return;
            }

            $props[$setter] = $value;
            return;
        }

        /**
         * Actual selector was passed.
         */
        $section = $node->find($getter, 0);
        if (!$this->found($node->getSelector() . ' ' . $getter, $section)) {
            return;
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
         * Throw error when not array.
         */
        if (!is_array($setter)) {
            throw new \Exception('Setters is not array');
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
                $this->trigger(
                    'parse.exception',
                    new \Exception('Error processing section ' . $getter . ' ' . $get, null, $e)
                );
            }
        }
    }

    /**
     * @param $node
     *
     * @return null|\Pckg\Parser\Node\NodeInterface
     */
    public function makeNode($node, $selector = null)
    {
        if (!$node) {
            return null;
        }

        $nodeProxy = $this->node;

        return new $nodeProxy($node, $selector);
    }
}
