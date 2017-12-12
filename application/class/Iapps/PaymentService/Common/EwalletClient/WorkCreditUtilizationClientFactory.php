<?php

namespace Iapps\PaymentService\Common\EwalletClient;

use Iapps\Common\Helper\RequestHeader;
use Iapps\PaymentService\PaymentRequest\PaymentRequestClient;

class WorkCreditUtilizationClientFactory{

    protected static $_instance = array();

    public static function build($amount, $payment_request_client = NULL, $user_id = NULL)
    {
        $type = 'utilise';
        if( $amount < 0 )
            $type = 'refund';

        if( !array_key_exists($type, self::$_instance) )
        {
            if( !$url = getenv('EWALLET_SERVICE_URL') )
                throw new \Exception('Ewallet Service URL Is Not Defined');
            
            $header = RequestHeader::get();
            $header['X-app'] = getenv('SLIDE_APPID');
            unset($header['X-version']);

            if( $type == 'utilise' )
            {
                self::$_instance[$type] = new SystemWorkCreditUtilizationClient(array(
                        'base_url' => $url,
                        'header' => $header
                    ));                
            }
            else
            {
                $header = RequestHeader::get();
                $header['X-app'] = getenv('SLIDE_APPID');
                unset($header['X-version']);
                self::$_instance[$type] = new WorkCreditRefundClient(array(
                    'base_url' => $url,
                    'header' => $header
                ));
            }
        }

        self::$_instance[$type]->setUserProfileId($user_id);

        return self::$_instance[$type];
    }
}