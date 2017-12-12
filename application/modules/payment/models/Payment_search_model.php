<?php

use Iapps\PaymentService\PaymentSearch\IPaymentSearchDataMapper;
use Iapps\Common\Core\EntitySelector;
use Iapps\Common\Core\SearchableFieldNameConverter;
use Iapps\PaymentService\Payment\Payment;
use Iapps\PaymentService\Payment\PaymentCollection;
use Iapps\Common\Core\IappsDateTime;

class Payment_search_model extends Base_Model
                           implements IPaymentSearchDataMapper{
    
    public function map(\stdClass $data) {
        $entity = new Payment();

        if( isset($data->payment_id) )
            $entity->setId($data->payment_id);

        if( isset($data->country_code) )
            $entity->setCountryCode($data->country_code);

        if( isset($data->module_code) )
            $entity->setModuleCode($data->module_code);

        if( isset($data->channelID))
            $entity->setChannelID($data->channelID);
        
        if( isset($data->transactionID) )
            $entity->setTransactionID($data->transactionID);

        if( isset($data->user_profile_id) )
            $entity->setUserProfileId($data->user_profile_id);

        if( isset($data->country_currency_code) )
            $entity->setCountryCurrencyCode($data->country_currency_code);

        if( isset($data->amount) )
            $entity->setAmount($data->amount);

        if( isset($data->status_id) )
            $entity->getStatus()->setId($data->status_id);

        if( isset($data->status_code) )
            $entity->getStatus()->setCode($data->status_code);

        if( isset($data->status_name) )
            $entity->getStatus()->setDisplayName($data->status_name);

        if( isset($data->status_group_id) )
            $entity->getStatus()->getGroup()->setId($data->status_group_id);

        if( isset($data->status_group_code) )
            $entity->getStatus()->getGroup()->setCode($data->status_group_code);

        if( isset($data->status_group_name) )
            $entity->getStatus()->getGroup()->setDisplayName($data->status_group_name);

        if( isset($data->payment_code) )
            $entity->setPaymentCode($data->payment_code);

        if( isset($data->ewallet_id) )
            $entity->setEwalletId($data->ewallet_id);

        if( isset($data->receipt_url) )
            $entity->setReceiptUrl($data->receipt_url);

        if( isset($data->payment_request_id) )
            $entity->setPaymentRequestId($data->payment_request_id);

        if( isset($data->description1) )
            $entity->getDescription1()->setEncryptedValue($data->description1);

        if( isset($data->description2) )
            $entity->getDescription2()->setEncryptedValue($data->description2);

        if( isset($data->agent_id) )
            $entity->setAgentId($data->agent_id);

        if( isset($data->requested_by) )
            $entity->setRequestedBy($data->requested_by);

        if( isset($data->user_type) )
            $entity->setUserType($data->user_type);

        if( isset($data->created_at) )
            $entity->setCreatedAt(IappsDateTime::fromUnix($data->created_at));
        
        if ( isset($data->status))
            $entity->setPaymentRequestStatus($data->status);
      
        if( isset($data->created_by) )
            $entity->setCreatedBy($data->created_by);

        if( isset($data->updated_at) )
            $entity->setUpdatedAt(IappsDateTime::fromUnix($data->updated_at));

        if( isset($data->updated_by) )
            $entity->setUpdatedBy($data->updated_by);

        if( isset($data->deleted_at) )
            $entity->setDeletedAt(IappsDateTime::fromUnix($data->deleted_at));

        if( isset($data->deleted_by) )
            $entity->setDeletedBy($data->deleted_by);

        return $entity;
    }
    
    protected function _selectStatement()
    {
        $this->db->select("p.id as payment_id,
                           p.country_code,
                           p.module_code,
                           p.channelID,
                           p.transactionID,
                           p.user_profile_id,
                           p.country_currency_code,
                           p.amount,
                           p.status_id,
                           sc.code as status_code,
                           sc.display_name as status_name,
                           scg.id as status_group_id,
                           scg.code as status_group_code,
                           scg.display_name as status_group_name,
                           p.payment_code,
                           p.ewallet_id,
                           p.receipt_url,
                           p.payment_request_id,
                           p.description1,
                           p.description2,
                           p.agent_id,
                           p.requested_by,
                           p.user_type,
                           p.created_at,
                           p.created_by,
                           p.updated_at,
                           p.updated_by,
                           p.deleted_at,
                           p.deleted_by");        
    }
    
    public function findById($id, $deleted = false) {
        $this->_selectStatement();
        $this->db->from('iafb_payment.payment p');
        $this->db->join('iafb_payment.system_code sc', 'p.status_id = sc.id');
        $this->db->join('iafb_payment.system_code_group scg', 'sc.system_code_group_id = scg.id');
        if( !$deleted )
            $this->db->where('p.deleted_at', NULL);
        $this->db->where('p.id', $id);
        $this->db->where('scg.code', PaymentStatus::getSystemGroupCode());

        $query = $this->db->get();
        if( $query->num_rows() > 0)
        {
            return $this->map($query->row());
        }

        return false;
    }
    
    public function findByFilters(EntitySelector $paymentFilters) 
    {
        $this->_selectStatement();
        $this->db->from('iafb_payment.payment p');
        $this->db->join('iafb_payment.system_code sc', 'p.status_id = sc.id');
        $this->db->join('iafb_payment.system_code_group scg', 'sc.system_code_group_id = scg.id');
        $this->db->where('p.deleted_at', NULL);
        
        if( count($paymentFilters) > 0 )
            $this->_conditionStatement($paymentFilters, new SearchableFieldNameConverter("p"));            
      
        $query = $this->db->get();
        if( $query->num_rows() > 0 )
        {
            return $this->mapCollection($query->result(), new PaymentCollection(), $query->num_rows());
        }

        return false;
    }
}