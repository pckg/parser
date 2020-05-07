<?php namespace Pckg\Parser\Driver;

use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Response;
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

    const PARSER_DEFAULT = [
        'cleanupInput' => true,
        'removeScripts' => true,
        'removeStyles' => true,
        //'htmlSpecialCharsDecode' => true,
    ];

    const PARSER_RAW = [
        'cleanupInput' => false,
        'removeScripts' => false,
        'removeStyles' => false,
    ];

    /**
     * @param \Pckg\Parser\ParserInterface $parser
     * @param string $url
     * @param callable|null $then
     *
     * @return array
     * @throws \PHPHtmlParser\Exceptions\ChildNotFoundException
     * @throws \PHPHtmlParser\Exceptions\CircularException
     * @throws \PHPHtmlParser\Exceptions\NotLoadedException
     * @throws \PHPHtmlParser\Exceptions\StrictException
     */
    public function getListings(string $url)
    {
        /**
         * Get HTML.
         */
        $this->trigger('page.status', 'parsing');
        $html = $this->getHttp200($url);

        /**
         * Emit response.
         */
        $this->trigger('index.html', $html);

        $listings = $this->getListingsFromHtml($this->source->getIndexStructure(), $html);
        $this->trigger('page.status', 'parsed');

        return $listings;
    }

    public function makeDom(string $html, $options = self::PARSER_DEFAULT)
    {
        return (new Dom())->setOptions($options)->loadStr($html);
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

        $options = [];
        $isJson = strpos($selector, 'json:') === 0;
        if ($isJson) {
            $this->trigger('debug', 'Using raw / unclean input');
            $options = Curl::PARSER_RAW;
        }
        $dom = $this->makeDom($html, $options);

        /**
         * We have simple JSON element with all the data.
         */
        if ($isJson) {
            if ($selectors) {
                $finalSelector = substr($selector, 5);
                $element = $dom->find($finalSelector, 0);
                if (!$this->found($finalSelector, $element)) {
                    throw new \Exception('No JSON element ' . $finalSelector);
                }
                $script = $this->makeNode($element, $finalSelector);

                /**
                 * Selector is actually a single callback when JSON is expected.
                 */
                $raw = $script->getInnerText();
                $props = $selectors(json_decode($raw, true), $raw, $script);

                return $props;
            }
            $selector = array_keys($structure)[1];
            $selectors = $structure[$selector];
        }

        /**
         * We will loop over defined structure.
         */
        try {
            $listings = collect($dom->find($selector));
            $this->trigger('debug', 'Located ' . $listings->count() . ' elements with selector ' . $selector);
        } catch (\Throwable $e) {
            $this->trigger('parse.exception', new \Exception('Error locating selector ' . $selector, null, $e));

            return [];
        }

        return $listings->map(function (Dom\AbstractNode $node, $i) use ($selectors, $selector) {
            try {
                $props = [];

                foreach ($selectors as $subSelector => $details) {
                    try {
                        $this->processSectionByStructure($this->makeNode($node, $selector), $subSelector, $details,
                            $props);
                    } catch (SkipException $e) {
                        throw $e;
                    } catch (\Throwable $e) {
                        $this->trigger('parse.exception', $e);
                    }
                }

                return $props;
            } catch (SkipException $e) {
                $this->trigger('parse.log', 'Skipping index ' . $i . ': ' . exception($e));
            } catch (\Throwable $e) {
                $this->trigger('parse.exception', new \Exception('Error parsing listing on index ' . $i, $null, $e));
            }
        })->removeEmpty()->rekey()->all();
    }

    /**
     * @param array $structure
     * @param string $url
     *
     * @return array
     * @throws \PHPHtmlParser\Exceptions\ChildNotFoundException
     * @throws \PHPHtmlParser\Exceptions\CircularException
     * @throws \PHPHtmlParser\Exceptions\StrictException
     * @throws \Exception
     */
    public function getListingProps(string $url)
    {
        /**
         * Get HTML.
         */
        $html = $this->getHttp200($url);

        /**
         * Emit response.
         */
        $this->trigger('listing.html', $html);

        $props = $this->getListingPropsFromHtml($this->source->getListingStructure(), $html);

        return $props;
    }

    public function autoParseListing(&$props)
    {
        $props = $this->getListingPropsFromHtml($this->source->getListingStructure());
        $this->source->afterListingParse($selenium, $props);
    }

    /**
     * @param array $structure
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
        $dom = null;

        /**
         * Get listing structure.
         */
        $props = [];
        foreach ($structure as $selector => $details) {
            try {
                if (!$dom) {
                    $options = static::PARSER_DEFAULT;
                    if (strpos($selector, 'json:') === 0) {
                        $this->trigger('debug', 'Using raw / unclean input');
                        $options = static::PARSER_RAW;
                    }

                    $dom = (new Dom())->setOptions($options)->loadStr($html);

                    if (strpos($selector, 'json:') === 0 && !$details) {
                        continue; // skip to next selector, faking
                    }
                }

                $this->processSectionByStructure(new \Pckg\Parser\Node\CurlNode($dom->find('html', 0)), $selector,
                    $details, $props);
            } catch (\Throwable $e) {
                $this->trigger('parse.exception', new \Exception('Error parsing node selector ' . $selector, null, $e));
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
    public function getHttp200($url)
    {
        $response = cache(AbstractSource::class . '.getHttp200.' . sha1($url), function () use ($url) {
            $this->trigger('debug', 'Not using cache for ' . $url);

            /**
             * Make CURL request.
             */
            $options = null;
            $response = retry(function () use (&$options, $url) {
                /**
                 * We want to use a different proxy every time.
                 */
                if (isset($options['proxy'])) {
                    $this->trigger('proxy.retry', $options['proxy']);
                }

                /**
                 * Prepare HTTP client and options.
                 */
                [$client, $options] = $this->getClientAndOptions($options['proxy'] ?? null);

                /**
                 * Add alternative options.
                 */
                $options['headers']['Referer'] = parse_url($url, PHP_URL_SCHEME) . '://' . parse_url($url, PHP_URL_HOST) . '/';

                /**
                 * Try to make a request.
                 */
                return $client->get($url, $options);
            }, 1, function (\Throwable $e) use (&$options) {
                $this->trigger('parse.exception', $e);

                /**
                 * Do not repeat non-CONNECT errors.
                 */
                if (!strpos($e->getMessage(), 'CONNECT')) {
                    return true; // end
                }

                /**
                 * Do not repeat non-proxy requests
                 */
                if (!isset($options['proxy'])) {
                    return true; // end
                }

                $this->trigger('proxy.error', $options['proxy']);
            });

            if (isset($options['proxy'])) {
                $this->trigger('proxy.success', $options['proxy']);
            }

            /**
             * Throw an error.
             */
            if (!$response) {
                throw new \Exception('Empty response');
            }

            /**
             * Expect code 200. This may be driver or source dependant?
             */
            $code = $response->getStatusCode();
            if ($code !== 200) {
                throw new \Exception('HTTP code not 200 (' . $code . ')');
            }

            /**
             * Extract HTML from response.
             */
            return $response->getBody()->getContents();
        }, 'app', '1day'); // cache for 24h?

        return $response;
    }

    /**
     * @param $url
     *
     * @return mixed|\Pckg\Manager\Cache
     * @throws \Exception
     */
    public function postHttp200($url, $formData, $preOptions = [])
    {
        $response = cache(AbstractSource::class . '.postHttp200.' . sha1($url . json_encode($formData)), function () use ($url, $formData, $preOptions) {
            $this->trigger('debug', 'Not using cache for ' . $url);
            [$client, $options] = $this->getClientAndOptions();
            $options = array_merge($preOptions, $options);

            /**
             * Make CURL request.
             */
            $options['form_params'] = $formData;
            $options['allow_redirects'] = false;
            try {
                $response = $client->post($url, $options);
            } catch (\Throwable $e) {
                if (isset($options['proxy']) && strpos($e->getMessage(), 'CONNECT')) {
                    $this->trigger('proxy.error', $options['proxy']);
                }
                throw $e;
            }

            if (isset($options['proxy'])) {
                $this->trigger('proxy.success', $options['proxy']);
            }

            /**
             * Expect code 200. This may be driver or source dependant?
             * @var $response Response
             * @var $client Client
             */
            $code = $response->getStatusCode();
            if ($code === 301) {
                return $response->getHeaderLine('Location');
            }

            if ($code !== 200) {
                throw new \Exception('HTTP code not 200 (' . $code . ')');
            }

            /**
             * Extract HTML from response.
             */
            return $response->getBody()->getContents();
        }, 'app', '1day'); // cache for 24h?

        return $response;
    }

    protected function getClientAndOptions($exclude = null)
    {
        $client = $this->getHttpClient();
        $agents = [
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/81.0.4044.113 Safari/537.36',
            'Mozilla/5.0 (Windows NT 10.0; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/81.0.4044.113 Safari/537.36',
            'Mozilla/5.0 (Windows NT 10.0) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/81.0.4044.113 Safari/537.36',
            'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_4) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/81.0.4044.113 Safari/537.36',
            'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/81.0.4044.113 Safari/537.36',
            'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/80.0.3987.149 Safari/537.36',
            /*'Mozilla/5.0 (iPhone; CPU iPhone OS 13_4 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) CriOS/81.0.4044.62 Mobile/15E148 Safari/604.1',
            'Mozilla/5.0 (iPad; CPU OS 13_4 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) CriOS/81.0.4044.62 Mobile/15E148 Safari/604.1',
            'Mozilla/5.0 (iPod; CPU iPhone OS 13_4 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) CriOS/81.0.4044.62 Mobile/15E148 Safari/604.1',
            'Mozilla/5.0 (Linux; Android 10) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/81.0.4044.111 Mobile Safari/537.36',
            'Mozilla/5.0 (Linux; Android 10; SM-A205U) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/81.0.4044.111 Mobile Safari/537.36',
            'Mozilla/5.0 (Linux; Android 10; SM-A102U) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/81.0.4044.111 Mobile Safari/537.36',
            'Mozilla/5.0 (Linux; Android 10; SM-G960U) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/81.0.4044.111 Mobile Safari/537.36',
            'Mozilla/5.0 (Linux; Android 10; SM-N960U) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/81.0.4044.111 Mobile Safari/537.36',
            'Mozilla/5.0 (Linux; Android 10; LM-Q720) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/81.0.4044.111 Mobile Safari/537.36',
            'Mozilla/5.0 (Linux; Android 10; LM-X420) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/81.0.4044.111 Mobile Safari/537.36',
            'Mozilla/5.0 (Linux; Android 10; LM-Q710(FGN)) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/81.0.4044.111 Mobile Safari/537.36',*/
        ];
        $options = [
            'headers' => [
                'User-Agent' => $agents[array_rand($agents)],
                'Accept-Language' => 'en,en-US;q=0.9,en;q=0.8',
                'Accept-Encoding' => 'gzip, deflate', // br
                'Cache-Control' => 'no-cache',
                'Pragma' => 'no-cache',
                'Connection' => 'keep-alive',
            ],
        ];

        /**
         * Check for proxy. This is usually a Selenium Hub.
         */
        $proxy = $this->getHttpProxy($exclude);
        if ($proxy) {
            $options['proxy'] = 'http://' . $proxy;
        }
        return [$client, $options];
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