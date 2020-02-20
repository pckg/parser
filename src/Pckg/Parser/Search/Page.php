<?php namespace Pckg\Parser\Search;

class Page implements PageInterface
{

    protected $page;

    protected $listings = [];

    public function __construct($page = [])
    {
        $this->page = $page;
    }

    public function getProps()
    {
        return $this->page;
    }

    public function getId()
    {
        return $this->page['id'];
    }

    public function clone(array $data = [])
    {
        return new static(array_merge($this->page, $data));
    }

    public function processListings(array $listings)
    {
        $this->listings = $listings;

        return $this;
    }

    public function getListings()
    {
        return $this->listings;
    }

    public function getSearch()
    {
        return $this->page['search'] ?? null;
    }

    public function updateStatus(string $status)
    {
        d('Status: ' . $status);
    }

    public function getPage()
    {
        return $this->page['page'] ?? 1;
    }

}