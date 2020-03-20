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
                $table->text('to')->default('')->comment('用于存储手机号，多个以逗号分割');

                //temp_id:存储模板标记，用于存储任何第三方服务商提供的短信模板标记/id
                $table->string('temp_id')->default(null)->nullable()->comment('第三方代理器模版ID');
                $table->tinyInteger('type', false, true)->default(1)->comment('短信类型：1营销短信0通知短信');
                $table->string('msgid', 255)->default(null)->comment('第三方消息ID，唯一标识');

                $table->integer('tenant_id')->default(1)->nullable()->comment('发送商户ID');
                //content:内容
                $table->text('content')->default(null)->comment('短信内容');
                //voice_code:语言验证码code
                $table->string('voice_code')->default(null)->nullable()->comment('语音验证码');

                $table->tinyInteger('status')->default(1)->comment('发送短信状态：1成功2失败');
                $table->string('result_status',255)->default(null)->nullable()->comment('运营商返回短信发送状态');
                //代理器名称
                $table->string('agents', 50)->default(0)->comment('代理器名称，config配置代理器名称');
                $table->text('params')->default(null)->comment('发送短信数据');


                //代理器使用日志，记录每个代理器的发送状态，可用于排错
                $table->text('result_info')->nullable()->comment('返回结果');

                $table->timestamps();
                $table->softDeletes();
                $table->engine = 'InnoDB';
            });
        } else {
            Schema::table('sms_logs', function (Blueprint $table) {
                if (!Schema::hasColumn('sms_logs', 'msgid')) {
                    $table->string('msgid', 255)->after('temp_id')->default(null)->comment('第三方消息ID，唯一标识');
                }
                if (!Schema::hasColumn('sms_logs', 'type')) {
                    $table->tinyInteger('type', false, true)->after('temp_id')->default(1)->comment('短信类型：1营销短信0通知短信');
                }
                if (!Schema::hasColumn('sms_logs', 'result_status')) {
                    $table->string('result_status',255)->default(null)->nullable()->after('status')->comment('运营商返回短信发送状态：DELIVRD为成功，其他值均为失败');
                }
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
