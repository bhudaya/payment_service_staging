<?php

namespace Iapps\PaymentService\PromoCode;

class UserPromoRewardServiceFactory{

    protected static $_instance = array();

    public static function build()
    {
        if (self::$_instance == NULL) {
            $_ci = get_instance();
            $_ci->load->model('promocode/User_promo_reward_model');
            $repo = new UserPromoRewardRepository($_ci->User_promo_reward_model);
            self::$_instance = new UserPromoRewardService($repo);
        }

        return self::$_instance;
    }
}