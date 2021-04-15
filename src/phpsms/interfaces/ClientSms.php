<?php

namespace Send\PhpSms\Interfaces;

interface ClientSms
{


    /**
     * @param array $data
     * @return mixed
     */
    public function sendClientSms(array $data);
}
