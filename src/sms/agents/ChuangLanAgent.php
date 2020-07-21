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


class ChuangLanAgent extends Agent implements ContentSms, LogSms, ClientSms, ReportSms, BalanceSms,AcceptLogSms
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
        $url = config('sendsms.is_dev')==true?config('sendsms.dev_url'):self::$sendUrl;
        $this->request($params,$url);
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
    protected function setResult($result, $params,$url)
    {

        if ($result['request']) {
            $sendUrl = config('sendsms.is_dev')==true?config('sendsms.dev_url'):self::$sendUrl;
            if ($url == $sendUrl) {
                $this->sendLogSms($params, json_decode($result['response'], true));
            }
            $this->result(Agent::INFO, $result['response']);
            $result = json_decode($result['response'], true);
            $this->result(Agent::CODE, $result['code']);
            if ($result['code'] == '0') {
                $this->result(Agent::SUCCESS, true);
                if (isset($result['balance'])) {
                    $this->result(Agent::RESULT_DATA, $result);
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
            'act_id'=>$params['act_id']??0,
            'number'=> $this->getEmsNums($params['msg']),
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
     * @param array $data(phone,msg)
     * @return mixed|void
     *异步发送多个请求
     */
    public function sendClientSms(array $data)
    {
        $url = config('sendsms.is_dev')==true?config('sendsms.dev_url'):self::$sendUrl;

        $client = new Client();
        $requests = function ($data)use ($url) {
            $total = collect($data)->count();
            for ($i = 0; $i < $total; $i++) {
                $info = $this->_getInfo($data, $i);
                $key = $info['key'];
                if(!Cache::store('redis')->has($key)){
                    yield new Request('post', $url, [], json_encode($info, true));
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
                if ($result['code'] === '0') {
                    $this->result(Agent::SUCCESS, true);
                    if (isset($result['balance'])) {
                        $this->result(Agent::RESULT_DATA, $result);
                    }
                } else {
                    $this->result(Agent::SUCCESS, false);
                    $this->result(Agent::INFO, $result['errorMsg']);
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
        isset($data[$index]['act_id']) ? $info['act_id'] = $data[$index]['act_id'] : '';
        $info['key'] = $data[$index]['key']??$info['phone'];
        return $info;
    }

    /**
     * @param array $params
     * @return mixed|void
     * 记录日志
     */
    public function getReportSms(array $params)
    {
        $url = config('sendsms.is_dev')==true?config('sendsms.dev_url'):self::$reportUrl;
        $data = $params['msgids'];
        $type = $params['type'];
        $client = new Client();
        $requests = function ($data) use ($type,$url) {
            $total = collect($data)->count() / 100;
            for ($i = 0; $i < $total; $i++) {
                $info = $this->_getAccount($type);
                $url = $url . '?' . http_build_query($info);
                yield new Request('get', $url);
            }
        };
        $pool = new Pool($client, $requests($data), [
            'concurrency' => config('sendsms.concurrency'),
            'fulfilled' => function ($response, $index) use ($data) {
                // this is delivered each successful response
                $result = json_decode($response->getBody()->getContents(), true);
                $config = config('sendsms.log');
                if ($result['ret'] == 0) {
                    $re = $result['result'];
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
                // this is delivered each failed request
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
     * @param $info
     * @throws \Aliyun\OTS\OTSClientException
     * @throws \Aliyun\OTS\OTSServerException
     */
    private function updateLog($config, $re){
        if ($config['channel'] == self::LOG_DATABASE_CHANNEL) {
            collect($re)->chunk(100)->each(function ($values){
                foreach ($values as $item) {
                    $msgId = $item['msgId']??'';
                    $status = $item['status'] ?? '';
                    if(!empty($msgid) && $status){
                        $data = [
                            'updated_at' => date('Y-m-d H:i:s'),
                            'result_status' => $status,
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
                    $msgId = (string)$item['msgId']??'';
                    $status = (string)$item['status'] ?? '';
                    if(!empty($msgid) && !empty($status)) {
                        $data = [
                            'result_status' => $status,
                        ];
                        $where = ['msgid' => $msgId, 'agents' => (string)$this->agent, 'is_back' => 0];
                        $model = self::getRows($where);
                        if (!empty($model)) {
                            $data['id'] = $model['id'];
                            $data['is_back'] = 1;
                            $data['tenant_id'] = $model['tenant_id'];
                            $where = [
                                'id' => $model['id'],
                                'tenant_id' => $model['tenant_id'],
                                'msgid' => (string)$model['msgid'],
                            ];
                            self::updateRows($data, $where);
                        }
                    }
                }
            });
        }
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
        $url = config('sendsms.is_dev')==true?config('sendsms.dev_url'):self::$reportUrl;
        $this->request($param, $url);
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
            $param['password'] = config('sendsms.agents.' . $this->agent . '.market.password');
        }
        return $param;
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
                $lists = DB::table('sms_logs')->where($where)
                    ->where('msgid', '!=', '')
                    ->get();
                if ($lists->isNotEmpty()){
                    $this->sendReport($lists);
                }
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
                        $this->sendReport($lists['data']);
                    }
                    while(!is_null($lists['next_token'])){
                        $lists = self::getPageList($where,100,$lists['next_token']);
                        if($lists['data']->isNotEmpty()){
                           $this->sendReport($lists['data']);
                        }
                    }
                }
            }
        }
    }

    /**
     * @param $data
     * @return mixed
     */
    private function sendReport($data){
        return $data->groupBy('type')->map(function ($items,$key){
            $param['type'] = $key;
            $param['msgids'] = $items->pluck('msgid')->toArray();
            if (!empty($param['msgid'])) {
                $this->getReportSms($param);
            }
        });
    }


}
