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

    /**
     * @param array|string $to
     * @param string $content
     * 发送普通短信
     */
    public function sendContentSms($to, $content)
    {
        $params = [
            'account' => $this->config('notice')['account'],
            'password' => $this->config('notice')['password'],
            'msg' => urlencode(($this->config('sign') . $content)),
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
            'account' => $this->config('market')['account'],
            'password' => $this->config('market')['password'],
            'msg' => urlencode(($this->config('sign') . $content)),
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
        $config = config('sendsms.log');
        $data = [
            'to' => $params['phone'],
            'content' => $params['msg'],
            'status' => $result['code'] == 0 ? 1 : 2,
            'agents' => $this->agent,
            'result_info' => json_encode($result),
        ];
        if ($config['channel'] == self::LOG_DATABASE_CHANNEL) {
            if (Schema::hasTable('sms_logs')) {
                return DB::table('sms_logs')->insert($data);
            }
        } elseif ($config['channel'] == self::LOG_FILE_CHANNEL) {
            $file = $config['file'];
            if (!file_exists($file)) {
                fopen($file, "w");
            }
            $log = new Logger('sendSmsLog');
            $log->pushHandler(new StreamHandler($file, Logger::INFO));
            $log->addInfo(json_encode($data, true));
        }

    }
}
