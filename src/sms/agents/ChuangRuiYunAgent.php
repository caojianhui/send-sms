<?php

namespace Send\Sms;

use GuzzleHttp\Client;
use GuzzleHttp\Pool;
use GuzzleHttp\Psr7\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Send\Sms\sms\interfaces\AcceptLogSms;

class ChuangRuiYunAgent extends Agent implements TemplateSms, ContentSms, LogSms, ClientSms, ReportSms, BalanceSms,AcceptLogSms
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
        $this->setResult($result, $params,$url);
    }

    /**
     * @param $result
     */
    protected function setResult($result, $params,$url)
    {
        if ($result['request']) {
            $groupUrl = config('sendsms.is_dev')==true?config('sendsms.dev_url'):self::$groupSendUrl;
            $sendUrl = config('sendsms.is_dev')==true?config('sendsms.dev_url'):self::$groupSendUrl;
            if ($url == $groupUrl || $url !==$sendUrl) {
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
            'act_id'=>$params['act_id']??0,
            'number'=> $this->getEmsNums($params['content']),
        ];
        if (isset($params['tenant_id'])) {
            $data['tenant_id'] = $params['tenant_id'];
        }
        $this->log($data);
    }

    /**
     * @param $message
     * @return float|int
     * 计算短信条数
     */
    private function getEmsNums($message)
    {
        $sign = config('sendsms.default_sign');
        $checked = strpos($message,$sign);
        if($checked ===false){
            $message = $message.$sign;
        }
        $length = Str::length($message);
        if($length>70){
            $count =  ceil($length/67);
        }else{
            $count = 1;
        }
        return (int)$count;
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
        $client = new Client();
        $requests = function ($data) use ($reportUrl) {
            $total = collect($data)->count() / 100;
            for ($i = 0; $i < $total; $i++) {
                $info = $this->_getAccount();
                $url = $reportUrl . '?' . http_build_query($info);
                yield new Request('get', $url);
            }
        };
        $pool = new Pool($client, $requests($data), [
            'concurrency' => config('sendsms.concurrency'),
            'fulfilled' => function ($response, $index) use ($data) {
                // this is delivered each successful response
                $result = json_decode($response->getBody()->getContents(), true);
                $config = config('sendsms.log');
                if ($result['code'] == 0) {
                    $re = $result['data'];
                    if(!empty($re)){
                        $this->updateLog($config,$re);
                        $this->result(Agent::SUCCESS,true);
                    }else{
                        $this->result(Agent::SUCCESS, true);
                        $this->result(Agent::INFO, '返回结果为空');
                    }
                }else{
                    $this->result(Agent::SUCCESS, false);
                    $this->result(Agent::INFO, '查询状态失败:'.json_encode($result));
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
     * @throws \Aliyun\OTS\OTSClientException
     * @throws \Aliyun\OTS\OTSServerException
     */
    private function updateLog($config, $re){
        if ($config['channel'] == self::LOG_DATABASE_CHANNEL) {
            collect($re)->chunk(100)->each(function ($values){
                foreach ($values as $item) {
                    $msgId = isset($item['batchId'])? $item['batchId']:($item['smUuid']??'');
                    $status = $item['deliverResult']??'';
                    if (!empty($msgId) && !empty($status) ){
                        $data = [
                            'result_status' => $status,
                            'updated_at'=>date('Y-m-d H:i:s'),
                            'is_back'=>1,
                        ];
                        DB::table('sms_logs')->where('agents', $this->agent)->where('msgid', $msgId)
                            ->where('is_back',0)
                            ->update($data);
                    }
                }
            });
        }elseif ($config['channel'] == self::LOG_TABLESTORE_CHANNEL){
            collect($re)->chunk(100)->each(function ($values){
                foreach ($values as $item) {
                    $msgId = isset($item['batchId'])? $item['batchId']:($item['smUuid']??'');
                     $status = $item['deliverResult']??'';
                    if(!empty($msgId) && !empty($status)){
                        $data = [
                            'result_status' => (string)$status,
                        ];
                        $where = ['msgid' =>(string)$msgId,'agents'=>(string)$this->agent,'is_back'=>0];
                        $model = self::getRows($where);
                        if(!empty($model)){
                            $data['id'] = $model['id'];
                            $data['is_back']=1;
                            $data['tenant_id'] =  $model['tenant_id'];
                            $where = [
                                'id' => $model['id'],
                                'tenant_id' => $model['tenant_id'],
                                'msgid' => (string)$model['msgid'],
                            ];
                            self::updateRows($data,$where);
                        }
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




    /**
     * @param array $params
     * @return mixed|void
     * @throws \Aliyun\OTS\OTSClientException
     * @throws \Aliyun\OTS\OTSServerException
     */
    public function acceptLog(array $params)
    {
        $where = ['is_back' => 0,'status'=>1];
        if (isset($params['tenant_id']) && !empty($params['tenant_id']) ){
            $where['tenant_id'] = $params['tenant_id'];
        }
        if (isset($params['act_id'])  && !empty($params['act_id'])){
            $where['act_id'] = $params['act_id'];
        }
        $config = config('sendsms.log');
        if ($config['channel'] == self::LOG_DATABASE_CHANNEL) {
            if (Schema::hasTable('sms_logs')) {
                DB::table('sms_logs')->where($where)
                    ->where('msgid', '!=', '')
                    ->chunkById(1000,function ($items) {
                        $param['msgids'] = $items->pluck('msgid')->toArray();
                        if (!empty($param['msgids'])) {
                            $this->getReportSms($param);
                        }
                    });
            }
        } elseif ($config['channel'] == self::LOG_FILE_CHANNEL) {
            $this->result(self::SUCCESS, false);
            $this->result(self::RESULT_DATA, []);
        } elseif ($config['channel'] == self::LOG_TABLESTORE_CHANNEL) {
            $tableConfig = config('sendsms.table_store');
            if (!empty($tableConfig['AccessKeyID']) && !empty($tableConfig['AccessKeySecret'])) {
                $limit = 100;
                $total = self::getTotal($where);
                if($total>0){
                    $lists = self::getPageList($where,$limit);
                    if($lists['data']->isNotEmpty()){
                        $param['msgids'] = $lists['data']->pluck('msgid')->toArray();
                        if (!empty($param['msgids'])) {
                            $this->getReportSms($param);
                        }
                    }
                    while(!is_null($lists['next_token'])){
                        $lists = self::getPageList($where,100,$lists['next_token']);
                        if($lists['data']->isNotEmpty()){
                            $param['msgid'] = $lists['data']->pluck('msgid')->toArray();
                            if (!empty($param['msgids'])) {
                                $this->getReportSms($param);
                            }
                        }
                    }
                }
            }
        }
    }



}
