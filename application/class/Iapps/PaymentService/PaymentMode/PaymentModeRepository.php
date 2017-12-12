<?php

namespace Iapps\PaymentService\PaymentMode;

use Iapps\Common\Core\IappsBaseRepository;
use Iapps\PaymentService\Common\CacheKey;

class PaymentModeRepository extends IappsBaseRepository{

    public function findByCode($code)
    {
        $cacheKey = CacheKey::PAYMENT_MODE_CODE . $code;

        if( !$result = $this->getElasticCache($cacheKey) )
        {
            if( $result = $this->getDataMapper()->findByCode($code) )
            {
                $this->setElasticCache($cacheKey, $result);
            }
        }

        return $result;
    }

    public function findAll($limit, $page)
    {
        if( $limit == MAX_VALUE AND $page == 1)
        {
            $cacheKey = CacheKey::PAYMENT_MODE_ALL;
        
            if( !$result = $this->getElasticCache($cacheKey) )
            {
                $result = $this->getDataMapper()->findAll($limit, $page);
                if( $result )
                    $this->setElasticCache($cacheKey, $result);
            }

            return $result;        
        }
            
        return $this->getDataMapper()->findAll($limit, $page);
    }

    public function findByParam(PaymentMode $paymentMode, $limit, $page)
    {
        return $this->getDataMapper()->findByParam($paymentMode, $limit, $page);
    }

    public function findByFilters(PaymentModeCollection $filters)
    {
        return $this->getDataMapper()->findByFilters($filters);
    }
}