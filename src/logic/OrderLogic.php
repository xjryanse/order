<?php
namespace xjryanse\order\logic;

use xjryanse\finance\service\FinanceIncomeOrderService;
use xjryanse\order\service\OrderService;

/**
 * 订单逻辑
 */
class OrderLogic
{
    /**
     * 财务费用数据同步更新
     */
    public static function financeSync( $orderId )
    {
        //已支付订单金额
        $paiedMoney = FinanceIncomeOrderService::getOrderMoney($orderId);
        return OrderService::getInstance( $orderId )->update(['pay_prize'=>$paiedMoney]);
    }
    

}
