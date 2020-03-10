<?php

namespace Send\Sms\Facades;

use Illuminate\Support\Facades\Facade;

class Sms extends Facade
{
    protected static function getFacadeAccessor()
    {
        return 'Send\\Sms\\Sms';
    }
}
