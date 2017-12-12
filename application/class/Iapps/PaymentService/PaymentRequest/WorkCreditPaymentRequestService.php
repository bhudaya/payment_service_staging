<?php

namespace Iapps\PaymentService\PaymentRequest;

use Iapps\Common\Microservice\AccountService\AccountServiceFactory;
use Iapps\PaymentService\Common\EwalletClient\WorkCreditUtilizationClientFactory;
use Iapps\PaymentService\Common\Logger;
use Iapps\PaymentService\Common\MessageCode;
use Iapps\PaymentService\Payment\PaymentDescription;
use Iapps\PaymentService\PaymentMode\PaymentModeType;
use Iapps\Common\Microservice\AccountService\AccountService;
use Iapps\Common\Helper\RequestHeader;

class WorkCreditPaymentRequestService extends PaymentRequestService
{
    function __construct(PaymentRequestRepository $rp, $ipAddress = '127.0.0.1', $updatedBy = NULL)
    {
        parent::__construct($rp, $ipAddress, $updatedBy);

        $this->payment_code = PaymentModeType::MAIN_AGENT_WORK_CREDIT;
    }

    protected function _isCollectionRequest(PaymentRequest $request)
    {
        $option = $request->getOption();

        if( $isCollection = $option->getValue('is_collection') )
        {
            if( $isCollection == 1)
                return true;
        }

        return false;
    }

    protected function _requestAction(PaymentRequest $request)
    {        
        try{
            //get workcredit holder
            $holder = $this->_getWorkCreditHolder();

            $client = WorkCreditUtilizationClientFactory::build($request->getAmount(), $this->getPaymentRequestClient(), $holder->getId());

            $client->setModuleCode($request->getModuleCode());
            $client->setTransactionID($request->getTransactionID());
            $client->setCountryCurrencyCode($request->getCountryCurrencyCode());
            $client->setAmount($request->getAmount());
            if( $this->_isCollectionRequest($request) )
                $client->setIsCollection(true);

            $response = $client->request();

            $request->getOption()->add('workcredit_request', $client->getOption());
            $request->getResponse()->add('workcredit_request', $response->getResponse());

            if( !$response->isSuccess() )
            {
                $lastResponse = $client->getLastResponse();
                if( isset($lastResponse['status_code']) )
                    $this->setResponseCode($lastResponse['status_code']);

                if( isset($lastResponse['message']) )
                    $this->setResponseMessage($lastResponse['message']);

                $request->setStatus(PaymentRequestStatus::FAIL);
            }
            else
            {
                $request->setReferenceID($response->getRequestToken());
                $request->setStatus(PaymentRequestStatus::PENDING);
            }

            return $response->isSuccess();
        } catch (\Exception $ex) {
            Logger::error($ex->getMessage());
            $this->setResponseCode($ex->getCode());
            return false;
        }
        
        
    }

    protected function _cancelAction(PaymentRequest $request)
    {
        try{
            //get workcredit holder
            $holder = $this->_getWorkCreditHolder();
            //make request to ewallet service
            $client = WorkCreditUtilizationClientFactory::build($request->getAmount(), $this->getPaymentRequestClient(), $holder->getId());

            $client->setToken($request->getReferenceID());
            if( $this->_isCollectionRequest($request) )
                $client->setIsCollection(true);

            if( $client->cancel() )
            {
                return true;
            }

            $lastResponse = $client->getLastResponse();
            if( isset($lastResponse['status_code']) )
                $this->setResponseCode($lastResponse['status_code']);

            if( isset($lastResponse['message']) )
                $this->setResponseMessage($lastResponse['message']);

            return false;
        } catch (\Exception $ex) {
            Logger::error($ex->getMessage());
            $this->setResponseCode($ex->getCode());
            return false;
        }
    }

    protected function _completeAction(PaymentRequest $request)
    {
        try{
            //get workcredit holder
            $holder = $this->_getWorkCreditHolder();
            //make request to ewallet service
            $client = WorkCreditUtilizationClientFactory::build($request->getAmount(), $this->getPaymentRequestClient(), $holder->getId());

            $client->setToken($request->getReferenceID());
            if( $this->_isCollectionRequest($request) )
                $client->setIsCollection(true);

            if( $client->complete() )
            {
                $request->getResponse()->add('workcredit_complete', $client->getLastResponse());
                $request->setAgentId($holder->getId());
                return parent::_completeAction($request);
            }

            $lastResponse = $client->getLastResponse();
            if( isset($lastResponse['status_code']) )
                $this->setResponseCode($lastResponse['status_code']);

            if( isset($lastResponse['message']) )
                $this->setResponseMessage($lastResponse['message']);

            return false;
        } catch (\Exception $ex) {
            Logger::error($ex->getMessage());
            $this->setResponseCode($ex->getCode());
            return false;
        }                    
    }

    protected function _generateDetail1(PaymentRequest $request)
    {
        if($this->getPaymentRequestClient() == PaymentRequestClient::AGENT) {
            //get main agent detail
            $storeName = 'unknown';

            if ($mainAgent = $this->_getMainAgent())
                $storeName = $mainAgent->getName();

            $desc = new PaymentDescription();
            $desc->add('', 'You were served by ' . $storeName);

            $request->setDetail1($desc);
            return true;
        }

        /*
         * The balance info available in different response regard of utilize or refund
         */
        if( $request->getAmount() >= 0 )
            $resp = $request->getResponse()->getValue('ewallet_request');
        else
            $resp = $request->getResponse()->getValue('ewallet_complete');

        //get agent detail
        if( $resp )
        {
            if( !is_array($resp) )
                $resp = json_decode($resp, true);

            $resp = json_decode(json_encode($resp), true);

            $initial_balance = null;
            if( isset($resp['response']['result']['balance']['initial'] ) )
            {
                $initial_balance = $resp['response']['result']['balance']['initial'];
            }
            elseif( isset($resp['result']['balance']['initial']) )
            {
                $initial_balance = $resp['result']['balance']['initial'];
            }

            $desc = new PaymentDescription();
            $desc->add('Initial Balance', $initial_balance);

            $request->setDetail1($desc);
        }

        return true;
    }

    //get agent detail
    protected function _generateDetail2(PaymentRequest $request)
    {
        if($this->getPaymentRequestClient() == PaymentRequestClient::AGENT) {
            //get agent detail
            $acc_serv = AccountServiceFactory::build();
            if ($agent = $acc_serv->getUser(NULL, $this->getUpdatedBy())) {
                $desc = new PaymentDescription();
                $desc->add('', $agent->getName() . ', ID: ' . $agent->getAccountID());

                $request->setDetail2($desc);
                return true;
            }
        }

        /*
         * The balance info available in different response regard of utilize or refund
         */
        if( $request->getAmount() >= 0 )
            $resp = $request->getResponse()->getValue('ewallet_request');
        else
            $resp = $request->getResponse()->getValue('ewallet_complete');

        if( $resp )
        {
            if( !is_array($resp) )
                $resp = json_decode($resp, true);

            $resp = json_decode(json_encode($resp), true);
            $new_balance = null;
            if( isset($resp['response']['result']['balance']['new']) )
            {
                $new_balance = $resp['response']['result']['balance']['new'];
            }
            elseif( isset($resp['result']['balance']['new']) )
            {
                $new_balance = $resp['result']['balance']['new'];
            }

            $desc = new PaymentDescription();
            $desc->add('New Balance', $new_balance);

            $request->setDetail2($desc);
        }

        return true;
    }

    /**
     * 
     * @return User
     * @throws \Exception
     */
    protected function _getWorkCreditHolder()
    {
        $headers = RequestHeader::get();
        $headers['X-app'] = getenv("SLIDE_APPID");  //workaround! these APIs only works with system level call
        unset($headers['X-version']);
        $acc_serv = new AccountService(array(
                'header' => $headers
            ));
        if( !$requestor = $acc_serv->getUser(null, $this->_request->getUserProfileId()) )
            throw new \Exception("Invalid user structure: " . $this->_request->getUserProfileId(), MessageCode::CODE_USER_NOT_FOUND);
        
        $functions = $acc_serv->getUserRoleFunctionsByUserProfileId($this->_request->getUserProfileId());
        if( isset($functions->main_agent_functions) OR
            isset($functions->store_franchise_agent_functions) )
            return $requestor;
        
        if( !$structure = $acc_serv->getUplineStructure($this->_request->getUserProfileId()) )
            throw new \Exception("Invalid user structure: " . $this->_request->getUserProfileId(), MessageCode::CODE_USER_NOT_FOUND);

        if( isset($functions->store_branch_agent_functions) OR 
            isset($functions->store_branch_agt_staff_functions) )
        {//get main agent
            return $this->_getMainAgentFromStructure($structure);
        }
        
        if( isset($functions->store_franchise_agt_staff_functions) )
        {//get franchise
            return $this->_getFranchiseFromStructure($structure);
        }
        
        //else 
        return $requestor;        
    }
    
    protected function _getMainAgentFromStructure($structure)
    {
        if( $upline = $structure->first_upline )
        {
            if( $upline->getRoles()->hasRole(array('main_agent')) )
            {
                return $upline->getUser();
            }
        }

        if( $upline = $structure->second_upline )
        {
            if( $upline->getRoles()->hasRole(array('main_agent')) )
            {
                return $upline->getUser();
            }
        }
        
        throw new \Exception("No Main Agent Found", MessageCode::CODE_USER_NOT_FOUND);
    }
    
    protected function _getFranchiseFromStructure($structure)
    {
        if( $upline = $structure->first_upline )
        {
            if( $upline->getRoles()->hasRole(array('store_franchise_agent')) )
            {
                return $upline->getUser();
            }
        }
        
        throw new \Exception("No Franchise Found", MessageCode::CODE_USER_NOT_FOUND);
    }

    protected function _getMainAgent()
    {
        $acc_serv = AccountServiceFactory::build();
        if( $structure = $acc_serv->getAgentUplineStructure() )
        {
            if( $upline = $structure->first_upline )
            {
                if( $upline->getRoles()->hasRole(array('main_agent')) )
                {
                    return $upline->getUser();
                }
            }

            if( $upline = $structure->second_upline )
            {
                if( $upline->getRoles()->hasRole(array('main_agent')) )
                {
                    return $upline->getUser();
                }
            }
        }

        return false;
    }
}
