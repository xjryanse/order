<?php
namespace xjryanse\order\model;

/**
 * 订单流程表
 */
class OrderFlowNode extends Base
{
    use \xjryanse\traits\ModelUniTrait;
    // 20230516:数据表关联字段
    public static $uniFields = [
        [
            'field'     =>'order_id',
            // 去除prefix的表名
            'uni_name'  =>'order',
            'uni_field' =>'id',
            'in_exist'  => true,
            'del_check' => false,
        ]
    ];
    
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