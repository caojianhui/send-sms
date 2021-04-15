<?php

namespace Send\PhpSms\Agents;

use Send\PhpSms\Interfaces\ContentSms;
use Send\PhpSms\Interfaces\TemplateSms;
use Send\PhpSms\Interfaces\VoiceCode;

/**
 * Class LogAgent
 */
class LogAgent extends Agent implements TemplateSms, ContentSms, VoiceCode
{
    public function sendContentSms($to, $content,array $data)
    {
        $this->result(Agent::SUCCESS, true);
        $this->result(Agent::INFO, 'send content sms success');
    }

    public function sendTemplateSms($to, $tempId, array $data)
    {
        $this->result(Agent::SUCCESS, true);
        $this->result(Agent::INFO, 'send template sms success');
    }

    public function sendVoiceCode($to, $code)
    {
        $this->result(Agent::SUCCESS, true);
        $this->result(Agent::INFO, 'send voice verify success');
    }
}
