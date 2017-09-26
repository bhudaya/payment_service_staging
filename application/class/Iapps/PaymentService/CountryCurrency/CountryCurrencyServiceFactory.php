<?php

namespace Iapps\PaymentService\CountryCurrency;

class CountryCurrencyServiceFactory {

    protected static $_instance;

    public static function build()
    {
        if( self::$_instance == NULL )
        {
            $_ci = get_instance();
            $_ci->load->model('countrycurrency/Country_currency_model');
            $repo = new CountryCurrencyRepository($_ci->Country_currency_model);
            self::$_instance = new CountryCurrencyService($repo);
        }

        return self::$_instance;
    }
}