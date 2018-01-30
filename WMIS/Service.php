<?php

namespace Interactionez\WMIS;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;

use Slim\App as SlimApp;

class Service {
    private static $app;
    private static $endpoints = [];
    private static $filters = [];
    private static $options;

    public static function initialize($options) {
        self::$options = $options;
        self::$app = new SlimApp();
        self::$app->add(self::class . "::authorize");

        foreach($options["topics"] as $name => $topic) {
            if (array_key_exists("endpoint", $options)) {
                $endpoint = $topic["endpoint"];
            } else {
                $endpoint = str_replace(".", "/", strtolower($name));
            }

            array_push(self::$endpoints, "/api/$endpoint");
            self::$app->post("/api/$endpoint", self::class . "::receive");
        }

        self::registerAuthorizationFilter(self::class . "::authorizeUserAgent");

        // TODO: option - activate after hooks are set
        // self::registerAuthorizationFilter(self::class . "::authorizeWebhookSource");
        // self::registerAuthorizationFilter(self::class . "::authorizeWebhookTopic");
    }

    public static function authorize(ServerRequestInterface $request, ResponseInterface $response, callable $next) {
        file_put_contents("php://stdout", "Authorization Start\n");

        $headers = $request->getHeaders();

        if (!self::applyAuthorizationFilters($request)) {
            return $response->withStatus(401);
            file_put_contents("php://stdout", "Authorization End (Failed)\n");
        }

        file_put_contents("php://stdout", "Authorization End (Success)\n");
        return $next($request, $response);
    }

    private static function authorizeUserAgent(ServerRequestInterface $request) {
        file_put_contents("php://stdout", "Authorizing UserAgent\n");
        $header = $request->getHeader("HTTP_USER_AGENT");
        if (count($header) !== 1) {
            return false;
        }

        $pattern = "/WooCommerce\/[0-9]+\.[0-9]+\.[0-9]+ Hookshot \(WordPress\/[0-9]+\.[0-9]+\.[0-9]+\)/";
        return preg_match($pattern, $header[0]) == true;
    }

    private static function authorizeWebhookSource(ServerRequestInterface $request) {
        file_put_contents("php://stdout", "Authorizing WebhookSource\n");
        $source = $request->getHeader("HTTP_X_WC_WEBHOOK_SOURCE");
        if (count($source) !== 1) {
            return false;
        }

        return self::$options["source"] == $source[0];
    }

    private static function authorizeWebhookTopic(ServerRequestInterface $request) {
        file_put_contents("php://stdout", "Authorizing WebhookTopic\n");
        $header = $request->getHeader("HTTP_X_WC_WEBHOOK_TOPIC");
        if (count($header) !== 1) {
            return false;
        }

        foreach (self::$options["topics"] as $name => $topic) {
            if ($header[0] == $name) {
                return true;
            }
        }
        return false;
    }

    public static function registerAuthorizationFilter($filter) {
        array_push(self::$filters, $filter);
    }

    private static function applyAuthorizationFilters(ServerRequestInterface $request) {
        file_put_contents("php://stdout", "ApplyAuthorizationFilters\n");

        $result = true;
        foreach (self::$filters as $fkey => $filter) {
            if(is_callable($filter)) {
                $return = call_user_func($filter, $request);
                $result &= $return == true;
            }
        }

        file_put_contents("php://stdout", "ApplyAuthorizationFilters Result: $result\n");
        return $result;
    }

    private static function applyMapping($topic, $data) {
        file_put_contents("php://stdout", "ApplyMapping for " . $topic . "\n");
        $result = call_user_func(self::$options["topics"][$topic]["map"], $data);

        return $result;
    }

    public static function receive(ServerRequestInterface $request, ResponseInterface $response, $args) {
        file_put_contents("php://stdout", "Received data from source\n");

        $topic = $request->getHeader("HTTP_X_WC_WEBHOOK_TOPIC")[0];
        $data = json_decode($request->getBody()->getContents());

        $result = self::applyMapping($topic, $data);
        self::Send($topic, $result);

        return $response->withStatus(200);
    }   

    private static function send($topic, $data) {
        file_put_contents("php://stdout", "Sending data to destinations\n");
    }

    public static function start() {
        self::$app->run();
    }
};
