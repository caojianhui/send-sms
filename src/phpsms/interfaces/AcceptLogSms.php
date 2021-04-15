<?php


namespace Send\PhpSms\sms\interfaces;


interface AcceptLogSms
{

    /**
     * @param array $params
     * @return mixed
     */
    public function acceptLog(array $params);
}
