<?php
namespace EduID\Client;
use EduID\Client as ClientBase;
use EduID\Curler;

class AppAssertion extends ClientBase {

    private $assertion;

    private $scurl;

    public function __construct() {
        $this->paramShort .= "t:a:";
        parent::__construct();
        // -I client info
        // -i client id (ch.edu.xxx)
        // -r release version

        if (!$this->authorize()) {
            $this->log(json_encode($this->user));
            $this->fatal("Client rejected");
        }
        $this->log(json_encode($this->user));
    }

    public function getAssertion() {
        $this->curl->setPathInfo("authorization");
        $this->curl->setToken($this->userToken);
        $this->curl->useJwtToken(["iss" => $this->client_id]);

        $data = [
            "request_type" => "code",
            "redirect_uri" => "https://mdl-tst.htwchur.ch",
            "client_id"    => $this->client_id
        ];

        $this->curl->post(json_encode($data), "application/json");

        if ($this->curl->getStatus() == 200) {
            $this->log($this->curl->getBody());

            $this->log($this->curl->getBody());
            $data  = json_decode($this->curl->getBody(), true);

            $code = $data["code"];

            $this->log($data["redirect_uri"]);
            $curl = new Curler($data["redirect_uri"]);

            //$curl->setPathInfo("token");

            $assertion = [
                "grant_type" => 'urn:ietf:param:oauth:grant-type:jwt-bearer',
                "assertion"  => $code
            ];

            $curl->post(json_encode($assertion), "application/json");
            if ($curl->getStatus() == 200) {
                $this->log("eduid asserion is fine ");
                $this->log($curl->getBody());

                $curl->setToken(json_decode($curl->getBody(), true));
                $curl->useJwtToken(["iss" => $this->client_id]);

                $this->scurl = $curl;

            }
        }
        else {
            $this->log($curl->getStatus());
        }
    }

    public function useAssertion($target) {
        // create JWT code for the target

        $data = [
            "grant_type" => 'authorization_code',
            "code"  => $code
        ];

        $this->scurl->post(json_encode($data), "application/json");

    }
}

?>