<?php

namespace Iapps\PaymentService\PaymentSearch;

use Iapps\Common\Core\IappsBaseService;
use Iapps\PaymentService\Common\MessageCode;
use Iapps\Common\Core\EntitySelector;

class PaymentSearchService extends IappsBaseService{
    
    protected $paymentSelector;
    protected static $_instance;

    /**
     * 
     * @return PaymentSearchService
     */
    public static function build()
    {
        if( self::$_instance == NULL )
        {
            $_ci = get_instance();
            $_ci->load->model('payment/Payment_search_model');
            $repo = new PaymentSearchRepository($_ci->Payment_search_model);
            self::$_instance = new PaymentSearchService($repo);
        }

        return self::$_instance;
    }
            
    function __construct(PaymentSearchRepository $rp, $ipAddress = '127.0.0.1', $updatedBy = NULL) {
        parent::__construct($rp, $ipAddress, $updatedBy);
        
        $this->paymentSelector = new EntitySelector();
    }
    
    public function setPaymentSelector(EntitySelector $selector)
    {
        $this->paymentSelector = $selector;
        return $this;
    }

    /**
     * 
     * @return EntitySelector
     */
    public function getPaymentSelector()
    {
        return $this->paymentSelector;
    }
    
    public function search()
    {
        if( count($this->paymentSelector) <= 0 )
        {
            $this->setResponseCode(MessageCode::CODE_GET_PAYMENT_LIST_FAILED);
            return false;
        }

        if( $info = $this->getRepository()->findByFilters($this->paymentSelector) )
        {
            $this->setResponseCode(MessageCode::CODE_GET_PAYMENT_LIST_SUCCESS);
            return $info;
        }

        $this->setResponseCode(MessageCode::CODE_GET_PAYMENT_LIST_FAILED);
        return false;
    }
}

