<?php
namespace xjryanse\order\model;

/**
 * 订单总表
 */
class Order extends Base
{
    //成交时间
    public function setOrderFinishTimeAttr($value)
    {
        return self::setTimeVal($value);
    }
    //业务员结佣时间
    public function setOrderBusierTimeAttr($value)
    {
        return self::setTimeVal($value);
    }
    //末次支付时间
    public function setLastPayTimeAttr($value)
    {
        return self::setTimeVal($value);
    }
    //发货时间
    public function setOrderDeliverTimeAttr($value)
    {
        return self::setTimeVal($value);
    }
    //收货时间
    public function setOrderReceiveTimeAttr($value)
    {
        return self::setTimeVal($value);
    }
}