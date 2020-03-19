<?php

namespace Send\Sms;

interface ReportSms
{

    /**
     * @param array $params
     * @return mixed
     * 获取短信状态接口
     */
    public function getReportSms(array $params);
}
