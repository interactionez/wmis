<?php

namespace Interactionez\WMIS\Authentication;

use Psr\Http\Message\RequestInterface;

class JWT implements AuthenticationProviderInterface {
	
	private $file = ".jwt.token.json";
	
	/**
	 * @var \Interactionez\WMIS\Authentication\JWTAuthenticationInfo
	 */
	private $authenticationInfo;
	
	/**
	 * @var string
	 */
	private $token;
	
	public function __construct(JWTAuthenticationInfo $authenticationInfo) {
		$this->authenticationInfo = $authenticationInfo;
	}
	
	public function validateExistingToken() {
		if (file_exists($this->file)) {
			$data = file_get_contents($this->file);
			$data = json_decode($data);
			
			$now = time();
			$expiresOn = strtotime($data->expiresOn->date);
			
			if ($now - $expiresOn <= 0) {
				$this->token = $data->access_token;
				return true;
			}
		}
		
		return false;
	}
	
	public function authenticate() {
		if ($this->validateExistingToken()) {
			return true;
		}
		
		$url         = $this->authenticationInfo->endpoint;
		$data_string = "Username={$this->authenticationInfo->username}&Password={$this->authenticationInfo->password}&Organization={$this->authenticationInfo->organization}";
		
		$options = [
			CURLOPT_CUSTOMREQUEST => "POST",
			CURLOPT_RETURNTRANSFER => true,   // return web page
			CURLOPT_HEADER => false,  // don't return headers
			CURLOPT_POSTFIELDS => $data_string,
			CURLOPT_CONNECTTIMEOUT => 120,    // time-out on connect
			CURLOPT_TIMEOUT => 120,    // time-out on response
		];
		
		$ch = curl_init($url);
		curl_setopt_array($ch, $options);
		
		$responseData = curl_exec($ch);
		$responseCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		
		curl_close($ch);
		
		if($responseCode === 200) {
			$response = json_decode($responseData);
		} else {
			return false;
		}
		
		$f                   = fopen($this->file, "w+");
		$response->createdOn = new \DateTime("now");
		$response->expiresOn = new \DateTime("+" . $response->expires_in . " seconds");
		fwrite($f, json_encode($response));
		fclose($f);
		
		$this->token = $response->access_token;
		
		return true;
	}
	
	/**
	 * @param $headers
	 */
	public function handleRequest(&$headers) {
		$header = "Authorization: Bearer " . $this->token;
		array_push($headers, $header);
	}
}