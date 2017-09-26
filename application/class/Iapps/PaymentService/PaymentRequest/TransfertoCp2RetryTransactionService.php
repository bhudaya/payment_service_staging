<?php

namespace Iapps\PaymentService\PaymentRequest;

use Iapps\Common\Core\IappsBasicBaseService;
use Iapps\PaymentService\Common\PaymentEventProducer;
use Iapps\PaymentService\PaymentMode\PaymentModeType;

class TransfertoCp2RetryTransactionService extends IappsBasicBaseService{

    public function process()
    {
        $PaymentReqServ = PaymentRequestServiceFactory::build(PaymentModeType::CASH_PICKUP_TRANSFERTO_2);
        $PaymentReqServ->setUpdatedBy($this->getUpdatedBy());
        $PaymentReqServ->setIpAddress($this->getIpAddress());
        $this->_retryRequest($PaymentReqServ);

        return true;
    }

    protected function _retryRequest(TransferToCashPickupPaymentRequestService2 $paymentRequestServ)
    {
        $i = 0;
        if ($requests = $paymentRequestServ->findPendingRequest()){
            foreach ($requests->result as $req) {
                if ($req instanceof PaymentRequest) {
                     if ($response = $paymentRequestServ->reprocessRequest($req)) {
                            PaymentEventProducer::publishPaymentRequestChanged($req->getModuleCode(), $req->getTransactionID(), $req->getPaymentCode());
                     }
                }
                $i++;
            }
        }
    }
}