<?php

namespace Iapps\PaymentService\Common\EwalletClient;

use Iapps\Common\Helper\MicroserviceHelper\MicroserviceHelper;
use Iapps\PaymentService\PaymentRequest\PaymentRequestClientInterface;

class SystemWorkCreditUtilizationClient extends EwalletClient
{
	protected $_requestUri  = 'system/mainagent/workcredit/utilize/request';
    protected $_cancelUri   = 'system/mainagent/workcredit/utilize/cancel';
    protected $_completeUri = 'system/mainagent/workcredit/utilize/complete';

    protected $user_profile_id;

    public function setUserProfileId($user_id)
    {
    	$this->user_profile_id = $user_id;
    	return $this;
    }

    public function getUserProfileId()
    {
    	return $this->user_profile_id;
    }

    public function getOption()
    {
        $option = array('headers' => $this->_getHeaders(),
            'user_profile_id' => $this->getUserProfileId(),
            'module_code' => $this->getModuleCode(),
            'transactionID' => $this->getTransactionID(),
            'country_currency_code' => $this->getCountryCurrencyCode(),
            'amount' => $this->getAmount() );

        return json_encode($option);
    }

    public function request()
    {
        $option = array(
            'method' => 'post',
            'uri' => $this->_requestUri,
            'param' => array('user_profile_id' => $this->getUserProfileId(),
            				'module_code' => $this->getModuleCode(),
                            'transactionID' => $this->getTransactionID(),
                            'country_currency_code' => $this->getCountryCurrencyCode(),
                            'amount' => $this->getAmount() ),
            'header' => $this->_getHeaders()
        );

        if( $resp = $this->_microServ->call($option) )
            $response['success'] = true;
        else
            $response['success'] = false;

        $response['response'] = $this->_microServ->getLastReponse();

        $this->setLastResponse($this->_microServ->getLastReponse());
        return new WorkCreditUtilizationClientResponse($response);
    }

    public function cancel()
    {
        $option = array(
            'method' => 'post',
            'uri' => $this->_cancelUri,
            'param' => array('user_profile_id' => $this->getUserProfileId(),
            				'request_token' => $this->getToken() ),
            'header' => $this->_getHeaders()
        );

        if( $resp = $this->_microServ->call($option) )
        {//cancelled
            $this->setLastResponse($this->_microServ->getLastReponse());
            return true;
        }

        $this->setLastResponse($this->_microServ->getLastReponse());
        return false;
    }

    public function complete()
    {
        $option = array(
            'method' => 'post',
            'uri' => $this->_completeUri,
            'param' => array('user_profile_id' => $this->getUserProfileId(),
            				'request_token' => $this->getToken() ),
            'header' => $this->_getHeaders()
        );

        if( $resp = $this->_microServ->call($option) )
        {//completed
            $this->setLastResponse($this->_microServ->getLastReponse());
            return true;
        }

        $this->setLastResponse($this->_microServ->getLastReponse());
        return false;
    }
}