<?php

namespace xjryanse\order\service;

/**
 * 订单类型管理
 */
class OrderTypeService {

    use \xjryanse\traits\InstTrait;
    use \xjryanse\traits\MainModelTrait;
    use \xjryanse\traits\MainModelQueryTrait;

    protected static $mainModel;
    protected static $mainModelClass = '\\xjryanse\\order\\model\\OrderType';

}
