<?php
namespace xjryanse\order\logic;

use xjryanse\finance\service\FinanceIncomeOrderService;
use xjryanse\finance\service\FinanceRefundService;
use xjryanse\order\service\OrderIncomeDistributeService;
use xjryanse\goods\service\GoodsPrizeService;
use xjryanse\order\service\OrderService;
use Exception;

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
        $refundMoney = FinanceRefundService::getOrderMoney($orderId);
        
        $data['pay_prize']      = $paiedMoney;
        $data['refund_prize']   = $refundMoney;
        
        return OrderService::getInstance( $orderId )->update( $data );
    }
    /**
     * 分润
     * @param type $orderId     订单id
     * @param type $prizeKey    价格key
     */
    public static function distri( $orderId, $prizeKey )
    {
        $orderInfo = OrderService::getInstance( $orderId )->get();
        if(!$orderInfo){
            throw new Exception('订单不存在');
        }
        $prizeInfo = GoodsPrizeService::getByGoodsAndPrizeKey( $orderInfo['goods_id'], $prizeKey );
        //商品id，价格key，价格，归属用户id
        return OrderIncomeDistributeService::newDistribute($orderInfo['goods_id'], $prizeKey, $prizeInfo['prize'], $prizeInfo['belong_user_id']);
    }    
}
