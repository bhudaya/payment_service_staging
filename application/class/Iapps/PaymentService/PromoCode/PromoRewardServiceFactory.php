<?php

namespace Iapps\PaymentService\PromoCode;

class PromoRewardServiceFactory{

    protected static $_instance = array();

    public static function build()
    {
        if (self::$_instance == NULL) {
            $_ci = get_instance();
            $_ci->load->model('promocode/Promo_reward_model');
            $repo = new PromoRewardRepository($_ci->Promo_reward_model);
            self::$_instance = new PromoRewardService($repo);
        }

        return self::$_instance;
    }
}