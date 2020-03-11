<?php

namespace Send\Sms;

class ChuangRuiYunAgent extends Agent implements TemplateSms, ContentSms, LogSms
{
    protected static $sendCodeUrl = 'http://api.1cloudsp.com/api/v2/single_send';
    protected static $groupSendUrl = 'http://api.1cloudsp.com/api/v2/send';
    protected $agent = 'ChuangRuiYun';
    protected $unsubscribe = '退订T';


    /**
     * @param array|string $to
     * @param int|string $tempId
     * @param array $tempData
     * 发送模版消息
     */
    public function sendTemplateSms($to, $tempId, array $tempData)
    {

        $params = [
            'templateId' => $tempId,
            'mobile' => $to,
            'tempId' => $tempId,
            'accesskey' => config('sendsms.agents.' . $this->agent . '.accesskey'),
            'secret' => config('sendsms.agents.' . $this->agent . '.secret'),
            'sign' => config('sendsms.agents.' . $this->agent . '.sign'),
        ];
        if (!empty($tempData)) {
            $params = array_merge($tempData, $params);
        }
        $this->request($params);
    }

    /**
     * @param array|string $to
     * @param string $content
     * 发送营销内容短信
     */
    public function sendContentSms($to, $content)
    {
        $params = [
            'mobile' => $to,
            'content' => $content . $this->unsubscribe,
            'accesskey' => config('sendsms.agents.' . $this->agent . '.accesskey'),
            'secret' => config('sendsms.agents.' . $this->agent . '.secret'),
            'sign' => config('sendsms.agents.' . $this->agent . '.sign'),
        ];
        $this->request($params);
    }

    protected function request(array $params)
    {
        if (strpos($params['mobile'], ',') !== false && !isset($params['tempId'])) {
            $url = self::$groupSendUrl;
        } else {
            $url = self::$sendCodeUrl;
        }
        $result = $this->curlPost($url, [], [
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
                $this->result(Agent::INFO, $result['msg']);
            }
        } else {
            $this->result(Agent::INFO, 'request failed');
        }
    }


    public function sendLogSms(array $params, array $result)
    {
        $data = [
            'to' => $params['mobile'],
            'content' => $params['content'],
            'status' => $result['code'] == 0 ? 1 : 2,
            'agents' => $this->agent,
            'params' => json_encode($params),
            'result_info' => json_encode($result),
        ];
        if (isset($params['tenant_id'])) {
            $data['tenant_id'] = $params['tenant_id'];
        }
        $this->log($data);
    }

}
