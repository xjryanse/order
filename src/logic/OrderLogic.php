<?php
namespace xjryanse\order\logic;

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
        //封装在存储过程中：20210119
        return false;
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

        $data['distri_reason']  = $prizeInfo['prize_name'];
        $data['partin_role']    = $prizeInfo['belong_role'];
        $data['owner_id']       = $prizeInfo['belong_user_id'];
        //商品id，价格key，价格，归属用户id
        return OrderIncomeDistributeService::newDistribute($orderId, $prizeKey, $prizeInfo['prize'], $prizeInfo['belong_user_id'],$data);
    }    
}
