<?php

namespace Send\Sms;

use GuzzleHttp\Client;
use GuzzleHttp\Pool;
use GuzzleHttp\Psr7\Request;
use Illuminate\Support\Facades\Cache;
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
        $url = config('sendsms.is_dev')==true?config('sendsms.dev_url'):self::$sendCodeUrl;

        $this->request($params, $url);
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
        $url = config('sendsms.is_dev')==true?config('sendsms.dev_url'):self::$groupSendUrl;

        $this->request($params, $url);
    }

    /**
     * @param array $params
     * @param $url
     */
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
                    $this->result(Agent::RESULT_DATA, $result['data']);
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
        $groupSendUrl = config('sendsms.is_dev')==true?config('sendsms.dev_url'):self::$groupSendUrl;
        $client = new Client();
        $requests = function ($data)use ($groupSendUrl) {
            $total = collect($data)->count();
            for ($i = 0; $i < $total; $i++) {
                $info = $this->_getInfo($data, $i);
                $key = $info['key'];
                if(!Cache::store('redis')->has($key)){
                    $url = $groupSendUrl . '?' . http_build_query($info);
                    yield new Request('get', $url);
                }
            }
        };
        $pool = new Pool($client, $requests($data), [
            'concurrency' => config('sendsms.concurrency'),
            'fulfilled' => function ($response, $index) use ($data) {
                $result = json_decode($response->getBody()->getContents(), true);
                $info = $this->_getInfo($data, $index);
                Cache::store('redis')->put($info['key'],$info,config('sendsms.cache_time'));
                $this->sendLogSms($info, $result);
                if ($result['code'] == '0') {
                    $this->result(Agent::SUCCESS, true);
                    $this->result(Agent::INFO, $result['msg']);
                    if (isset($result['data'])) {
                        $this->result(Agent::RESULT_DATA, $result['data']);
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


    /**
     * @param array $params
     * @param array $result
     */
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
            'msgid' => $result['code'] == '0' ? ($result['batchId'] ?? '') : ($result['smUuid'] ?? ''),
            'act_id'=>$params['act_id']??0
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
            'key'=>$data[$index]['key']??$data[$index]['phone'],
            'act_id'=>$data[$index]['act_id']??0,
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
        $reportUrl = config('sendsms.is_dev')==true?config('sendsms.dev_reports_url'):self::$reportUrl;
        $data = $params['msgids'];
        $tenantId = $params['tenant_id'];
        $client = new Client();
        $type = $params['type'];
        $requests = function ($data) use ($type,$reportUrl) {
            $total = collect($data)->count() / 100;
            for ($i = 0; $i < $total; $i++) {
                $info = $this->_getAccount();
                $url = $reportUrl . '?' . http_build_query($info);
                yield new Request('get', $url);
            }
        };
        $pool = new Pool($client, $requests($data), [
            'concurrency' => config('sendsms.concurrency'),
            'fulfilled' => function ($response, $index) use ($data, $type,$tenantId) {
                // this is delivered each successful response
                $result = json_decode($response->getBody()->getContents(), true);
                $config = config('sendsms.log');
                if ($result['code'] == 0) {
                    $re = $result['data'];
                    if(!empty($re)){
                        $this->updateLog($config,$re,$type,$tenantId);
                        $this->result(Agent::SUCCESS,true);
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
     * @param $config
     * @param $re
     * @param $type
     * @throws \Aliyun\OTS\OTSClientException
     * @throws \Aliyun\OTS\OTSServerException
     */
    private function updateLog($config, $re, $type, $tenantId){
        if ($config['channel'] == self::LOG_DATABASE_CHANNEL) {
            collect($re)->chunk(100)->each(function ($values)use ($type,$tenantId) {
                foreach ($values as $item) {
                    $msgid = $type == self::TYPE_MARKET ? $item['batchId'] : $item['smUuid'];
                    $data = [
                        'update_at' => date('Y-m-d H:i:s'),
                        'result_status' => $item['deliverResult'] ?? '',
                    ];
                    DB::where('agents', $this->agent)->where('msgid', $msgid)
                        ->update($data);
                }
            });
        }elseif ($config['channel'] == self::LOG_TABLESTORE_CHANNEL){
            collect($re)->chunk(100)->each(function ($values)use ($type,$tenantId){
                foreach ($values as $item) {
                    $msgid = $type == self::TYPE_MARKET ? $item['batchId'] : $item['smUuid'];
//                    info('send_msgid='.$msgid);
//                    info('send_result_item='.json_encode($item));
                    $data = [
                        'result_status' => (string)$item['deliverResult'] ?? '',
                    ];
                    $where = ['msgid' =>(string)$msgid,'agents'=>(string)$this->agent,'is_back'=>0];
                    $model = self::getRows($where);
                    if(!empty($model)){
                        $data['id'] = $model['id'];
                        $data['is_back']=1;
                        $data['tenant_id'] =  $model['tenant_id'];
                        self::updateRows($data,$where);
                    }
                }
            });

        }
    }

    /**
     * @param array $params
     * @return mixed|void
     */
    public function getBalanceSms(array $params)
    {
        $balanceUrl = config('sendsms.is_dev')==true?config('sendsms.dev_url'):self::$balanceUrl;
        $account = $this->_getAccount();
        $this->request($account, $balanceUrl);
    }
}
