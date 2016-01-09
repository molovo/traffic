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

    /**
     * The current router instance.
     *
     * @var Router|null
     */
    private static $router = null;

    /**
     * The route cache.
     *
     * @var Route[]
     */
    private static $routes = [];

    /**
     * The current route.
     *
     * @var Route|null
     */
    public static $current = null;

    /**
     * Allow for static access to HTTP Methods.
     *
     * @param string $method The method name
     * @param array  $args   The function arguments
     *
     * @return mixed The route is compiled
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

        return static::$router->compile($method, $route, $closure);
    }

    /**
     * Allow for chained access to HTTP Methods.
     *
     * @param string $method The method name
     * @param array  $args   The function arguments
     *
     * @return mixed The route is compiled
     */
    public function __call($method, $args)
    {
        $method                = strtolower($method);
        list($route, $closure) = $args;

        if (!in_array($method, static::$allowed)) {
            throw new InvalidMethodException($method.' is not a valid method name.');
        }

        return $this->compile($method, $route, $closure);
    }

    /**
     * Get the current request method.
     *
     * @return string The request method
     */
    public static function requestMethod()
    {
        $method = filter_input(
            INPUT_SERVER,
            'REQUEST_METHOD',
            FILTER_SANITIZE_FULL_SPECIAL_CHARS
        );

        return strtolower($method);
    }

    /**
     * Compile a route.
     *
     * @param string  $method   The HTTP method
     * @param string  $route    The route string to match
     * @param Closure $callback The callback to run should the route match
     *
     * @return Route The compiled route object
     */
    public function compile($method, $route, Closure $callback)
    {
        $name  = $route;
        if (is_array($route)) {
            list($route, $name) = $route;
        }

        // Regex for matching strings and placeholders separately
        $regex = '#(?:\/?'
                .'(?:\{(['.self::VALID_URL_CHARS.']+)\}?)'
                .'|(?:(['.self::VALID_URL_CHARS.']+)'
                .')\/?)#i';

        // Separate strings and placeholders into arrays
        preg_match_all($regex, $route, $matches);
        list(, $placeholders, $strings) = $matches;

        // Create and cache the Route object
        return static::$routes[$name] = new Route($method, $name, $route, $callback, $placeholders, $strings);
    }

    /**
     * Execute all routes.
     */
    public static function execute()
    {
        foreach (static::$routes as $route) {
            $route->execute();
        }
    }

    /**
     * Return a compiled route by name.
     *
     * @param string $name The route name
     *
     * @return Route The compiled route
     */
    public static function route($name)
    {
        if (isset(static::$routes[$name])) {
            return static::$routes[$name];
        }
    }

    /**
     * Return the current route.
     *
     * @return Route The current route
     */
    public static function current()
    {
        return static::$current;
    }
}
