<?php

namespace mii\core;

use Mii;


abstract class App {


    public $charset = 'UTF-8';

    /**
     * @var \mii\core\Container
     */
    public $container;

    public $controller;

    public $_config = [];

    protected $_auth;



    public function __construct(array $config = []) {
        Mii::$app = $this;

        $this->init($config);
    }

    public function init(array $config) {

        $this->_config = $config;

        Mii::$container = new Container();

        $components = $this->default_components();

        if(isset($config['components'])) {
            foreach($components as $name => $component) {
                if (!isset($config['components'][$name])) {
                    $config['components'][$name] = $component;
                } elseif (is_array($config['components'][$name]) && !isset($config['components'][$name]['class'])) {
                    $config['components'][$name]['class'] = $component['class'];
                }
            }
            $components = $config['components'];
        }

        foreach($components as $name => $config) {

            $this->set($name, $config);
        }

        $this->register_exception_handler();
        $this->register_error_handler();

        register_shutdown_function(function() {
            if ($error = error_get_last() AND in_array($error['type'], [E_PARSE, E_ERROR, E_USER_ERROR]))
            {
                // Clean the output buffer
                ob_get_level() AND ob_clean();

                // Fake an exception for nice debugging
                //throw new ErrorException($error['message'], $error['type'], 0, $error['file'], $error['line']);
                \mii\web\Exception::handler(new \ErrorException($error['message'], $error['type'], 0, $error['file'], $error['line']));

                // Shutdown now to avoid a "death loop"
                exit(1);
            }
        });
    }

    abstract function run();

    public function register_exception_handler() {}
    public function register_error_handler() {}


    public function config($group = false, $value = false) {
        if($value) {
            if($group)
                $this->_config[$group] = $value;
            else
                $this->_config = $value;

        } else {

            if ($group) {
                if(isset($this->_config[$group]))
                    return $this->_config[$group];
                return [];
            }

            return $this->_config;
        }
    }


    public function default_components() {
        return [
            'log' => ['class' => 'mii\log\Logger'],
            'user' => ['class' => 'mii\auth\User'],
            'auth' => ['class' => 'mii\auth\Auth'],
            'db' => ['class' => 'mii\db\Database'],
            'cache' => ['class' => 'mii\cache\Apc']
        ];
    }


    private $_components = [];

    private $_definitions = [];


    public function __get($name) {
        if ($this->has($name)) {
            return $this->get($name);
        }
    }

    public function has($id, $instantiated = false) {
        return $instantiated
            ? isset($this->_components[$id]) AND \Mii::$container->has($this->_components[$id])
            : isset($this->_components[$id]);
    }


    public function get($id, $throwException = true) {

        if (isset($this->_components[$id])) {
            return Mii::$container->get($id);
        }

        throw new \Exception("Unknown component ID: $id");

        if (isset($this->_definitions[$id])) {
            $definition = $this->_definitions[$id];

            if(is_array($definition)) {
                $class = $definition['class'];
                unset($definition['class']);
            } elseif(is_string($definition)) {
                $this->_components[$id] = $definition;
                return \Mii::$container->get($definition);
            }

            if (is_object($definition) && !$definition instanceof \Closure) {
                return $this->_components[$id] = $definition;
            } else {
                $this->_components[$id] = $class;
                return \Mii::$container->get($class, [$definition]);
            }
        } elseif ($throwException) {
            throw new \Exception("Unknown component ID: $id");
        } else {
            return null;
        }
    }

    public function __isset($name) {
        if ($this->has($name, true)) {
            return true;
        }
        return false;
    }

    public function set($id, $definition) {

        if ($definition === null) {
            unset($this->_components[$id]);
            // todo: remove from container
            return;
        }

        if(is_string($definition)) {
            \Mii::$container->share($id, $definition);
            $this->_components[$id] = true;

        } elseif(is_object($definition) || is_callable($definition, true)) {
            // an object, a class name, or a PHP callable
            $this->_components[$id] = $definition;

        } elseif (is_array($definition)) {
            // a configuration array
            if (isset($definition['class'])) {
                $this->_components[$id] = $definition['class'];
                unset($definition['class']);
                $params = (count($definition)) ? [$definition] : [];

                \Mii::$container->share($id, $this->_components[$id], $params);
            } else {
                throw new \Exception("The configuration for the \"$id\" component must contain a \"class\" element.");
            }

        } else {
            throw new \Exception("Unexpected configuration type for the \"$id\" component: " . gettype($definition));
        }
        return;

        if ($definition === null) {
            unset($this->_components[$id]);
            // todo: remove from container
            return;
        }

        if (is_array($definition)) {
            // a configuration array
            if (isset($definition['class'])) {
                $this->_definitions[$id] = $definition;

                \Mii::$container->share($id, $definition, [$definition]);
            } else {
                throw new \Exception("The configuration for the \"$id\" component must contain a \"class\" element.");
            }
        }

        $this->_components[$id] = true;

    }

}