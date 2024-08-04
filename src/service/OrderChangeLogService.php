<?php

namespace xjryanse\order\service;

use xjryanse\system\service\SystemCateService;
use xjryanse\customer\service\CustomerService;
use xjryanse\user\service\UserService;
use xjryanse\logic\Arrays;

/**
 * 订单变更日志
 */
class OrderChangeLogService {

    use \xjryanse\traits\InstTrait;
    use \xjryanse\traits\MainModelTrait;
    use \xjryanse\traits\MainModelRamTrait;
    use \xjryanse\traits\MainModelCacheTrait;
    use \xjryanse\traits\MainModelCheckTrait;
    use \xjryanse\traits\MainModelGroupTrait;
    use \xjryanse\traits\MainModelQueryTrait;


    protected static $mainModel;
    protected static $mainModelClass = '\\xjryanse\\order\\model\\OrderChangeLog';

    /**
     * 20221115：记录操作日志
     * @param type $changeType
     * @param type $orderId
     * @param type $subOrderId
     * @param type $data
     * @return type
     */
    public static function log($changeType, $orderId, $subOrderId = '', $data = []) {
        $savData['order_id'] = $orderId;
        $savData['sub_order_id'] = $subOrderId;
        $savData['change_type'] = $changeType;
        $savData['log'] = method_exists(__CLASS__, $changeType) ? call_user_func([__CLASS__, $changeType], $data) : '';
        // 20221115 有log才保存
        return $savData['log'] ? self::save($savData) : '';
    }

    /**
     * 包车下单
     */
    protected static function admBaoAdd($data) {
        $customerId = Arrays::value($data, 'customer_id');
        $customerInfo = CustomerService::getInstance($customerId)->get();
        $custStr = $customerInfo['short_name'] ?: $customerInfo['customer_name'];

        $userId = Arrays::value($data, 'user_id');
        $userInfo = UserService::getInstance($userId)->get();
        $userStr = $userInfo['namePhone'];

        return '客户[' . $custStr . ']下单人[' . $userStr . ']出车时间[' . $data['plan_start_time'] . ']';
    }

    /*
     * 订单信息改变
     * @param type $diffArr
     * [变更前，变更后]
     */

    protected static function orderChange($diffArr) {
        $keys['sub_order_type']     = '用车类型';
        $keys['need_invoice']       = '开票';
        $keys['customer_id']        = '下单客户';
        $keys['user_id']            = '下单用户';
        $keys['busier_id']          = '业务员';
        //TODO sub_order_type类型细化
        $enumArr['sub_order_type']  = SystemCateService::columnByGroup('dBaoType');
        $enumArr['need_invoice']    = SystemCateService::columnByGroup('dTrueFalse');

        $keysArr = array_keys($keys);
        $strArr = [];
        foreach ($diffArr as $k => $v) {
            if (!in_array($k, $keysArr)) {
                continue;
            }
            if (in_array($k, ['sub_order_type', 'need_invoice'])) {
                // TODO解耦，抽离
                // 202211115：枚举转换
                $v[0] = isset($enumArr[$k]) ? $enumArr[$k][$v[0]]['cate_name'] : '';
                $v[1] = isset($enumArr[$k]) ? $enumArr[$k][$v[1]]['cate_name'] : '';
            }
            // 20221115:动态枚举
            if ($k == 'busier_id') {
                $conUser = [];
                $conUser[] = ['id', 'in', $v];
                $dynArr = UserService::where($conUser)->column('realname', 'id');
                $v[0] = Arrays::value($dynArr, $v[0]);
                $v[1] = Arrays::value($dynArr, $v[1]);
            }

            $strArr[] = $keys[$k] . '由[' . $v[0] . ']改为[' . $v[1] . ']';
        }
        return implode('，', $strArr);
    }

}
