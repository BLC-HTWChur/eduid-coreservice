<?php
set_include_path("../lib" . PATH_SEPARATOR .
                get_include_path());

// load RESTling
include_once('RESTling/contrib/Restling.auto.php');
include_once('eduid.autoloader.php');

use Lcobucci\JWT as JWT;
use Lcobucci\JWT\Signer as Signer;

require('class.Curler.php');

$param = getopt("u:");
/**
 * -n - Servername
 * -u - serverurl
 */

// create a new config directory
$home = $_SERVER["HOME"];
$cfgdir = "$home/.eduid";

if (!file_exists($cfgdir)) {
    mkdir("$home/.eduid", 700);
}

$idHost = "http://192.168.0.72/eduid/eduid.php";
$tPath = "token";
$sPath = "federation";

$c = new Curler($idHost);

//if (!file_exists("$cfgdir/client.json")) {
//    register_client($c);
//}
//
//read_client_token($c);
//authenticate_user($c);


// service home
$servicehome = $param["u"];

// split the service home into proto/host/path
echo $servicehome . "\n";

$serviceInfo = verify_service($servicehome);
echo json_encode($serviceInfo) . "\n";

function verify_service($servicehome) {
    $s = new Curler($servicehome);

    $s->setPathInfo("service.txt");
    $s->get();

    if ($s->getStatus() == 200) {
        $lines = explode("\n" , $s->getBody());
        foreach ($lines as $line) {
            $line = trim($line);
            if (!empty($line)) {
                list($type, $path) = explode("; ", $line);

                if ($type == "application/x-rsd+json") {
                    $s->setPath($path);
                    $s->setPathInfo();

                    // get the rsd
                    $s->get();

                    if ($s->getStatus() == 200) {
                        $rsd = json_decode($s->getBody(),true);
                        $servicename = $rsd["engineName"];
                        $basepath    = rtrim($rsd["homePageLink"], "/");
                        $enginepath  = trim($rsd["engineLink"], "/");
                        $tokenservice = "token";

                        if (array_key_exists("org.ietf.oauth2", $rsd["apis"])) {
                            $apilink = trim($rsd["apis"]["org.ietf.oauth2"]["apiLink"], "/");

                            $tokenendpoint = $basepath;
                            if (!empty($enginepath)) {
                                $tokenendpoint .= "/$enginepath";
                            }
                            $tokenendpoint .= "/$apilink/$tokenservice";

                            // the serviceInfo is stored in the federation                
                            return array("service_uuid" => generate_uuid(),
                                                 "name" => $servicename,
                                                 "mainurl" => $servicehome,
                                                 "idurl" => $tokenendpoint,
                                                 "rsdurl" => $s->getLastUri());
                        }

                        break; // end for loop.
                    }
                    else {
                        echo "cannot get rsd\n";
                    }
                }
            }
        }
    }
    else {
        echo("I cannot determine service endpoints!\n");
    }
}

// try to identify the token endpoint

function authenticate_user($curl) {
    $username = readline("email");
    $password = readline("password");

    if (!empty($username) && !empty($password)) {
        $data = array("grant_type" => "password", 
                      "username"   => $username, 
                      "password"   => $password);

        $curl->post(json_encode($data), "application/json");
        
        if ($curl->getStatus() == 200) {
            $curl->setMacToken(json_decode($curl->getBody(), true));
        }
    }
}

function read_client_token($curl) {
    if (file_exists("$cfgdir/client.json")) {
        $file = fopen("$cfgdir/client.json","r");
        $clToken = fread($file,filesize("$cfgdir/client.json"));
        fclose($file);

        if ($clToken) {
            $curl->setMacToken(json_decode($clToken, true));        
        }
    }
}

function register_client($curl) {
    
    // CLIENT INFORMATION
    $appID = "ch.htwchur.eduid.cli";
    $appMacKey= "";
    $appKID = "";
    $appAccessKey = "";

    $client_id = generate_uuid();
    $client_name = gethostname();

    $jwt = new JWT\Builder();
    
    $jwt->setIssuer($appID);
    $jwt->setAudience("$idHost/$tPath");
    $jwt->setHeader("kid", $appKID);
    $jwt->setSubject($client_id);
    $jwt->set("name", $client_name);
    
    $cn = 'Lcobucci\JWT\Signer\Hmac\Sha256';
    $signer = new $cn;
    $jwt->sign($signer,
               $appMacKey);

    $t = $jwt->getToken();
    
    $curl->setHeader(array("Authorization"=> "Bearer $t"));
    $data = array("grant_type" => "client_credentials");
    $curl->post(json_encode($data), "application/json");
    
    if ($curl->getStatus() == 200) {
        // store the body in our config file
        $tokenfile = fopen("$cfgdir/client.json", "w");
        
        if ($tokenfile) {
            fwrite($tokenfile, $curl->getBody());
            fclose($tokenfile);
        }
    }
}

function generate_uuid() {
	return sprintf( '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
		mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff ),
		mt_rand( 0, 0xffff ),
		mt_rand( 0, 0x0fff ) | 0x4000,
		mt_rand( 0, 0x3fff ) | 0x8000,
		mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff )
	);
}

?>
