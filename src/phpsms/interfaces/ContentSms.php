<?php

namespace Send\PhpSms;

interface ContentSms
{
    /**
     * Content SMS send process.
     *
     * @param string|array $to
     * @param string $content
     */
    public function sendContentSms($to, $content,array $data);
}
