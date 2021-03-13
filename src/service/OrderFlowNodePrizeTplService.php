<?php
namespace xjryanse\order\service;

use xjryanse\system\interfaces\MainModelInterface;
use xjryanse\logic\Arrays;
use xjryanse\goods\service\GoodsPrizeService;

/**
 * 订单流程模板
 */
class OrderFlowNodePrizeTplService implements MainModelInterface
{
    use \xjryanse\traits\InstTrait;
    use \xjryanse\traits\MainModelTrait;

    protected static $mainModel;
    protected static $mainModelClass    = '\\xjryanse\\order\\model\\OrderFlowNodePrizeTpl';
    
    /**
     * 
     */
    public static function getPrizeKeys( $saleType ,$nodeKey )
    {
        $con[] = ['sale_type','=',$saleType];
        $con[] = ['node_key','=',$nodeKey ];
        $prizeKeys = self::mainModel()->where( $con )->value('prize_keys');
        return $prizeKeys ? explode(',', $prizeKeys): [] ;
    }
    
    /**
     * 根据当前流程节点，设定订单的应付金额
     * @param type $orderId
     * @param type $nodeKey
     */
    public static function setOrderPrePrize( $orderId ,$nodeKey )
    {
        $orderInfo  = OrderService::getInstance( $orderId )->get(0);
        $saleType   = Arrays::value($orderInfo, 'order_type');
        $goodsId    = Arrays::value($orderInfo, 'goods_id');  
        //根据订单id和当前节点key，获取价格key
        $prizeKeys = self::getPrizeKeys($saleType, $nodeKey);
        if($prizeKeys){
            $con[]  = ['goods_id','=',$goodsId];
            $con[]  = ['prize_key','in',$prizeKeys];
            $prizeAll = GoodsPrizeService::sum( $con , 'prize');
            //更新订单的最小定金
            OrderService::mainModel( )->where('id',$orderId)->update(['pre_prize'=>$prizeAll]);
        }
    }

}
