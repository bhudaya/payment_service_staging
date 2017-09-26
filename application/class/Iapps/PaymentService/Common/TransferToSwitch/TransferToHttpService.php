<?php

namespace Iapps\PaymentService\Common\TransferToSwitch;

class TransferToHttpService {

    private $url;
    private $username;
    private $password;
    private $auth ;

    

    public function post(array $headers, array $params ,$uri )
    {
        $serviceUrl = $this->url . $uri;
        $dataJson = json_encode($params);

        if (strpos($uri, 'confirm')  || strpos($uri, 'bank_account_number') ) {
            $dataJson = "{}";
        }

        // PREPARE THE CURL CALL
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL,            $serviceUrl);
        curl_setopt($curl, CURLOPT_HTTPHEADER,     $headers);
        curl_setopt($curl, CURLOPT_POST,           TRUE);
        curl_setopt($curl, CURLOPT_POSTFIELDS,     $dataJson);
        curl_setopt($curl, CURLOPT_USERPWD,        $this->getUsername() .":".$this->getPassword());
        curl_setopt($curl, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, TRUE);
        $output = curl_exec($curl);
        return $output;
    }


    public function get(array $headers, array $params ,$uri )
    {
        $serviceUrl = $this->url . $uri;
        $dataJson = json_encode($params);

        if (strpos($uri, 'confirm')  || strpos($uri, 'bank_account_number') ) {
            $dataJson = "{}";
        }
        // PREPARE THE CURL CALL
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL,            $serviceUrl);
        curl_setopt($curl, CURLOPT_HTTPHEADER,     $headers);
        curl_setopt($curl, CURLOPT_POST,            false);
        //curl_setopt($curl, CURLOPT_POSTFIELDS,     $dataJson);
        curl_setopt($curl, CURLOPT_USERPWD,        $this->getUsername() .":".$this->getPassword());
        curl_setopt($curl, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, TRUE);
        $output = curl_exec($curl);
        return $output;
    }



    
    public function getAuth()
    {
        return $this->auth;
    }    public function setAuth($auth)
    {
        $this->auth = $auth;
    }
    public function getUsername()
    {
        return $this->username;
    }
    public function setUsername($username)
    {
        $this->username = $username;
    }
    public function getPassword()
    {
        return $this->password;
    }
    public function setPassword($password)
    {
        $this->password = $password;
    }
    public function setUrl($url)
    {
        $this->url = $url;
    }
    public function getUrl($url)
    {
        return $this->url;
    }

}