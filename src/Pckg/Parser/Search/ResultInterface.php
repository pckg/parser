<?php

namespace Pckg\Parser\Search;

/**
 * Interface ResultInterface
 *
 * @package Pckg\Parser\Search
 */
interface ResultInterface
{

    /**
     * @param string $status
     *
     * @return mixed
     */
    public function updateStatus(string $status);

    /**
     * @return mixed
     */
    public function getUrl();
}
