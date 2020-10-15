<?php
namespace xjryanse\order\service;

/**
 * 订单流程
 */
class OrderFlowNodeService
{
    use \xjryanse\traits\InstTrait;
    use \xjryanse\traits\MainModelTrait;

    protected static $mainModel;
    protected static $mainModelClass    = '\\xjryanse\\order\\model\\OrderFlowNode';

}
