<?php

namespace xjryanse\order\service;

/**
 * 订单分组
 */
class OrderGroupService {

    use \xjryanse\traits\InstTrait;
    use \xjryanse\traits\MainModelTrait;
    use \xjryanse\traits\MainModelQueryTrait;
    use \xjryanse\traits\SubServiceTrait;

    protected static $mainModel;
    protected static $mainModelClass = '\\xjryanse\\order\\model\\OrderGroup';

    /**
     * 带子表的添加
     * @param type $groupType   分组类型
     * @param type $orderIds
     * @param array $data
     */
    public static function addWithSub($groupType, $orderIds, $data = []) {
        self::checkTransaction();
        $data['group_type'] = $groupType;   //按供应商，按客户
        $res = self::save($data);
        foreach ($orderIds as $orderId) {
            $tmpData = [];
            $tmpData['group_id'] = $res['id'];
            $tmpData['order_id'] = $orderId;
            OrderGroupOrderService::save($tmpData);
        }
        return $res;
    }

    public function extraAfterDelete() {
        $con[] = ['group_id', '=', $this->uuid];
        if (!$this->get(0)) {
            //删除用户的关联
            OrderGroupOrderService::mainModel()->where($con)->delete();
        }
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
