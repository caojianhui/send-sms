<?php

namespace Send\Sms;


class ChuangLanAgent extends Agent implements ContentSms, MarketSms
{
    //发送短信接口URL
    protected $sendUrl = 'http://smsbj1.253.com/msg/send/json';

    /**
     * @param array|string $to
     * @param string $content
     * 发送普通短信
     */
    public function sendContentSms($to, $content)
    {
        $params = [
            'account' => $this->config(['notice']['account']),
            'password' => $this->config(['notice']['password']),
            'msg' => $this->config(['sign']).$content,
            'to' => $to,
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
            'account' => $this->config(['market']['account']),
            'password' => $this->config(['market']['password']),
            'msg' => $this->config(['sign']).$content,
            'to' => $to,
        ];
        $this->request($params);
    }

    protected function request(array $params)
    {
        $params = $this->createParams($params);
        $result = $this->curlPost(self::$sendUrl, [], [
            CURLOPT_POSTFIELDS => http_build_query($params),
        ]);
        $this->setResult($result);
    }

    /**
     * @param $result
     */
    protected function setResult($result)
    {
        if ($result['request']) {
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
}
