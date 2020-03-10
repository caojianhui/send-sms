<?php

namespace Send\Sms;

interface MarketSms
{
    /**
     * Content SMS send process.
     *
     * @param string|array $to
     * @param string       $content
     * @param array $data
     */
    public function sendMarketSms($to, $content,array $data);
}
