<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateSmsLogsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasTable('sms_logs')) {
            Schema::create('sms_logs', function (Blueprint $table) {
                $table->bigIncrements('id');

                //to:用于存储手机号
                $table->string('to')->default('')->comment('用于存储手机号');

                //temp_id:存储模板标记，用于存储任何第三方服务商提供的短信模板标记/id
                $table->string('temp_id')->default('')->comment('第三方代理器模版ID');

                //data:模板短信的模板数据，建议json格式
                $table->string('data')->default('')->comment('模版短信数据，json格式');

                //content:内容
                $table->string('content')->default('')->comment('短信内容');

                //voice_code:语言验证码code
                $table->string('voice_code')->default('')->comment('语音验证码');
                $table->tinyInteger('status')->default(0)->comment('发送短信状态：0发起1成功2失败');
                $table->string('agents', 50)->default(0)->comment('代理器名称，config配置代理器名称');
                //发送状态改变时的时间
                $table->integer('sent_time')->unsigned()->default(0);

                //代理器使用日志，记录每个代理器的发送状态，可用于排错
                $table->text('result_info')->nullable();

                $table->timestamps();
                $table->softDeletes();
                $table->engine = 'InnoDB';

                //说明
                //1：temp_id和data用于发送模板短信。
                //2：content用于直接发送短信内容，不使用模板。
                //3：voice_code用于存储语言验证码code。
            });
        }

    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('sms_logs');
    }
}
