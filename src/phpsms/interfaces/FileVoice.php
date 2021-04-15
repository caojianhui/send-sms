<?php

namespace Send\PhpSms\Interfaces;

interface FileVoice
{
    /**
     * File voice send process.
     *
     * @param string|array $to
     * @param int|string   $fileId
     */
    public function sendFileVoice($to, $fileId);
}
