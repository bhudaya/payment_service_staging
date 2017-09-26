<?php

namespace Iapps\PaymentService\PaymentRequest;

use Iapps\Common\Core\IappsBasicBaseService;
use Iapps\PaymentService\Common\PaymentEventProducer;
use Iapps\PaymentService\PaymentMode\PaymentModeType;

class TransfertoRetryTransactionService extends IappsBasicBaseService{

    public function process()
    {
        $PaymentReqServ = PaymentRequestServiceFactory::build(PaymentModeType::BANK_TRANSFER_TRANSFERTO);
        $PaymentReqServ->setUpdatedBy($this->getUpdatedBy());
        $PaymentReqServ->setIpAddress($this->getIpAddress());
        $this->_retryRequest($PaymentReqServ);

        return true;
    }

    protected function _retryRequest(TransfertoPaymentRequestService $paymentRequestServ)
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