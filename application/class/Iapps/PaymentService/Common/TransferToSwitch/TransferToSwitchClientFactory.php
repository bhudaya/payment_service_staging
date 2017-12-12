<?php

namespace Iapps\PaymentService\Common\TransferToSwitch;

use Iapps\PaymentService\Common\CoreConfigDataServiceFactory;
use Iapps\PaymentService\Common\CoreConfigType;

class TransferToSwitchClientFactory{

    protected static $_instance;
    public static function build(array $option = array())
    {
        $core_config = CoreConfigDataServiceFactory::build();
        
        if( !$user_name = $core_config->getConfig(CoreConfigType::TRANSFERTO_SWITCH_USERNAME) ) //get from db
            throw new \Exception('TRANSFERTO Switch User Name Is Not Defined');
        if( !$password = $core_config->getConfig(CoreConfigType::TRANSFERTO_SWITCH_PASSWORD) )
            throw new \Exception('TRANSFERTO Switch User Password Is Not Defined');
        if( !$bearer = $core_config->getConfig(CoreConfigType::TRANSFERTO_SWITCH_BEARER) )
            throw new \Exception('TRANSFERTO Switch Bearer Is Not Defined');

        if( !$url = getenv('TRANSFERTO_SWITCH_URL') ) { //get env file
            throw new \Exception('TRANSFERTO Switch URL Is Not Defined In ENV File');
        }    

        if( count($option) > 0 )
        {
            self::$_instance = TransferToSwitchClient::fromOption(array(
                'username' => $user_name,
                'password' => $password,
                'bearer' => $bearer,
                'url' => $url
            ),
                $option);
        }
        else
        {
            self::$_instance = new TransferToSwitchClient(array(
                'username' => $user_name,
                'password' => $password,
                'bearer' => $bearer,
                'url' => $url
            ));
        }
  
        return self::$_instance;
    }

    public static function buildFromOption(array $option)
    {
        $client = TransferToSwitchClientFactory::build();
        if( isset($option['signed_data']) )
            $client->setSignedData($option['signed_data']);
        if( isset($option['inquire_signed_data']) )
            $client->setInquireSignedData($option['inquire_signed_data']);
        if( isset($option['reference_no']) )
            $client->setReferenceNo($option['reference_no']);
        if( isset($option['trans_date']) )
            $client->setTransDate($option['trans_date']);
        if( isset($option['sender_fullname']) )
            $client->setSenderFullname($option['sender_fullname']);
        if( isset($option['sender_address']) )
            $client->setSenderAddress($option['sender_address']);
        if( isset($option['sender_phone']) )
            $client->setSenderPhone($option['sender_phone']);
                
        if( isset($option['sender_dob']) )
            $client->setSenderDob($option['sender_dob']);
        if( isset($option['sender_gender']) )
            $client->setSenderGender($option['sender_gender']);
        if( isset($option['sender_nationality']) )
            $client->setSenderNationality($option['sender_nationality']);
        if( isset($option['sender_host_countrycode']) )
            $client->setSenderHostCountrycode($option['sender_host_countrycode']);
        if( isset($option['sender_host_identity']) )
            $client->setSenderHostIdentity($option['sender_host_identity']);
        if( isset($option['sender_host_identitycard']) )
            $client->setSenderHostIdentitycard($option['sender_host_identitycard']);
        if( isset($option['sender_postal_code']) )
            $client->setSenderPostalCode($option['sender_postal_code']);
        if( isset($option['sender_id_type']) )
            $client->setSenderIdType($option['sender_id_type']);
        

        if( isset($option['receiver_full_name']) )
            $client->setReceiverFullname($option['receiver_full_name']);
        if( isset($option['receiver_fullname']) )
            $client->setReceiverFullname($option['receiver_fullname']);
        if( isset($option['account_holder_name']) )
            $client->setReceiverFullname($option['account_holder_name']);

        if( isset($option['receiver_address']) )
            $client->setReceiverAddress($option['receiver_address']);
        if( isset($option['receiver_mobile_phone']) )
            $client->setReceiverMobilePhone($option['receiver_mobile_phone']);
        if( isset($option['receiver_birth_date']) )
            $client->setReceiverBirthDate($option['receiver_birth_date']);
        if( isset($option['receiver_gender']) )
            $client->setReceiverGender($option['receiver_gender']);
        if( isset($option['bank_code']) ) {
            $client->setBankCode($option['bank_code']);
        }
        if( isset($option['account_no']) )
            $client->setAccountNo($option['account_no']);
        if( isset($option['landed_currency']) )
            $client->setLandedCurrency($option['landed_currency']);
        if( isset($option['landed_amount']) )
            $client->setLandedAmount($option['landed_amount']);


        return $client;
    }
}