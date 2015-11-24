<?php

namespace mii\log;


use mii\core\Exception;
use mii\util\Debug;

class File extends Logger {


    protected $base_path;
    protected $file = '';
    protected $levels = Logger::ALL;
    protected $category;

    protected $is_init = false;

    protected $messages = [];


    public function __construct($params) {
        $this->file = $params['file'];
        $this->levels = isset($params['levels']) ? $params['levels'] : Logger::ALL;
        $this->category = isset($params['category']) ? $params['category'] : '' ;
        $this->base_path = isset($params['base_path']) ? $params['base_path'] : path('app');

    }


    public function init() {
        $this->is_init = true;

        $path = dirname($this->base_path.$this->file);
        if (!is_dir($path)) {
            mkdir($path, 0777, true);
        }


        register_shutdown_function(function () {
            $this->flush();
        });
    }

    public function log($level, $message, $category) {

        if(! ($this->levels & $level))
            return;

        if($this->category AND $this->category !== $category)
            return;

        if(! $this->is_init)
            $this->init();

        $this->messages[] = [$message, $level, $category, time()];

    }


    public function flush() {

        if(!count($this->messages))
            return;

        $text = implode("\n", array_map([$this, 'format_message'], $this->messages)) . "\n";
        $this->messages = [];


        if (($fp = @fopen($this->base_path.$this->file, 'a')) === false) {
            // TODO: throw new Exception("Unable to append to log file: {$this->file}");
        }
        @flock($fp, LOCK_EX);

        @fwrite($fp, $text);
        @flock($fp, LOCK_UN);
        @fclose($fp);
    }


    public function format_message($message)
    {
        list($text, $level, $category, $timestamp) = $message;

        $level = Logger::$level_names[$level];
        if (!is_string($text)) {
            // exceptions may not be serializable if in the call stack somewhere is a Closure
            if ($text instanceof \Exception) {
                $text = (string) $text;
            } else {
               // TODO:: $text = Debug::dump($text);
            }
        }
        //$prefix = $this->getMessagePrefix($message);
        return date('Y-m-d H:i:s', $timestamp) . " [$level][$category] $text";

    }
}