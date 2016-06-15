<?php

class BasicTest extends PHPUnit_Framework_TestCase
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

    public function testLoadCurler()
    {
        $c = new EduID\Curler();

        $this->assertInstanceOf("EduID\Curler", $c, "Curler Failed");
    }

    public function testVerifyFederationService() {
        // federation service
        $stack = (object)[];

        $fedService = "http://192.168.56.102/eduid/eduid.php";

        $stack->curl = new EduID\Curler($fedService);
        $stack->curl->get();

        // forbid direct calls.
        $this->assertEquals(400,
                            $stack->curl->getStatus(),
                            'federation service endpoint core error');
        return $stack;
    }

    /**
     * @depends testVerifyFederationService
     */
    public function testVerifyBadEndpoint($stack) {
        $stack->curl->setPathInfo("funny-endpoint");
        $stack->curl->get();

        // expect NOT IMPLMENETED Error
        $this->assertEquals(501,
                            $stack->curl->getStatus(),
                            'federation service endpoint core error');
        return $stack;
    }

    /**
     * @depends testVerifyFederationService
     */
    public function testClientCrendentialsAuth($stack) {
        $clientToken = '{"access_key":"ErwxcbK9K7XSaQX.C3rNiniV0Gb_Ijzf.VBAWd.xamfa33.wWQ","kid":"944hO3PQuo","mac_algorithm":"HS256","mac_key":"ZTupCWRq-hL-uS-mKqHx7Fr-N6TsKdF_X1QqLxEBDisY1i0AzGZveflSa5aK8GcUySaaeFBHPVxF4x8s5WN92YR2SSBQpE_NnANr","token_type":"Bearer","client_id":"ch.eduid.cli-test.1","issued_at":1465936363}';

        $stack->clientToken = json_decode($clientToken, true);
        $stack->clientId    = $this->generateUuid();

        $stack->curl->setMacToken($stack->clientToken);
        $stack->curl->setPathInfo("token");
        $stack->curl->useJwtToken(array(
            "subject" => $stack->clientId,
            "name"    => gethostname()
        ));

        $data = array("grant_type" => "client_credentials");

        $stack->curl->post(json_encode($data), "application/json");

        $this->assertEquals(200,
                            $stack->curl->getStatus(),
                            'federation service rejected client token');

        $this->assertNotEmpty($stack->curl->getBody(), 'Body is Empty!');


        $stack->instanceToken = json_decode($stack->curl->getBody(), true);
        $stack->curl->useMacToken();
        $stack->curl->setMacToken($stack->instanceToken);
        return $stack;
    }

    /**
     * @depends testClientCrendentialsAuth
     */
    public function testPasswordAuth($stack) {
        $stack->adminUser = (object)array(
            "username" => 'cli-admin@eduid.ch',
            "password" => 'test123'
        );

        $data = array("grant_type" => "password",
                      "username"   => $stack->adminUser->username,
                      "password"   => $stack->adminUser->password);

        $stack->curl->post(json_encode($data), "application/json");

        $this->assertEquals(200,
                            $stack->curl->getStatus(),
                            'federation service rejected credentials');

        $this->assertNotEmpty($stack->curl->getBody(), 'Body is Empty!');

        $stack->adminToken = json_decode($stack->curl->getBody(), true);

        $stack->curl->setMacToken($stack->adminToken);
        return $stack;
    }

    /**
     * @depends testPasswordAuth
     */
    public function testUserProfile($stack) {

        $stack->curl->setPathInfo("user-profile");
        $stack->curl->get();

        $this->assertEquals(200,
                            $stack->curl->getStatus(),
                            'federation service rejected user token');

        $this->assertNotEmpty($stack->curl->getBody(), 'Body is Empty!');

        $ud = json_decode($stack->curl->getBody(), true);

        $this->assertEquals($ud[0]["extra"]["name"],
                            "cli admin",
                            "wrong user profile returned");
        return $stack;
    }

    /**
     * @depends testPasswordAuth
     */
    public function testChangePassword($stack) {
        $stack->curl->setPathInfo("user-profile");

        $new_password = "test0987";

        $data = array(
            "oldpassword" => $stack->adminUser->password,
            "newpassword" => $new_password
        );

        $stack->curl->post(json_encode($data), "application/json");
        $this->assertEquals(204,
                            $stack->curl->getStatus(),
                            'federation service rejected password update');
        $this->assertEmpty($stack->curl->getBody(), 'Body is Empty!');

        // change back
        $data = array(
            "oldpassword" => $new_password,
            "newpassword" => $stack->adminUser->password
        );

        $stack->curl->post(json_encode($data), "application/json");
        $this->assertEquals(204,
                            $stack->curl->getStatus(),
                            'federation service rejected password update');
        $this->assertEmpty($stack->curl->getBody(), 'Body is not Empty!');
        return $stack;
    }

    /**
     * @depends testPasswordAuth
     */
    public function testAddUser($stack) {
        $user = array(
            "mailaddress" => "test.user@eduid.ch",
            "given_name"=> "Test",
            "family_name"=> "EduID-User",
            "name"=> "Test EduID-User",
            "user_password" => "helloworld"
        );
        $stack->curl->setPathInfo("user-profile/federation");

        $stack->curl->put(json_encode($user), 'application/json');

        $this->assertEquals(204,
                            $stack->curl->getStatus(),
                            'federation service rejected password update');
        $this->assertEmpty($stack->curl->getBody(), 'Body is Empty!');

        // login as user
        $stack->curl->setMacToken($stack->instanceToken);
        $stack->curl->setPathInfo("token");

        $data = array("grant_type" => "password",
                      "username"   => $user["mailaddress"],
                      "password"   => $user["user_password"]);

        $stack->curl->post(json_encode($data), "application/json");
        $this->assertEquals(200,
                            $stack->curl->getStatus(),
                            'federation service rejected credentials');

        $stack->basicUser = $user;

        $this->assertNotEmpty($stack->curl->getBody(), 'Body is Empty!');

        $stack->basicToken = json_decode($stack->curl->getBody(), true);
        return $stack;
    }

    /**
     * @depends testAddUser
     */
    public function testAddUserNonAdmin($stack) {
        $user = array(
            "mailaddress"   => "test.user2@eduid.ch",
            "given_name"    => "Test2",
            "family_name"   => "EduID-User",
            "name"          => "Test2 EduID-User",
            "user_password" => "helloworld"
        );

        $stack->curl->setMacToken($stack->basicToken);

        $stack->curl->setPathInfo("user-profile/federation");

        $stack->curl->put(json_encode($user), 'application/json');

        $this->assertEquals(403,
                            $stack->curl->getStatus(),
                            'federation service did not propertly reject the new uesr '. $stack->curl->getStatus());
        return $stack;
    }

    /**
     * @depends testAddUser
     */
    public function testGetAllClients($stack) {
        $stack->curl->setMacToken($stack->adminToken);
        $stack->curl->setPathInfo("client");
        $stack->curl->get();

        $this->assertEquals(200,
                            $stack->curl->getStatus(),
                            'federation service rejected credentials');

        $this->assertNotEmpty($stack->curl->getBody(), 'Body is Empty!');

        $cli = json_decode($stack->curl->getBody(), true);

        return $stack;
    }

    /**
     * @depends testAddUser
     */
    public function testAddClient($stack) {
         $client = array(
            "client_id" => "ch.eduid.test-cli",
            "info" => array("os"=> "test")
        );
        $stack->curl->setMacToken($stack->adminToken);
        $stack->curl->setPathInfo("client");
        $stack->curl->put(json_encode($client), "application/json");

        $this->assertEquals(200,
                            $stack->curl->getStatus(),
                            'federation service rejected credentials');

        $this->assertNotEmpty($stack->curl->getBody(), 'Body is Empty!');

        $tst = json_decode($stack->curl->getBody(), true);
        $this->assertNotEmpty($tst, "JSON data is empty");

        $this->assertArrayHasKey("client_uuid",
                                 $tst,
                                 "client_uuid is missing");
        $this->assertArrayHasKey("client_id",
                                 $tst,
                                 "client_id is missing");
        $this->assertNotEmpty($tst["client_uuid"],
                              "client_uuid is empty");
        $this->assertEquals($client["client_id"],
                            $tst["client_id"],
                            "mismatching in and output");

        $stack->tstCliId = $tst["client_uuid"];
        return $stack;
    }
    /**
     * @depends testAddUser
     */
    public function testAddClientUser($stack) {
         $client = array(
            "client_id" => "ch.eduid.test-cli",
            "info" => array("os"=> "test")
        );
        $stack->curl->setMacToken($stack->basicToken);
        $stack->curl->setPathInfo("client");
        $stack->curl->put(json_encode($client), "application/json");

        $this->assertEquals(403,
                            $stack->curl->getStatus(),
                            'federation service did not respond correctly' .
                            $stack->curl->getStatus());
        return $stack;
    }


    /**
     * @depends testAddClient
     */
    public function testGrantClientAdmin($stack) {

        $data = array("user_mail" => $stack->basicUser["mailaddress"]);
        $stack->curl->setMacToken($stack->adminToken);

        $stack->curl->setPathInfo("client/user/".$this->tstCliId);
        $stack->curl->put(json_encode($data), "application/json");
        $this->assertEquals(204,
                            $stack->curl->getStatus(),
                            'federation service did not respond correctly' .
                            $stack->curl->getStatus());

        $this->assertEmpty($stack->curl->getBody(), 'Body is not Empty!');
        return $stack;
    }

    /**
     * @depends testGrantClientAdmin
     */
    public function testGetUserClients($stack) {
        $stack->curl->setMacToken($stack->basicToken);
        $stack->curl->setPathInfo("client");

        $stack->curl->get();

        $this->assertEquals(200,
                            $stack->curl->getStatus(),
                            'federation service rejected credentials');

        $this->assertNotEmpty($stack->curl->getBody(), 'Body is Empty!');

       // $cli = json_decode($stack->curl->getBody(), true);
        return $stack;
    }

    /**
     * @depends testRevokeClientAdmin
     */
    public function testGrantClientUser($stack) {
        // we need a special user that!
        $data = array("user_mail" => $stack->basicUser["mailaddress"]);
        $stack->curl->setMacToken($stack->basicToken);

        $stack->curl->setPathInfo("client/user/".$stack->tstCliId);
        $stack->curl->put(json_encode($data), "application/json");
        $this->assertEquals(403,
                            $stack->curl->getStatus(),
                            'federation service did not respond correctly' .
                            $stack->curl->getStatus());

        $this->assertEmpty($stack->curl->getBody(), 'Body is not Empty!');
        return $stack;
    }

    /**
     * @depends testRevokeClientAdmin
     */
    public function testRevokeClientUser($stack) {
        // we need a special user that!
        $data = array("user_mail" => $stack->basicUser["mailaddress"]);

        $stack->curl->setMacToken($stack->basicToken);
        $stack->curl->setPathInfo("client/user/" . $stack->tstCliId);

        $stack->curl->put(json_encode($data), "application/json");
        $this->assertEquals(403,
                            $stack->curl->getStatus(),
                            'federation service did not respond correctly');

        $this->assertEmpty($stack->curl->getBody(), 'Body is not Empty!');
        return $stack;
    }



    /**
     * @depends testAddClientVersionUser
     */
    public function testRevokeClientAdmin($stack) {
        $data = array("user_mail" => $stack->basicUser["mailaddress"]);

        $stack->curl->setMacToken($stack->adminToken);
        $stack->curl->setPathInfo("client/user/".$stack->tstCliId);

        $stack->curl->put(json_encode($data), "application/json");
        $this->assertEquals(204,
                            $stack->curl->getStatus(),
                            'federation service did not respond correctly');

        $this->assertEmpty($stack->curl->getBody(), 'Body is not Empty!');
        $this->assertEquals(1,0, 'break here!');
        return $stack;
    }


//    public function testGetClientVersions($stack) {}
//    public function testAddClientVersionUser($stack) {}
//    public function testAddClientVersionAdmin($stack) {}
//
//    public function testGetFederationUsers($stack) {}
//    public function testMakeFederationUser($stack) {}
//    public function testRevokeFederationUser($stack) {}

    // helpers
    private function generateUuid() {
        return sprintf( '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff ),
            mt_rand( 0, 0xffff ),
            mt_rand( 0, 0x0fff ) | 0x4000,
            mt_rand( 0, 0x3fff ) | 0x8000,
            mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff )
        );
    }
}

?>