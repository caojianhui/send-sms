<?php

namespace Send\PhpSms\Interfaces;

interface ReportSms
{

    /**
     * @param array $params
     * @return mixed
     * 获取短信状态接口
     */
    public function getReportSms(array $params);
}
