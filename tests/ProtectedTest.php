<?php

use Send\Sms\Sms;

class ProtectedTest extends PHPUnit_Framework_TestCase
{
    public static function getPrivateMethod($name)
    {
        $obj = new Sms(false);
        $class = new ReflectionClass($obj);
        $method = $class->getMethod($name);
        $method->setAccessible(true);

        return $method;
    }

    public function testConfiguration()
    {
        $method = self::getPrivateMethod('configure');
        $obj = new Sms(false);
        $method->invokeArgs($obj, []);
        $config = include __DIR__ . '/../src/config/sendsms.php';
        $this->assertCount(count($config['scheme']), Sms::scheme());
        $this->assertCount(count($config['scheme']), Sms::config());
    }
}
