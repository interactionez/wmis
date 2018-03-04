<?php

require "tests/bootstrap.php";

use Interactionez\WMIS\Authentication\JWT;
use Interactionez\WMIS\Authentication\JWTAuthenticationInfo;
use Interactionez\WMIS\Service;

$authenticationInfo               = new JWTAuthenticationInfo();
$authenticationInfo->username     = "godot";
$authenticationInfo->password     = "godot";
$authenticationInfo->organization = "GODOT-TEST-API";
$authenticationInfo->endpoint     = "https://api-godot.bizpulse.ro/api/Login";

$service = new Service([
	"source" => "http://localhost/",
	"destination" => [
		"url" => "https://api-godot.bizpulse.ro",
		"authentication" => new JWT($authenticationInfo)
	],
	"topics" => [
		"product.updated" => [
			"map" => function($object) {
				file_put_contents("php://stdout", "Mapping Object\n");
				
				return [
					"endpoint" => "/UpdateShow",
					"data" => [
						"ShowName" => $object->name,
						"ShowDescription" => $object->description
					]
				];
			}
		]
	]
]);
$service->start();
