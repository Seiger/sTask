<?php namespace Seiger\sTask\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * Class sSeo
 *
 * This class is a facade for the sSeo component, which allows easy access to its functionality.
 *
 * @package Seiger\sSeo
 * @mixin \Seiger\sTask\sTask
 */
class sTask extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return 'sTask';
    }
}