<?php

class Router
{
    private $basePath, $routes = [], $errorRoute;

    private $matchTypes = array(
        'i' => '[0-9]++',
        'a' => '[0-9A-Za-z]++'
    );

    public function __construct()
    {
        $this->basePath = dirname($_SERVER['PHP_SELF']);
    }

    public function route($method, $route, $target)
    {
        $this->routes[] = array($method, $route, $target);
        return;
    }

    public function routeError($callback) {
        $this->errorRoute = $callback;
    }

    private function match()
    {
        $params = array();
        $requestUrl = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '/';
        $requestUrl = substr($requestUrl, strlen($this->basePath));

        if (($strpos = strpos($requestUrl, '?')) !== false) {
            $requestUrl = substr($requestUrl, 0, $strpos);
        }

        $requestMethod = isset($_SERVER['REQUEST_METHOD']) ? $_SERVER['REQUEST_METHOD'] : 'GET';

        foreach ($this->routes as $handler) {

            list($methods, $route, $target) = $handler;

            $method_match = (stripos($methods, $requestMethod) !== false);

            if (!$method_match) continue;

            if ($route === '*') {
                $match = true;
            } elseif (isset($route[0]) && $route[0] === '@') {
                $pattern = '`' . substr($route, 1) . '`u';
                $match = preg_match($pattern, $requestUrl, $params) === 1;
            } elseif (($position = strpos($route, '[')) === false) {
                $match = strcmp($requestUrl, $route) === 0;
            } else {
                if (strncmp($requestUrl, $route, $position) !== 0) {
                    continue;
                }
                $regex = $this->compileRoute($route);
                $match = preg_match($regex, $requestUrl, $params) === 1;
            }

            if ($match) {
                if ($params) {
                    foreach ($params as $key => $value) {
                        if (is_numeric($key)) unset($params[$key]);
                    }
                }
                return array(
                    'target' => $target,
                    'params' => $params
                );
            }
        }
        return false;
    }

    private function compileRoute($route)
    {
        if (preg_match_all('`(/|\.|)\[([^:\]]*+)(?::([^:\]]*+))?\](\?|)`', $route, $matches, PREG_SET_ORDER)) {

            $matchTypes = $this->matchTypes;

            foreach ($matches as $match) {

                list($block, $pre, $type, $param, $optional) = $match;

                if (isset($matchTypes[$type])) {
                    $type = $matchTypes[$type];
                }

                if ($pre === '.') {
                    $pre = '\.';
                }

                $optional = $optional !== '' ? '?' : null;

                $pattern = '(?:'
                    . ($pre !== '' ? $pre : null)
                    . '('
                    . ($param !== '' ? "?P<$param>" : null)
                    . $type
                    . ')'
                    . $optional
                    . ')'
                    . $optional;

                $route = str_replace($block, $pattern, $route);
            }
        }
        return "`^$route$`u";
    }

    public function run()
    {
        $match = $this->match();

        ($match && is_callable($match['target']))
            ? exit(call_user_func_array($match['target'], $match['params']))
            : exit(call_user_func($this->errorRoute));
    }
}