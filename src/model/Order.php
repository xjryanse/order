<?php
namespace xjryanse\order\model;

/**
 * 订单总表
 */
class Order extends Base
{

    public function setOrderFinishTimeAttr($value)
    {
        return self::setTimeVal($value);
    }
    public function setOrderBusierTimeAttr($value)
    {
        return self::setTimeVal($value);
    }
    public function setLastPayTimeAttr($value)
    {
        return self::setTimeVal($value);
    }    
}