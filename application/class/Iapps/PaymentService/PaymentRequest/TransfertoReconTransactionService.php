<?php

namespace Iapps\PaymentService\PaymentRequest;

use Iapps\Common\Core\IappsBasicBaseService;
use Iapps\PaymentService\Common\PaymentEventProducer;
use Iapps\PaymentService\PaymentMode\PaymentModeType;

class TransfertoReconTransactionService extends IappsBasicBaseService{

    public function process($trx_date)
    {
        $PaymentReqServ = PaymentRequestServiceFactory::build(PaymentModeType::BANK_TRANSFER_TRANSFERTO);
        $PaymentReqServ->setUpdatedBy($this->getUpdatedBy());
        $PaymentReqServ->setIpAddress($this->getIpAddress());
        $this->_reconTransaction($PaymentReqServ,$trx_date);
        return true;
    }

    protected function _reconTransaction(TransfertoPaymentRequestService $paymentRequestServ,$trx_date)
    {
        $paymentRequestServ->reconTransaction($trx_date);
    }
}