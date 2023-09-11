<?php

namespace xjryanse\order\service;

use xjryanse\goods\service\GoodsService;
use xjryanse\logic\DataCheck;

/**
 * 订单购物车
 */
class OrderShoppingCartService {

    use \xjryanse\traits\InstTrait;
    use \xjryanse\traits\MainModelTrait;
    use \xjryanse\traits\MainModelQueryTrait;

    protected static $mainModel;
    protected static $mainModelClass = '\\xjryanse\\order\\model\\OrderShoppingCart';

    public static function cartAdd($userId, $goodsId, $number = 1) {
        $con[] = ['user_id', '=', $userId];
        $con[] = ['goods_id', '=', $goodsId];
        $info = self::find($con, 0);
        if ($info) {
            //往已有记录添数量
            return self::mainModel()->where('id', $info['id'])->setInc('goods_number', $number);
        } else {
            //新增记录
            $data['user_id'] = $userId;
            $data['goods_id'] = $goodsId;
            //店铺号
            $goodsInfo = GoodsService::getInstance($goodsId)->get();
            $data['shop_id'] = $goodsInfo['shop_id'];
            $data['goods_number'] = $number;
            return self::save($data);
        }
    }

    /**
     * 钩子-保存前
     */
    public static function extraPreSave(&$data, $uuid) {
        DataCheck::must($data, ['user_id', 'goods_id']);
    }

    /**
     * 钩子-保存后
     */
    public static function extraAfterSave(&$data, $uuid) {
        
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
        
    }

    /**
     * 钩子-删除前
     */
    public function extraPreDelete() {
        
    }

    /**
     * 钩子-删除后
     */
    public function extraAfterDelete() {
        
    }

    /**
     * 使购物车的商品不可用，一般用于有用户下单，或者商品下架时使用
     */
    public static function invalid($goodsId) {
        return self::mainModel()->where('goods_id', $goodsId)->update(['is_valid' => 0]);
    }

    /*
     * 删除指定用户指定商品：一般用于下单后清除购物车
     */

    public static function delUserGoods($goodsId, $userId) {
        $con[] = ['goods_id', '=', $goodsId];
        $con[] = ['user_id', '=', $userId];
        return self::mainModel()->where($con)->delete();
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
     * 用户id
     */
    public function fUserId() {
        return $this->getFFieldValue(__FUNCTION__);
    }

    /**
     * 店铺id
     */
    public function fShopId() {
        return $this->getFFieldValue(__FUNCTION__);
    }

    /**
     * 商品id
     */
    public function fGoodsId() {
        return $this->getFFieldValue(__FUNCTION__);
    }

    /**
     * 商品数量
     */
    public function fGoodsNumber() {
        return $this->getFFieldValue(__FUNCTION__);
    }

    /**
     * 商品是否可用，0否1是
     */
    public function fIsValid() {
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
