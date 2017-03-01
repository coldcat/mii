<?php

namespace mii\console;

use mii\util\Console;

class Controller
{

    public $name;

    public $description;

    public $color;

    public $interactive = true;

    public function __construct(Request $request, Response $response)
    {
        // Assign the request to the controller
        $this->request = $request;

        // Assign a response to the controller
        $this->response = $response;

    }


    protected function before()
    {
        return true;
    }



    protected function after() {}


    /**
     * Executes the given action and calls the [Controller::before] and [Controller::after] methods.
     *
     * Can also be used to catch exceptions from actions in a single place.
     *
     * 1. Before the controller action is called, the [Controller::before] method
     * will be called.
     * 2. Next the controller action will be called.
     * 3. After the controller action is called, the [Controller::after] method
     * will be called.
     *
     * @throws  HTTP_Exception_404
     * @return  Response
     */
    public function execute($params = [])
    {
        //$method = new \ReflectionMethod($this, $this->request->action());

        $this->before();

        call_user_func([$this, $this->request->action], $this->request->params);

        $this->after();

        return $this->response;

    }

    public function is_color_enabled($stream = \STDOUT)
    {
        return $this->color === null ? Console::stream_supports_ansi_colors($stream) : $this->color;
    }

    public function ansi_format($string)
    {
        if ($this->is_color_enabled()) {
            $args = func_get_args();
            array_shift($args);
            $string = Console::ansi_format($string, $args);
        }
        return $string;
    }

    public function stdout($string)
    {
        if ($this->is_color_enabled()) {
            $args = func_get_args();
            array_shift($args);
            $string = Console::ansi_format($string, $args);
        }
        return Console::stdout($string);
    }

    public function stdin() {
        return Console::stdin();
    }

    public function confirm($message, $default = false) {
        if ($this->interactive) {
            return Console::confirm($message, $default);
        } else {
            return true;
        }
    }

    protected function info($msg, $options = []) {
        $msg = strtr($msg, $options);
        $this->stdout($msg."\n", Console::FG_GREEN);
        \Mii::info($msg, 'console');
    }

    protected function warning($msg, $options = []) {
        $msg = strtr($msg, $options);
        $this->stdout($msg."\n", Console::FG_PURPLE);
        \Mii::warning(strtr($msg, $options), 'console');
    }

    protected function error($msg, $options = []) {
        $msg = strtr($msg, $options);
        $this->stdout($msg."\n", Console::FG_RED);
        \Mii::error(strtr($msg, $options), 'console');
    }

}