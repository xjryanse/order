<?php
namespace xjryanse\order\model;

/**
 * 订单商品表
 */
class OrderGoods extends Base
{
    use \xjryanse\traits\ModelUniTrait;
    // 20230516:数据表关联字段
    public static $uniFields = [
        [
            'field'     =>'goods_id',
            'uni_name'  =>'goods',
            'uni_field' =>'id',
            'del_check' => true
        ],
        [
            'field'     =>'order_id',
            'uni_name'  =>'order',
            'uni_field' =>'id',
        ],
    ];
}