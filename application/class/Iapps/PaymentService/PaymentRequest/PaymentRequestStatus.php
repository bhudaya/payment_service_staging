<?php

namespace Iapps\PaymentService\PaymentRequest;

class PaymentRequestStatus{

    const PENDING = 'pending';
    const SUCCESS = 'success';
    const FAIL = 'fail';
    const CANCELLED = 'cancelled';
    const PENDING_COLLECTION = 'pending_collection';

}