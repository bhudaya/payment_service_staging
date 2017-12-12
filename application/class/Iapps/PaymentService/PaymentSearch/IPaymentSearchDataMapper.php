<?php

namespace Iapps\PaymentService\PaymentSearch;

use Iapps\Common\Core\IappsBaseDataMapper;
use Iapps\Common\Core\EntitySelector;

interface IPaymentSearchDataMapper extends IappsBaseDataMapper{
    public function findByFilters(EntitySelector $paymentFilters);
}