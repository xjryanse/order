<?php
namespace xjryanse\order\service;

/**
 * 订单总表
 */
class OrderService
{
    use \xjryanse\traits\InstTrait;
    use \xjryanse\traits\MainModelTrait;

    protected static $mainModel;
    protected static $mainModelClass    = '\\xjryanse\\order\\model\\Order';

}
