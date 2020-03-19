<?php

namespace Send\Sms;


use GuzzleHttp\Client;
use GuzzleHttp\Pool;
use GuzzleHttp\Psr7\Request;
use Illuminate\Support\Facades\DB;


class ChuangLanAgent extends Agent implements ContentSms, LogSms, ClientSms, ReportSms, BalanceSms
{
    //发送短信接口URL
    protected static $sendUrl = 'http://smsbj1.253.com/msg/send/json';
    protected static $reportUrl = 'http://smsbj1.253.com/msg/pull/report';
    protected static $balanceUrl = 'http://smsbj1.253.com/msg/send/json';
    protected $agent = 'ChuangLan';
    protected $unsubscribe = '退订T';
    const TYPE_MARKET = 1;//营销短信
    const TYPE_NOTICE = 0;//通知短信

    /**
     * @param array|string $to
     * @param string $content
     * 发送普通短信/或营销短信
     */
    public function sendContentSms($to, $content, array $data)
    {
        $type = $data['type'] ?? self::TYPE_NOTICE;
        $params = $this->_getAccount($type);
        $params['msg'] = (config('sendsms.agents.' . $this->agent . '.sign') . $content . $this->unsubscribe);
        $params['phone'] = $to;
        isset($data['tenant_id']) ? $params['tenant_id'] = $data['tenant_id'] : '';
        $this->request($params, self::$sendUrl);
    }


    protected function request(array $params, $url)
    {
        $result = $this->curlPost($url, [], [
            CURLOPT_POSTFIELDS => json_encode($params),
            CURLOPT_HTTPHEADER => $params
        ]);
        $this->setResult($result, $params, $url);
    }

    /**
     * @param $result
     */
    protected function setResult($result, $params, $url)
    {

        if ($result['request']) {
            if ($url == self::$sendUrl) {
                $this->sendLogSms($params, json_decode($result['response'], true));
            }
            $this->result(Agent::INFO, $result['response']);
            $result = json_decode($result['response'], true);
            $this->result(Agent::CODE, $result['code']);
            if ($result['code'] == '0') {
                $this->result(Agent::SUCCESS, true);
                if (isset($result['balance'])) {
                    $this->result(Agent::DATA, $result);
                }
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
            'type' => $params['type'] ?? self::TYPE_NOTICE,
            'msgid' => $result['code'] == '0' ? ($result['msgId'] ?? '') : '',
        ];
        if (isset($params['tenant_id'])) {
            $data['tenant_id'] = $params['tenant_id'];
        }
        $this->log($data);
    }

    /**
     * @param array $data
     * @return mixed|void
     *异步发送多个请求
     */
    public function sendClientSms(array $data)
    {
        $client = new Client();
        $requests = function ($data) {
            $total = collect($data)->count();
            for ($i = 0; $i < $total; $i++) {
                $info = $this->_getInfo($data, $i);
                yield new Request('post', self::$sendUrl, [], json_encode($info, true));
            }
        };
        $pool = new Pool($client, $requests($data), [
            'concurrency' => config('sendsms.scheme.concurrency'),
            'fulfilled' => function ($response, $index) use ($data) {
                // this is delivered each successful response
                $result = \GuzzleHttp\json_decode($response->getBody()->getContents(), true);
                $info = $this->_getInfo($data, $index);
                $this->sendLogSms($info, $result);
                if ($result['code'] == '0') {
                    $this->result(Agent::SUCCESS, true);
                    if (isset($result['balance'])) {
                        $this->result(Agent::DATA, $result);
                    }
                } else {
                    $this->result(Agent::INFO, $result['errorMsg']);
                }
            },
            'rejected' => function ($reason, $index) {
                // this is delivered each failed request
                $this->result(Agent::INFO, $reason);
            },
        ]);
        $promise = $pool->promise();
        $promise->wait();
    }

    /**
     * @param $data
     * @param $index
     * @return array
     */
    public function _getInfo($data, $index)
    {
        $type = $data[$index]['type'] ?? self::TYPE_NOTICE;
        $info = $this->_getAccount($type);
        $info['msg'] = (config('sendsms.agents.' . $this->agent . '.sign') . $data[$index]['msg'] . $this->unsubscribe);
        $info['phone'] = $data[$index]['phone'];
        isset($data[$index]['tenant_id']) ? $info['tenant_id'] = $data[$index]['tenant_id'] : '';
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
        $type = $params['type'];
        $client = new Client();
        $requests = function ($data) use ($type) {
            $total = collect($data)->count() / 100;
            for ($i = 0; $i < $total; $i++) {
                $info = $this->_getAccount($type);
                yield new Request('post', self::$reportUrl, [], json_encode($info, true));
            }
        };
        $pool = new Pool($client, $requests($data), [
            'concurrency' => config('sendsms.scheme.concurrency'),
            'fulfilled' => function ($response, $index) use ($data) {
                // this is delivered each successful response
                $result = \GuzzleHttp\json_decode($response->getBody()->getContents(), true);
                $config = config('sendsms.log');
                if ($result['ret'] == 0) {
                    $re = json_decode($result['result'], true);
                    if ($config['channel'] == self::LOG_DATABASE_CHANNEL) {
                        foreach ($re as $item) {
                            $data = [
                                'update_at' => date('Y-m-d H:i:s'),
                                'result_status' => $item['status'] ?? '',
                            ];
                            DB::where('agent', $this->agent)->where('msgid', $item['msgId'])
                                ->update($data);
                        }
                    }
                }
            },
            'rejected' => function ($reason, $index) {
                // this is delivered each failed request
                $this->result(Agent::INFO, $reason);
            },
        ]);
        $promise = $pool->promise();
        $promise->wait();

    }


    /**
     * @param array $params
     * @return mixed|void
     * 获取营销短信余额
     */
    public function getBalanceSms(array $params)
    {
        $type = $params['channel'] ?? self::TYPE_NOTICE;
        $param = $this->_getAccount($type);
        $this->request($param, self::$balanceUrl);
    }

    /**
     * @param $type
     * @return array
     * 获取账号密钥
     */
    public function _getAccount($type)
    {
        $param = [
            'account' => config('sendsms.agents.' . $this->agent . '.notice.account'),
            'password' => config('sendsms.agents.' . $this->agent . '.notice.password'),
            'type' => $type
        ];
        if ($type == self::TYPE_MARKET) {
            $param['account'] = config('sendsms.agents.' . $this->agent . '.market.account');
            $param['password'] = config('sendsms.agents.' . $this->agent . '.market.account');
        }
        return $param;
    }


}
