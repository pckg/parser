<?php namespace Pckg\Parser\Source;

use Pckg\Concept\Event\Dispatcher;

class SourceFactory
{

    /**
     * @param $source
     *
     * @return SourceInterface|AbstractSource|mixed
     * @throws \Exception
     */
    public static function create($source)
    {
        if (!class_exists($source) || !in_array(SourceInterface::class, class_implements($source))) {
            throw new \Exception('Cannot create source class ' . $source);
        }

        $dispatcher = new Dispatcher();

        return new $source($dispatcher);
    }

    /**
     * @param $capability
     *
     * @return array
     */
    public static function createMultipleWithCapability($capability)
    {
        return collect(config('fons.sources', []))->map(function($source) {
            return SourceFactory::create($source);
        })->filter(function(SourceInterface $source) use ($capability) {
            return $source->hasCapability($capability);
        })->all();
    }

}