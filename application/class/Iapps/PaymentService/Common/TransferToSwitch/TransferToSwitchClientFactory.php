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
            $client->setSenderFullName($option['sender_fullname']);
        if( isset($option['sender_address']) )
            $client->setSenderAddress($option['sender_address']);
        if( isset($option['sender_phone']) )
            $client->setSenderPhone($option['sender_phone']);
        if( isset($option['receiver_fullname']) )
            $client->setReceiverFullName($option['receiver_fullname']);
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
            $branch_name = $option['bank_code'] == '014' ? 'BCA' : 'BCA';
            $transaction_type = $option['bank_code'] == '014' ? '1' : '1';
            //$payable_code =  $option['bank_code'] == 'BDO' ? 'CBBM' : 'CBOM';
            $client->setBranchName($branch_name);
            $client->setTransactionType($transaction_type);
            //$client->setPayableCode($payable_code);
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