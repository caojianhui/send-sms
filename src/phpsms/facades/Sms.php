<?php

namespace Send\PhpSms\Facades;

use Illuminate\Support\Facades\Facade;

class Sms extends Facade
{
    protected static function getFacadeAccessor()
    {
        return 'Send\\PhpSms\\Sms';
    }
}
