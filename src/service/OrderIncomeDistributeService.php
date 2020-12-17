<?php
namespace xjryanse\order\service;

use xjryanse\order\service\OrderService;
use Exception;
/**
 * 订单分钱表
 */
class OrderIncomeDistributeService
{
    use \xjryanse\traits\InstTrait;
    use \xjryanse\traits\MainModelTrait;

    protected static $mainModel;
    protected static $mainModelClass    = '\\xjryanse\\order\\model\\OrderIncomeDistribute';

    /**
     * 新的分钱逻辑
     * @param type $orderId
     * @param type $distriKey
     * @param type $distriPrize
     * @param type $ownerId
     * @param type $data
     */
    public static function newDistribute($orderId,$distriKey,$distriPrize,$ownerId,$data=[])
    {
        //校验事务
        self::checkTransaction();
        //取订单信息
        $info = OrderService::getInstance( $orderId )->get();
        if(!$info){
            throw new Exception('未找到订单信息'.$orderId);
        }
        if(self::orderHasKey($orderId, $distriKey)){
            throw new Exception('分润key已存在');
        }
        $orderDistriPrize = self::getOrderDistriPrize($orderId);
        if( $orderDistriPrize + $distriPrize > $info['pay_prize'] ){
            throw new Exception('超出订单已付金额，不可分润');
        }
        //拼接数据
        $data['order_id']       = $orderId;
        $data['order_type']     = $info['order_type'];
        $data['distri_key']     = $distriKey;
        $data['distri_prize']   = $distriPrize;
        $data['owner_id']       = $ownerId;
        //写入表
        $res = self::save($data);
        //更新钱
        OrderService::distriPrizeSync($orderId);
        return $res;
    }
    /**
     * 订单是否有分钱key
     * @param type $orderId
     * @param type $distriKey
     * @return type
     */
    public static function orderHasKey( $orderId ,$distriKey )
    {
        $con[] = ['order_id','=',$orderId];
        $con[] = ['distri_key','=',$distriKey];
        
        return self::count($con) ? true : false;
    }
    /**
     * 获取订单已分派金额
     */
    public static function getOrderDistriPrize( $orderId )
    {
        $con[] = ['order_id','=',$orderId ];
        return self::sum( $con, 'distri_prize');
    }

}
