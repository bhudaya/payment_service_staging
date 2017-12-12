<?php

namespace Iapps\PaymentService\PaymentMode;

use Iapps\Common\Core\IappsBasicBaseService;
use Iapps\PaymentService\Common\MessageCode;
use Iapps\PaymentService\Common\Logger;
use Iapps\Common\Microservice\AccountService\AccountServiceFactory;
use Iapps\PaymentService\Common\PaymentDirection;
use Iapps\PaymentService\Common\FunctionCode;
use Iapps\PaymentService\PaymentRequest\PaymentRequestStaticChannel;

class SupportedPaymentModeListingService extends IappsBasicBaseService{
        
    public function getSelfService()
    {
        try{
            $pmServ = PaymentModeServiceFactory::build();
            if( !$pmlist = $pmServ->getAllPaymentMode() )  //get all
                throw new \Exception("Failed to get payment mode list", MessageCode::CODE_GET_PAYMENT_MODE_SUCCESS);
            
            //filter by self service channel
            $filteredList = new PaymentModeCollection();
            foreach($pmlist->getResult() AS $pm)
            {
                if( $pm->isChannelSupportedForSelfService(PaymentRequestStaticChannel::$channelCode) )
                    $filteredList->addData($pm);
            }
            
            $this->setResponseCode(MessageCode::CODE_GET_PAYMENT_MODE_SUCCESS);
            return $filteredList->getFieldValues("code");
        } catch (\Exception $ex) {
            Logger::debug($ex->getMessage());
            $this->setResponseCode($ex->getCode());
            return false;
        }        
    }
    
    public function getByFunction($access_token, $direction, $access_type = NULL)
    {
        $payment_mode_arr = array();

        $account_serv = AccountServiceFactory::build();

        if ($direction == PaymentDirection::IN) {
            if ($account_serv->checkAccess($access_token, FunctionCode::COUNTER_CASHIN, $access_type)) {
                $payment_mode_arr = array(PaymentModeType::STORE_CASH, PaymentModeType::EWALLET);
            } else if ($account_serv->checkAccess($access_token, FunctionCode::MOBILE_CASHIN, $access_type)) {
                $payment_mode_arr = array(PaymentModeType::MOBILE_AGENT_CASH, PaymentModeType::EWALLET);
            } else if ($account_serv->checkAccess($access_token, FunctionCode::ADMIN_PAYMENT_IN, $access_type)) {
                $payment_mode_arr = array(PaymentModeType::ADMIN_CASH);
            } else if ($account_serv->checkAccess($access_token, FunctionCode::PARTNER_PAYMENT_IN, $access_type)) {
                $payment_mode_arr = array(PaymentModeType::PARTNER_CASH);
            } else if ($account_serv->checkAccess($access_token, FunctionCode::FRANCHISE_CASHIN, $access_type)) {
                $payment_mode_arr[] = PaymentModeType::FRANCHISE_CASH;
            }
        } else if ($direction == PaymentDirection::OUT) {
            if ($account_serv->checkAccess($access_token, FunctionCode::COUNTER_CASHOUT, $access_type)) {
                $payment_mode_arr[] = PaymentModeType::STORE_CASH;
            } else if ($account_serv->checkAccess($access_token, FunctionCode::MOBILE_CASHOUT, $access_type)) {
                $payment_mode_arr[] = PaymentModeType::MOBILE_AGENT_CASH;
            } else if ($account_serv->checkAccess($access_token, FunctionCode::ADMIN_PAYMENT_OUT, $access_type)) {
                $payment_mode_arr = array(PaymentModeType::ADMIN_CASH, PaymentModeType::EWALLET, PaymentModeType::ADMIN_BANK_TRANSFER);
            } else if ($account_serv->checkAccess($access_token, FunctionCode::PARTNER_PAYMENT_OUT, $access_type)) {
                $payment_mode_arr = array(PaymentModeType::PARTNER_CASH, PaymentModeType::EWALLET);
            } else if ($account_serv->checkAccess($access_token, FunctionCode::FRANCHISE_CASHOUT, $access_type)) {
                $payment_mode_arr = array(PaymentModeType::FRANCHISE_CASH);
            }
        }

        $this->setResponseCode(MessageCode::CODE_GET_PAYMENT_MODE_SUCCESS);
        return $payment_mode_arr;
    }
}