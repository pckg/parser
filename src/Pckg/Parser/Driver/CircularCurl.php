<?php namespace Pckg\Parser\Driver;

use PHPHtmlParser\Dom;
use Pckg\Parser\Source\SourceInterface;
use Pckg\Parser\ParserInterface;
use Pckg\Parser\Driver\Curl;

class CircularCurl extends Curl
{

    /**
     * @param array  $structure
     * @param string $url
     *
     * @return \Pckg\Collection
     * @throws \Exception
     */
    public function getListings(SourceInterface $parser, string $url, callable $then = null)
    {
        $html = $this->getHttp200($url);

        $listings = $this->getListingsFromHtml($parser->getIndexStructure(), $html);

        if ($then) {
            $then($this, $listings, $html);
        }

        return $listings;
    }

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

        return collect($nodes)->map(function(Dom\AbstractNode $node, $i) use ($struct, $content) {
            try {
                $props = [];
                foreach ($struct as $selector => $prop) {
                    try {
                        $elementNode = $this->makeNode($content->find($selector, $i));
                        if (!$elementNode) {
                            continue;
                        }
                        if (is_array($prop)) {
                            foreach ($prop as $k => $v) {
                                try {
                                    $this->processSectionByStructure($elementNode, $k, $v, $props);
                                } catch (\Throwable $e) {
                                    d('err', exception($e));
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
                    } catch (\Throwable $e) {
                        d('error circ 2', exception($e));
                    }
                }

                return $props;
            } catch (\Throwable $e) {
                d('error circ', exception($e));
            }
        })->removeEmpty()->rekey()->all();
    }

}