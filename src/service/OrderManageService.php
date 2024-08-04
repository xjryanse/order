<?php

namespace xjryanse\order\service;

use xjryanse\wechat\service\WechatWePubFansUserService;

/**
 * 获取管理员id
 */
class OrderManageService {

    use \xjryanse\traits\InstTrait;
    use \xjryanse\traits\MainModelTrait;
    use \xjryanse\traits\MainModelRamTrait;
    use \xjryanse\traits\MainModelCacheTrait;
    use \xjryanse\traits\MainModelCheckTrait;
    use \xjryanse\traits\MainModelGroupTrait;
    use \xjryanse\traits\MainModelQueryTrait;


    protected static $mainModel;
    protected static $mainModelClass = '\\xjryanse\\order\\model\\OrderManage';

    /**
     * 获取管理员id
     * @param type $orderType   订单类型
     */
    public static function getManageUserIds($orderType, $companyId = '', $con = []) {
        $con[] = ['order_type', '=', $orderType];
        $con[] = ['company_id', '=', $companyId];
        $con[] = ['status', '=', 1];
        $manageUserIds = self::column('manage_user_id', $con);
        return $manageUserIds;
    }

    /**
     * 20230322:获取管理员绑定的公众号openid，一般用于发送模板消息
     */
    public static function getManageOpenidsForMessage($orderType, $companyId) {
        $con[] = ['accept_msg', '=', '1'];
        $manageUserIds = self::getManageUserIds($orderType, $companyId, $con);

        $cond = [];
        $cond[] = ['user_id', 'in', $manageUserIds];
        $openids = WechatWePubFansUserService::column('openid', $cond);
        return $openids;
    }

}
