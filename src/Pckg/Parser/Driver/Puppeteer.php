<?php

namespace Pckg\Parser\Driver;

use Pckg\Parser\Node\PuppeteerNode;

class Puppeteer extends Selenium
{
    protected $node = PuppeteerNode::class;

    protected $clientClass = \Pckg\Parser\Client\Puppeteer::class;
}
