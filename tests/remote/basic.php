<?php

set_include_path("../../lib" . PATH_SEPARATOR .
                get_include_path());

// load RESTling
include_once('RESTling/contrib/Restling.auto.php');
include_once('eduid.autoloader.php');

require('./class.curler.php');

$taddr = "192.168.0.72";
$tpath = "/eduid/eduid.php";
$tinfo = "token";

$c = new Curler(array("host" => $taddr, "path"=> $tpath));

$c->get();

echo ($c->getStatus() == 403 ? "OK": "FAILED ");
echo "\n";

$c->setPathInfo($tinfo);
$c->get();

echo ($c->getStatus() == 400 ? "OK": "FAILED");
echo "\n";

use Lcobucci\JWT as JWT;
use Lcobucci\JWT\Signer as Signer;

$jwt = new JWT\Builder();

$access_key = "acf5acfaa58665e6e74f9d03e504b7dce7bc9568";
$mac_key    = "helloWorld";
$kid        = '1234test-12';

$jwt->setIssuer('io.mobinaut.test');
$jwt->setAudience("http://192.168.0.72/eduid/eduid.php/token");

$jwt->setHeader("kid", $kid);
// $jwt->setId('123asd3414fafr23r');
        
$jwt->setSubject("phish@lo-f.at"); // use my email as device id 
$jwt->set("name", "phishs tester"); // use fake name

$cn = 'Lcobucci\JWT\Signer\Hmac\Sha256';
$signer = new $cn;
$jwt->sign($signer,
           $mac_key);

$t = $jwt->getToken();

$c->setHeader(array("Authorization"=> "Bearer $t"));

$d = array("grant_type" => "client_credentials");
$c->post(json_encode($d), "application/json");


echo ($c->getStatus() == 200 ? "OK": "FAILED");
echo "\n";

$clientToken = json_decode($c->getBody(), true);

$c->resetHeader();
$c->setMacToken($clientToken);
// log in the user

// user info
$userpw = "helloworld";

$data = array("grant_type"=> "password", "username"=> "christian.glahn@htwchur.ch", "password" => $userpw);

$jsonData = json_encode($data);
$c->post($jsonData, "application/json");

echo ($c->getStatus() == 200 ? "OK": "FAILED");
echo "\n";

$userToken = json_decode($c->getBody(), true);
$c->setMacToken($userToken); // now we are logged in

$d = array("grant_type" => "authorization_code", "redirect_uri" => "https://moodle.htwchur.ch", "code" => $userToken["access_token"], "client_id" => 'io.mobinaut.test');

$jsonData = json_encode($d);
$c->post($jsonData, "application/json");

echo ($c->getStatus() == 200 ? "OK": "FAILED");
echo "\n";
echo $c->getStatus() . "\n";

echo $c->getBody() . "\n";
$b = json_decode($c->getBody());
$a = explode('.', $b->access_token);
echo base64_decode($a[1]) . "\n";


?>