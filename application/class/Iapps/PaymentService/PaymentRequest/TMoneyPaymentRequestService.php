<?php

namespace Iapps\PaymentService\PaymentRequest;

use Iapps\Common\Helper\GuidGenerator;
use Iapps\Common\Microservice\RemittanceService\RemittanceTransactionServiceFactory;
use Iapps\PaymentService\Common\TMoneySwitch\TMoneySwitchFunction;
use Iapps\PaymentService\Common\TMoneySwitch\TmoneySwitchResponse;
use Iapps\PaymentService\Common\MessageCode;
use Iapps\PaymentService\Common\TMoneySwitch\TMoneySwitchClientFactory;
use Iapps\PaymentService\Payment\PaymentDescription;
use Iapps\PaymentService\PaymentMode\PaymentModeType;
use Iapps\PaymentService\PaymentModeAttribute\PaymentModeAttributeCode;
use Iapps\PaymentService\PaymentModeAttribute\PaymentModeAttributeServiceFactory;
use Iapps\PaymentService\PaymentRequestValidator\TMoneyPaymentRequestValidator;
use Iapps\PaymentService\Common\Logger;
use Iapps\Common\Microservice\AccountService\AccountServiceFactory;
use Iapps\Common\Core\IappsDateTime;
use Iapps\PaymentService\Payment\PaymentServiceFactory;
use Iapps\PaymentService\Common\CoreConfigDataServiceFactory;
use Iapps\PaymentService\Common\CoreConfigType;
use Iapps\Common\CommunicationService\CommunicationServiceProducer;

class TMoneyPaymentRequestService extends PaymentRequestService{

    function __construct(PaymentRequestRepository $rp, $ipAddress = '127.0.0.1', $updatedBy = NULL)
    {
        parent::__construct($rp, $ipAddress, $updatedBy);
        $this->payment_code = PaymentModeType::BANK_TRANSFER_TMONEY;
    }

    public function complete($user_profile_id, $request_id, $payment_code, array $response)
    {
        if( $response =  parent::complete($user_profile_id, $request_id, $payment_code, $response) )
        {
            if( $this->_request instanceof PaymentRequest )
            {
                $response['additional_info'] = $this->_request->getResponseFields(array('bank_code', 'account_no', 'reference_no', 'trans_date'));
            }

            return $response;
        }

        return false;
    }

    public function checkTmoneyServer(){
        try{
            $tmoney_switch_client = TMoneySwitchClientFactory::build();
        }
        catch(\Exception $e)
        {//this is internal error, should not happen
            $this->setResponseCode(MessageCode::CODE_INVALID_SWITCH_SETTING);
            return false;
        }
        $response = $tmoney_switch_client->signIn() ;
        if($response){
            $checkdate = date('Y-m-d H:i:s' ,strtotime('+8 hours')); //utc to sing time  
            $subject = 'TMoney Server Checking '.$checkdate;
            if ($response->getResponseCode() == "0"){
                $resp_array = json_decode($response->getFormattedResponse(),true);
                $result = "<p>TMoney Server Checking:</p>";
                if(array_key_exists("resultCode",$resp_array)) {
                    $result .= "<p>Result Code :" . $resp_array["resultCode"] . "</p>";
                    $result .= "<p>Result Description :" . $resp_array["resultDesc"] . "</p>";
                    $result .= "<p>Time Stamp :" . $resp_array["timeStamp"] . "</p>";
                    $result .= "<p>User Name :" . $resp_array["user"]["custName"] . "</p>";
                    $result .= "<p>Last Login :" . $resp_array["user"]["lastLogin"] . "</p>";
                }
                $this->_notifyServerCheck($result,$subject);
                return true ;
            }else{
                $result = "<p>TMoney Server Checking:</p>";
                $result .= "<p>" . $response->getFormattedResponse() . "</p>";
                $this->_notifyServerCheck($result,$subject);
                return false ;
            }
        }
        return false ;
    }

    /*
     * TMoney only call to switch upon complete
     */
    public function _requestAction(PaymentRequest $request)
    {

        try{
            $tmoney_switch_client = TMoneySwitchClientFactory::build();
        }
        catch(\Exception $e)
        {//this is internal error, should not happen
            $this->setResponseCode(MessageCode::CODE_INVALID_SWITCH_SETTING);
            return false;
        }

        $tmoney_switch_client->setReferenceNo($request->getTransactionID() . $request->getModuleCode());

        $tmoney_switch_client->setTransactionID($request->getTransactionID());

        if( $account = $request->getOption()->getValue('bank_account') )
            $tmoney_switch_client->setAccountNo($account);
        if( $receiver_fullname = $request->getOption()->getValue('account_holder_name') )
            $tmoney_switch_client->setReceiverFullname($receiver_fullname);
        if( $bank_code = $request->getOption()->getValue('bank_code') )
            $tmoney_switch_client->setBankCode($bank_code);

        if( $sender_address = $request->getOption()->getValue('sender_address') )
            $tmoney_switch_client->setSenderAddress($sender_address);
        if( $sender_phone = $request->getOption()->getValue('sender_phone') )
            $tmoney_switch_client->setSenderPhone($sender_phone);
        if( $sender_fullname = $request->getOption()->getValue('sender_fullname') )
            $tmoney_switch_client->setSenderFullname($sender_fullname);
        if( $receiver_address = $request->getOption()->getValue('receiver_address') )
            $tmoney_switch_client->setReceiverAddress($receiver_address);
        if( $receiver_mobile_phone = $request->getOption()->getValue('receiver_mobile_phone') )
            $tmoney_switch_client->setReceiverMobilePhone($receiver_mobile_phone);

        if( $receiver_gender = $request->getOption()->getValue('receiver_gender') )
            $tmoney_switch_client->setReceiverGender($receiver_gender);
        if( $receiver_birth_date = $request->getOption()->getValue('receiver_birth_date') )
            $tmoney_switch_client->setReceiverBirthDate($receiver_birth_date);
        if( $receiver_email = $request->getOption()->getValue('receiver_email') )
            $tmoney_switch_client->setReceiverEmail($receiver_email);


        if( $landed_currency = $request->getOption()->getValue('landed_currency') )
            $tmoney_switch_client->setLandedCurrency($landed_currency);

        $transDate = IappsDateTime::fromString($request->getDateOfTransfer());
        $tmoney_switch_client->setTransDate($transDate->getFormat('Y-m-d'));

        $tmoney_switch_client->setLandedAmount(-1*$request->getAmount());

        // create sign data
        //$signed_data = $tmoney_switch_client->generateSignedData('remit');
        //$tmoney_switch_client->setSignedData($signed_data);

        // create inquiry sign data
        //$inquire_signed_data = $tmoney_switch_client->generateSignedData('inquiry');
        //$tmoney_switch_client->setInquireSignedData($inquire_signed_data);

        $option_array = json_decode($tmoney_switch_client->getOption(), true);
        //set user type
        if( $user_type = $request->getOption()->getValue('user_type')) {
            $option_array['user_type'] = $user_type;
        }

        $request->getOption()->setArray($option_array);

        //this validation in main class
        $v = TMoneyPaymentRequestValidator::make($request);
        if( !$v->fails() )
        {

            $request->setStatus(PaymentRequestStatus::PENDING);
            return true;

        }

        $this->setResponseCode(MessageCode::CODE_PAYMENT_INVALID_INFO);
        return false;
    }

    public function _completeAction(PaymentRequest $request)
    {
        //make request to switch
        try{
            $tmoney_switch_client = TMoneySwitchClientFactory::build($request->getOption()->toArray());
        }
        catch(\Exception $e)
        {//this is internal error, should not happen
            $this->setResponseCode(MessageCode::CODE_INVALID_SWITCH_SETTING);
            return false;
        }

        if(!empty($request->getReferenceID())){
            $request->setPending();
            //Processed already
            Logger::debug('TMONEY Processed');
            return false;
        }

        $tmoney_switch_client->setResponseFields($request->getResponse()->toArray());

        $response = $tmoney_switch_client->bankTransfer() ;
        $request->getResponse()->setJson(json_encode(array("TMoney Bank Transfer"=>$tmoney_switch_client->getTransactionType())));


        if($response )
        {
            $result = $this->_checkResponse($request, $response);
            $request->getResponse()->add('tmoney_response', $response->getFormattedResponse());

            //save selected sent and return data tmoney , need to compare with tmoney if fail during process
            $request->getResponse()->add('tmoney_process', $tmoney_switch_client->getTmoneyInfo());
            // it must be set even inquiry , transfer stage to ignore re complete again from delivering process
            $request->setReferenceID($response->getTransactionIDSwitcher()); //transaction id from payment return
            //$request->setReferenceID($response->getRefNoSwitcher()); //refNO from payment return
            //$request->setReferenceID($tmoney_switch_client->getSwitcherReferenceNo()); //refNo once sent transfer
            if( $result ) {
                return parent::_completeAction($request);
            }else{
                if($request->getStatus()==PaymentRequestStatus::FAIL){
                    $this->setResponseMessage($response->getDescription());
                    $request->setFail();
                    $this->getRepository()->updateResponse($request);
                    Logger::debug('TMoney Failed - ' . $request->getStatus() . ': ' . $response->getResponse() . ': ' . $tmoney_switch_client->getTmoneyInfo());
                }
                if($request->getStatus()==PaymentRequestStatus::PENDING){
                    $this->setResponseMessage($response->getDescription());
                    $this->getRepository()->updateResponse($request);
                    Logger::debug('TMoney Pending - ' . $request->getStatus() . ': ' . $response->getResponse() . ': ' . $tmoney_switch_client->getTmoneyInfo());
                }
            }

        }else{
            $request->getResponse()->add('tmoney_process', $tmoney_switch_client->getTmoneyInfo());  //sent and return data tmoney
            Logger::error('TMoney Error Process Log - ' . $tmoney_switch_client->getTmoneyInfo());
        }

        return false;

    }

    public function findPendingRequest(){
        $payment_request = new PaymentRequest();
        $payment_request->setPending();
        $payment_request->setPaymentCode($this->getPaymentCode());
        $requests = $this->getRepository()->findBySearchFilter($payment_request, null, null, null);

        return $requests;
    }

    public function reprocessRequest(PaymentRequest $request){
        //make request to switch
        try{
            $tmoney_switch_client = TMoneySwitchClientFactory::build($request->getOption()->toArray());
        }
        catch(\Exception $e)
        {//this is internal error, should not happen
            $this->setResponseCode(MessageCode::CODE_INVALID_SWITCH_SETTING);
            return false;
        }
        //get last info
        $tmoney_switch_client->setResponseFields($request->getResponse()->toArray());

        $last_response = $request->getResponse()->toArray() ;

        if(array_key_exists("tmoney_response",$last_response)) {

            $tmoney_response = $last_response["tmoney_response"];
            $tmoney_response_arr = json_decode($tmoney_response,true) ;

            if(array_key_exists("resultCode",$tmoney_response_arr)) {

                if ($tmoney_response_arr["resultCode"] == "PRC"  || $tmoney_response_arr["resultCode"] == "PB-001") {
                    if ($response = $tmoney_switch_client->bankTransfer()) {


                        $request->getResponse()->setJson(json_encode(array("TMoney Bank Transfer" => $tmoney_switch_client->getTransactionType())));
                        $request->getResponse()->add('tmoney_response', $response->getFormattedResponse());
                        $request->getResponse()->add('tmoney_process', $tmoney_switch_client->getTmoneyInfo());
                        //$request->setReferenceID($response->getRefNoSwitcher());
                        $request->setReferenceID($response->getTransactionIDSwitcher()); //transaction id from payment send or return

                        $ori_request = clone($request);
                        $result = $this->_checkResponse($request, $response);
                        $this->getRepository()->beginDBTransaction();
                        if ($result) {

                            if ($complete = parent::_completeAction($request)) {
                                if (parent::_updatePaymentRequestStatus($request, $ori_request)) {
                                    Logger::debug("TMoney reprocess Request Success");
                                    Logger::debug($request->getTransactionID());

                                    if ($this->getRepository()->statusDBTransaction() === FALSE){
                                        $this->getRepository()->rollbackDBTransaction();
                                    }else{
                                        $this->getRepository()->commitDBTransaction();
                                    }
                                    $this->setResponseCode(MessageCode::CODE_REQUEST_COMPLETED);
                                    return true;
                                } else {
                                    Logger::debug("TMoney reprocess Request failed");
                                    $this->getRepository()->rollbackDBTransaction();
                                    $this->setResponseCode(MessageCode::CODE_PAYMENT_REQUEST_FAIL); //***
                                    return false;
                                }
                            }
                        } else {//failed or still processing


                            $request->getResponse()->setJson(json_encode(array("TMoney Bank Transfer" => $tmoney_switch_client->getTransactionType())));
                            $request->getResponse()->add('tmoney_response', $response->getFormattedResponse());
                            $request->getResponse()->add('tmoney_process', $tmoney_switch_client->getTmoneyInfo());

                            if ($request->getStatus() == PaymentRequestStatus::FAIL) {
                                $this->setResponseMessage($response->getRemarks());
                                $request->setFail();
                                $this->getRepository()->updateResponse($request);
                                if ($this->getRepository()->statusDBTransaction() === FALSE){
                                    $this->getRepository()->rollbackDBTransaction();
                                }else {
                                    $this->getRepository()->commitDBTransaction();
                                }
                                return true;
                            } elseif ($request->getStatus() == PaymentRequestStatus::PENDING) {
                                $this->getRepository()->updateResponse($request);
                                if ($this->getRepository()->statusDBTransaction() === FALSE){
                                    $this->getRepository()->rollbackDBTransaction();
                                }else {
                                    $this->getRepository()->commitDBTransaction();
                                }
                                return false;
                            }
                        }
                        $this->getRepository()->rollbackDBTransaction();


                    }
                }
            }

        }
        $this->setResponseCode(MessageCode::CODE_PAYMENT_REQUEST_FAIL);
        return false;
    }

    public function updateRequest(PaymentRequest $request){
        if($this->getRepository()->update($request)){
            return true;
        }
        return false;
    }

    public function inquireRequest(PaymentRequest $request){
        try{
            $tmoney_switch_client = TMoneySwitchClientFactory::build($request->getOption()->toArray());
        }
        catch(\Exception $e)
        {//this is internal error, should not happen
            $this->setResponseCode(MessageCode::CODE_INVALID_SWITCH_SETTING);
            return false;
        }


        if($response = $tmoney_switch_client->inquiry() ) {   //add for tmoney only
            if (!$response->getResponseCode() == '0') {
                return false;
            }

            return $response;
        }
        return false;
    }

    protected function _generateDetail1(PaymentRequest $request)
    {
        //get bank transfer detail
        $bank_name ="";

        if ($request->getOption() != NULL) {
            $desc = new PaymentDescription();

            $attrServ = PaymentModeAttributeServiceFactory::build();

            $option_array = $request->getOption()->toArray();
            if ($option_array != NULL) {
                if (array_key_exists('reference_no', $option_array)) {
                    $desc->add('Transfer Ref No.', $option_array['reference_no']);
                }
                if (array_key_exists('bank_code', $option_array)) {
                    if( $value = $attrServ->getValueByCode($this->payment_code, PaymentModeAttributeCode::BANK_CODE, $option_array['bank_code']) )
                        $bank_name = $value->getValue();
                    $desc->add('Bank', $bank_name);
                }
                if (array_key_exists('account_no', $option_array)) {
                    $desc->add('Bank Account No.', $option_array['account_no']);
                }
                if (array_key_exists('trans_date', $option_array)) {
                    $desc->add('Date of Transfer', $option_array['trans_date']);
                }
            }

            $request->setDetail1($desc);
        }

        return true;
    }


    public function _checkAccount($bank_code, $account_number,$acc_holder_name)
    {

        //make chcekTrx to switch
        $request =[];
        try{
            $tmoney_switch_client = TMoneySwitchClientFactory::build($request);
        }
        catch(\Exception $e)
        {//this is internal error, should not happen
            $this->setResponseCode(MessageCode::CODE_INVALID_SWITCH_SETTING);
            return false;
        }

        if($response = $tmoney_switch_client->checkAccount($bank_code,$account_number) )
        {

            $result = array(
                //'responseCode'=>$response->getResponseCode(),
                'bankAccount'=>$response->getDestBankacc(),
                'CorrectAccountHolderName'=>$response->getDestAccHolder(),
                'description'=>$response->getDescription()
                //'formatResponse'=>$response->getFormattedResponse()
            );

            if ($response->getResponseCode() == "00" || $response->getResponseCode() == "0") {
                $this->setResponseCode(MessageCode::CODE_CHECK_BANK_ACCOUNT_SUCCESS);
                if(strtoupper($acc_holder_name) == strtoupper($response->getDestAccHolder()) ){
                    $this->setResponseCode(MessageCode::CODE_CHECK_ACCOUNT_HOLDER_NAME_SUCCESS);
                    $result["description"] = "Success";
                }else{
                    //$result["responseCode"] = "01";
                    $this->setResponseCode(MessageCode::CODE_CHECK_ACCOUNT_HOLDER_NAME_FAILED);
                    $result["description"] = "Invalid Account Holder Name";
                }

            }else{
                $this->setResponseCode(MessageCode::CODE_CHECK_BANK_ACCOUNT_FAILED);
            }
            //$this->setResponseMessage("Check Bank Account Failed");
            return $result ;
        }

        return false;
    }


    protected function _notifyServerCheck($result,$subject)
    {
        //get config data
        $configServ = CoreConfigDataServiceFactory::build();
        if( $email = $configServ->getConfig(CoreConfigType::TMONEY_SERVER_CHECK_EMAIL) )
        {
            $email = explode('|', $email);
            if( is_array($email) ) {
                $content  = $result ;
                $content .= "<p></p><p>Thank You</p>";
                $ics = new CommunicationServiceProducer();
                return $ics->sendEmail(getenv('ICS_PROJECT_ID'), $subject, $content, $content, $email);
            }
        }
        return false;
    }

}