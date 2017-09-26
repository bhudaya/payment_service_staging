<?php

namespace Iapps\PaymentService\PaymentRequestValidator;

use Iapps\PaymentService\Common\TransferToSwitch\TransferToSwitchClient;
use Iapps\PaymentService\Common\TransferToSwitch\TransferToSwitchClientFactory;
use Iapps\PaymentService\Common\TransferToSwitch\TransferToSwitchClientValidator;
use Iapps\PaymentService\Common\MessageCode;
use Iapps\PaymentService\PaymentAccess\PaymentAccessCheckerFactory;
use Iapps\PaymentService\PaymentRequest\PaymentRequest;

class TransferToPaymentRequestValidator extends PaymentRequestValidator
{
    public function validate()
    {
        parent::validate();

        if( !$this->fails() )
        {
            if( !$this->_validateSwitchParameter() )
               $this->isFailed = true;
        }

        $this->isFailed = false;
    }

    protected function _validateCountryCurrency()
    {
        if( parent::_validateCountryCurrency() )
        {
            return $this->request->getCountryCurrencyCode() == 'ID-IDR';
            //return $this->request->setCountryCurrencyCode('PH-PHP');
        }

        return false;
    }

    protected function _validateSwitchParameter()
    {
        $client = TransferToSwitchClientFactory::buildFromOption($this->request->getOption()->toArray());
        $v = TransferToSwitchClientValidator::make($client);

        return !$v->fails();
    }
}