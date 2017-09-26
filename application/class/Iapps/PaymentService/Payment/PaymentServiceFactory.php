<?php

namespace Iapps\PaymentService\Payment;

class PaymentServiceFactory{

    protected static $_instance = array();

    public static function build()
    {
        if (self::$_instance == NULL) {
            $_ci = get_instance();
            $_ci->load->model('payment/Payment_model');
            $repo = new PaymentRepository($_ci->Payment_model);
            self::$_instance = new PaymentService($repo);
        }

        return self::$_instance;
    }
}