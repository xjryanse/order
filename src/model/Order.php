<?php
namespace xjryanse\order\model;

/**
 * 订单总表
 */
class Order extends Base
{
    public static $picFields = ['buyer_sign','seller_sign'];
    
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
    //预计开始
    public function setPlanStartTimeAttr($value)
    {
        return self::setTimeVal($value);
    }
    //预计结束
    public function setPlanFinishTimeAttr($value)
    {
        return self::setTimeVal($value);
    }    //末次支付时间
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
    /**
     * 用户签名
     * @param type $value
     * @return type
     */
    public function getBuyerSignAttr($value) {
        return self::getImgVal($value);
    }

    /**
     * 用户签名，图片带id只取id
     * @param type $value
     * @throws \Exception
     */
    public function setBuyerSignAttr($value) {
        return self::setImgVal($value);
    }
    
    /**
     * 用户签名
     * @param type $value
     * @return type
     */
    public function getSellerSignAttr($value) {
        return self::getImgVal($value);
    }

    /**
     * 用户签名，图片带id只取id
     * @param type $value
     * @throws \Exception
     */
    public function setSellerSignAttr($value) {
        return self::setImgVal($value);
    }
    
}