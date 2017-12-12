<?php

namespace Iapps\PaymentService\PaymentRequest;

use Iapps\Common\Core\IappsBasicBaseService;
use Iapps\PaymentService\Common\PaymentEventProducer;
use Iapps\PaymentService\PaymentMode\PaymentModeType;

class TMoneyCheckServerService extends IappsBasicBaseService{

    public function process()   //called by  batch job
    {
        $TMoneyPaymentReqServ = PaymentRequestServiceFactory::build(PaymentModeType::BANK_TRANSFER_TMONEY);
        $TMoneyPaymentReqServ->setUpdatedBy($this->getUpdatedBy());
        $TMoneyPaymentReqServ->setIpAddress($this->getIpAddress());

        if($TMoneyPaymentReqServ->checkTmoneyServer()){
            return true;
        }           
        
        return false;
    }


}