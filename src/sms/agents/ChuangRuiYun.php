<?php

namespace Send\Sms;
class ChuangRuiYun extends Agent implements TemplateSms, ContentSms
{
    protected static $sendCodeUrl = 'http://api.1cloudsp.com/api/v2/single_send';
    protected static $groupSendUrl = 'http://api.1cloudsp.com/api/v2/send';

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
        ];
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
            'content' => $content,
        ];
        $this->request($params);
    }

    protected function request(array $params)
    {
        $params = $this->createParams($params);
        if (strpos($params['mobile'], ',') !== false) {
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
            }else{
                $this->result(Agent::INFO,$result['msg']);
            }
        } else {
            $this->result(Agent::INFO, 'request failed');
        }
    }
}
