<?php
namespace xjryanse\order\model;

/**
 * 订单快递
 */
class OrderExpress extends Base
{
    public function setFileIdAttr($value) {
        return self::setImgVal($value);
    }
    public function getFileIdAttr($value) {
        return self::getImgVal($value,false);
    }
}