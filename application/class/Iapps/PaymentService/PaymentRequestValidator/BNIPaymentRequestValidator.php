<?php

namespace Iapps\PaymentService\PaymentRequestValidator;

use Iapps\PaymentService\Common\BNISwitch\BNISwitchClient;
use Iapps\PaymentService\Common\BNISwitch\BNISwitchClientFactory;
use Iapps\PaymentService\Common\BNISwitch\BNISwitchClientValidator;
use Iapps\PaymentService\Common\MessageCode;
use Iapps\PaymentService\PaymentAccess\PaymentAccessCheckerFactory;
use Iapps\PaymentService\PaymentRequest\PaymentRequest;

class BNIPaymentRequestValidator extends PaymentRequestValidator
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
        }

        return false;
    }

    protected function _validateSwitchParameter()
    {
        $client = BNISwitchClientFactory::buildFromOption($this->request->getOption()->toArray());
        $v = BNISwitchClientValidator::make($client);

        return !$v->fails();
    }
}