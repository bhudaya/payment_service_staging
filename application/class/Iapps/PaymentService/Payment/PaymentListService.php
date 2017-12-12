<?php

namespace Iapps\PaymentService\Payment;

use Iapps\Common\Core\IappsBasicBaseService;
use Iapps\PaymentService\PaymentSearch\PaymentSearchService;
use Iapps\Common\Core\EntitySelector;
use Iapps\PaymentService\PaymentMode\PaymentModeServiceFactory;

class PaymentListService extends IappsBasicBaseService{
    
    //filters
    protected $user_profile_ids = array();
    protected $selector;
    
    function __construct($ipAddress = '127.0.0.1', $updatedBy = NULL) {
        parent::__construct($ipAddress, $updatedBy);
        $this->selector = new EntitySelector();
    }    

    /**
     * 
     * @return EntitySelector
     */
    public function getSelector()
    {
        return $this->selector;
    }
    
    public function addUserProfileId($user_profile_id)
    {
        $this->user_profile_ids[] = $user_profile_id;
        return $this;
    }
        
    public function getList()
    {
        $searchService = PaymentSearchService::build();
        
        //get filter
        $this->_constructFilter();
        $searchService->setPaymentSelector($this->selector);
        $result = $searchService->search();
        if( count($result->getResult()) > 0 )
        {
            $payment_mode_serv = PaymentModeServiceFactory::build();
            if( $info = $payment_mode_serv->getAllPaymentMode() )
            {
                $result->getResult()->joinPaymentMode($info->getResult());
            }
        }
        $this->setResponseCode($searchService->getResponseCode());
        return $result;
    }
    
    protected function _constructFilter()
    {
        $this->selector->order('created_at', false);
    }
}
