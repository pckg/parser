<?php namespace Pckg\Parser\Driver;

use GuzzleHttp\Client;
use Pckg\Parser\SkipException;
use PHPHtmlParser\Dom;
use Pckg\Parser\Source\SourceInterface;
use Pckg\Parser\Source\AbstractSource;
use Pckg\Parser\ParserInterface;
use Pckg\Parser\Driver\AbstractDriver;
use Pckg\Parser\Node\CurlNode;
use Pckg\Parser\Driver\DriverInterface;

class Curl extends AbstractDriver implements DriverInterface
{

    /**
     * @param \Pckg\Parser\ParserInterface $parser
     * @param string                       $url
     * @param callable|null                $then
     *
     * @return array
     * @throws \PHPHtmlParser\Exceptions\ChildNotFoundException
     * @throws \PHPHtmlParser\Exceptions\CircularException
     * @throws \PHPHtmlParser\Exceptions\NotLoadedException
     * @throws \PHPHtmlParser\Exceptions\StrictException
     */
    public function getListings(string $url, callable $then = null)
    {
        /**
         * Get HTML.
         */
        $this->source->getPage()->updateStatus('parsing');
        $html = $this->getHttp200($url);

        $listings = $this->getListingsFromHtml($this->source->getIndexStructure(), $html);
        $this->source->getPage()->updateStatus('parsed');

        if ($then) {
            $then($listings, $html);
        }

        return $listings;
    }

    public function getCurlParserOptions()
    {
        return [
            'cleanupInput'  => true,
            'removeScripts' => true,
            'removeStyles'  => true,
            //'htmlSpecialCharsDecode' => true,
        ];
    }

    /**
     * @param $structure
     * @param $html
     *
     * @return array
     * @throws \PHPHtmlParser\Exceptions\ChildNotFoundException
     * @throws \PHPHtmlParser\Exceptions\CircularException
     * @throws \PHPHtmlParser\Exceptions\NotLoadedException
     * @throws \PHPHtmlParser\Exceptions\StrictException
     */
    public function getListingsFromHtml($structure, $html)
    {
        $selector = array_keys($structure)[0];
        $selectors = $structure[$selector];

        $options = $this->getCurlParserOptions();

        if (strpos($selector, 'json:') === 0) {
            d('using raw input');
            $options = [
                'cleanupInput'  => false,
                'removeScripts' => false,
                'removeStyles'  => false,
            ];
        }
        $dom = (new Dom())->setOptions($options)->loadStr($html);

        /**
         * We have simple JSON element with all the data.
         */
        if (strpos($selector, 'json:') === 0) {
            $element = $dom->find(substr($selector, 5), 0);
            if (!$element) {
                d('no json element?', substr($selector, 5));
                return [];
            }
            $script = new CurlNode($element);

            /**
             * Selector is actually a single callback.
             */
            return $selectors(json_decode($script->getInnerText(), true));
        }

        /**
         * We will loop over defined structure.
         */
        try {
            $listings = collect($dom->find($selector));
            d('located elements ' . $listings->count() . ' with selector ' . $selector);
        } catch (\Throwable $e) {
            d('error locating element', exception($e));
            return [];
        }

        return $listings->map(function(Dom\AbstractNode $node, $i) use ($selectors) {
            d('index ' . $i);
            try {
                $props = [];

                foreach ($selectors as $selector => $details) {
                    try {
                        $this->processSectionByStructure($this->makeNode($node), $selector, $details, $props);
                    } catch (SkipException $e) {
                        throw $e;
                    } catch (\Throwable $e) {
                        d('EXCEPTION [parsing index listing selector 1] ' . $selector . ' ' . exception($e));
                    }
                }

                return $props;
            } catch (SkipException $e) {
                d('skip exception');
            } catch (\Throwable $e) {
                d('EXCEPTION [parsing index listing]' . exception($e));
            }
        })->removeEmpty()->rekey()->all();
    }

    /**
     * @param array  $structure
     * @param string $url
     *
     * @return array
     * @throws \PHPHtmlParser\Exceptions\ChildNotFoundException
     * @throws \PHPHtmlParser\Exceptions\CircularException
     * @throws \PHPHtmlParser\Exceptions\StrictException
     * @throws \Exception
     */
    public function getListingProps(string $url, callable $then = null)
    {
        /**
         * Get HTML.
         */
        $html = $this->getHttp200($url);

        $props = $this->getListingPropsFromHtml($this->source->getListingStructure(), $html);

        if ($then) {
            $then($props);
        }

        return $props;
    }

    public function autoParseListing(&$props)
    {
        $props = $this->getListingPropsFromHtml($this->source->getListingStructure());
        d('parsing sub');
        $this->source->afterListingParse($selenium, $props);
        d('parsed sub');
    }

    /**
     * @param array  $structure
     * @param string $html
     *
     * @return array
     * @throws \PHPHtmlParser\Exceptions\ChildNotFoundException
     * @throws \PHPHtmlParser\Exceptions\CircularException
     * @throws \PHPHtmlParser\Exceptions\StrictException
     */
    public function getListingPropsFromHtml(array $structure, string $html)
    {
        /**
         * Start parsing dom.
         */
        $dom = new Dom();
        $dom->loadStr($html);

        /**
         * Get listing structure.
         */
        $props = [];
        foreach ($structure as $selector => $details) {
            try {
                $this->processSectionByStructure(new \Pckg\Parser\Node\CurlNode($dom->find('body', 0)), $selector,
                                                 $details, $props);
            } catch (\Throwable $e) {
                d('exception parsing node selector ', $selector, exception($e));
            }
        }

        return $props;
    }

    /**
     * @return Client
     * @throws \InvalidArgumentException
     */
    public function getHttpClient()
    {
        return new Client();
    }

    /**
     * @param $url
     *
     * @return mixed|\Pckg\Manager\Cache
     * @throws \Exception
     */
    protected function getHttp200($url)
    {
        return cache(AbstractSource::class . '.getHttp200.' . sha1($url), function() use ($url) {
            d("requesting", $url);
            $client = $this->getHttpClient();
            $options = [
                // 'debug'   => true,
                'headers' => [
                    'User-Agent'      => 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/78.0.3904.87 Safari/537.36',
                    'Accept-Language' => 'en,en-US;q=0.9,en;q=0.8',
                    'Accept-Encoding' => 'gzip, deflate', // br
                ],
            ];

            /**
             * Check for proxy.
             */
            $proxy = $this->getHttpProxy();
            if ($proxy) {
                $options['proxy'] = 'http://' . $proxy;
            }

            /**
             * Make request.
             */
            $response = $client->get($url, $options);

            /**
             * Expect code 200?
             */
            $code = $response->getStatusCode();
            if ($code !== 200) {
                throw new \Exception('HTTP code not 200 (' . $code . ')');
            }

            /**
             * Throttle.
             */
            $this->throttle();

            /**
             * Extract HTML from response.
             */
            return $response->getBody()->getContents();
        }, 'app', 24 * 60 * 60);
    }

    protected function throttle()
    {
        return; // no throttle
        $sleep = rand(1, 5);
        d('sleeping', $sleep);
        sleep($sleep);
    }

    /**
     * @param $section Dom\AbstractNode
     * @param $attribute
     *
     * @return mixed
     */
    public function getElementAttribute($section, string $attribute)
    {
        return $section->getAttribute($attribute);
    }

    /**
     * @param $section Dom\HtmlNode
     *
     * @return mixed
     */
    public function getElementInnerHtml($section)
    {
        return $section->innerHtml;
    }

    /**
     * @param $section Dom\AbstractNode
     * @param $css
     *
     * @return mixed
     * @throws \PHPHtmlParser\Exceptions\ChildNotFoundException
     */
    public function getElementByCss($section, string $css)
    {
        return $section->find($css, 0);
    }

    /**
     * @param $section Dom\AbstractNode
     * @param $css
     *
     * @return mixed
     * @throws \PHPHtmlParser\Exceptions\ChildNotFoundException
     */
    public function getElementsByCss($section, string $css)
    {
        return $section->find($css, null);
    }

    /**
     * @param $section Dom\AbstractNode
     * @param $xpath
     *
     * @return mixed
     * @throws \PHPHtmlParser\Exceptions\ChildNotFoundException
     */
    public function getElementByXpath($section, string $xpath)
    {
        return $section->find($xpath, 0);
    }

}