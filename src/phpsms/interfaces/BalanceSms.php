<?php

namespace Send\PhpSms\Interfaces;

interface BalanceSms
{

    /**
     * @param array $params
     * @return mixed
     * 获取短信余额
     */
    public function getBalanceSms(array $params);
}
