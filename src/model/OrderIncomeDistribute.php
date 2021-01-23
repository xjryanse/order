<?php
namespace xjryanse\order\model;

/**
 * 订单分钱表
 */
class OrderIncomeDistribute extends Base
{
    /**
     * 用户头像图标
     * @param type $value
     * @return type
     */
    public function getFileIdAttr($value) {
        return self::getImgVal($value);
    }

    /**
     * 图片修改器，图片带id只取id
     * @param type $value
     * @throws \Exception
     */
    public function setFileIdAttr($value) {
        return self::setImgVal($value);
    }
}