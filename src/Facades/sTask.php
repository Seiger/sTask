<?php namespace Seiger\sTask\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * Class sTask Facade
 *
 * Facade for asynchronous task management service
 *
 * @mixin \Seiger\sTask\sTask
 * @package Seiger\sTask
 * @author Seiger IT Team
 * @since 1.0.0
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