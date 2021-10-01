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
    use \xjryanse\traits\SubServiceTrait;    

    protected static $mainModel;
    protected static $mainModelClass = '\\xjryanse\\order\\model\\OrderGoods';

    public static function orderGoodsInfo($orderId){
        $cond   = [];
        $cond[] = ['order_id','in',$orderId];
        $orderGoodsListsRaw = self::mainModel()->alias('a')
                ->join('w_goods b','a.goods_id = b.id')->where($cond)
                ->field("a.id,a.order_id,a.goods_id,a.goods_name,b.goods_desc,a.amount,a.unit_prize,a.totalPrize,b.goods_pic")
                ->select();
        $orderGoodsLists = $orderGoodsListsRaw ? $orderGoodsListsRaw->toArray() : [];
        //取图片
        foreach( $orderGoodsLists as &$v){
            $picId = $v['goods_pic'];
            $v['goodsPic'] = Cachex::funcGet('FileData_'.$picId, function() use ($picId){
                return SystemFileService::mainModel()->where('id', $picId )->field('id,file_path,file_path as rawPath')->find()? : [];
            });
        }
        return $orderGoodsLists;
    }
    /**
     * 逐步弃用，使用OrderService 同名方法
     * 取下单的商品总价（用于计算配送费）
     */
    public static function orderGoodsPrize($orderId){
        $con[] = ['order_id','=',$orderId];
        return self::mainModel()->where($con)->value('sum( amount * unit_prize) as total');
    }
    /**
     * 钩子-保存前
     */
    public static function extraPreSave(&$data, $uuid) {
        self::checkTransaction();
        DataCheck::must($data, ['order_id','goods_id','amount']);
        $goodsId = Arrays::value($data, 'goods_id');
        $stock   = StoreChangeDtlService::getStockByGoodsId($goodsId);
        //库存校验
        if($stock < Arrays::value($data, 'amount')){
            throw new Exception('库存不足,当前'.$stock);
        }
    }
    /**
     * 钩子-保存后
     */
    public static function extraAfterSave(&$data, $uuid) {
        $storeData['goods_id']      = Arrays::value($data, 'goods_id');
        $storeData['change_type']   = 2;  //默认出库
        $storeData['amount']        = -1 * abs($data['amount']);  //默认出库
        $storeData['has_settle']    = 0;  //未结表示未发货
        $storeData['order_goods_id']= $uuid;  //用于关联
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

    }    
    /**
     * 钩子-删除前
     */
    public function extraPreDelete()
    {
        self::checkTransaction();
        //先删关联出入库明细
        $con[] = ['order_goods_id','=',$this->uuid];
        $storeChangeIds = StoreChangeDtlService::ids($con);
        foreach($storeChangeIds as $id){
            //为了使用触发器
            StoreChangeDtlService::getInstance($id)->delete();
        }
    }
    /**
     * 钩子-删除后
     */
    public function extraAfterDelete()
    {

    }    
    
    /**
     * 将订单列表同步写入store数据库
     * @param type $orderId
     */
    protected static function orderStockSync($orderId){
        
    }
    
    protected static function extraDetail( &$item ,$uuid )
    {
        $item['goodsPic'] = GoodsService::getInstance($item['goods_id'])->fGoodsPic();
        return $item;
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
