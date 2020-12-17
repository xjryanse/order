<?php
namespace xjryanse\order\service;

use xjryanse\order\service\OrderIncomeDistributeService;
/**
 * 订单总表
 */
class OrderService
{
    use \xjryanse\traits\InstTrait;
    use \xjryanse\traits\MainModelTrait;

    protected static $mainModel;
    protected static $mainModelClass    = '\\xjryanse\\order\\model\\Order';

    /**
     * 退款校验
     */
    public function refundCheck( $toRefundMoney )
    {
        $info = $this->get( 0 );
        //已付金额 大于等于 已退金额加本次待退金额；
        return $info['pay_prize'] >= $info['refund_prize'] + $toRefundMoney;
    }
    /**
     * 同步订单分派金额
     */
    public static function distriPrizeSync( $orderId )
    {
        $distriPrize = OrderIncomeDistributeService::getOrderDistriPrize($orderId);
        return self::getInstance( $orderId )->update(['distri_prize'=>$distriPrize]);
    }
    
    public function fGoodsId() {
        return $this->getFFieldValue(__FUNCTION__);
    }
}
