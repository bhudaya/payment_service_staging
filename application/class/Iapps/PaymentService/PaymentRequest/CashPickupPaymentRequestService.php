<?php

namespace Iapps\PaymentService\PaymentRequest;

use Iapps\Common\Helper\RequestHeader;
use Iapps\Common\Helper\ResponseHeader;
use Iapps\PaymentService\PaymentMode\PaymentModeType;
use Iapps\PaymentService\Common\MessageCode;
use Iapps\PaymentService\Common\Logger;
use Iapps\PaymentService\PaymentMode\PaymentModeServiceFactory;

class CashPickupPaymentRequestService extends PaymentRequestService{

    function __construct(PaymentRequestRepository $rp, $ipAddress = '127.0.0.1', $updatedBy = NULL)
    {
        parent::__construct($rp, $ipAddress, $updatedBy);

        $this->payment_code = PaymentModeType::CASH_PICKUP;
    }

    public function request($user_profile_id, $module_code, $transaction_id, $country_currency_code, $amount, array $option)
    {
        //add admin access token as option
        $headers = RequestHeader::get();
        $option['token'] = NULL;
        if( array_key_exists(ResponseHeader::FIELD_X_AUTHORIZATION, $headers) )
            $option['token'] = $headers[ResponseHeader::FIELD_X_AUTHORIZATION];

        return parent::request($user_profile_id, $module_code, $transaction_id, $country_currency_code, $amount, $option);
    }
    
    protected function _validateComplete(PaymentRequest $request) {
        if( parent::_validateComplete($request) )
        {
            try{
                $externalResponse = $this->_validateExternalResponse($request);
                $request->setReferenceID($externalResponse['reference_no']);
                return true;
            } catch (\Exception $ex) {
                Logger::debug($ex->getMessage());
                $this->setResponseCode($ex->getCode());
                return false;
            }
        }
        
        return false;
    }
    protected function _completeAction(PaymentRequest $request) {
        //set to pending collection
        $request->setPendingCollection();
        return false;   //this will handle pending request
    }
    
    protected function _validateExternalResponse(PaymentRequest $request)   //throws exception
    {
        if( !$externalResponse = $request->getResponse()->getValue('external_response') )
            throw new \Exception("No external_response given", MessageCode::CODE_PAYMENT_INVALID_INFO);
        
        $externalResponse = json_decode($externalResponse, true);
        if( !isset($externalResponse['reference_no']) )
            throw new \Exception("No reference_no given", MessageCode::CODE_PAYMENT_INVALID_INFO);
        
        if( !isset($externalResponse['pin_number']) )
            throw new \Exception("No pin_number given", MessageCode::CODE_PAYMENT_INVALID_INFO);
        
        if( !isset($externalResponse['partner_system']) )
            throw new \Exception("No partner_system given", MessageCode::CODE_PAYMENT_INVALID_INFO);
        
        return $externalResponse;
    }  
    
    protected function _extractRequest($request_id, $user_profile_id, $payment_code)
    {
        if( !$request = $this->getRepository()->findById($request_id) )
            throw new \Exception("Request not found: " . $request_id, MessageCode::CODE_REQUEST_NOT_FOUND);
        
        if( !($request instanceof PaymentRequest) )
            throw new \Exception("Request not found: " . $request_id, MessageCode::CODE_REQUEST_NOT_FOUND);
        
        if( $request->getUserProfileId() != $user_profile_id )
            throw new \Exception("Request user not match: " . $request_id, MessageCode::CODE_REQUEST_NOT_FOUND);
        
        if( $request->getPaymentCode() != $payment_code )
            throw new \Exception("Request payment code not match: " . $request_id, MessageCode::CODE_REQUEST_NOT_FOUND);
            
        return $request;
    }
    
    protected function _validateStatus(PaymentRequest $request, array $statuses)
    {
        if( !in_array($request->getStatus(), $statuses) )
             throw new \Exception("Statuses not match: " . $request->getId(), MessageCode::CODE_REQUEST_IS_ALREADY_PROCESSED);
    }
    
    protected function _getPaymentModeInfo()
    {
        $pmServ = PaymentModeServiceFactory::build();
        if( !$pmInfo = $pmServ->getPaymentModeInfo($this->payment_code) )
            throw new \Exception("Invalid payment code: " . $this->payment_code, MessageCode::CODE_COUNTRY_CURRENCY_INVALID_PAYMENT_MODE);
                
        return $pmInfo;
    }


    //override
    public function updateCollection($user_profile_id, $request_id, $payment_code, $status, $remarks)
    {
        try{
            $this->_request = $this->_extractRequest($request_id, $user_profile_id, $payment_code);
            $ori_request = clone($this->_request);
            $pmInfo = $this->_getPaymentModeInfo();
            $this->_validateStatus($this->_request, array(PaymentRequestStatus::PENDING_COLLECTION));
            
            if( $pmInfo['self_service'] == 1 )   //cant update if self service
                throw new \Exception("Not allow to allow: " . $this->payment_code, MessageCode::CODE_PAYMENT_NOT_ACCESSIBLE);
            
            $this->getRepository()->startDBTransaction();
            $this->_request->getResponse()->add('remarks', $remarks);
            if( $status == PaymentRequestStatus::SUCCESS )
            {
                $this->_request->setSuccess();
                if( !PaymentRequestService::_completeAction($this->_request) )
                {//return false
                    $this->setResponseCode(MessageCode::CODE_PAYMENT_REQUEST_CHECK_UPDATE_FAIL);
                    return false;
                }
            }
            else
                $this->_request->setFail();
                
            if ($this->_updatePaymentRequestStatus($this->_request, $ori_request))
            {
                $this->getRepository()->completeDBTransaction();
                $this->setResponseCode(MessageCode::CODE_PAYMENT_REQUEST_CHECK_UPDATE_SUCCESS);
                return $this->_request->getSelectedField(array('id', 'module_code', 'transactionID', 'payment_code', 'country_currency_code', 'amount', 'status', 'reference_id', 'response'));
            }
            
            throw new \Exception("Update failed: " . $request->getId(), MessageCode::CODE_PAYMENT_REQUEST_CHECK_UPDATE_FAIL);
        }
        catch (\Exception $ex)
        {
            Logger::debug($ex->getMessage());
            $this->setResponseCode($ex->getCode());
            return false;
        }
    }
}