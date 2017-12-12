<?php

namespace Iapps\PaymentService\PaymentSearch;

use Iapps\Common\Core\IappsBaseRepository;
use Iapps\Common\Core\EntitySelector;

class PaymentSearchRepository extends IappsBaseRepository{

    public function findByFilters(EntitySelector $paymentFilters)
    {
        return $this->getDataMapper()->findByFilters($paymentFilters);
    }
}