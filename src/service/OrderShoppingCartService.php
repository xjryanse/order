<?php
namespace xjryanse\order\service;

/**
 * 订单购物车
 */
class OrderShoppingCartService
{
    use \xjryanse\traits\InstTrait;
    use \xjryanse\traits\MainModelTrait;

    protected static $mainModel;
    protected static $mainModelClass    = '\\xjryanse\\order\\model\\OrderShoppingCart';

}
