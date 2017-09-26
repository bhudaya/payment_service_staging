<?php

namespace Iapps\PaymentService\Currency;

class CurrencyServiceFactory{

    protected static $_instance = NULL;

    public static function build()
    {
        if( CurrencyServiceFactory::$_instance == NULL )
        {
            $_ci = get_instance();
            $_ci->load->model('currency/Currency_model');
            $repo = new CurrencyRepository($_ci->Currency_model);
            CurrencyServiceFactory::$_instance = new CurrencyService($repo);
        }

        return CurrencyServiceFactory::$_instance;
    }
}