<?php

namespace Iapps\PaymentService\Payment;

use Iapps\Common\Core\EntitySelector;

class AgentPaymentListService extends PaymentListService{    
    
    protected function _constructFilter()
    {
        parent::_constructFilter();
        
        $compulsorySelector = new EntitySelector();
        
        $selector1 = new EntitySelector();
        $selector1->equals('user_type', PaymentUserType::USER);
        $selector1->equalsIn('requested_by', $this->user_profile_ids);
                
        $selector2 = new EntitySelector();
        $selector2->equals('user_type', PaymentUserType::AGENT);
        $selector2->equalsIn('user_profile_id', $this->user_profile_ids);
                
        $compulsorySelector->multipleCondition($selector1, false);
        $compulsorySelector->multipleCondition($selector2, false);
        $this->selector->multipleCondition($compulsorySelector);
    }
}
