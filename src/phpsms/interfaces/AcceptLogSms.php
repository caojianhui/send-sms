<?php


namespace Send\PhpSms\Interfaces;


interface AcceptLogSms
{

    /**
     * @param array $params
     * @return mixed
     */
    public function acceptLog(array $params);
}
