<?php

namespace Pckg\Parser\Search;

interface PageInterface
{

    public function getId();

    public function processListings(array $listings);

    /**
     * @return mixed|SearchInterface
     */
    public function getSearch();

    public function updateStatus(string $status);

    public function clone(array $data = []);

    public function getPage();
}
