<?php

class AssertionTest extends PHPUnit_Framework_TestCase
{

    private $clientId;

    private $clientToken;
    private $adminUser;
    private $basicUser;

    private $basicToken;
    private $adminToken;
    private $instanceToken;
    private $tstToken;

    private $tstCliId;

    private $curl;


    public function testInitStack() {
        $stack = (object)[];

        $stack->serviceUri = "http://192.168.56.102/eduid/eduid.php";

        $clientToken = '{"access_key":"N8JHVh4mPhdH06GEstFZPHlaKrA4kBpX.Zwk7pxKw.kwvR1Lay","kid":"AP6LPG3h_c","mac_algorithm":"HS256","mac_key":"IP-9Dmj__FbX7lir3043xvGt8n-1ur437U2_6b1wG3iCdqV7qPZLax69IVZ3cTW9BOzyPqUni2RmiBiyh8aiu6g4R6WUOHSgnhD2","token_type":"Bearer","seq_nr":0,"client_id":"ch.eduid.cli-test.1","extra":{"os":"cli"},"issued_at":1465988226}';

        $stack->clientToken = json_decode($clientToken, true);
        $stack->clientId    = $this->generateUuid();

        // users:
        // preinstalled
        $stack->adminUser = (object)array(
            "username" => 'cli-admin@eduid.ch',
            "password" => 'test123'
        );

        // created
        $stack->basicUser = array(
            "mailaddress" => "test.user@eduid.ch",
            "given_name"=> "Test",
            "family_name"=> "EduID-User",
            "name"=> "Test EduID-User",
            "user_password" => "helloworld"
        );

        // created
        $stack->otherUser = array(
            "mailaddress" => "test.user-b@eduid.ch",
            "given_name"=> "Test B",
            "family_name"=> "EduID-User",
            "name"=> "Test B EduID-User",
            "user_password" => "helloworld"
        );

        // MUST NOT be created
        $stack->noUser = array(
            "mailaddress"   => "test.user2@eduid.ch",
            "given_name"    => "Test2",
            "family_name"   => "EduID-User",
            "name"          => "Test2 EduID-User",
            "user_password" => "helloworld"
        );

        // created
        $stack->client = array(
            "client_id" => "ch.eduid.test-cli",
            "info" => array("os"=> "test")
        );

        // MUST NOT be created
        $stack->noClient = array(
            "client_id" => "ch.eduid.test-nocli",
            "info" => array("os"=> "failed test")
        );

        return $stack;
    }
}
?>