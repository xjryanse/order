<?php
namespace xjryanse\order\model;

/**
 * 订单流程表
 */
class OrderFlowNode extends Base
{
    /**
     * 完成时间
     * @param type $value
     * @return type
     */
    public function setFinishTimeAttr($value) {
        return self::setTimeVal($value);
    }
    public function setPlanFinishTimeAttr($value) {
        return self::setTimeVal($value);
    }

}