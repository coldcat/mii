<?php

namespace mii\web;

use mii\captcha\Captcha;
use mii\core\Router;

/**
 * Class App
 *
 * @property Session $session The session component.
 * @property \mii\auth\User $user The user component.
 * @property Request $request
 * @property Blocks $blocks
 * @property Router $router
 * @property Response $response
 * @property Captcha $captcha
 * @property UploadHandler $upload
 *
 */
class App extends \mii\core\App
{
    public $user;

    public $maintenance;
    public $maintenance_message;

    public function run()
    {

        if($this->maintenance) {
            $protocol = "HTTP/1.0";
            if ( "HTTP/1.1" === $_SERVER["SERVER_PROTOCOL"] )
                $protocol = "HTTP/1.1";
            header( "$protocol 503 Service Unavailable", true, 503 );
            header( "Retry-After: 30" );

            echo $this->maintenance_message
            ? $this->maintenance_message
            : 'На сайте технические работы, которые закончатся через несколько секунд. Пожалуйста, обновите страницу в браузере';

            die;
        }


        $this->request->execute()->send();
    }

    public function default_components() {
        return [
            'log' => ['class' => 'mii\log\Logger'],
            'blocks' => ['class' => 'mii\web\Blocks'],
            'auth' => ['class' => 'mii\auth\Auth'],
            'db' => ['class' => 'mii\db\Database'],
            'cache' => ['class' => 'mii\cache\Apcu'],
            'mailer' => ['class' => 'mii\email\PHPMailer'],
            'session' => ['class' => 'mii\web\Session'],
            'router' => ['class' => 'mii\core\Router'],
            'request' => ['class' => 'mii\web\Request'],
            'response' => ['class' => 'mii\web\Response'],
            'captcha' => ['class' => 'mii\captcha\Captcha'],
            'upload' => ['class' => 'mii\web\UploadHandler'],
            'error' => ['class' => 'mii\web\ErrorHandler']
        ];
    }



}