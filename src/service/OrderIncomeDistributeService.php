<?php

namespace xjryanse\order\service;

use xjryanse\order\service\OrderService;
use xjryanse\finance\service\FinanceOutcomeService;
use Exception;

/**
 * 订单分钱表
 */
class OrderIncomeDistributeService {

    use \xjryanse\traits\InstTrait;
    use \xjryanse\traits\MainModelTrait;
    use \xjryanse\traits\MainModelRamTrait;
    use \xjryanse\traits\MainModelCacheTrait;
    use \xjryanse\traits\MainModelCheckTrait;
    use \xjryanse\traits\MainModelGroupTrait;
    use \xjryanse\traits\MainModelQueryTrait;


    protected static $mainModel;
    protected static $mainModelClass = '\\xjryanse\\order\\model\\OrderIncomeDistribute';

    public static function save($data) {
        if (isset($data['order_id'])) {
            $data['order_type'] = OrderService::getInstance($data['order_id'])->fOrderType();
        }

        $res = self::commSave($data);
        if (isset($data['distri_status']) && $data['distri_status'] == XJRYANSE_OP_FINISH) {
            $res = FinanceOutcomeService::save($data);
            $data['money'] = isset($data['distri_prize']) ? $data['distri_prize'] : 0;
            $resp = FinanceOutcomeService::save($data);

            $data['outcome_id'] = $resp['id'];
            //更新付款单
            OrderService::getInstance($res['id'])->update($data);
        }

        return $res;
    }

    public function update($data) {
        $info = $this->get(0);
        if (isset($data['distri_status']) && $data['distri_status'] == XJRYANSE_OP_FINISH) {
            $financeData = array_merge($info->toArray(), $data);
            unset($financeData['id']);
            if (!$info['outcome_id']) {
                $resp = FinanceOutcomeService::save($financeData);
                $data['outcome_id'] = $resp['id'];
            } else {
                //付款单
                $resp = FinanceOutcomeService::getInstance($data['outcome_id'])->update($financeData);
            }
        }

        $res = $this->commUpdate($data);
        return $res;
    }

    /**
     * 新的分钱逻辑
     * @param type $orderId
     * @param type $distriKey
     * @param type $distriPrize
     * @param type $ownerId
     * @param type $data
     */
    public static function newDistribute($orderId, $distriKey, $distriPrize, $ownerId, $data = []) {
        //校验事务
        self::checkTransaction();
        //取订单信息
        $info = OrderService::getInstance($orderId)->get();
        if (!$info) {
            throw new Exception('未找到订单信息' . $orderId);
        }
        if (self::orderHasKey($orderId, $distriKey)) {
            throw new Exception('分润key已存在');
        }
        $orderDistriPrize = self::getOrderDistriPrize($orderId);
        if ($orderDistriPrize + $distriPrize > $info['pay_prize']) {
            throw new Exception('超出订单已付金额，不可分润');
        }
        //拼接数据
        $data['order_id'] = $orderId;
        $data['order_type'] = $info['order_type'];
        $data['distri_key'] = $distriKey;
        $data['distri_prize'] = $distriPrize;
        $data['distri_prize'] = $distriPrize;
        $data['distri_status'] = XJRYANSE_OP_TODO;  //待分派
        //写入表
        $res = self::save($data);
        //更新钱
        OrderService::distriPrizeSync($orderId);
        return $res;
    }

    /**
     * 订单是否有分钱key
     * @param type $orderId
     * @param type $distriKey
     * @return type
     */
    public static function orderHasKey($orderId, $distriKey) {
        $con[] = ['order_id', '=', $orderId];
        $con[] = ['distri_key', '=', $distriKey];

        return self::count($con) ? true : false;
    }

    /**
     * 获取订单已分派金额
     */
    public static function getOrderDistriPrize($orderId) {
        $con[] = ['order_id', '=', $orderId];
        return self::sum($con, 'distri_prize');
    }

    /**
     *
     */
    public function fId() {
        return $this->getFFieldValue(__FUNCTION__);
    }

    /**
     *
     */
    public function fAppId() {
        return $this->getFFieldValue(__FUNCTION__);
    }

    /**
     * 公司id
     */
    public function fCompanyId() {
        return $this->getFFieldValue(__FUNCTION__);
    }

    /**
     * 订单id
     */
    public function fOrderId() {
        return $this->getFFieldValue(__FUNCTION__);
    }

    /**
     * 订单类型：tm_auth；tm_rent；tm_buy；os_buy；公证noary
     */
    public function fOrderType() {
        return $this->getFFieldValue(__FUNCTION__);
    }

    /**
     * 分钱key，每个订单唯一
     */
    public function fDistriKey() {
        return $this->getFFieldValue(__FUNCTION__);
    }

    /**
     * 分钱原因
     */
    public function fDistriReason() {
        return $this->getFFieldValue(__FUNCTION__);
    }

    /**
     * 分派金额
     */
    public function fDistriPrize() {
        return $this->getFFieldValue(__FUNCTION__);
    }

    /**
     * todo/finish
     */
    public function fDistriStatus() {
        return $this->getFFieldValue(__FUNCTION__);
    }

    /**
     *
     */
    public function fPartinRole() {
        return $this->getFFieldValue(__FUNCTION__);
    }

    /**
     * 归属人id
     */
    public function fOwnerId() {
        return $this->getFFieldValue(__FUNCTION__);
    }

    /**
     * 去向表
     */
    public function fToTable() {
        return $this->getFFieldValue(__FUNCTION__);
    }

    /**
     * 去向表id
     */
    public function fToTableId() {
        return $this->getFFieldValue(__FUNCTION__);
    }

    /**
     * 排序
     */
    public function fSort() {
        return $this->getFFieldValue(__FUNCTION__);
    }

    /**
     * 状态(0禁用,1启用)
     */
    public function fStatus() {
        return $this->getFFieldValue(__FUNCTION__);
    }

    /**
     * 有使用(0否,1是)
     */
    public function fHasUsed() {
        return $this->getFFieldValue(__FUNCTION__);
    }

    /**
     * 锁定（0：未锁，1：已锁）
     */
    public function fIsLock() {
        return $this->getFFieldValue(__FUNCTION__);
    }

    /**
     * 锁定（0：未删，1：已删）
     */
    public function fIsDelete() {
        return $this->getFFieldValue(__FUNCTION__);
    }

    /**
     * 备注
     */
    public function fRemark() {
        return $this->getFFieldValue(__FUNCTION__);
    }

    /**
     * 创建者，user表
     */
    public function fCreater() {
        return $this->getFFieldValue(__FUNCTION__);
    }

    /**
     * 更新者，user表
     */
    public function fUpdater() {
        return $this->getFFieldValue(__FUNCTION__);
    }

    /**
     * 创建时间
     */
    public function fCreateTime() {
        return $this->getFFieldValue(__FUNCTION__);
    }

    /**
     * 更新时间
     */
    public function fUpdateTime() {
        return $this->getFFieldValue(__FUNCTION__);
    }

}
