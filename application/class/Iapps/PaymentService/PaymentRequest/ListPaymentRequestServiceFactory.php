<?php

namespace Iapps\PaymentService\PaymentRequest;

use Iapps\PaymentService\PaymentMode\PaymentModeType;

class ListPaymentRequestServiceFactory{
    protected static $_instance = array();

    public static function build()
    {
    	$_ci = get_instance();
    	$_ci->load->model('paymentrequest/Payment_request_model');
        $repo = new PaymentRequestRepository($_ci->Payment_request_model);
        self::$_instance = new ListPaymentRequestService($repo);

        return self::$_instance;
    }
}