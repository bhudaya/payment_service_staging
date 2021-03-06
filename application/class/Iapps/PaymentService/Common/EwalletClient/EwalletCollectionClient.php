<?php

namespace Iapps\PaymentService\Common\EwalletClient;

class EwalletCollectionClient extends EwalletClient{

    protected $_requestUri = 'ewallet/collection/request';
    protected $_cancelUri = 'ewallet/collection/cancel';
    protected $_completeUri = 'ewallet/collection/complete';

    protected $user_profile_id;
    protected $is_collection = false; //default is refund
    protected $is_investment_return = false; //default is refund

    public function setUserProfileId($user_id)
    {
        $this->user_profile_id = $user_id;
        return $this;
    }

    public function getUserProfileId()
    {
        return $this->user_profile_id;
    }

    public function setIsCollection($is_collection)
    {
        $this->is_collection = $is_collection;
        return $this;
    }

    public function getIsCollection()
    {
        return $this->is_collection;
    }

    public function setIsInvestmentReturn($is_investment_return)
    {
        $this->is_investment_return = $is_investment_return;
        return $this;
    }

    public function getIsInvestmentReturn()
    {
        return $this->is_investment_return;
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
                             'amount' => $this->getAmount(),
                             'is_collection' => $this->getIsCollection(),
                             'is_investment_return' => $this->getIsInvestmentReturn() ),
            'header' => $this->_getHeaders()
        );

        if( $resp = $this->_microServ->call($option) )
            $response['success'] = true;
        else
            $response['success'] = false;

        $response['response'] = $this->_microServ->getLastReponse();

        $this->setLastResponse($this->_microServ->getLastReponse());
        return new EwalletCollectionClientResponse($response);
    }

    public function cancel()
    {
        $option = array(
            'method' => 'post',
            'uri' => $this->_cancelUri,
            'param' => array('request_token' => $this->getToken(),
                             'is_collection' => $this->getIsCollection(),
                             'is_investment_return' => $this->getIsInvestmentReturn()),
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
            'param' => array('request_token' => $this->getToken(),
                             'is_collection' => $this->getIsCollection(),
                             'is_investment_return' => $this->getIsInvestmentReturn()),
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