<?php

namespace Iapps\PaymentService\Common\BNISwitch;

use Iapps\Common\Helper\ResponseMessage;
use Iapps\PaymentService\PaymentRequest\PaymentRequestResponseInterface;
use Iapps\Common\Helper\StringMasker;
use Iapps\PaymentService\PaymentRequest\PaymentRequestStatus;

class BNISwitchResponse implements PaymentRequestResponseInterface{
    protected $raw;
    protected $formatted_response;
    protected $api_request;

    protected $invoice_no;
    protected $response_status;
    protected $date_processed;
    protected $date_delivered;
    protected $remarks;

    protected $response_code;
    protected $description;
    protected $bni_ref_no;
    protected $dest_acc_holder;
    protected $dest_bankacc;
   

    function __construct($response, $api_request)
    {
        $this->setAPIRequest($api_request);
        $this->setRaw($response);
    }

    protected function _extractResponse()
    {
        $fields_array =  $this->jsonSerialize();

        If(is_array($fields_array) && count($fields_array)>0){

             if(array_key_exists('poInfoInquiryResponse', $fields_array)) {
                 $this->setFormattedResponse(array($fields_array['poInfoInquiryResponse']));
                 foreach ($fields_array['poInfoInquiryResponse'] AS $field => $value) {

                     if($value['paymentInfo']['paymentDetail']){
                         $this->setResponseCode('0');
                         $this->setDateProcessed($value['paymentInfo']['paymentDetail']['paidDate']);
                         $this->setBniRefNo($value['paymentInfo']['paymentDetail']['bniReference']);
                         $this->setResponseStatus($value['paymentInfo']['statusDescription']);
                     }
                 }
             }

            if(array_key_exists('accountInfoInquiryResponse', $fields_array)) {
                $this->setFormattedResponse(array($fields_array['accountInfoInquiryResponse']));
                foreach ($fields_array['accountInfoInquiryResponse'] AS $field => $value) {

                    if ($field == 'accountInfo') {
                        $this->setResponseCode('0');
                        $this->setDestAccHolder($value['accountName']);
                        $this->setDestBankacc($value['accountName']);
                    }
                }
            }



            if(array_key_exists('processPOResponse', $fields_array)) {
                $this->setFormattedResponse(array($fields_array['processPOResponse']));
                foreach ($fields_array['processPOResponse'] AS $field => $value) {

                    if($field == 'okMessage'){
                        $this->setResponseCode('0');
                        $this->setDescription($value['message']);
                    }
                }
            }

            if(array_key_exists('Fault', $fields_array)) {
                $this->setFormattedResponse(array($fields_array['Fault']));
                foreach ($fields_array['Fault'] AS $field => $value) {
                    if ($field == 'detail') {
                        //print_r($value);
                        $this->setResponseCode($value['Fault_element']['errorCode']);
                        $this->setDescription($value['Fault_element']['errorDescription']);
                    }
                }
            }

        }else{
            /*
            $this->setFormattedResponse(array('ERR'=>'timeout'));
            //ERROR but make it processing
            $this->setResponseCode('PRC');
            $this->setDescription('Received timeout but pending confirmation from BNI');
            */
            $this->setFormattedResponse(array('resultCode'=>'PRC','resultDesc'=>'timeout'));
            //ERROR but make it processing
            $this->setResponseCode('PRC');
            $this->setDescription('Timeout from BNI');

        }

    }

    public function setRaw($raw)
    {
        $this->raw = $raw;
        $this->_extractResponse();
        return $this;
    }

    public function getRaw()
    {
        return $this->raw;
    }

    public function setFormattedResponse($formatted_response)
    {
        $this->formatted_response = json_encode($formatted_response);
        return $this;
    }

    public function getFormattedResponse()
    {
        return $this->formatted_response;
    }

    public function setAPIRequest($api_request)
    {
        $this->api_request = $api_request;
    }

    public function getAPIRequest()
    {
        return $this->api_request;
    }

    public function getResponse()
    {
        return $this->getFormattedResponse();
    }

    public function isSuccess()
    {
        return (in_array($this->getResponseCode(), array('0','00', 'DWN')) || $this->getResponseStatus()=="PAID");
    }

    public function isPending()
    {
        return (in_array($this->getResponseCode(), array('1', '01', 'PRC', 'RCV', 'CRT', 'RDY')) || in_array($this->getResponseStatus(), array(BNISwitchFunction::BNI_STATUS_INPROCESS, BNISwitchFunction::BNI_STATUS_OUTSTANDING, BNISwitchFunction::BNI_STATUS_FOR_VERIFICATION)));
    }

    public function getStatus()
    {
        $status = PaymentRequestStatus::FAIL;
        if($this->isSuccess())
        {
            $status = PaymentRequestStatus::SUCCESS;
        }elseif($this->isPending())
        {
            $status = PaymentRequestStatus::PENDING;
        }
        return $status;
    }


    public function getBniRefNo()
    {
        return $this->bni_ref_no;
    }

    public function setBniRefNo($bni_ref_no)
    {
        $this->bni_ref_no = $bni_ref_no;
    }


    public function getDestAccHolder()
    {
        return $this->dest_acc_holder;
    }

    public function setDestAccHolder($dest_acc_holder)
    {
        $this->dest_acc_holder = $dest_acc_holder;
    }

    public function getDestBankacc()
    {
        return $this->dest_bankacc;
    }

    public function setDestBankacc($dest_bankacc)
    {
        $this->dest_bankacc = $dest_bankacc;
    }


    public function setResponseCode($response_code)
    {
        $this->response_code = $response_code;
        return $this;
    }

    public function getResponseCode()
    {
        return $this->response_code;
    }
    
    public function setDescription($description)
    {
        $this->description = $description;
        return $this;
    }

    public function getDescription()
    {
        return $this->description;
    }

    public function setInvoiceNo($invoice_no){
        $this->invoice_no = $invoice_no;
    }

    public function getInvoiceNo(){
        return $this->invoice_no;
    }
    
    public function setResponseStatus($response_status){
        $this->response_status = $response_status;
    }

    public function getResponseStatus(){
        return $this->response_status;
    }

    public function setDateProcessed($date_processed){
        $this->date_processed = $date_processed;
    }

    public function getDateProcessed(){
        return $this->date_processed;
    }

    public function setDateDelivered($date_delivered){
        $this->date_delivered = $date_delivered;
    }

    public function getDateDelivered(){
        //$date_delivered = IappsDateTime::getSystemTimeFromLocalTime($start_time, $timeZone)->getUnix();
        return $this->date_delivered;
    }

    public function setRemarks($remarks){
        $this->remarks = $remarks;
    }

    public function getRemarks(){
        return $this->remarks;
    }

    protected function jsonSerialize(){

        $response = str_ireplace(['soapenv:','ser:', 'xsi:', 'remm:'], '', $this->getRaw());
        //get field before <soapenv:Body>
        $xml     = simplexml_load_string($response);
        $fields_array = array();
        if (!empty($xml) && !empty($xml->getName()) && count($xml->children()) > 0) { 
            $ns = $xml->getNamespaces(true); 
            $response = $xml->children()->Body->children();

            $json = json_encode($response);
            $fields_array = json_decode($json,true);
        }
        return $fields_array;
    }

    public function getSelectedField(array $fields)
    {
        return ArrayExtractor::extract($this->jsonSerialize(), $fields);
    }
}