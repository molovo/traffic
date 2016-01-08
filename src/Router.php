<?php

namespace Molovo\Traffic;

use Closure;
use Molovo\Traffic\Exceptions\InvalidMethodException;

class Router
{
    /**
     * HTTP Methods.
     */
    const GET     = 'get';
    const HEAD    = 'head';
    const POST    = 'post';
    const PUT     = 'put';
    const PATCH   = 'patch';
    const DELETE  = 'delete';
    const OPTIONS = 'options';
    const ANY     = 'any';

    const VALID_URL_CHARS = 'a-zA-Z0-9-_\.\~\:\?\#\[\]\@\!\$\&\'\(\)\*\+\,\;\=';

    /**
     * Allowed HTTP Methods.
     *
     * @var string[]
     */
    private static $allowed = [
        self::GET,
        self::HEAD,
        self::POST,
        self::PUT,
        self::PATCH,
        self::DELETE,
        self::OPTIONS,
        self::ANY,
    ];

    private static $router = null;

    /**
     * Allow for static access to HTTP Methods.
     *
     * @param string $method The method name
     * @param array  $args   The function arguments
     *
     * @return mixed The passed closure is invoked
     */
    public static function __callStatic($method, $args)
    {
        $method                = strtolower($method);
        list($route, $closure) = $args;

        if (!in_array($method, static::$allowed)) {
            throw new InvalidMethodException($method.' is not a valid method name.');
        }

        if (!static::$router) {
            static::$router = new static;
        }

        return static::$router->check($method, $route, $closure);
    }

    public function __call($method, $args)
    {
        $method                = strtolower($method);
        list($route, $closure) = $args;

        if (!in_array($method, static::$allowed)) {
            throw new InvalidMethodException($method.' is not a valid method name.');
        }

        return $this->check($method, $route, $closure);
    }

    public function check($method, $route, Closure $closure)
    {
        if ($method === self::ANY) {
            return $this->any($route, $closure);
        }

        if ($this->method() === $method && is_array(($vars = $this->match($route)))) {
            return call_user_func_array($closure, $vars);
        }

        return;
    }

    private function any($route, callable $closure)
    {
        if (in_array($this->method(), static::$allowed) && is_array(($vars = $this->match($route)))) {
            return call_user_func_array($closure, $vars);
        }

        return;
    }

    public function method()
    {
        $method = filter_input(
            INPUT_SERVER,
            'REQUEST_METHOD',
            FILTER_SANITIZE_FULL_SPECIAL_CHARS
        );

        return strtolower($method);
    }

    public function match($route)
    {
        // Get the current uri
        $uri = filter_input(
            INPUT_SERVER,
            'REQUEST_URI',
            FILTER_SANITIZE_FULL_SPECIAL_CHARS
        );

        // Create an empty array to store return vars in
        $vars = [];

        // Sanitize multiple slashes
        $uri = preg_replace('/\/+/', '/', $uri);

        // Remove leading and trailing slashes
        if ($uri !== '/') {
            $uri = strpos($uri, '/') === 0
                 ? substr($uri, 1)
                 : $uri;
            $uri = strpos($uri, '/') === strlen($uri) - 1
                 ? substr($uri, 0, -1)
                 : $uri;
        }

        if ($uri === $route) {
            return $vars;
        }

        // Explode uri
        $uri = explode('/', $uri);

        // Regex for matching strings and placeholders separately
        $regex = '#(?:\/?'
                .'(?:\{(['.self::VALID_URL_CHARS.']+)\}?)'
                .'|(?:(['.self::VALID_URL_CHARS.']+)'
                .')\/?)#i';

        // Separate strings and placeholders into arrays
        preg_match_all($regex, $route, $matches);
        list(, $placeholders, $strings) = $matches;

        if (sizeof($uri) !== sizeof($strings)) {
            return false;
        }

        // Loop through each of the exploded uri parts
        foreach ($uri as $pos => $bit) {
            if (!isset($strings[$pos]) || !isset($placeholders[$pos])) {
                return false;
            }

            // Check if the current position is a string, and if the uri matches
            if (isset($strings[$pos]) && $strings[$pos] !== '') {
                if ($bit !== $strings[$pos]) {
                    return false;
                }

                continue;
            }

            // Check if the current position is a var, and if the uri matches
            if (isset($placeholders[$pos]) && $placeholders[$pos] !== '') {
                if (!$this->matchVar($bit, $placeholders[$pos])) {
                    return false;
                }

                $vars[] = $bit;

                continue;
            }
        }

        return $vars;
    }

    private function matchVar($bit, $var)
    {
        if (!strstr($var, ':')) {
            return true;
        }

        list($name, $type) = explode(':', $var, 2);

        switch ($type) {
            case 'int':
                return ctype_digit($bit);
            case 'string':
                return is_string($bit);
            case 'email':
                return filter_var($bit, FILTER_VALIDATE_EMAIL);
            case 'ip':
                return filter_var($bit, FILTER_VALIDATE_IP);
        }

        return false;
    }
}
