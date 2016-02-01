<?php

namespace Molovo\Traffic;

use Closure;

class Route
{
    /**
     * The request method this route will be executed on.
     *
     * @var string|null
     */
    public $method = null;

    /**
     * The name given to this route.
     *
     * @var string|null
     */
    public $name = null;

    /**
     * The route string which this Route object matches.
     *
     * @var string|null
     */
    public $route = null;

    /**
     * The callback applied when this route is executed.
     *
     * @var Closure|null
     */
    public $callback = null;

    /**
     * An array of placeholders in the route string.
     *
     * @var string[]
     */
    public $placeholders = null;

    /**
     * An array of strings in the route string.
     *
     * @var string[]
     */
    public $strings = null;

    /**
     * Create the route object.
     *
     * @param string  $method
     * @param string  $name
     * @param string  $route
     * @param Closure $callback
     * @param array   $placeholders
     * @param array   $strings
     */
    public function __construct($method, $name, $route, Closure $callback, array $placeholders = [], array $strings = [])
    {
        $this->method       = $method;
        $this->name         = $name;
        $this->route        = $route;
        $this->callback     = $callback;
        $this->placeholders = $placeholders;
        $this->strings      = $strings;
    }

    /**
     * Get the uri for this route.
     *
     * @param array $args An array of arguments to replace placeholders in the
     *                    compiled route string.
     *
     * @return string The uri
     */
    public function uri(array $args = [])
    {
        if ($this->route === '/') {
            return '/';
        }

        $compiled = [];

        $route = $this->route;

        // Strip any leading or trailing slashes
        $route = strpos($route, '/') === 0
             ? substr($route, 1)
             : $route;
        $route = substr($route, -1, 1) === '/'
             ? substr($route, 0, -1)
             : $route;
        $route = explode('/', $route);

        foreach ($route as $bit) {
            if ($placeholder = $this->getPlaceholder($bit)) {
                $compiled[] = isset($args[$placeholder])
                            ? $args[$placeholder]
                            : null;
                continue;
            }

            $compiled[] = $bit;
        }

        return '/'.implode('/', $compiled);
    }

    /**
     * Check if the current request uri matches this route.
     *
     * @return array|false Vars to be passed to the callback
     */
    public function match()
    {
        // Get the current uri
        $uri = $_SERVER['REQUEST_URI'];

        // We only care about the uri path, not query strings etc.
        $uri = parse_url($uri, PHP_URL_PATH);

        // Create an empty array to store return vars in
        $vars = [];

        // Sanitize multiple slashes
        $uri = preg_replace('/\/+/', '/', $uri);

        // Remove leading and trailing slashes
        if ($uri !== '/') {
            $uri = strpos($uri, '/') === 0
                 ? substr($uri, 1)
                 : $uri;
            $uri = substr($uri, strlen($uri), 1) === '/'
                 ? substr($uri, 0, -1)
                 : $uri;
        }

        $route = $this->route;

        if ($uri === $route) {
            return $vars;
        }

        $uri = explode('/', $uri);

        if (count($uri) > count($this->strings)) {
            return false;
        }

        // Loop through each of the exploded uri parts
        foreach ($this->strings as $pos => $string) {
            $bit = isset($uri[$pos]) ? $uri[$pos] : '';

            if (!isset($this->strings[$pos]) || !isset($this->placeholders[$pos])) {
                return false;
            }

            // Check if the current position is a string, and if the uri matches
            if (isset($this->strings[$pos]) && $this->strings[$pos] !== '') {
                if ($bit !== $this->strings[$pos]) {
                    return false;
                }

                continue;
            }

            // Check if the current position is a var, and if the uri matches
            if (isset($this->placeholders[$pos]) && $this->placeholders[$pos] !== '') {
                if (!$this->matchVar($bit, $this->placeholders[$pos])) {
                    return false;
                }

                $vars[] = $bit;

                continue;
            }
        }

        Router::$current = $this;

        return $vars;
    }

    /**
     * Check if the route matches the current request, and if so
     * execute its callback.
     *
     * @return mixed The route callback is invoked
     */
    public function execute()
    {
        if ($this->method === Router::ANY) {
            return $this->executeAny();
        }

        if (Router::requestMethod() === $this->method && is_array(($vars = $this->match()))) {
            $ref = new \ReflectionFunction($this->callback);

            return $ref->invokeArgs($vars);
        }

        return;
    }

    /**
     * Check if the route matches the current request on ANY method,
     * and if so execute its callback.
     *
     * @return mixed The route callback is invoked
     */
    private function executeAny()
    {
        if (in_array(Router::requestMethod(), Router::allowed()) && is_array(($vars = $this->match()))) {
            $ref = new \ReflectionFunction($this->callback);

            return $ref->invokeArgs($vars);
        }

        return;
    }

    /**
     * Match individual variables, and validate against their defined type.
     *
     * @param string $bit The value to match
     * @param string $var The variable name (and type) to compare to
     *
     * @return bool
     */
    private function matchVar($bit, $var)
    {
        if (!strstr($var, ':')) {
            return true;
        }

        list($name, $type) = explode(':', $var, 2);

        if ($bit) {
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
        }

        if (substr($name, -1) === '?') {
            return true;
        }

        return false;
    }

    /**
     * Retrieve the placeholder name from a section of a URL.
     *
     * @param string $bit The URL section to parse
     *
     * @return string The placeholder name
     */
    private function getPlaceholder($bit)
    {
        if (preg_match_all('/\{(.+)\}/i', $bit, $matches)) {
            $matches = $matches[1];

            $placeholder = $matches[0];
            if (strstr($placeholder, ':')) {
                $placeholder = explode(':', $placeholder);
                $placeholder = $placeholder[0];
            }

            return $placeholder;
        }

        return;
    }
}
