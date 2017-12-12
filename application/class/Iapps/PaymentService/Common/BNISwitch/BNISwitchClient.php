<?php

namespace Iapps\PaymentService\Common\BNISwitch;

use Iapps\Common\Core\IappsDateTime;
use Iapps\Common\Helper\HttpService;
use Iapps\Common\Helper\StringMasker;
use Iapps\PaymentService\PaymentRequest\PaymentRequestClientInterface;
use Iapps\PaymentService\Common\Logger;


class BNISwitchClient implements PaymentRequestClientInterface{
    protected $config = array();
    protected $_http_serv;
    protected $_option;

    protected $signed_data;
    protected $inquire_signed_data;
    protected $reference_no;
    protected $trans_date;
    protected $sender_fullname;
    protected $sender_firstname;
    protected $sender_lastname;
    protected $sender_middlename;
    protected $sender_address;
    protected $sender_address1;
    protected $sender_address2;
    protected $sender_phone;
    protected $receiver_fullname;
    protected $receiver_firstname;
    protected $receiver_lastname;
    protected $receiver_middlename;
    protected $receiver_address;
    protected $receiver_address1;
    protected $receiver_address2;
    protected $receiver_mobile_phone;
    protected $receiver_gender;
    protected $receiver_birth_date;
    protected $transaction_type;
    protected $payable_code;
    protected $bank_code;
    protected $branch_name;
    protected $account_no;
    protected $landed_currency;
    protected $landed_amount;
    protected $message_to_bene1;
    protected $message_to_bene2;

    protected $trans_date_bni;
    protected $reference_no_bni;
    protected $inst_name;
    protected $transactionID;
    protected $receiver_email;

    protected $_number_of_check_trx =0 ;
    protected $info ;
    protected $_transfer_hit_count = 0 ;
    protected $_inquiry_hit_count = 0 ;
    protected $_last_process;
    protected $_check_trx_info = array() ;
    protected $_last_rc ;  //the last response code
    protected $_transfer_response ;
    protected $response_fields ;
    protected $bni_info;
    protected $_inquiry_response ;
    protected $switcher_transaction_no;
    protected $_timeout_of_check_trx = 0;
    protected $_internal_reff_id  ;

    function __construct(array $config)
    {
        $this->config = $config;

        if( !$this->_getUserName() OR
            !$this->_getPassword() OR
            !$this->_getClientId() OR
            !$this->_getPrivateKeyFile() OR
            !$this->_getUrl())
            throw new \Exception('invalid switch configuration');

        $this->_http_serv = new BNIHttpService();
        $this->_http_serv->setUrl($this->_getUrl());
    }

    public function testBni($data){
        echo "data :" ;
        echo "<br><br>";
        print_r($data);
        $header = array
        (
            'Content-Type: text/xml',
        );
        echo "<br><br>";
        $response = $this->_http_serv->post($header, $data);
        print_r($response);
    }

    public function bankTransfer(){

        $this->getLastResponse(); //get from  db fields
        $this->setLastResponse(); //set to class fields

        if ($this->_last_rc == "PRC") {
            if ( ($this->_last_process == "check_transaction"  && $this->_number_of_check_trx <=2  &&  $this->_timeout_of_check_trx ==1)    ||   ($this->_last_process == "transfer" && $this->_number_of_check_trx == 0)) {

                $rslt = $this->checkTrx();
                if($this->_number_of_check_trx == 3) {
                    $response = $this->formatToFail("Received timeout when check transaction");
                    return new BNISwitchResponse($response, 'poInfoInquiry');  //
                }

                if ($rslt["resultCode"] == "0") {
                    return $rslt;
                } else {
                    if ($rslt["resultCode"] == "1") {
                        $response = $this->formatToFail("Transaction ID is not found");
                    } else {
                        $response = $this->formatToFail("Received timeout when check transaction");

                    }
                }
                return new BNISwitchResponse($response, "poInfoInquiry");
            }
        }

        if ($inquiryResponse = $this->inquiry()) {

            $this->_inquiry_response = json_decode($inquiryResponse->getFormattedResponse(),true) ;
            $this->_addInfo("inquiry_response",$this->_inquiry_response);

            //remove saluation
            $account_name = $this->remove_saluation($inquiryResponse->getDestAccHolder());
            $inquiryResponse->setDestAccHolder($account_name);

            if (!$inquiryResponse->isSuccess()) {
                // max 2 times in inquiry timeout
                if($inquiryResponse->getResponseCode() == "PRC"  &&  $this->_inquiry_hit_count >= 2 ){
                    //set to fail
                    $response = $this->formatToFail("Received timeout when inquiry process");
                    return new BNISwitchResponse($response,"accountInfoInquiry");
                }
                return $inquiryResponse;
            }
        }else{
            return false ;
        }

        if( $trfResponse = $this->transfer()) {
            $this->_transfer_response = json_decode($trfResponse->getFormattedResponse(),true) ;
            $this->_addInfo("transfer_response", $this->_transfer_response);

            if (!$trfResponse->isSuccess()) {
                if ($trfResponse->getResponseCode() == "PRC" ) {
                    //set to fail
                    if($this->_transfer_hit_count > 1){
                        $response = $this->formatToFail("Received timeout when transfer process");
                        return new BNISwitchResponse($response, "accountInfoInquiry");
                    }
                }
            }
            return $trfResponse;  // return timeout
        }else{
            return false;
        }
    }


    public function generateSignedData($type){

        $pkcs12 = file_get_contents($this->_getPrivateKeyFile());
        openssl_pkcs12_read( $pkcs12, $certs, "" );
        $private_key_pem =  $certs['pkey'] ;

        if($type == BNISwitchFunction::CODE_INQUIRY){
            $data       =  $this->_getClientId() . $this->getBankCode() . $this->getAccountNo() ;
            $data_sha1  = sha1($data);
            openssl_sign($data, $signature, $private_key_pem, OPENSSL_ALGO_SHA1);
            $encodedSignature = base64_encode($signature);
            return $encodedSignature ;

        }else if($type == BNISwitchFunction::CODE_INFO){
            $data       =  $this->_getClientId() . $this->getReferenceNoBni() ;
            openssl_sign($data, $signature, $private_key_pem, OPENSSL_ALGO_SHA1);
            $encodedSignature = base64_encode($signature);
            return $encodedSignature ;

        }else if($type == BNISwitchFunction::CODE_REMIT){

            $ref_number_bni =     str_ireplace(['RMT','TR'], '', $this->getReferenceNo());
            $this->setReferenceNoBni(substr($ref_number_bni,2,15));
            $data       =  $this->_getClientId() . $this->getReferenceNoBni() . $this->getTransDateBni() . $this->getLandedAmount() . $this->getAccountNo() ;
            $data_sha1  = sha1($data);
            openssl_sign($data, $signature, $private_key_pem, OPENSSL_ALGO_SHA1);
            $encodedSignature = base64_encode($signature);
            return $encodedSignature ;
        }

        return false;
    }

    public function checkAccount($bank_code , $account_number){
        $this->setBankCode($bank_code);
        $this->setAccountNo($account_number);
        $this->setInquireSignedData($this->generateSignedData('inquiry'));;
        $option = '<s:Envelope xmlns:s="http://schemas.xmlsoap.org/soap/envelope/">
                    <s:Body xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema">
                    <accountInfoInquiry xmlns="http://service.bni.co.id/remm">
                    <header xmlns="">
                    <clientId>'. $this->_getClientId() .'</clientId>
                    <signature>'. $this->getInquireSignedData() .'</signature>
                    </header>
                    <bankCode xmlns="">'. $this->getBankCode()   .'</bankCode>
                    <accountNum xmlns="">'. $this->getAccountNo()  .'</accountNum>
                    </accountInfoInquiry>
                    </s:Body>
                    </s:Envelope>';
        $this->_option = $option;
        $header = array
        (
            'Content-Type: text/xml',
        );
        $this->_http_serv->seturl($this->_getUrl());
        set_time_limit($this->_getTimeLimit());

        if($account_number == "1231234567"  &&  $bank_code == "014"){
            $response ='<soapenv:Envelope xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:soapenc="http://schemas.xmlsoap.org/soap/encoding/" xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/">
                <soapenv:Header/>
                <soapenv:Body>
                  <remm:accountInfoInquiryResponse xmlns:remm="http://service.bni.co.id/remm">
                    <accountInfo>
                      <bankCode>014</bankCode>
                      <accountNumber>1231234567</accountNumber>
                      <accountName>Mr. Remittance Test</accountName>
                    </accountInfo>
                  </remm:accountInfoInquiryResponse>
                </soapenv:Body> </soapenv:Envelope>';
            return new BNISwitchResponse($response, 'accountInfoInquiry');
        }
        $response = $this->_http_serv->post($header, $this->_option);

        $format_response = new BNISwitchResponse($response, 'accountInfoInquiry');
        $account_name = $this->remove_saluation($format_response->getDestAccHolder()) ;
        $format_response->setDestAccHolder($account_name);
        return  $format_response ;
    }


    public function checkTrx()
    {
        $this->setInquireSignedData($this->generateSignedData('info'));

        $option = '<s:Envelope xmlns:env = "http://www.w3.org/2003/05/soap-envelope" xmlns:dpm = "http://www.datapower.com/schemas/management"
            xmlns:dpfunc = "http://www.datapower.com/extensions/functions" xmlns:s = "http://schemas.xmlsoap.org/soap/envelope/" >
            <s:Body xmlns:xsd = "http://www.w3.org/2001/XMLSchema"
            xmlns:xsi = "http://www.w3.org/2001/XMLSchema-instance">
            <poInfoInquiry>
            <header>
            <clientId>'.$this->_getClientId().'</clientId>
            <signature>'.$this->getInquireSignedData().'</signature>
            <paymentOrderKey >
            <refNumber>'.$this->getReferenceNoBni().'</refNumber>
            <clientId>'.$this->_getClientId().'</clientId>
            <trxDate>'. $this->getTransDateBni().'</trxDate>
            </paymentOrderKey>
            </poInfoInquiry>
            </s:Body>
            </s:Envelope>';

        $this->_option = $option;
        $header = array
        (
            'Content-Type: text/xml',
        );
        $this->_http_serv->seturl($this->_getUrl());

        set_time_limit($this->_getTimeLimit());
        $response = $this->_http_serv->post($header, $this->_option);
        return new BNISwitchResponse($response, 'poInfoInquiry');  //
    }


    public function inquiry(){
        $this->setInquireSignedData($this->generateSignedData('inquiry'));
        $this->_inquiry_hit_count++;

        $option = '<s:Envelope xmlns:s="http://schemas.xmlsoap.org/soap/envelope/">
                    <s:Body xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema">
                    <accountInfoInquiry xmlns="http://service.bni.co.id/remm">
                    <header xmlns="">
                    <clientId>'. $this->_getClientId() .'</clientId>
                    <signature>'. $this->getInquireSignedData() .'</signature>
                    </header>
                    <bankCode xmlns="">'. $this->getBankCode()   .'</bankCode>
                    <accountNum xmlns="">'. $this->getAccountNo()  .'</accountNum>
                    </accountInfoInquiry>
                    </s:Body>
                    </s:Envelope>';


        //to save in inquiry param (log)
        $option_array = array(
            'bankCode'=>$this->getBankCode(),
            'accountNum'=>$this->getAccountNo()
        );

        $this->_option = $option;
        $header = array
        (
            'Content-Type: text/xml',
        );

        $this->_http_serv->seturl($this->_getUrl());
        set_time_limit($this->_getTimeLimit());
        $response = $this->_http_serv->post($header, $this->_option);

        $this->_addInfo("number_of_inquiry_calls",$this->_inquiry_hit_count);
        $this->_addInfo("last_process","inquiry");
        $this->_addInfo("inquiry_param",$option_array);

        $format_response =  new BNISwitchResponse($response, 'accountInfoInquiry');  //   accountInfoInquiry /apiStatus = xml field response
        $account_name = $format_response->getDestAccHolder();
        $format_response->setDestAccHolder($account_name);
        return $format_response ;
    }



    public function transfer()
    {

        $this->setInquireSignedData($this->generateSignedData('remit'));

        $this->_transfer_hit_count++;

        $option = '<s:Envelope xmlns:s="http://schemas.xmlsoap.org/soap/envelope/">
                    <s:Body xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema">
                    <processPO xmlns="http://service.bni.co.id/remm">
                    <header xmlns="">
                    <clientId>'. $this->_getClientId() .'</clientId>
                    <signature>'. $this->getInquireSignedData() .'</signature>
                    </header>
                    <paymentOrder xmlns="">
                    <refNumber>'.$this->getReferenceNoBni().'</refNumber> 
                    <serviceType>'.$this->getTransactionType().'</serviceType>
                    <trxDate>'. $this->getTransDateBni() .'</trxDate>
                    <currency>'.$this->getLandedCurrency().'</currency>
                    <amount>'.$this->getLandedAmount().'</amount>
                    <orderingName>'.$this->getSenderFullname().'</orderingName>
                    <orderingAddress1>'.$this->getSenderAddress1().'</orderingAddress1>
                    <orderingAddress2/>
                    <orderingPhoneNumber/>
                    <beneficiaryAccount>'.$this->getAccountNo().'</beneficiaryAccount>
                    <beneficiaryName>'.$this->getReceiverFullname().'</beneficiaryName>
                    <beneficiaryAddress1>'.$this->getReceiverAddress1().'</beneficiaryAddress1>
                    <beneficiaryAddress2/>
                    <beneficiaryPhoneNumber>'.$this->getReceiverMobilePhone().'</beneficiaryPhoneNumber> 
                    <acctWithInstcode>A</acctWithInstcode> 
                    <acctWithInstName>'.$this->getInstName().'</acctWithInstName>
                    <acctWithInstAddress1/>
                    <acctWithInstAddress2/>
                    <acctWithInstAddress3/>
                    <detailPayment1/>
                    <detailPayment2/>
                    <detailCharges>OUR</detailCharges>
                    </paymentOrder>
                    </processPO>
                    </s:Body>
                    </s:Envelope>';


        //to save in transfer param (log)
        $option_array = array(
            'refNumber'=>$this->getReferenceNoBni(),
            'trxDate'=>$this->getTransDateBni(),
            'currency'=>$this->getLandedCurrency(),
            'amount'=>$this->getLandedAmount(),
            'orderingName'=>$this->getSenderFullname(),
            'orderingAddress1'=>$this->getSenderAddress1(),
            'beneficiaryAccount'=>$this->getAccountNo(),
            'beneficiaryName'=>$this->getReceiverFullname(),
            'beneficiaryPhoneNumber'=>$this->getReceiverMobilePhone()
        );

        $this->_option = $option;

        $header = array
        (
            'Content-Type: text/xml'
        );

        $this->_http_serv->seturl($this->_getUrl());
        //curl
        set_time_limit($this->_getTimeLimit());
        $response = $this->_http_serv->post($header, $this->_option);

        $this->_addInfo("transfer_param",$option_array);
        $this->_addInfo("number_of_transfer_calls",$this->_transfer_hit_count);
        $this->_addInfo("last_process","transfer");

        return new BNISwitchResponse($response, 'processPO');
    }


    public function setTransactionID($transactionID)
    {
        $this->transactionID = $transactionID;
        return $this;
    }
    public function getTransactionID()
    {
        return $this->transactionID;
    }


    public function getReceiverEmail()
    {
        return $this->receiver_email;
    }

    public function setReceiverEmail($receiver_email)
    {
        $this->receiver_email = $receiver_email;
    }


    protected function _getUserName()
    {
        if( array_key_exists('username', $this->config) )
            return $this->config['username'];

        return false;
    }

    protected function _getPassword()
    {
        if( array_key_exists('password', $this->config) )
            return $this->config['password'];

        return false;
    }

    protected function _getConduitCode()
    {
        if( array_key_exists('conduit_code', $this->config) )
            return $this->config['conduit_code'];

        return false;
    }

    protected function _getLocatorCode()
    {
        if( array_key_exists('locator_code', $this->config) )
            return $this->config['locator_code'];

        return false;
    }

    protected function _getPrivateKeyFile()
    {
        if( array_key_exists('private_key_file', $this->config) )
            return $this->config['private_key_file'];

        return false;
    }



    protected function _getClientId()
    {
        if( array_key_exists('client_id', $this->config) )
            return $this->config['client_id'];

        return false;
    }


    /*
     * This will first try from config['url']
     * Then environment
     * Otherwise return false
     */
    protected function _getUrl()
    {
        if( array_key_exists('url', $this->config) )
            return $this->config['url'];
        else if( getenv('BNI_SWITCH_URL') )
            return getenv('BNI_SWITCH_URL');

        return false;
    }

    protected function _getTimeLimit()
    {
        if( getenv('SWITCH_TIME_LIMIT') )
            return getenv('SWITCH_TIME_LIMIT');

        return 90;
    }

    public static function fromOption(array $config, array $option)
    {
        $c = new BNISwitchClient($config);

        $c->setSenderFullname('User Slide');
        if( isset($option['signed_data']) )
            $c->setSignedData($option['signed_data']);
        if( isset($option['inquire_signed_data']) )
            $c->setInquireSignedData($option['inquire_signed_data']);
        if( isset($option['reference_no']) )
            $c->setReferenceNo($option['reference_no']);
        if( isset($option['trans_date']) ) {
            $c->setTransDate($option['trans_date']);
            $c->setTransDateBni($option['trans_date']  . 'T'. date('H:i:s'));
        }
        if( isset($option['sender_fullname']) )
            $c->setSenderFullname($option['sender_fullname']);
        if( isset($option['sender_address']) )
            $c->setSenderAddress($option['sender_address']);
        if( isset($option['sender_phone']) )
            $c->setSenderPhone($option['sender_phone']);
        if( isset($option['receiver_fullname']) )
            $c->setReceiverFullname($option['receiver_fullname']);
        if( isset($option['receiver_address']) )
            $c->setReceiverAddress($option['receiver_address']);
        if( isset($option['receiver_mobile_phone']) )
            $c->setReceiverMobilePhone($option['receiver_mobile_phone']);
        if( isset($option['receiver_gender']) )
            $c->setReceiverGender($option['receiver_gender']);
        if( isset($option['receiver_birth_date']) )
            $c->setReceiverBirthDate($option['receiver_birth_date']);
        if( isset($option['bank_code']) ) {
            $c->setBankCode($option['bank_code']);
            $branch_name = $option['bank_code'] == '009' ? 'BNI' : 'MAKATI';
            $transaction_type = $option['bank_code'] == '009' ? 'BNI' : 'INTERBANK';
            $instName = $option['bank_code'] == '009' ? 'BNINIDJAXXX' : $option['bank_code'];
            $payable_code =  $option['bank_code'] == '009' ? 'CBBM' : 'CBOM';
            $c->setBranchName($branch_name);
            $c->setTransactionType($transaction_type);
            $c->setPayableCode($payable_code);
            $c->setInstName($instName);

        }
        if( isset($option['account_no']) )
            $c->setAccountNo($option['account_no']);
        if( isset($option['landed_currency']) ) {
            $c->setLandedCurrency($option['landed_currency']);
        }else{
            $c->setLandedCurrency("IDR");
        }
        if( isset($option['landed_amount']) )
            $c->setLandedAmount($option['landed_amount']);

        return $c;
    }


    public function generateSignedData2($type){
        if($type == BNISwitchFunction::CODE_INQUIRY){
            return shell_exec("java -jar ".dirname(__FILE__)."/tool/jar/BNIRemit.jar ". $type . " " . $this->getReferenceNo());
        }else if($type == BNISwitchFunction::CODE_REMIT){
            return shell_exec("java -jar ".dirname(__FILE__)."/tool/jar/BNIRemit.jar ". $type . " " . $this->getReferenceNo(). " " . $this->getLandedAmount(). " " . $this->getTransDate() . " " . $this->getAccountNo());
        }
        return false;
    }


    //getter/setter
    public function setSignedData($signed_data)
    {
        $this->signed_data = $signed_data;
        return $this;
    }

    public function getSignedData()
    {
        return $this->signed_data;
    }

    public function setInquireSignedData($inquire_signed_data)
    {
        $this->inquire_signed_data = $inquire_signed_data;
        return $this;
    }

    public function getInquireSignedData()
    {
        return $this->inquire_signed_data;
    }

    public function setReferenceNo($reference_no)
    {
        $this->reference_no = $reference_no;
        return $this;
    }

    public function getReferenceNo()
    {
        return $this->reference_no;
    }

    public function setReferenceNoBni($reference_no_bni)
    {
        $this->reference_no_bni = $reference_no_bni;
        return $this;
    }

    public function getReferenceNoBni()
    {
        return $this->reference_no_bni;
    }

    public function setTransDateBni($trans_date_bni)
    {
        $this->trans_date_bni = $trans_date_bni ;
        return $this;
    }

    public function getTransDateBni()
    {
        return $this->trans_date_bni;
    }

    public function setTransDate($trans_date)
    {
        $this->trans_date = $trans_date;
        return $this;
    }

    public function getTransDate()
    {
        return $this->trans_date;
    }

    public function setSenderFullname($sender_fullname)
    {
        $this->sender_fullname = trim($sender_fullname);
        $arrName = $this->formatName($this->sender_fullname);

        $this->sender_firstname = isset($arrName[0]) ? $arrName[0] : '';
        $this->sender_middlename = isset($arrName[1]) ? $arrName[1] : '';
        $this->sender_lastname = isset($arrName[2]) ? $arrName[2] : '';

        return $this;
    }

    public function getSenderFullname()
    {
        return $this->sender_fullname;
    }

    public function setSenderFirstname($sender_firstname)
    {
        $this->sender_firstname = $sender_firstname;
        return $this;
    }

    public function getSenderFirstname()
    {
        return $this->sender_firstname;
    }

    public function setSenderLastname($sender_lastname)
    {
        $this->sender_lastname = $sender_lastname;
        return $this;
    }

    public function getSenderLastname()
    {
        return $this->sender_lastname;
    }

    public function setSenderMiddlename($sender_middlename)
    {
        $this->sender_middlename = $sender_middlename;
        return $this;
    }

    public function getSenderMiddlename()
    {
        return $this->sender_middlename;
    }

    public function setSenderAddress($sender_address)
    {
        $this->sender_address = trim($sender_address);
        $arrAddress = $this->formatAddress($this->sender_address);

        $this->sender_address1 = isset($arrAddress[0]) ? $arrAddress[0] : '';
        $this->sender_address2 = isset($arrAddress[1]) ? $arrAddress[1] : '';

        return $this;
    }

    public function getSenderAddress()
    {
        return $this->sender_address;
    }

    public function setSenderAddress1($sender_address1)
    {
        $this->sender_address1 = $sender_address1;
        return $this;
    }

    public function getSenderAddress1()
    {
        return $this->sender_address1;
    }

    public function setSenderAddress2($sender_address2)
    {
        $this->sender_address2 = $sender_address2;
        return $this;
    }

    public function getSenderAddress2()
    {
        return $this->sender_address2;
    }

    public function setSenderPhone($sender_phone)
    {
        $this->sender_phone = ltrim(preg_replace('/\s+/', '', $sender_phone), "+");
        return $this;
    }

    public function getSenderPhone()
    {
        return $this->sender_phone;
    }

    public function setReceiverFullname($receiver_fullname)
    {
        $receiver_fullname = $this->remove_saluation($receiver_fullname);
        $this->receiver_fullname = trim($receiver_fullname);

        $arrName = $this->formatName($this->receiver_fullname);

        $this->receiver_firstname = isset($arrName[0]) ? $arrName[0] : '';
        $this->receiver_middlename = isset($arrName[1]) ? $arrName[1] : '';
        $this->receiver_lastname = isset($arrName[2]) ? $arrName[2] : '';

        return $this;
    }

    public function getReceiverFullname()
    {
        return $this->receiver_fullname;
    }

    public function setReceiverFirstName($receiver_firstname)
    {
        $this->receiver_firstname = $receiver_firstname;
        return $this;
    }

    public function getReceiverFirstName()
    {
        return $this->receiver_firstname;
    }

    public function setReceiverLastName($receiver_lastname)
    {
        $this->receiver_lastname = $receiver_lastname;
        return $this;
    }

    public function getReceiverLastName()
    {
        return $this->receiver_lastname;
    }

    public function setReceiverMiddleName($receiver_middlename)
    {
        $this->receiver_middlename = $receiver_middlename;
        return $this;
    }

    public function getReceiverMiddleName()
    {
        return $this->receiver_middlename;
    }

    public function setReceiverAddress($receiver_address)
    {
        $this->receiver_address = trim($receiver_address);
        $arrAddress = $this->formatAddress($this->receiver_address);

        $this->receiver_address1 = isset($arrAddress[0]) ? $arrAddress[0] : '';
        $this->receiver_address2 = isset($arrAddress[1]) ? $arrAddress[1] : '';
        return $this;
    }

    public function getReceiverAddress()
    {
        return $this->receiver_address;
    }

    public function setReceiverAddress1($receiver_address1)
    {
        $this->receiver_address1 = $receiver_address1;
        return $this;
    }

    public function getReceiverAddress1()
    {
        return $this->receiver_address1;
    }

    public function setReceiverAddress2($receiver_address2)
    {
        $this->receiver_address2 = $receiver_address2;
        return $this;
    }

    public function getReceiverAddress2()
    {
        return $this->receiver_address2;
    }

    public function setReceiverMobilePhone($receiver_mobile_phone)
    {
        $this->receiver_mobile_phone = ltrim(preg_replace('/\s+/', '', $receiver_mobile_phone), "+");
        return $this;
    }

    public function getReceiverMobilePhone()
    {
        return $this->receiver_mobile_phone;
    }

    public function setReceiverGender($receiver_gender)
    {
        $this->receiver_gender = $receiver_gender;
        return $this;
    }

    public function getReceiverGender()
    {
        return $this->receiver_gender;
    }

    public function setReceiverBirthDate($receiver_birth_date)
    {
        $this->receiver_birth_date = $receiver_birth_date;
        return $this;
    }

    public function getReceiverBirthDate()
    {
        return $this->receiver_birth_date;
    }

    public function setTransactionType($transaction_type)
    {
        $this->transaction_type = $transaction_type;
        return $this;
    }

    public function getTransactionType()
    {
        return $this->transaction_type;
    }

    public function setInstName($instName)
    {
        $this->inst_name = $instName;
        return $this;
    }

    public function getInstName()
    {
        return $this->inst_name;
    }

    public function setPayableCode($payable_code)
    {
        $this->payable_code = $payable_code;
        return $this;
    }

    public function getPayableCode()
    {
        return $this->payable_code;
    }

    public function setBankCode($bank_code)
    {
        $this->bank_code = $bank_code;
        return $this;
    }

    public function getBankCode()
    {
        return $this->bank_code;
    }

    public function setBranchName($branch_name)
    {
        $this->branch_name = $branch_name;
        return $this;
    }

    public function getBranchName()
    {
        return $this->branch_name;
    }

    public function setAccountNo($account_no)
    {
        $this->account_no = trim($account_no);
        return $this;
    }

    public function getAccountNo()
    {
        return $this->account_no;
    }

    public function setLandedCurrency($landed_currency)
    {
        $this->landed_currency = $landed_currency;
        return $this;
    }

    public function getLandedCurrency()
    {
        return $this->landed_currency;
    }

    public function setLandedAmount($landed_amount)
    {
        $this->landed_amount = $landed_amount;
        return $this;
    }

    public function getLandedAmount()
    {
        return $this->landed_amount;
    }

    public function setMessageToBene1($message_to_bene1)
    {
        $this->message_to_bene1 = $message_to_bene1;
        return $this;
    }

    public function getMessageToBene1()
    {
        return $this->message_to_bene1;
    }

    public function setMessageToBene2($message_to_bene2)
    {
        $this->message_to_bene2 = $message_to_bene2;
        return $this;
    }

    public function getMessageToBene2()
    {
        return $this->message_to_bene2;
    }

    private function formatAddress($address, $length = 75){
        $arrAddress = array($address, '');
        if(strlen($address)>$length){
            $arrAddress = explode( "\n", wordwrap($address, 75));
        }
        return $arrAddress;
    }

    private function formatName($fullname){
        $arrName = array($fullname, '', '.');

        if (preg_match('/\s/', $fullname)) {
            $firstName = mb_substr($fullname, 0, mb_strpos($fullname, " "));
            $lastName = mb_substr($fullname, -abs(mb_strpos(strrev($fullname), " ")));
            $arrName[0] = $firstName;
            $arrName[1] = strtoupper(substr($firstName,0,1));
            $arrName[2] = $lastName;
        }

        return $arrName;
    }


    public function getOption()
    {
        $option = array('username' => $this->_getUserName(),
            'signed_data' => $this->getSignedData(),
            'inquire_signed_data' => $this->getInquireSignedData(),
            'trans_date' => $this->getTransDate(),
            'reference_no' => $this->getReferenceNo(),
            'sender_fullname' => $this->getSenderFullname(),
            'sender_address' => $this->getSenderAddress(),
            'sender_phone' => $this->getSenderPhone(),
            'receiver_fullname' => $this->getReceiverFullname(),
            'receiver_address' => $this->getReceiverAddress(),
            'receiver_mobile_phone' => $this->getReceiverMobilePhone(),
            'receiver_birth_date' => $this->getReceiverBirthDate(),
            'receiver_gender' => $this->getReceiverGender(),
            'bank_code' => $this->getBankCode(),
            'account_no' => $this->getAccountNo(),
            'landed_amount' => $this->getLandedAmount(),
            'landed_currency' => $this->getLandedCurrency()
        );
        return json_encode($option);
    }

    public function remove_saluation($name) {
        $saluations = array("ibu","bapak" ,"bpk" ,"tuan" ,"nyonya" ,"mr" );
        $name = strtolower($name);
        $name =  str_replace(".","",$name);
        $name =  str_replace(",","",$name);

        foreach($saluations as $find) {
            if (strpos($name, $find) !== false){
                $name =  str_replace($find,"",$name);
            }
        }
        $name = ltrim(strtoupper($name));
        return $name;
    }


    //-----------------------------------------------------------------------

    public function setLastResponse(){
        $this->_addInfo("number_of_inquiry_calls",$this->_inquiry_hit_count);
        $this->_addInfo("number_of_transfer_calls",$this->_transfer_hit_count);
        $this->_addInfo("number_of_check_trx",$this->_number_of_check_trx);
        $this->_addInfo("check_trx_info",$this->_check_trx_info);
    }

    public function getLastResponse(){
        //get last response from request
        $last_response = $this->getResponseFields() ;
        if(array_key_exists('bni_process', $last_response)) {
            $bni_process = json_decode($last_response["bni_process"], true);
            if(array_key_exists('number_of_inquiry_calls', $bni_process))
                $this->_inquiry_hit_count        =  $bni_process["number_of_inquiry_calls"];
            if(array_key_exists('number_of_transfer_calls', $bni_process))
                $this->_transfer_hit_count       =  $bni_process["number_of_transfer_calls"];
            if(array_key_exists('last_process', $bni_process))
                $this->_last_process             =  $bni_process["last_process"];
            if(array_key_exists('check_trx_info', $bni_process))
                $this->_check_trx_info           =  $bni_process["check_trx_info"];
            if(array_key_exists('number_of_check_trx', $bni_process))
                $this->_number_of_check_trx     =  $bni_process["number_of_check_trx"];

            if(array_key_exists('number_of_quotation_calls', $bni_process))
                $this->_quot_hit_count        =  $bni_process["number_of_quotation_calls"];

            if(array_key_exists('timeout_of_check_trx', $bni_process))
                $this->_timeout_of_check_trx     =  $bni_process["timeout_of_check_trx"];
        }
        if(array_key_exists('bni_response', $last_response)) {
            $bni_response = json_decode($last_response["bni_response"], true);
            if(array_key_exists('status', $bni_response))
                $this->_last_rc  =  $bni_response["status"];
        }
    }

    protected function _addInfo($key,$value)
    {
        $this->info[$key] = $value;
        $this->setBniInfo(json_encode($this->getInfo()));
        return $this ;
    }

    protected function _removeInfo($key)
    {
        unset($this->info[$key]);
        $this->setBniInfo(json_encode($this->getInfo()));
        return $this ;
    }

    public function getBniInfo()
    {
        return $this->bni_info;
    }
    public function setBniInfo($bni_info)
    {
        $this->bni_info = $bni_info;
    }

    public function getInfo()
    {
        return $this->info;
    }
    public function setInfo($info)
    {
        $this->info = $info;
    }

    public function getResponseFields()
    {
        return $this->response_fields;
    }
    public function setResponseFields($response_fields)
    {
        $this->response_fields = $response_fields;
    }

    public function getSelectedArray(array $fields , array $selected){
        $result = array() ;
        foreach ($fields  as $i => $value) {
            if(in_array($i ,$selected)){
                $result[$i] =$value;
            }
        }
        return $result;
    }


    public function formatToFail($msg){
        $format = '<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:soapenc="http://schemas.xmlsoap.org/soap/encoding/" xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">
                        <soapenv:Header />
                        <soapenv:Body>
                        <soapenv:Fault xmlns:m="http://service.bni.co.id/remm" xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/">
                           <faultcode>m:Fault</faultcode>
                           <faultstring></faultstring>
                           <detail encodingStyle="">
                              <remm:Fault_element xmlns:remm="http://service.bni.co.id/remm">
                                     <errorCode></errorCode>
                                     <errorDescription>'.$msg.'</errorDescription>
                               </remm:Fault_element>
                             </detail>
                        </soapenv:Fault>
                        </soapenv:Body></soapenv:Envelope>';

        return $format;
    }


    public function formatSuccess($ref_number){

        $format = '<soapenv:Envelope 
                    xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" 
                    xmlns:soapenc="http://schemas.xmlsoap.org/soap/encoding/" 
                    xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">
                    <soapenv:Header/> 
                    <soapenv:Body>
                    <remm:poInfoInquiryResponse xmlns:remm="http://service.bni.co.id/remm">
                    <paymentInfoList> <paymentInfo>
                    <status>NEW</status> <statusDescription>NEW</statusDescription> <paymentDetail>
                    <bniReference>'.$ref_number.'</bniReference> 
                    <paidDate/>
                    <paidCurrency/>
                    <paidAmount/>
                    <chargesAmount/> 
                    <beneficiaryAccount/> 
                    <beneficiaryName/>
                    </paymentDetail> 
                    </paymentInfo>
                    </paymentInfoList> 
                    </remm:poInfoInquiryResponse>
                    </soapenv:Body> 
                    </soapenv:Envelope>';

        return $format;
    }

}