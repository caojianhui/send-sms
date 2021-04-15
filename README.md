# send-sms
创蓝和创瑞云短信接口整合

安装
composer require send/sms
1. 配置
配置代理器所需参数
为你需要用到的短信服务商(即代理器)配置必要的参数。可以在config\sendsms.php中键为agents的数组中配置，也可以手动在程序中设置，示例如下：

//example:
Sms::config([
    'Luosimao' => [
        'apikey' => 'your api key',
        'voiceApikey' => 'your voice api key',
    ],
    'YunPian'  => [
        'apikey' => 'your api key',
    ],
    'SmsBao' => [
        'username' => 'your username',
        'password'  => 'your password'
    ]
]);
配置代理器调度方案
可在config\sendsms.php中键为scheme的数组中配置。也可以手动在程序中设置，示例如下：

//example:
Sms::scheme([
    //被使用概率为2/3
    'Luosimao' => '20',

    //被使用概率为1/3，且为备用代理器
    'YunPian' => '10 backup',

    //仅为备用代理器
    'SmsBao' => '0 backup',
]);
调度方案解析： 如果按照以上配置，那么系统首次会尝试使用Luosimao或YunPian发送短信，且它们被使用的概率分别为2/3和1/3。 如果使用其中一个代理器发送失败，那么会启用备用代理器，按照配置可知备用代理器有YunPian和SmsBao，那么会依次调用直到发送成功或无备用代理器可用。 值得注意的是，如果首次尝试的是YunPian，那么备用代理器将会只使用SmsBao，也就是会排除使用过的代理器。

2. Enjoy it!
require('path/to/vendor/autoload.php');
use Send\PhpSms\Sms;
// 接收人手机号
$to = '1828****349';
// 短信模版
$templates = [
    'YunTongXun' => 'your_temp_id',
    'SubMail'    => 'your_temp_id'
];
// 模版数据
$tempData = [
    'code' => '87392',
    'minutes' => '5'
];
// 短信内容
$content = '【签名】这是短信内容...';

// 只希望使用模板方式发送短信，可以不设置content(如:云通讯、Submail、Ucpaas)
Sms::make()->to($to)->template($templates)->data($tempData)->send();

// 只希望使用内容方式发送，可以不设置模板id和模板data(如:短信宝、云片、luosimao)
Sms::make()->to($to)->content($content)->send();

// 同时确保能通过模板和内容方式发送，这样做的好处是可以兼顾到各种类型服务商
Sms::make()->to($to)
    ->template($templates)
    ->data($tempData)
    ->content($content)
    ->send();

// 语音验证码
Sms::voice('02343')->to($to)->send();

// 语音验证码兼容模版语音(如阿里大鱼的文本转语音)
Sms::voice('02343')
    ->template('Alidayu', 'your_tts_code')
    ->data(['code' => '02343'])
    ->to($to)
    ->send();
3. 在laravel和lumen中使用
服务提供器
//服务提供器
'providers' => [
    ...
    Send\PhpSms\PhpSmsServiceProvider::class,
]

//别名
'aliases' => [
    ...
    'Sms' => Send\PhpSms\Facades\Sms::class,
]
生成配置文件
php artisan vendor:publish
生成的配置文件为config/semdsms.php，然后在该文件中按提示配置。

使用
详见API，示例：

PhpSms::make()->to($to)->content($content)->send();
API
API - 全局配置
Sms::scheme([$name[, $scheme]])
设置/获取代理器的调度方案。

调度配置支持热更新，即在应用系统的整个运行过程中都能随时修改。

设置
手动设置代理器调度方案(优先级高于配置文件)，如：

Sms::scheme([
    'SmsBao' => '80 backup'
    'YunPian' => '100 backup'
]);
//或
Sms::scheme('SmsBao', '80 backup');
Sms::scheme('YunPian', '100 backup');
获取
通过该方法还能获取所有或指定代理器的调度方案，如：

//获取所有的调度方案:
$scheme = Sms::scheme();

//获取指定代理器的调度方案:
$scheme['SmsBao'] = Sms::scheme('SmsBao');
scheme静态方法的更多使用方法见高级调度配置

Sms::config([$name[, $config][, $override]]);
设置/获取代理器的配置数据。

参数配置支持热更新，即在应用系统的整个运行过程中都能随时修改。

设置
手动设置代理器的配置数据(优先级高于配置文件)，如：

Sms::config([
   'SmsBao' => [
       'username' => ...,
       'password' => ...,
   ]
]);
//或
Sms::config('SmsBao', [
   'username' => ...,
   'password' => ...,
]);
获取
通过该方法还能获取所有或指定代理器的配置参数，如：

//获取所有的配置:
$config = Sms::config();

//获取指定代理器的配置:
$config['SmsBao'] = Sms::config('SmsBao');
Sms::beforeSend($handler[, $override]);
发送前钩子，示例：

Sms::beforeSend(function($task, $index, $handlers, $prevReturn){
    //获取短信数据
    $smsData = $task->data;
    ...
    //如果返回false会终止发送任务
    return true;
});
更多细节请查看 task-balancer 的 beforeRun 钩子

Sms::beforeAgentSend($handler[, $override]);
代理器发送前钩子，示例：

Sms::beforeAgentSend(function($task, $driver, $index, $handlers, $prevReturn){
    //短信数据:
    $smsData = $task->data;
    //当前使用的代理器名称:
    $agentName = $driver->name;
    //如果返回false会停止使用当前代理器
    return true;
});
更多细节请查看 task-balancer 的 beforeDriverRun 钩子

Sms::afterAgentSend($handler[, $override]);
代理器发送后钩子，示例：

Sms::afterAgentSend(function($task, $agentResult, $index, $handlers, $prevReturn){
     //$result为代理器的发送结果数据
     $agentName = $agentResult['driver'];
     ...
});
更多细节请查看 task-balancer 的 afterDriverRun 钩子

Sms::afterSend($handler[, $override]);
发送后钩子，示例：

Sms::afterSend(function($task, $taskResult, $index, $handlers, $prevReturn){
    //$result为发送后获得的结果数组
    $success = $taskResult['success'];
    ...
});
更多细节请查看 task-balancer 的 afterRun 钩子

Sms::queue([$enable[, $handler]])
该方法可以设置是否启用队列以及定义如何推送到队列。

$handler匿名函数可使用的参数:

$sms : Sms实例
$data : Sms实例中的短信数据，等同于$sms->all()
定义如何推送到队列：

//自动启用队列
Sms::queue(function($sms, $data){
    //define how to push to queue.
    ...
});

//第一个参数为true,启用队列
Sms::queue(true, function($sms, $data){
    //define how to push to queue.
    ...
});

//第一个参数为false,暂时关闭队列
Sms::queue(false, function($sms, $data){
    //define how to push to queue.
    ...
});
如果已经定义过如何推送到队列，还可以继续设置关闭/开启队列：

Sms::queue(true);//开启队列
Sms::queue(false);//关闭队列
获取队列启用情况：

$enable = Sms::queue();
//为true,表示当前启用了队列。
//为false,表示当前关闭了队列。
API - 发送相关
Sms::make()
生成发送短信的sms实例，并返回实例。

$sms = Sms::make();

//创建实例的同时设置短信内容：
$sms = Sms::make('【签名】这是短信内容...');

//创建实例的同时设置短信模版：
$sms = Sms::make('YunTongXun', 'your_temp_id');
//或
$sms = Sms::make([
    'YunTongXun' => 'your_temp_id',
    'SubMail' => 'your_temp_id',
    ...
]);
Sms::voice()
生成发送语音验证码的sms实例，并返回实例。

$sms = Sms::voice();

//创建实例的同时设置验证码
$sms = Sms::voice($code);
如果你使用Luosimao语音验证码，还需用在配置文件中Luosimao选项中设置voiceApikey。
语音文件ID即是在服务商配置的语音文件的唯一编号，比如阿里大鱼语音通知的voice_code。
模版语音是另一种语音请求方式，它是通过模版ID和模版数据进行的语音请求，比如阿里大鱼的文本转语音通知。
type($type)
设置实例类型，可选值有Sms::TYPE_SMS和Sms::TYPE_VOICE，返回实例对象。

to($mobile)
设置发送给谁，并返回实例。

$sms->to('1828*******');

//兼容腾讯云
$sms->to([86, '1828*******'])
template($agentName, $id)
指定代理器设置模版或批量设置，并返回实例。

//设置指定服务商的模板id
$sms->template('YunTongXun', 'your_temp_id')
    ->template('SubMail', 'your_temp_id');

//一次性设置多个服务商的模板id
$sms->template([
    'YunTongXun' => 'your_temp_id',
    'SubMail' => 'your_temp_id',
    ...
]);
data($key, $value)
设置模板短信的模板数据，并返回实例对象。

//单个数据
$sms->data('code', $code);

//同时设置多个数据
$sms->data([
    'code' => $code,
    'minutes' => $minutes
]);
通过template和data方法的组合除了可以实现模版短信的数据填充，还可以实现模版语音的数据填充。

content($text)
设置内容短信的内容，并返回实例对象。

一些内置的代理器(如SmsBao、YunPian、Luosimao)使用的是内容短信(即直接发送短信内容)，那么就需要为它们设置短信内容。

$sms->content('【签名】这是短信内容...');
code($code)
设置语音验证码，并返回实例对象。

file($agentName, $id)
设置语音文件，并返回实例对象。

$sms->file('Agent1', 'agent1_file_id')
    ->file('Agent2', 'agent2_file_id');

//或
$sms->file([
    'Agent1' => 'agent1_file_id',
    'Agent2' => 'agent2_fiile_id',
]);
params($agentName, $params)
直接设置参数到服务商提供的原生接口上，并返回实例对象。

$sms->params('Agent1', [
    'callbackUrl' => ...,
    'userData'    => ...,
]);

//或
$sms->params([
    'Agent1' => [
        'callbackUrl' => ...,
        'userData'    => ...,
    ],
    'Agent2' => [
        ...
    ],
]);
all([$key])
获取Sms实例中的短信数据，不带参数时返回所有数据，其结构如下：

[
    'type'      => ...,
    'to'        => ...,
    'templates' => [...],
    'data'      => [...], // template data
    'content'   => ...,
    'code'      => ...,   // voice code
    'files'     => [...], // voice files
    'params'    => [...],
]
agent($name)
临时设置发送时使用的代理器(不会影响备用代理器的正常使用)，并返回实例，$name为代理器名称。

$sms->agent('SmsBao');
通过该方法设置的代理器将获得绝对优先权，但只对当前短信实例有效。

send()
请求发送短信/语音验证码。

//会遵循是否使用队列
$result = $sms->send();

//忽略是否使用队列
$result = $sms->send(true);
$result数据结构请参看task-balancer

自定义代理器
step 1
可将配置项(如果有用到)加入到config/sendsms.php中键为agents的数组里。

//example:
'Foo' => [
    'key' => 'your api key',
    ...
]
step 2
新建一个继承Send\PhpSms\Agent抽象类的代理器类，建议代理器类名为FooAgent，建议命名空间为Send\PhpSms。

如果类名不为FooAgent或者命名空间不为Send\PhpSms，在使用该代理器时则需要指定代理器类，详见高级调度配置。

step 3
实现相应的接口，可选的接口有:

接口	说明
ContentSms	发送内容短信
TemplateSms	发送模版短信
VoiceCode	发送语音验证码
ContentVoice	发送内容语音
TemplateVoice	发送模版语音
FileVoice	发送文件语音
高级调度配置
代理器的高级调度配置可以通过配置文件(config/sendsms.php)中的scheme项目配置，也可以通过scheme静态方法设置。 值得注意的是，高级调度配置的值的数据结构是数组。

指定代理器类
如果你自定义了一个代理器，类名不为FooAgent或者命名空间不为Send\PhpSms， 那么你还可以在调度配置时指定你的代理器使用的类。

配置方式：
通过配置值中agentClass键来指定类名。

示例：
Sms::scheme('agentName', [
    '10 backup',
    'agentClass' => 'My\Namespace\MyAgentClass'
]);
寄生代理器
如果你既不想使用内置的代理器，也不想创建文件写自定义代理器，那么寄生代理器或许是个好的选择， 无需定义代理器类，只需在调度配置时定义好发送短信和语音验证码的方式即可。

配置方式：
可以配置的发送过程有:

发送过程	参数列表	说明
sendContentSms	$agent, $to, $content	发送内容短信
sendTemplateSms	$agent, $to, $tmpId, $tmpData	发送模版短信
sendVoiceCode	$agent, $to, $code	发送语音验证码
sendContentVoice	$agent, $to, $content	发送内容语音
sendTemplateVoice	$agent, $to, $tmpId, $tmpData	发送模版语音
sendFileVoice	$agent, $to, $fileId	发送文件语音
示例：
Sms::scheme([
    'agentName' => [
        '20 backup',
        'sendContentSms' => function($agent, $to, $content){
            // 获取配置(如果设置了的话):
            $key = $agent->key;
            ...
            // 可使用的内置方法:
            $agent->curlGet($url, $params); //get
            $agent->curlPost($url, $params); //post
            ...
            // 更新发送结果:
            $agent->result(Agent::SUCCESS, true);
            $agent->result(Agent::INFO, 'some info');
            $agent->result(Agent::CODE, 'your code');
        },
        'sendVoiceCode' => function($agent, $to, $code){
            // 发送语音验证码，同上
        }
    ]
]);
