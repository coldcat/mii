<?php

namespace mii\web;

use mii\core\Component;
use mii\core\InvalidRouteException;


class Request extends Component
{

    // HTTP Methods
    const GET = 'GET';
    const POST = 'POST';
    const PUT = 'PUT';
    const DELETE = 'DELETE';
    const HEAD = 'HEAD';
    const OPTIONS = 'OPTIONS';
    const TRACE = 'TRACE';
    const CONNECT = 'CONNECT';

    /**
     * @var  string  method: GET, POST, PUT, DELETE, HEAD, etc
     */
    protected $_method = 'GET';

    /**
     * The name of the HTTP header for sending CSRF token.
     */
    const CSRF_HEADER = 'X-CSRF-Token';

    /**
     * @var  string  the URI of the request
     */
    protected $_uri;


    protected $_hostname;


    public $csrf_validation = true;


    public $enable_csrf_cookie = true;


    public $csrf_token_name = 'csrf_token';


    protected $_csrf_token;

    /**
     * @var  string  Magic salt to add to the cookie
     */
    protected $cookie_salt = null;

    /**
     * @var  integer  Number of seconds before the cookie expires
     */
    protected $cookie_expiration = 0;

    /**
     * @var  string  Restrict the path that the cookie is available to
     */
    public $cookie_path = '/';

    /**
     * @var  string  Restrict the domain that the cookie is available to
     */
    public $cookie_domain = null;

    /**
     * @var  boolean  Only transmit cookies over secure connections
     */
    public $cookie_secure = false;

    /**
     * @var  boolean  Only transmit cookies over HTTP, disabling Javascript access
     */
    public $cookie_httponly = false;

    /**
     * @var  array   parameters from the route
     */
    public $params = [];

    /**
     * @var  string  controller to be executed
     */
    public $controller;

    /**
     * @var  string  action to be executed in the controller
     */
    public $action;


    public function init(array $config = []): void {

        foreach ($config as $key => $value)
            $this->$key = $value;

        $uri = parse_url('http://domain.com'.$_SERVER['REQUEST_URI'], PHP_URL_PATH);


        if (\Mii::$app->base_url && strpos($uri, \Mii::$app->base_url) === 0) {
            // Remove the base URL from the URI
            $uri = (string) substr($uri, strlen(\Mii::$app->base_url));
        }

        $this->uri($uri);

        if (isset($_SERVER['REQUEST_METHOD'])) {
            // Use the server request method
            $this->method($_SERVER['REQUEST_METHOD']);
        }

        if($this->_method === 'PUT') {
            parse_str($this->raw_body(), $_POST);
        }
    }


    /**
     * Sets and gets the uri from the request.
     *
     * @param   string $uri
     * @return  string
     */
    public function uri($uri = NULL): string {
        if ($uri === NULL) {
            return empty($this->_uri) ? '/' : $this->_uri;
        }

        return $this->_uri = $uri;
    }

    public function get_hostname(): string {

        if (!$this->_hostname) {
            $http = $this->is_secure() ? 'https' : 'http';
            $domain = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : $_SERVER['SERVER_NAME'];
            $this->_hostname = $http . '://' . $domain;
        }

        return $this->_hostname;
    }

    public function get_user_agent(): string {
        return $_SERVER['HTTP_USER_AGENT'];
    }


    /**
     * Gets or sets the HTTP method. Usually GET, POST, PUT or DELETE in
     * traditional CRUD applications.
     *
     * @param   string $method Method to use for this request
     * @return  mixed
     */
    public function method($method = NULL) {
        if ($method === NULL) {
            // Act as a getter
            return $this->_method;
        }

        // Act as a setter
        $this->_method = strtoupper($method);

        return $this;
    }

    /**
     * Return if the request is sent via secure channel (https).
     * @return boolean if the request is sent via secure channel (https)
     */
    public function is_secure() {
        return isset($_SERVER['HTTPS']) && (strcasecmp($_SERVER['HTTPS'], 'on') === 0 || $_SERVER['HTTPS'] == 1)
            || isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && strcasecmp($_SERVER['HTTP_X_FORWARDED_PROTO'], 'https') === 0;
    }


    /**
     *  Returns GET parameter with a given name. If name isn't specified, returns an array of all GET parameters.
     *
     * @param   string $name the parameter name
     * @param   string $value the default parameter value if the parameter does not exist.
     * @return  mixed
     */
    public function get($key = null, $default = null) {
        if ($key) {
            return isset($_GET[$key]) ? $_GET[$key] : $default;
        }

        return $_GET;
    }

    public function param($key, $default = null) {
        if (isset($this->params[$key]))
            return $this->params[$key];

        return $default;
    }


    public function validate_csrf_token() {

        if (!$this->csrf_validation || in_array($this->method(), ['GET', 'HEAD', 'OPTIONS'], true)) {
            return true;
        }

        $token = '';

        if (isset($_POST[$this->csrf_token_name])) {

            $token = $_POST[$this->csrf_token_name];

        } elseif (isset($_SERVER['HTTP_X_CSRF_TOKEN'])) { // must be like self::CSRF_HEADER

            $token = $_SERVER['HTTP_X_CSRF_TOKEN'];

        } else {
            return false;
        }

        return $this->csrf_token() === $token;
    }


    public function csrf_token($new = false) {

        if ($this->_csrf_token === null || $new) {
            if ($new || ($this->_csrf_token = $this->load_csrf_token()) === null) {
                // Generate a new unique token
                $this->_csrf_token = bin2hex(random_bytes(20));

                // Store the new token
                if ($this->enable_csrf_cookie) {
                    $this->set_cookie($this->csrf_token_name, $this->_csrf_token);
                } else {
                    \Mii::$app->session->set($this->csrf_token_name, $this->_csrf_token);
                }
            }
        }

        return $this->_csrf_token;
    }

    public function load_csrf_token() {
        if ($this->enable_csrf_cookie) {
            return $this->get_cookie($this->csrf_token_name);
        } else {
            return \Mii::$app->session->get($this->csrf_token_name);
        }
    }


    /**
     * Gets HTTP POST parameters of the request.
     *
     * @param   mixed $key Parameter name
     * @param   string $default Default value if parameter does not exist
     * @return  mixed
     */
    public function post($key = null, $default = null) {
        if ($key === null) {
            return $_POST;
        }

        return isset($_POST[$key]) ? $_POST[$key] : $default;
    }

    /**
     * Returns whether this is an AJAX (XMLHttpRequest) request.
     * @return boolean whether this is an AJAX (XMLHttpRequest) request.
     */
    public function is_ajax() {
        return isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest';
    }

    /**
     * Returns whether this is a PJAX request
     * @return boolean whether this is a PJAX request
     */
    public function is_pjax() {
        return $this->is_ajax() && !empty($_SERVER['HTTP_X_PJAX']);
    }

    public function is_post() {
        return $this->method() === 'POST';
    }


    private $_raw_body;

    /**
     * Returns the raw HTTP request body.
     * @return string the request body
     */
    public function raw_body() {
        if ($this->_raw_body === null) {
            $this->_raw_body = file_get_contents('php://input');
        }

        return $this->_raw_body;
    }


    /**
     * Gets the value of a signed cookie. Cookies without signatures will not
     * be returned. If the cookie signature is present, but invalid, the cookie
     * will be deleted.
     *
     * @param   string $key cookie name
     * @param   mixed $default default value to return
     * @return  string
     */
    public function get_cookie(string $key, $default = null) {
        if (!isset($_COOKIE[$key])) {
            // The cookie does not exist
            return $default;
        }

        // Get the cookie value
        $cookie = $_COOKIE[$key];

        // Find the position of the split between salt and contents
        $split = strlen($this->salt($key, ''));

        if (isset($cookie[$split]) && $cookie[$split] === '~') {
            // Separate the salt and the value
            list ($hash, $value) = explode('~', $cookie, 2);

            if ($this->salt($key, $value) === $hash) {
                // Cookie signature is valid
                return $value;
            }

            // The cookie signature is invalid, delete it
            $this->delete_cookie($key);
        }

        return $default;
    }


    /**
     * Sets a signed cookie. Note that all cookie values must be strings and no
     * automatic serialization will be performed!
     *
     * @param   string $name name of cookie
     * @param   string $value value of cookie
     * @param   integer $expiration lifetime in seconds
     * @return  boolean
     */
    public function set_cookie($name, $value, $expiration = null) {
        if ($expiration === null) {
            // Use the default expiration
            $expiration = $this->cookie_expiration;
        }

        if ($expiration !== 0) {
            // The expiration is expected to be a UNIX timestamp
            $expiration += time();
        }

        // Add the salt to the cookie value
        $value = $this->salt($name, $value) . '~' . $value;

        return setcookie($name, $value, $expiration, $this->cookie_path, $this->cookie_domain, $this->cookie_secure, $this->cookie_httponly);
    }


    /**
     * Deletes a cookie by making the value null and expiring it.
     *
     *     Cookie::delete('theme');
     *
     * @param   string $name cookie name
     * @return  boolean
     */
    public function delete_cookie($name) {
        // Remove the cookie
        unset($_COOKIE[$name]);

        // Nullify the cookie and make it expire
        return setcookie($name, null, -86400, $this->cookie_path, $this->cookie_domain, $this->cookie_secure, $this->cookie_httponly);
    }


    /**
     * Generates a salt string for a cookie based on the name and value.
     *
     * @param   string $name name of cookie
     * @param   string $value value of cookie
     * @return  string
     */
    public function salt(string $name, string $value): string {
        // Require a valid salt
        if (!$this->cookie_salt) {
            throw new \InvalidArgumentException(
                'A valid cookie salt is required. Please set Cookie::$salt before calling this method.' .
                'For more information check the documentation'
            );
        }

        // Determine the user agent
        $agent = isset($_SERVER['HTTP_USER_AGENT']) ? strtolower($_SERVER['HTTP_USER_AGENT']) : 'unknown';

        return sha1($agent . $name . $value . $this->cookie_salt);
    }
}