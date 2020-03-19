<?php

namespace Send\Sms;

use GuzzleHttp\Client;
use GuzzleHttp\Pool;
use GuzzleHttp\Psr7\Request;
use Illuminate\Support\Facades\DB;

class ChuangRuiYunAgent extends Agent implements TemplateSms, ContentSms, LogSms, ClientSms, ReportSms, BalanceSms
{
    protected static $sendCodeUrl = 'http://api.1cloudsp.com/api/v2/single_send';
    protected static $groupSendUrl = 'http://api.1cloudsp.com/api/v2/send';
    protected static $reportUrl = 'http://api.1cloudsp.com/report/status';
    protected static $balanceUrl = 'http://api.1cloudsp.com/query/account';
    protected $agent = 'ChuangRuiYun';
    protected $unsubscribe = '退订T';

    const TYPE_MARKET = 1;//内容短信
    const TYPE_NOTICE = 0;//模版消息短信

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
            'type' => self::TYPE_NOTICE,
        ];
        $account = $this->_getAccount();
        $params = array_merge($params, $account);
        if (!empty($tempData)) {
            $params = array_merge($tempData, $params);
        }
        $this->request($params, self::$sendCodeUrl);
    }

    /**
     * @param array|string $to
     * @param string $content
     * 发送营销内容短信
     */
    public function sendContentSms($to, $content, array $data)
    {
        $params = [
            'mobile' => $to,
            'content' => $content . $this->unsubscribe,
            'type' => self::TYPE_MARKET,
        ];
        $account = $this->_getAccount();
        $params = array_merge($params, $account);
        if (!empty($data)) {
            $params = array_merge($data, $params);
        }
        $this->request($params, self::$groupSendUrl);
    }

    protected function request(array $params, $url)
    {
        $result = $this->curlPost($url, [], [
            CURLOPT_POSTFIELDS => http_build_query($params),
        ]);
        $this->setResult($result, $params, $url);
    }

    /**
     * @param $result
     */
    protected function setResult($result, $params, $url)
    {
        if ($result['request']) {
            if ($url !== self::$balanceUrl && $url !== self::$reportUrl) {
                $this->sendLogSms($params, json_decode($result['response'], true));
            }
            $this->result(Agent::INFO, $result['response']);
            $result = json_decode($result['response'], true);
            $this->result(Agent::CODE, $result['code']);
            if ($result['code'] == '0') {
                $this->result(Agent::SUCCESS, true);
                if (isset($result['data'])) {
                    $this->result(Agent::DATA, $result['data']);
                }
                $this->result(Agent::INFO, $result['msg']);
            } else {
                $this->result(Agent::INFO, $result['msg']);
            }
        } else {
            $this->result(Agent::INFO, 'request failed');
        }
    }

    /**
     * @param array $data
     *
     */
    public function sendClientSms(array $data)
    {
        $client = new Client();
        $requests = function ($data) {
            $total = collect($data)->count();
            for ($i = 0; $i < $total; $i++) {
                $info = $this->_getInfo($data, $i);
                $url = self::$groupSendUrl . '?' . http_build_query($info);
                yield new Request('get', $url);
            }
        };
        $pool = new Pool($client, $requests($data), [
            'concurrency' => config('sendsms.scheme.concurrency'),
            'fulfilled' => function ($response, $index) use ($data) {
                $result = \GuzzleHttp\json_decode($response->getBody()->getContents(), true);
                $info = $this->_getInfo($data, $index);
                $this->sendLogSms($info, $result);
                if ($result['code'] == '0') {
                    $this->result(Agent::SUCCESS, true);
                    $this->result(Agent::INFO, $result['msg']);
                    if (isset($result['data'])) {
                        $this->result(Agent::DATA, $result['data']);
                    }
                } else {
                    $this->result(Agent::INFO, $result['msg']);
                }
            },
            'rejected' => function ($reason, $index) {
                // this is delivered each failed request
                $this->result(Agent::SUCCESS, false);
                $this->result(Agent::INFO, $reason);
            },
        ]);
        $promise = $pool->promise();
        $promise->wait();
    }


    public function sendLogSms(array $params, array $result)
    {
        $data = [
            'to' => $params['mobile'],
            'content' => $params['content'],
            'status' => $result['code'] == 0 ? 1 : 2,
            'agents' => $this->agent,
            'temp_id' => $params['tempId'] ?? '',
            'params' => json_encode($params),
            'result_info' => json_encode($result),
            'type' => $params['type'] ?? self::TYPE_MARKET,
            'msgid' => $result['code'] == '0' ? ($result['batchId'] ?? '') : ($result['smUuid'] ?? '')
        ];
        if (isset($params['tenant_id'])) {
            $data['tenant_id'] = $params['tenant_id'];
        }
        $this->log($data);
    }

    /**
     * @return array
     */
    public function _getAccount()
    {
        return [
            'accesskey' => config('sendsms.agents.' . $this->agent . '.accesskey'),
            'secret' => config('sendsms.agents.' . $this->agent . '.secret'),
            'sign' => config('sendsms.agents.' . $this->agent . '.sign'),
        ];
    }

    /**
     * @param $data
     * @param $index
     * @return array
     */
    public function _getInfo($data, $index)
    {
        $info = [
            'mobile' => $data[$index]['phone'],
            'content' => $data[$index]['msg'] . $this->unsubscribe,
            'type' => $data[$index]['type'] ?? 0,
        ];
        isset($data[$index]['tenant_id']) ? $info['tenant_id'] = $data[$index]['tenant_id'] : '';
        $param = $this->_getAccount();
        $info = array_merge($info, $param);
        return $info;
    }

    /**
     * @param array $params
     * @return mixed|void
     * 记录日志
     */
    public function getReportSms(array $params)
    {
        $data = $params['msgids'];
        $client = new Client();
        $type = $params['type'];
        $requests = function ($data) use ($type) {
            $total = collect($data)->count() / 100;
            for ($i = 0; $i < $total; $i++) {
                $info = $this->_getAccount();
                yield new Request('post', self::$reportUrl, [], json_encode($info, true));
            }
        };
        $pool = new Pool($client, $requests($data), [
            'concurrency' => config('sendsms.scheme.concurrency'),
            'fulfilled' => function ($response, $index) use ($data, $type) {
                // this is delivered each successful response
                $result = \GuzzleHttp\json_decode($response->getBody()->getContents(), true);
                $config = config('sendsms.log');
                if ($result['code'] == 0) {
                    $re = json_decode($result['data'], true);
                    if ($config['channel'] == self::LOG_DATABASE_CHANNEL) {
                        foreach ($re as $item) {
                            $msgid = $type == self::TYPE_MARKET ? $item['batchId'] : $item['smUuid'];
                            $data = [
                                'update_at' => date('Y-m-d H:i:s'),
                                'result_status' => $item['deliverResult'] ?? '',
                            ];
                            DB::where('agent', $this->agent)->where('msgid', $msgid)
                                ->update($data);
                        }
                    }
                }
            },
            'rejected' => function ($reason, $index) {
                $this->result(Agent::INFO, $reason);
            },
        ]);
        $promise = $pool->promise();
        $promise->wait();

    }

    /**
     * @param array $params
     * @return mixed|void
     */
    public function getBalanceSms(array $params)
    {
        $account = $this->_getAccount();
        $this->request($account, self::$balanceUrl);
    }
}
