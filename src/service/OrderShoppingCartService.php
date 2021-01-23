<?php

namespace xjryanse\order\service;

/**
 * 订单购物车
 */
class OrderShoppingCartService {

    use \xjryanse\traits\InstTrait;
    use \xjryanse\traits\MainModelTrait;

    protected static $mainModel;
    protected static $mainModelClass = '\\xjryanse\\order\\model\\OrderShoppingCart';

    /**
     * 使购物车的商品不可用，一般用于有用户下单，或者商品下架时使用
     */
    public static function invalid( $goodsId )
    {
        return self::mainModel()->where('goods_id',$goodsId)->update(['is_valid'=>0]);
    }
    /*
     * 删除指定用户指定商品：一般用于下单后清除购物车
     */
    public static function delUserGoods( $goodsId,$userId )
    {
        $con[] = ['goods_id','=',$goodsId];
        $con[] = ['user_id' ,'=',$userId ];
        return self::mainModel()->where( $con )->delete();
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
