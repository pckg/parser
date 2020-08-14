<?php namespace Pckg\Parser\Driver;

use Pckg\Parser\SkipException;
use PHPHtmlParser\Dom;
use Pckg\Parser\Source\SourceInterface;
use Pckg\Parser\ParserInterface;
use Pckg\Parser\Driver\Curl;

class CircularCurl extends Curl
{

    public function getListingsFromHtml($structure, $html)
    {
        /**
         * Get HTML and parse DOM.
         */
        $dom = (new Dom())->loadStr($html);

        /**
         * Content selector.
         *
         * @var $content Dom\HtmlNode
         */
        $contentSelector = array_keys($structure)[0];
        $content = $dom->find($contentSelector, 0);

        if (!$content) {
            throw new \Exception('No content to parse');
        }

        $struct = $structure[$contentSelector];
        $firstSelector = array_keys($struct)[0];
        $nodes = $content->find($firstSelector);

        return collect($nodes)->map(function(Dom\Node\AbstractNode $node, $i) use ($struct, $content) {
            try {
                $props = [];
                foreach ($struct as $selector => $prop) {
                    try {
                        $elementNode = $this->makeNode($content->find($selector, $i), $selector);
                        if (!$elementNode) {
                            continue;
                        }
                        if (is_array($prop)) {
                            foreach ($prop as $k => $v) {
                                try {
                                    $this->processSectionByStructure($elementNode, $k, $v, $props);
                                } catch (SkipException $e) {
                                    throw $e;
                                } catch (\Throwable $e) {
                                    $this->trigger('parse.exception', $e);
                                }
                            }
                            continue;
                        }
                        if (is_only_callable($prop)) {
                            $prop($elementNode, $props);
                            continue;
                        }
                        $value = $elementNode->getInnerHtml();
                        $props[$prop] = $value;
                    } catch (SkipException $e) {
                        throw $e;
                    } catch (\Throwable $e) {
                        $this->trigger('parse.exception', $e);
                    }
                }

                return $props;
            } catch (SkipException $e) {
                $this->trigger('parse.log', 'Skipping index');
            } catch (\Throwable $e) {
                $this->trigger('parse.exception', $e);
            }
        })->removeEmpty()->rekey()->all();
    }

}