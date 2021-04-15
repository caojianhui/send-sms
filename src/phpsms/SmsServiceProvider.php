<?php

namespace Send\PhpSms;

use Illuminate\Support\ServiceProvider;
use Laravel\Lumen\Application as LumenApplication;

class SmsServiceProvider extends ServiceProvider
{
    /**
     * Indicates if loading of the provider is deferred.
     *
     * @var bool
     */
    protected $defer = true;

    /**
     * Bootstrap the application events.
     */
    public function boot()
    {
        if (function_exists('config_path')) {
            $publishPath = config_path('sendsms.php');
        } else {
            $publishPath = base_path('config/sendsms.php');
        }
        $this->publishes([
            __DIR__ . '/../config/sendsms.php' => $publishPath,
        ], 'config');
        $this->publishes([
            __DIR__ . '/../migrations/' => database_path('/migrations'),
        ], 'migrations');
    }

    /**
     * Register the service provider.
     */
    public function register()
    {
        if ($this->app instanceof LumenApplication) {
            $this->app->configure('sendsms');
        }
        $this->mergeConfigFrom(__DIR__ . '/../config/sendsms.php', 'sendsms');

        $this->app->singleton('Send\\Sms\\Sms', function () {
            Sms::scheme(config('sendsms.scheme', []));
            Sms::config(config('sendsms.agents', []));

            return new Sms(false);
        });
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public function provides()
    {
        return ['Send\\Sms\\Sms'];
    }
}
