<?php

namespace Pckg\Parser\Search;

interface SearchInterface
{

    public function getId();

    public function getScore($props);

    public function getDataProp($prop);

    public function getDataProps();

    public function createPage(array $data = []);
}
