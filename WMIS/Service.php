<?php
/**
 * WMIS (WooCommerce Middleware Integration Service)
 *
 * @package   WMIS
 * @author    Alexandru Ifrim <me@aifrimn.com>
 * @copyright 2017 Interacționez
 * @license   https://github.com/interactionez/wmis/blob/master/LICENSE (Apache License 2.0)
 * @link      https://github.com/interactionez/wmis Project repository
 */

namespace Interactionez\WMIS;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\App;

/**
 * The WMIS Service
 *
 * @package WMIS
 * @author  Alexandru Ifrim <me@aifrimn.com>
 */
class Service {
	/**
	 * @var \Slim\App
	 */
	private $app;
	
	/**
	 * @var array
	 */
	private $endpoints = [];
	
	/**
	 * @var array
	 */
	private $filters = [];
	
	/**
	 * @var
	 */
	private $options;
	
	public function __construct($options) {
		$this->options = $options;
		$this->app     = new App();
		$this->app->add([$this, "authorize"]);
		
		// todo: validate $options
		foreach($options["topics"] as $name => $topic) {
			if(array_key_exists("endpoint", $options)) {
				$endpoint = $topic["endpoint"];
			} else {
				$endpoint = str_replace(".", "/", strtolower($name));
			}
			
			array_push($this->endpoints, "/api/$endpoint");
			$this->app->post("/api/$endpoint", [$this, "receive"]);
		}
		
		$this->registerAuthorizationFilter([$this, "authorizeUserAgent"]);
		
		// TODO: option - activate after hooks are set
		// $this->registerAuthorizationFilter([$this, "authorizeWebhookSource"]);
		// $this->registerAuthorizationFilter([$this, "authorizeWebhookTopic"]);
	}
	
	public function authorize(ServerRequestInterface $request, ResponseInterface $response, callable $next) {
		$headers = $request->getHeaders();
		
		if(!$this->applyAuthorizationFilters($request)) {
			return $response->withStatus(401);
		}
		
		return $next($request, $response);
	}
	
	private function authorizeUserAgent(ServerRequestInterface $request) {
		$header = $request->getHeader("HTTP_USER_AGENT");
		
		if(count($header) !== 1) {
			return false;
		}
		
		$pattern = "/WooCommerce\/[0-9]+\.[0-9]+\.[0-9]+ Hookshot \(WordPress\/[0-9]+\.[0-9]+\.[0-9]+\)/";
		
		return preg_match($pattern, $header[0]) == true;
	}
	
	private function authorizeWebhookSource(ServerRequestInterface $request) {
		$source = $request->getHeader("HTTP_X_WC_WEBHOOK_SOURCE");
		
		if(count($source) !== 1) {
			return false;
		}
		
		return $this->options["source"] == $source[0];
	}
	
	private function authorizeWebhookTopic(ServerRequestInterface $request) {
		$header = $request->getHeader("HTTP_X_WC_WEBHOOK_TOPIC");
		
		if(count($header) !== 1) {
			return false;
		}
		
		foreach($this->options["topics"] as $name => $topic) {
			if($header[0] == $name) {
				return true;
			}
		}
		
		return false;
	}
	
	public function registerAuthorizationFilter(callable $filter) {
		array_push($this->filters, $filter);
	}
	
	private function applyAuthorizationFilters(ServerRequestInterface $request) {
		$result = true;
		
		foreach($this->filters as $key => $filter) {
			if(is_callable($filter)) {
				$return = call_user_func($filter, $request);
				$result &= $return == true;
			}
		}
		
		return $result;
	}
	
	/**
	 * @param $topic
	 * @param $data
	 *
	 * @return \Interactionez\WMIS\MappingResultInterface
	 */
	private function applyMapping($topic, $data) {
		$mappingResult = call_user_func($this->options["topics"][$topic]["map"], $data);
		
		return $mappingResult;
	}
	
	public function receive(ServerRequestInterface $request, ResponseInterface $response) {
		$topic = $request->getHeader("HTTP_X_WC_WEBHOOK_TOPIC")[0];
		$data  = json_decode($request->getBody()->getContents());
		
		$result = $this->applyMapping($topic, $data);
		
		if ($result !== null) {
			$this->send($result["endpoint"], $result["data"]);
		}
		
		return $response->withStatus(200);
	}
	
	private function send($endpoint, $data) {
		$this->options["destination"]["authentication"]->authenticate();
		
		$data_string = json_encode($data);
		$url         = $this->options["destination"]["url"] . $endpoint;
		
		$headers = [
			"Content-Type: application/json",
			"Accept: application/json",
			"Content-Length: " . strlen($data_string)
		];
		
		$this->options["destination"]["authentication"]->handleRequest($headers);
		
		$ch = curl_init($url);
		curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
		curl_setopt($ch, CURLOPT_POSTFIELDS, $data_string);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
		
		$result = curl_exec($ch);
		$responseCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

		curl_close($ch);
	}
	
	public function start() {
		$this->app->run();
	}
}

