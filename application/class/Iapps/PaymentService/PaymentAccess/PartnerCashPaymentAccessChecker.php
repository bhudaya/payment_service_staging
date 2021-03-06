<?php

namespace Iapps\PaymentService\PaymentAccess;

use Iapps\Common\Microservice\AccountService\AccessType;
use Iapps\Common\Microservice\AccountService\AccountService;
use Iapps\Common\Microservice\AccountService\AccountServiceFactory;
use Iapps\PaymentService\Common\FunctionCode;
use Iapps\Common\Microservice\AccountService\SessionType;

class PartnerCashPaymentAccessChecker extends PaymentAccessChecker
{
    public static function checkDirectionIn($token)
    {
        $acc_serv = AccountServiceFactory::build();
        return $acc_serv->checkAccess($token, FunctionCode::PARTNER_PAYMENT_IN, AccessType::WRITE, SessionType::LOGIN);
    }

    public static function checkDirectionOut($token)
    {
        $acc_serv = AccountServiceFactory::build();
        return $acc_serv->checkAccess($token, FunctionCode::PARTNER_PAYMENT_OUT, AccessType::WRITE, SessionType::LOGIN);
    }
}