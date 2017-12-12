<?php

namespace Iapps\PaymentService\PaymentRequest;

use Iapps\Common\Core\IappsBasicBaseService;
use Iapps\PaymentService\Common\PaymentEventProducer;
use Iapps\PaymentService\PaymentMode\PaymentModeType;

class BNIRetryTransactionService extends IappsBasicBaseService{

    public function process()
    {
        $BNIPaymentReqServ = PaymentRequestServiceFactory::build(PaymentModeType::BANK_TRANSFER_BNI);
        $BNIPaymentReqServ->setUpdatedBy($this->getUpdatedBy());
        $BNIPaymentReqServ->setIpAddress($this->getIpAddress());
        $this->_retryRequest($BNIPaymentReqServ);

        return true;
    }

    protected function _retryRequest(BNIPaymentRequestService $paymentRequestServ)
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