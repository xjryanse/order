<?php

namespace xjryanse\order\service;

use xjryanse\order\service\OrderService;
use xjryanse\goods\service\GoodsPrizeKeyService;
use xjryanse\finance\service\FinanceStatementOrderService;
/**
 * 订单加收费用模板
 */
class OrderExtraPrizeTplService {

    use \xjryanse\traits\InstTrait;
    use \xjryanse\traits\MainModelTrait;
    use \xjryanse\traits\SubServiceTrait;    

    protected static $mainModel;
    protected static $mainModelClass = '\\xjryanse\\order\\model\\OrderExtraPrizeTpl';
    /**
     * 添加到账单
     * @param type $orderId
     */
    public function addStatement($orderId){
        $tplInfo = $this->get();
        $orderInfo = OrderService::getInstance($orderId)->get();
        if($tplInfo['order_type'] != $orderInfo['order_type']) {
            throw new exception('订单类型不匹配，tpl:'.$tplInfo['order_type'].'-order:'.$orderInfo['order_type']);
        }
        // 价格key
        $prizeKey               = $tplInfo['prize_key'];
        // 价格
        $prize                  = $this->getPrize($orderId);
        // 保存
        return FinanceStatementOrderService::prizeKeySave($prizeKey, $orderId, $prize);
    }
    /**
     * 订单id取价格
     * @param type $orderId
     */
    public function getPrize($orderId){
        return 5;
    }
    
    /**
     *
     */
    public function fId() {
        return $this->getFFieldValue(__FUNCTION__);
    }

    /**
     * 公司id
     */
    public function fCompanyId() {
        return $this->getFFieldValue(__FUNCTION__);
    }
    public function fOrderType() {
        return $this->getFFieldValue(__FUNCTION__);
    }
    public function fPrizeKey() {
        return $this->getFFieldValue(__FUNCTION__);
    }
    public function fPrizeName() {
        return $this->getFFieldValue(__FUNCTION__);
    }
    public function fDescribe() {
        return $this->getFFieldValue(__FUNCTION__);
    }
    public function fBelongRole() {
        return $this->getFFieldValue(__FUNCTION__);
    }
    public function fPrize() {
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
