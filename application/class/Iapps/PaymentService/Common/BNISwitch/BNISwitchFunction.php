<?php

namespace Iapps\PaymentService\Common\BNISwitch;

class BNISwitchFunction {

    const CODE_REMIT             = 'remit';
    const CODE_INQUIRY           = 'inquiry';
    const CODE_INFO              = 'info';


    const BNI_REMIT_BNI_SOAP_REQUEST = 'urn:RemitAPI#creditCASAOnLine';
    const BNI_REMIT_OTHER_BANK_SOAP_REQUEST = 'urn:RemitAPI#creditOtherBank';

    const BNI_STATUS_INPROCESS = "INPROCESS";
    const BNI_STATUS_OUTSTANDING = "OUTSTANDING";
    const BNI_STATUS_FOR_VERIFICATION = "FOR VERIFICATION";

    CONST BNI_TIMEZONE = 'bni_timezone';
}