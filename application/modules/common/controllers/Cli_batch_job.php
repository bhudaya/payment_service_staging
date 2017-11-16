<?php

use Iapps\Common\Helper\RequestHeader;
use Iapps\Common\Helper\ResponseHeader;
use Iapps\PaymentService\PaymentRequest\GPLInquireTransactionStatusService;
use Iapps\Common\Core\IpAddress;
use Iapps\PaymentService\Common\MessageCode;
use Iapps\PaymentService\PaymentRequest\TransfertoRetryTransactionService;
use Iapps\PaymentService\PaymentRequest\TransfertoCp2RetryTransactionService;

class Cli_batch_job extends Cli_Base_Controller{

    public function inquireGPLTransactionStatus()
    {
        if (!$system_user_id = $this->_getUserProfileId())
            return false;

        RequestHeader::set(ResponseHeader::FIELD_X_AUTHORIZATION, $this->clientToken);

        $bdo_inquiry_serv = new GPLInquireTransactionStatusService();
        $bdo_inquiry_serv->setUpdatedBy($system_user_id);
        $bdo_inquiry_serv->setIpAddress(IpAddress::fromString($this->_getIpAddress()));

        $bdo_inquiry_serv->process();
        $this->_respondWithSuccessCode(MessageCode::CODE_JOB_PROCESS_PASSED);
        return true;
    }
    
    public function retryTransfertoTransaction()
    {
        if (!$system_user_id = $this->_getUserProfileId())
            return false;

        RequestHeader::set(ResponseHeader::FIELD_X_AUTHORIZATION, $this->clientToken);

        $retry_serv = new TransfertoRetryTransactionService();
        $retry_serv->setUpdatedBy($system_user_id);
        $retry_serv->setIpAddress(IpAddress::fromString($this->_getIpAddress()));

        $retry_serv->process();
        $this->_respondWithSuccessCode(MessageCode::CODE_JOB_PROCESS_PASSED);
        return true;
    }

    public function retryTransfertoCp2Transaction()
    {
        if (!$system_user_id = $this->_getUserProfileId())
            return false;

        RequestHeader::set(ResponseHeader::FIELD_X_AUTHORIZATION, $this->clientToken);

        $retry_serv = new TransfertoCp2RetryTransactionService();
        $retry_serv->setUpdatedBy($system_user_id);
        $retry_serv->setIpAddress(IpAddress::fromString($this->_getIpAddress()));

        $retry_serv->process();
        $this->_respondWithSuccessCode(MessageCode::CODE_JOB_PROCESS_PASSED);
        return true;
    }
    
}