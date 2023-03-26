<?php
namespace xjryanse\order\service;

use xjryanse\system\interfaces\MainModelInterface;
use xjryanse\logic\Arrays;
use xjryanse\goods\service\GoodsPrizeService;
use xjryanse\logic\Debug;
use xjryanse\logic\Cachex;

/**
 * 订单流程模板
 */
class OrderFlowNodePrizeTplService implements MainModelInterface
{
    use \xjryanse\traits\InstTrait;
    use \xjryanse\traits\MainModelTrait;
    // 静态模型：配置式数据表
    use \xjryanse\traits\StaticModelTrait;

    protected static $mainModel;
    protected static $mainModelClass    = '\\xjryanse\\order\\model\\OrderFlowNodePrizeTpl';
    
    /**
     * 
     */
    public static function getPrizeKeys( $saleType ,$nodeKey )
    {
        //20220617：使用静态优化性能
        $con[] = ['sale_type','=',$saleType];
        $con[] = ['node_key','=',$nodeKey ];
        $info  = self::staticConFind($con);
        return $info ? explode(',', $info['prize_keys']) : [] ;
        /*
         * 20220617注释
        return Cachex::funcGet( __CLASS__.'_'.__METHOD__.$saleType.$nodeKey, function() use ($saleType, $nodeKey){
            $con[] = ['sale_type','=',$saleType];
            $con[] = ['node_key','=',$nodeKey ];
            $prizeKeys = self::mainModel()->where( $con )->value('prize_keys');
            return $prizeKeys ? explode(',', $prizeKeys): [] ;
        });
         */
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
        // $goodsId    = Arrays::value($orderInfo, 'goods_id');  
        //根据订单id和当前节点key，获取价格key
        $prizeKeys = self::getPrizeKeys($saleType, $nodeKey);
        Debug::debug('当前节点，$saleType:'.$saleType.'$nodeKey'.$nodeKey.'$prizeKeys', $prizeKeys);
        if($prizeKeys){
            //$goodsLists  = OrderService::getInstance( $orderId )->getOrderGoods();
            $goodsLists  = OrderService::getInstance( $orderId )->objAttrsList('orderGoods');
            $prizeAll = GoodsPrizeService::goodsArrGetKeysPrize( $goodsLists , $prizeKeys );
            //调试
            Debug::debug('当前节点，$nodeKey', $nodeKey);
            Debug::debug('更新订单预付金额，$prizeAll', $prizeAll);
            //更新订单的最小定金
            OrderService::mainModel( )->where('id',$orderId)->update(['pre_prize'=>$prizeAll]);
        }
    }

}
