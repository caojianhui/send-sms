<?php

namespace Send\PhpSms\Interfaces;

interface VoiceCode
{
    /**
     * Voice code send process.
     *
     * @param string|array $to
     * @param int|string   $code
     */
    public function sendVoiceCode($to, $code);
}
