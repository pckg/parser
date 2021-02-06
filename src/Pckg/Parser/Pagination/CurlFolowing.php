<?php

namespace Pckg\Parser\Pagination;

use Pckg\Parser\Source\AbstractSource;
use Pckg\Parser\Search\PageInterface;
use PHPHtmlParser\Dom;
use Pckg\Parser\Driver\DriverInterface;

/**
 * Class CurlFolowing
 * Parse sub-pages by locating and following the pagination link.
 *
 * @package Pckg\Parser\Pagination
 */
class CurlFolowing
{

    public function process(PageInterface $searchSource, AbstractSource $parser, ...$params)
    {
        /**
         * @var $driver   DriverInterface
         * @var $selenium \RemoteWebDriver
         */
        [$driver, $holder] = $params;
    }
}
