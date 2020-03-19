<?php

namespace Send\Sms;

interface ClientSms
{


    /**
     * @param array $data
     * @return mixed
     */
    public function sendClientSms(array $data);
}
