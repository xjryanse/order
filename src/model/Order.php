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
}