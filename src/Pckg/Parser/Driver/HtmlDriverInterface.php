<?php

namespace Pckg\Parser\Driver;

interface HtmlDriverInterface extends DriverInterface
{

    /**
     * @param $node
     *
     * @return \Pckg\Parser\Node\NodeInterface
     */
    public function makeNode($node, $selector = null);
}
