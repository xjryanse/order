<?php

namespace xjryanse\order\service;

use xjryanse\goods\service\GoodsService;
use xjryanse\store\service\StoreChangeDtlService;
use xjryanse\system\service\SystemFileService;
use xjryanse\logic\Arrays;
use xjryanse\logic\DataCheck;
use xjryanse\logic\Cachex;
use Exception;

/**
 * 订单总表
 */
class OrderGoodsService {

    use \xjryanse\traits\InstTrait;
    use \xjryanse\traits\MainModelTrait;
    use \xjryanse\traits\MainModelQueryTrait;
    use \xjryanse\traits\SubServiceTrait;

    protected static $mainModel;
    protected static $mainModelClass = '\\xjryanse\\order\\model\\OrderGoods';
    //直接执行后续触发动作
    protected static $directAfter = true;

    public static function orderGoodsInfo($orderId) {
        $cond = [];
        $cond[] = ['order_id', 'in', $orderId];
        $orderGoodsListsRaw = self::mainModel()->alias('a')
                ->join('w_goods b', 'a.goods_id = b.id')->where($cond)
                ->field("a.id,a.order_id,a.goods_id,a.goods_name,b.goods_desc,a.amount,a.unit_prize,a.totalPrize,b.goods_pic")
                ->select();
        $orderGoodsLists = $orderGoodsListsRaw ? $orderGoodsListsRaw->toArray() : [];
        //取图片
        foreach ($orderGoodsLists as &$v) {
            $picId = $v['goods_pic'];
            $v['goodsPic'] = Cachex::funcGet('FileData_' . $picId, function() use ($picId) {
                        return $picId && SystemFileService::mainModel()->where('id', $picId)->field('id,file_path,file_path as rawPath')->find() ?: [];
                    });
        }
        return $orderGoodsLists;
    }

    /**
     * 逐步弃用，使用OrderService 同名方法
     * 取下单的商品总价（用于计算配送费）
     */
    public static function orderGoodsPrize($orderId) {
        $con[] = ['order_id', '=', $orderId];
        $prize = self::mainModel()->where($con)->value('sum( amount * unit_prize) as total');
        return round($prize, 2);
    }

    /**
     * 钩子-保存前
     */
    public static function extraPreSave(&$data, $uuid) {
        self::checkTransaction();
        DataCheck::must($data, ['order_id', 'goods_id', 'amount']);
        $goodsId = Arrays::value($data, 'goods_id');
        //商品信息
        $goodsInfo = GoodsService::getInstance($goodsId)->get();
        //20220316，普通商品才校验库存
        if ($goodsInfo['sale_type'] == 'normal') {
            $stock = StoreChangeDtlService::getStockByGoodsId($goodsId);
            //库存校验
            if ($stock < Arrays::value($data, 'amount')) {
                throw new Exception($goodsId . '库存不足,当前' . $stock);
            }
        }
    }

    /**
     * 钩子-保存后
     */
    public static function extraAfterSave(&$data, $uuid) {
        $storeData['goods_id'] = Arrays::value($data, 'goods_id');
        $storeData['change_type'] = 2;  //默认出库
        $storeData['amount'] = -1 * abs($data['amount']);  //默认出库
        $storeData['has_settle'] = 0;  //未结表示未发货
        $storeData['order_goods_id'] = $uuid;  //用于关联
        //详情改变
        $resp = StoreChangeDtlService::save($storeData);
        return $resp;
    }

    /**
     * 钩子-更新前
     */
    public static function extraPreUpdate(&$data, $uuid) {
        
    }

    /**
     * 钩子-更新后
     */
    public static function extraAfterUpdate(&$data, $uuid) {
        $info = self::getInstance($uuid)->get();
        $con[] = ['order_id', '=', $info['order_id']];
        $count = self::count($con);
        if ($count == 1) {
            $updData['amount'] = $info['amount'];
        }
        $updData['order_prize'] = self::orderGoodsPrize($info['order_id']);
        // 订单更新
        OrderService::getInstance($info['order_id'])->update($updData);
    }

    /**
     * 钩子-删除前
     */
    public function extraPreDelete() {
        self::checkTransaction();
        //先删关联出入库明细
        $con[] = ['order_goods_id', '=', $this->uuid];
        $storeChangeIds = StoreChangeDtlService::ids($con);
        foreach ($storeChangeIds as $id) {
            //为了使用触发器
            StoreChangeDtlService::getInstance($id)->delete();
        }
    }

    /**
     * 钩子-删除后
     */
    public function extraAfterDelete() {
        
    }

    /**
     * 20220622优化性能
     * @param type $data
     * @param type $uuid
     * @throws Exception
     */
    public static function ramPreSave(&$data, $uuid) {
        DataCheck::must($data, ['order_id', 'goods_id', 'amount']);
        $goodsId = Arrays::value($data, 'goods_id');
        //商品信息
        $goodsInfo = GoodsService::getInstance($goodsId)->get();
        //20220316，普通商品才校验库存
        if ($goodsInfo['sale_type'] == 'normal') {
            $stock = StoreChangeDtlService::getStockByGoodsId($goodsId);
            //库存校验
            if ($stock < Arrays::value($data, 'amount')) {
                throw new Exception($goodsId . '库存不足,当前' . $stock);
            }
        }
    }

    /**
     * 将订单列表同步写入store数据库
     * @param type $orderId
     */
    protected static function orderStockSync($orderId) {
        
    }

    protected static function extraDetail(&$item, $uuid) {
        $item['goodsPic'] = GoodsService::getInstance($item['goods_id'])->fGoodsPic();
        return $item;
    }

    /**
     * 20230418
     * @param type $ids
     * @return type
     */
    public static function extraDetails($ids) {
        return self::commExtraDetails($ids, function($lists) use ($ids) {

                    $orderCount = OrderService::groupBatchCount('id', array_column($lists, 'order_id'));

                    foreach ($lists as &$v) {
                        //消息发送数
                        $v['isOrderExist'] = Arrays::value($orderCount, $v['order_id'], 0);
                    }

                    return $lists;
                });
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
