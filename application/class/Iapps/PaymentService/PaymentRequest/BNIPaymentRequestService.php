<?php

namespace Iapps\PaymentService\PaymentRequest;

use Iapps\Common\Helper\GuidGenerator;
use Iapps\Common\Microservice\RemittanceService\RemittanceTransactionServiceFactory;
use Iapps\PaymentService\Common\BNISwitch\BNISwitchFunction;
use Iapps\PaymentService\Common\BNISwitch\BNISwitchResponse;
use Iapps\PaymentService\Common\MessageCode;
use Iapps\PaymentService\Common\BNISwitch\BNISwitchClientFactory;
use Iapps\PaymentService\Payment\PaymentDescription;
use Iapps\PaymentService\PaymentMode\PaymentModeType;
use Iapps\PaymentService\PaymentModeAttribute\PaymentModeAttributeCode;
use Iapps\PaymentService\PaymentModeAttribute\PaymentModeAttributeServiceFactory;
use Iapps\PaymentService\PaymentRequestValidator\BNIPaymentRequestValidator;
use Iapps\PaymentService\Common\Logger;
use Iapps\Common\Microservice\AccountService\AccountServiceFactory;
use Iapps\Common\Core\IappsDateTime;
use Iapps\PaymentService\Payment\PaymentServiceFactory;
use Illuminate\Support\Facades\Log;

class BNIPaymentRequestService extends PaymentRequestService{

    function __construct(PaymentRequestRepository $rp, $ipAddress = '127.0.0.1', $updatedBy = NULL)
    {
        parent::__construct($rp, $ipAddress, $updatedBy);
        $this->payment_code = PaymentModeType::BANK_TRANSFER_BNI;
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

    /*
     * BNI only call to switch upon complete
     */
    public function _requestAction(PaymentRequest $request)
    {

        try{
            $bni_switch_client = BNISwitchClientFactory::build();
        }
        catch(\Exception $e)
        {//this is internal error, should not happen
            $this->setResponseCode(MessageCode::CODE_INVALID_SWITCH_SETTING);
            return false;
        }

        $bni_switch_client->setReferenceNo($request->getTransactionID() . $request->getModuleCode());

        $bni_switch_client->setTransactionID($request->getTransactionID());
        
        
        if( $account = $request->getOption()->getValue('bank_account') )
            $bni_switch_client->setAccountNo($account);
        if( $receiver_fullname = $request->getOption()->getValue('account_holder_name') )
            $bni_switch_client->setReceiverFullname($receiver_fullname);
        if( $bank_code = $request->getOption()->getValue('bank_code') )
            $bni_switch_client->setBankCode($bank_code);

        if( $sender_address = $request->getOption()->getValue('sender_address') )
            $bni_switch_client->setSenderAddress($sender_address);
        if( $sender_phone = $request->getOption()->getValue('sender_phone') )
            $bni_switch_client->setSenderPhone($sender_phone);
        if( $sender_fullname = $request->getOption()->getValue('sender_fullname') )
            $bni_switch_client->setSenderFullname($sender_fullname);
        if( $receiver_address = $request->getOption()->getValue('receiver_address') )
            $bni_switch_client->setReceiverAddress($receiver_address);
        if( $receiver_mobile_phone = $request->getOption()->getValue('receiver_mobile_phone') )
            $bni_switch_client->setReceiverMobilePhone($receiver_mobile_phone);

        if( $receiver_gender = $request->getOption()->getValue('receiver_gender') )
            $bni_switch_client->setReceiverGender($receiver_gender);
        if( $receiver_birth_date = $request->getOption()->getValue('receiver_birth_date') )
            $bni_switch_client->setReceiverBirthDate($receiver_birth_date);
        if( $receiver_email = $request->getOption()->getValue('receiver_email') )
            $bni_switch_client->setReceiverEmail($receiver_email);
        
        
        if( $landed_currency = $request->getOption()->getValue('landed_currency') )
            $bni_switch_client->setLandedCurrency($landed_currency);

        $transDate = IappsDateTime::fromString($request->getDateOfTransfer());
        $bni_switch_client->setTransDate($transDate->getFormat('Y-m-d'));

        $bni_switch_client->setLandedAmount(-1*$request->getAmount());

        $option_array = json_decode($bni_switch_client->getOption(), true);
        //set user type
        if( $user_type = $request->getOption()->getValue('user_type')) {
            $option_array['user_type'] = $user_type;
        }

        $request->getOption()->setArray($option_array);

        //this validation in main class
        $v = BNIPaymentRequestValidator::make($request);
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
            $bni_switch_client = BNISwitchClientFactory::build($request->getOption()->toArray());
        }
        catch(\Exception $e)
        {//this is internal error, should not happen
            $this->setResponseCode(MessageCode::CODE_INVALID_SWITCH_SETTING);
            return false;
        }

        if(!empty($request->getReferenceID())){
            $request->setPending();
            //Processed already
            Logger::debug('BNI Processed');
            return false;
        }

        if($response = $bni_switch_client->bankTransfer() )
        {

            $result = $this->_checkResponse($request, $response);
            $request->getResponse()->setJson(json_encode(array("BNI Bank Transfer"=>$bni_switch_client->getTransactionType())));

            $request->getResponse()->add('bni_response', $response->getFormattedResponse());
            $request->getResponse()->add('bni_trx_date', $bni_switch_client->getTransDateBni());

            $request->setReferenceID($bni_switch_client->getReferenceNo());

            if( $result ) {
                //$request->setReferenceID($response->getRefNoSwitcher());
                $request->setReferenceID($bni_switch_client->getReferenceNoBni());
                return parent::_completeAction($request);
            }else{
                if($request->getStatus()==PaymentRequestStatus::FAIL){
                    $this->setResponseMessage($response->getDescription());
                    Logger::debug('BNI Failed - ' . $request->getStatus() . ': ' . $response->getResponse());
                }
            }
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
            $bni_switch_client = BNISwitchClientFactory::build($request->getOption()->toArray());
        }
        catch(\Exception $e)
        {//this is internal error, should not happen
            $this->setResponseCode(MessageCode::CODE_INVALID_SWITCH_SETTING);
            return false;
        }


        if($response = $bni_switch_client->inquiry() ) {
            $ori_request = clone($request);


            $result = $this->_checkResponse($request, $response);

            $this->getRepository()->startDBTransaction();
            if ($result) {
                if ($complete = parent::_completeAction($request)) {
                    $request->getResponse()->setJson(json_encode(array("reprocess"=>$bni_switch_client->getTransactionType())));
                    $request->getResponse()->add('bni_response', $response->getFormattedResponse());
                    $request->setReferenceID($response->getInvoiceNo());

                    if (parent::_updatePaymentRequestStatus($request, $ori_request)) {
                        $this->getRepository()->completeDBTransaction();
                        $this->setResponseCode(MessageCode::CODE_REQUEST_COMPLETED);
                        return true;
                    } else {
                        Logger::debug("reprocessRequest failed");
                        $this->getRepository()->rollbackDBTransaction();
                        $this->setResponseCode(MessageCode::CODE_PAYMENT_REQUEST_FAIL); //***
                        return false;
                    }
                }
            } else {//failed or still processing
                $request->getResponse()->setJson(json_encode(array("reprocess"=>$bni_switch_client->getTransactionType())));
                $request->getResponse()->add('bni_response', $response->getFormattedResponse());
                if ($request->getStatus() == PaymentRequestStatus::FAIL) {
                    $this->setResponseMessage($response->getRemarks());
                    $request->setFail();
                    $this->getRepository()->updateResponse($request);
                    $this->getRepository()->completeDBTransaction();
                    return true;
                }elseif (in_array($response->getResponseStatus(), array(BNISwitchFunction::BNI_STATUS_OUTSTANDING, BNISwitchFunction::BNI_STATUS_INPROCESS))) {
                    $this->getRepository()->updateResponse($request);
                    $this->getRepository()->completeDBTransaction();
                    return false;
                }
            }
        }
        $this->getRepository()->rollbackDBTransaction();
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
            $bni_switch_client = BNISwitchClientFactory::build($request->getOption()->toArray());
        }
        catch(\Exception $e)
        {//this is internal error, should not happen
            $this->setResponseCode(MessageCode::CODE_INVALID_SWITCH_SETTING);
            return false;
        }


        if($response = $bni_switch_client->inquiry() ) {
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

    public function _checkTrx(PaymentRequest $request)
    {
        //make chcekTrx to switch
        try{
            $bni_switch_client = BNISwitchClientFactory::build($request->getOption()->toArray());
        }
        catch(\Exception $e)
        {//this is internal error, should not happen
            $this->setResponseCode(MessageCode::CODE_INVALID_SWITCH_SETTING);
            return false;
        }

        if($response = $bni_switch_client->checkTrx() )
        {
            $result = $this->_checkResponse($request, $response);
            $request->getResponse()->add('bni_ref_no', $response->getBniRefNo());
            $request->setFirstCheckRemarks($response->getBniRefNo());
            if($response->getResponseStatus() =='Has Been Paid'){
                //$request->setFirstCheckStatus('approved');
                $request->setFirstCheckStatus(NULL);

            }

            if( $result ) {
                return parent::_checkTrx($request);
            }else{
                if($request->getStatus()==PaymentRequestStatus::FAIL){
                    $this->setResponseMessage($response->getDescription());
                    Logger::debug('BNI Failed - ' . $request->getStatus() . ': ' . $response->getResponse());
                }
            }
        }

        return false;
    }

}