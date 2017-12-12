<?php

namespace Iapps\PaymentService\PaymentRequest;

use Iapps\PaymentService\PaymentMode\PaymentModeType;
use Iapps\PaymentService\Common\Logger;
use Iapps\PaymentService\Common\MessageCode;

class SirManualTransferPaymentRequest extends BTIndoOCBCPaymentRequestService{

    function __construct(PaymentRequestRepository $rp, $ipAddress = '127.0.0.1', $updatedBy = NULL)
    {
        parent::__construct($rp, $ipAddress, $updatedBy);

        $this->payment_code = PaymentModeType::SIR_BANK_TRANSFER_MANUAL;
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
        return PaymentRequestService::_completeAction($request);
    }
    
    protected function _validateExternalResponse(PaymentRequest $request)   //throws exception
    {
        if( !$externalResponse = $request->getResponse()->getValue('external_response') )
            throw new \Exception("No external_response given", MessageCode::CODE_PAYMENT_INVALID_INFO);
        
        $externalResponse = json_decode($externalResponse, true);
        if( !isset($externalResponse['reference_no']) )
            throw new \Exception("No reference_no given", MessageCode::CODE_PAYMENT_INVALID_INFO);
        
        if( !isset($externalResponse['partner_system']) )
            throw new \Exception("No partner_system given", MessageCode::CODE_PAYMENT_INVALID_INFO);
        
        return $externalResponse;
    }
}