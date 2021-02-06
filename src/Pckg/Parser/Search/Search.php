<?php

namespace Pckg\Parser\Search;

class Search implements SearchInterface
{

    protected $search;

    public function __construct($search)
    {
        $this->search = $search;
    }

    public function getId()
    {
        return null;
    }

    public function getScore($props)
    {
        return null;
    }

    public function getDataProp($prop)
    {
        return $this->search[$prop];
    }

    public function getDataProps()
    {
        return $this->search;
    }

    public function createPage(array $data = [])
    {
        return new Page($data);
    }
}
