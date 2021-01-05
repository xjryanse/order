<?php

namespace xjryanse\order\service;

use xjryanse\order\service\OrderFlowNodeTplService;
/**
 * 订单流程
 */
class OrderFlowNodeService {

    use \xjryanse\traits\InstTrait;
    use \xjryanse\traits\MainModelTrait;

    protected static $mainModel;
    protected static $mainModelClass = '\\xjryanse\\order\\model\\OrderFlowNode';

    /*
     * 根据订单节点key取id
     */
    public function getIdByOrderNodeKey($orderId, $nodeKey) {
        $con[] = ['order_id', '=', $orderId];
        $con[] = ['node_key', '=', $nodeKey];
        return self::mainModel()->where($con)->order('id desc')->value('id');
    }
    /*
     * 根据订单节点key取id
     */
    public function getByOrderNodeKey($orderId, $nodeKey) {
        $con[] = ['order_id', '=', $orderId];
        $con[] = ['node_key', '=', $nodeKey];
        return self::mainModel()->where($con)->order('id desc')->find();
    }
    /**
     * 设定流程完成
     */
    public function setFinish() {
        $this->setField('flow_status', XJRYANSE_OP_FINISH);
        $this->setField('finish_time', date('Y-m-d H:i:s'));
        return true;
    }
    /**
     * 获取订单的末个流程节点
     */
    public static function orderLastFlow($orderId) {
        $con[] = ['order_id', '=', $orderId];
        $info = self::mainModel()->where($con)->order('id desc')->find();
        return $info;
    }
    /*
     * 获取指定流程的前一个流程节点
     */
    public static function orderPreFlow($orderId, $operate) {
        $con[] = ['order_id', '=', $orderId];
        $lists = self::mainModel()->where($con)->order('id desc')->select();
        foreach ($lists as $k => $v) {
            if ($v['node_key'] == $operate) {
                return $lists[$k + 1];
            }
        }
        return [];
    }
    /**
     * 获取审核回滚节点
     */
    public static function getAuditRollBackFlow($orderId, $operate) {
        //审核事项前一个节点
        $preFlow = self::orderPreFlow($orderId, $operate);
        //前一个节点之前没有模板节点，说明前一个节点是由用户操作触发的（例如取消订单）。
        //应该回滚到触发前的节点
        if (!OrderFlowNodeTplService::getPreNode($preFlow['node_key'])) {
            $preFlow = self::orderPreFlow($orderId, $preFlow['node_key']);
        }
        return $preFlow;
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
     *
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
     * 节点key
     */
    public function fNodeKey() {
        return $this->getFFieldValue(__FUNCTION__);
    }
    /**
     * 节点名称
     */
    public function fNodeName() {
        return $this->getFFieldValue(__FUNCTION__);
    }
    /**
     * 流程状态：todo待处理；doing进行中；finish已完成
     */
    public function fFlowStatus() {
        return $this->getFFieldValue(__FUNCTION__);
    }
    /**
     * 完成时间
     */
    public function fFinishTime() {
        return $this->getFFieldValue(__FUNCTION__);
    }
    /**
     * 等待操作用户角色
     */
    public function fOperateRole() {
        return $this->getFFieldValue(__FUNCTION__);
    }
    /**
     * 等待操作用户角色
     */
    public function fOperateUserId() {
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
