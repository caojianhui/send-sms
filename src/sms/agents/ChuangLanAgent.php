<?php

namespace Send\Sms;


use Illuminate\Log\Writer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;


class ChuangLanAgent extends Agent implements ContentSms, MarketSms, LogSms
{
    //发送短信接口URL
    protected static $sendUrl = 'http://smsbj1.253.com/msg/send/json';
    protected $agent = 'ChuangLan';
    protected $unsubscribe = '退订T';

    /**
     * @param array|string $to
     * @param string $content
     * 发送普通短信
     */
    public function sendContentSms($to, $content)
    {
        $params = [
            'account' => config('sendsms.agents.' . $this->agent . '.notice.account'),
            'password' => config('sendsms.agents.' . $this->agent . '.notice.password'),
            'msg' => urlencode((config('sendsms.agents.' . $this->agent . '.sign') . $content.$this->unsubscribe)),
            'phone' => $to,
        ];
        $this->request($params);
    }

    /**
     * @param array|string $to
     * @param string $content
     * @param array $data
     * 发送营销短信
     */
    public function sendMarketSms($to, $content, array $data)
    {

        $params = [
            'account' => config('sendsms.agents.' . $this->agent . '.notice.account'),
            'password' => config('sendsms.agents.' . $this->agent . '.notice.password'),
            'msg' => urlencode((config('sendsms.agents.' . $this->agent . '.sign') . $content.$this->unsubscribe)),
            'phone' => $to,
        ];
        $this->request($params);
    }

    protected function request(array $params)
    {

        $result = $this->curlPost(self::$sendUrl, [], [
            CURLOPT_POSTFIELDS => json_encode($params),
            CURLOPT_HTTPHEADER => $params
        ]);
        $this->setResult($result, $params);
    }

    /**
     * @param $result
     */
    protected function setResult($result, $params)
    {
        if ($result['request']) {
            $this->sendLogSms($params, json_decode($result['response'], true));

            $this->result(Agent::INFO, $result['response']);
            $result = json_decode($result['response'], true);
            $this->result(Agent::CODE, $result['code']);
            if ($result['code'] === 0) {
                $this->result(Agent::SUCCESS, true);
            } else {
                $this->result(Agent::INFO, $result['errorMsg']);
            }
        } else {
            $this->result(Agent::INFO, 'request failed');
        }
    }

    public function sendLogSms(array $params, array $result)
    {
        $data = [
            'to' => $params['phone'],
            'content' => $params['msg'],
            'status' => $result['code'] == 0 ? 1 : 2,
            'agents' => $this->agent,
            'params' => json_encode($params),
            'result_info' => json_encode($result),
        ];
        if(isset($params['tenant_id'])){
            $data['tenant_id'] = $params['tenant_id'];
        }
        $this->log($data);
    }
}
