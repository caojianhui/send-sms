<?php

namespace Send\PhpSms;

interface ClientSms
{


    /**
     * @param array $data
     * @return mixed
     */
    public function sendClientSms(array $data);
}
