<?php

namespace xjryanse\order\service;

use xjryanse\order\service\OrderIncomeDistributeService;
use xjryanse\order\service\OrderFlowNodeService;
use xjryanse\goods\service\GoodsService;
use xjryanse\goods\service\GoodsPrizeService;
use xjryanse\goods\service\GoodsPrizeTplService;
use xjryanse\logic\DataCheck;
use xjryanse\logic\DbOperate;
use xjryanse\logic\Arrays;
use xjryanse\logic\Debug;
use Exception;
/**
 * 订单总表
 */
class OrderService {

    use \xjryanse\traits\InstTrait;
    use \xjryanse\traits\MainModelTrait;
    use \xjryanse\traits\SubServiceTrait;    

    protected static $mainModel;
    protected static $mainModelClass = '\\xjryanse\\order\\model\\Order';

    public static function paginate( $con = [],$order='',$perPage=10,$having = '')
    {
        $hasOrderStatus = false;
        foreach( $con as $key=>$value){
            if($value[0] == 'order_status'){
                $hasOrderStatus = true;
            }
        }
        //没指定状态时，过滤掉已关闭的订单
        if( !$hasOrderStatus ){
            $con[] = ['order_status','<>','close']; //订单关闭过滤
        }
        $res = self::commPaginate($con, $order, $perPage, $having);
        return $res;
    }
    
    /**
     * 是否有未完订单
     * @param type $goodsTable
     * @param type $goodsTableId
     * @return type
     */
    public static function hasNoFinish( $goodsTable,$goodsTableId)
    {
        $con[] = ['goods_table','=',$goodsTable];
        $con[] = ['goods_table_id','=',$goodsTableId];
        $con[] = ['order_status','not in',['close','finish']];
        return self::count($con);
    }
    
    /**
     * 更新订单总价：
     * 适用于因为各种人为原因，需要从商品表重新同步订单总价的情况。
     */
    public function orderPrizeUpdate( )
    {
        $info = $this->get();
        $data['order_prize'] = GoodsPrizeService::totalPrize( Arrays::value($info, 'goods_id') );
        $this->update($data);
    }
    
    /**
     * 额外输入信息
     */
    public static function extraPreSave(&$data, $uuid) {
        if(Arrays::value($data, 'goods_id')){
            $data['order_prize'] = GoodsPrizeService::totalPrize( Arrays::value($data, 'goods_id') );
        }
        $goodsId     = Arrays::value($data, 'goods_id');
        $data['goods_name']      = GoodsService::getInstance( $goodsId )->fGoodsName();
        $data['goods_table']     = GoodsService::getInstance( $goodsId )->fGoodsTable();
        $data['goods_table_id']  = GoodsService::getInstance( $goodsId )->fGoodsTableId();
        $data['seller_customer_id']  = GoodsService::getInstance( $goodsId )->fCustomerId();
        $data['seller_user_id']  = GoodsService::getInstance( $goodsId )->fSellerUserId();
        
        if($data['goods_table']){
            $service        = DbOperate::getService( $data['goods_table'] );
            $info           = $service::getInstance( $data['goods_table_id'] )->get();
            //取卖家公司信息
            $data['seller_customer_id'] = Arrays::value($info, 'customer_id');
            $data['busier_id'] = Arrays::value($data, 'busier_id') ? : Arrays::value($info, 'busier_id');
        }
        //临时：20210326
        if( self::hasNoFinish($data['goods_table'], $data['goods_table_id'])){
            throw new Exception( $data['goods_name'] .'尚有未结订单，无法下单');
        }
        //【20210402】取消订单，删除全部未生成账单未结算的明细
        
        

        return $data;
    }
    
    public static function extraPreUpdate( &$item ,$uuid )
    {
        if( in_array(Arrays::value($item, 'order_status'),['finish','close'])){
            $data['order_finish_time'] = date('Y-m-d H:i:s');
        }
        return $data;
    }
    /**
     * 额外详情信息
     */
    protected static function extraDetail( &$item ,$uuid )
    {
//        return false;
        //添加分表数据:按类型提取分表服务类
        if(!$item){
            return false;
        }
        //20210201性能优化调整
        self::commExtraDetail($item,$id );
        //订单末条流程
        $item['lastFlowNode'] = OrderFlowNodeService::orderLastFlow( $uuid );
        return $item;
    }
    
    /**
     * 额外输入信息
     */
    public static function extraAfterSave(&$data, $uuid) {
        //交易关闭，重新上架订单
        if(isset($data['order_status']) && $data['order_status'] == ORDER_CLOSE){
            $info       = self::getInstance( $uuid )->get();
            $service    = DbOperate::getService($info['goods_table']);
            $goodsStatusData['goods_status'] = GOODS_ONSALE;
            if($service::mainModel()->hasField('goods_status')){
                $service::mainModel()->where('id',$info['goods_table_id'])->update($goodsStatusData);
            }
            //订单状态更新为完结
            $updData['is_complete']      = 1;   //0未完结，1已完结
        }
        
        $goodsId                    = self::getInstance($uuid)->fGoodsId();
        $goodsInfo                  = GoodsService::getInstance( $goodsId )->get(0);
        $updData['goods_name']      = Arrays::value($goodsInfo, 'goods_name');      //GoodsService::getInstance( $goodsId )->fGoodsName();
        $updData['goods_table']     = Arrays::value($goodsInfo, 'goods_table');     //GoodsService::getInstance( $goodsId )->fGoodsTable();
        $updData['goods_table_id']  = Arrays::value($goodsInfo, 'goods_table_id');  //GoodsService::getInstance( $goodsId )->fGoodsTableId();
        $con[] = ['id','=',$uuid];
        //过滤数据
        $updData = DbOperate::dataFilter( self::mainModel()->getTable(),$updData);
        self::mainModel()->where($con)->update($updData);
        //尝试流程节点的更新
        OrderFlowNodeService::checkLastNodeFinishAndNext( $uuid );
        return $data;
    }
    
    /**
     * 额外输入信息
     */
    public static function extraAfterUpdate(&$data, $uuid) {
        $res    = self::extraAfterSave($data, $uuid);
        $info   = self::getInstance()->get(0);
        //②写入订单子表
        $subService = self::getSubService( $info['order_type'] );
        if( $info['order_type'] && class_exists($subService) ){
            $subService::getInstance( $uuid )->update( $data );
        }
        
        return $res;
    }    
    
    /**
     * 删除价格数据
     */
    public function extraAfterDelete()
    {
        self::checkTransaction();
        //删流程
        $con[] = ['order_id','=',$this->uuid];
        OrderFlowNodeService::mainModel()->where( $con )->delete();
    }    
    
    public static function save( $data) {
        self::checkTransaction();
        //数据校验
        DataCheck::must($data, ['goods_id']);
        GoodsService::getInstance( $data['goods_id'])->get(0);
        if(GoodsService::getInstance( $data['goods_id'])->fGoodsStatus() != 'onsale'){
            $goodsName = GoodsService::getInstance( $data['goods_id'])->fGoodsName();
            throw new Exception('商品'.$goodsName.'已经销售或未上架');
        }
        
        $data['seller_user_id'] = GoodsService::getInstance( $data['goods_id'])->fSellerUserId();
        $data['order_type']     = GoodsService::getInstance( $data['goods_id'])->fSaleType();
        $data['shop_id']        = GoodsService::getInstance( $data['goods_id'])->fShopId();
        //订单状态:默认为待支付
        $data['order_status'] = isset($data['order_status']) ? $data['order_status'] : ORDER_NEEDPAY;
        //①订单保存
        $res = self::commSave( $data );
        
        //②写入订单子表
        $subService = self::getSubService( $data['order_type'] );
        if( class_exists($subService) ){
            $subService::save( $res ? $res->toArray() : [] );
        }
        
        //③写入流程表
        $nodeKey = camelize($data['order_type']).'BuyerOrder' ;//订单类型+买家下单
        OrderFlowNodeService::addFlow($res['id'], $nodeKey, '买家下单', 'buyer', $data);

        return $res;
    }

    /**
     * 退款校验
     */
    public function refundCheck($toRefundMoney) {
        $info = $this->get(0);
        //已付金额 大于等于 已退金额加本次待退金额；
        return $info['pay_prize'] >= $info['refund_prize'] + $toRefundMoney;
    }

    /**
     * 同步订单分派金额
     */
    public static function distriPrizeSync($orderId) {
        $distriPrize = OrderIncomeDistributeService::getOrderDistriPrize($orderId);
        return self::getInstance($orderId)->update(['distri_prize' => $distriPrize]);
    }
    /**
     * 对账单订单关联id
     * @param type $con
     * @param type $startTime   2021-01-01 00:00:00
     * @param type $endTime     2021-01-01 23:59:59
     */
    public static function statementOrders( $con , $startTime, $endTime )
    {
        $con[] = ['create_time','>=',$startTime];
        $con[] = ['create_time','<=',$endTime];
        $res = self::mainModel()->where( $con )->field('id as order_id')->select();
        return $res ? $res->toArray() : [];
    }
    /**
     * 根据价格key计算价格
     */
    public function prizeKeyGetPrize($prizeKey)
    {
        $goodsId    = $this->fGoodsId();
        $role       = GoodsPrizeTplService::keyBelongRole( $prizeKey );
        Debug::debug( 'prizeKeyGetPrize的$prizeKey', $prizeKey );        
        if( $role == 'buyer'){
            //是否最终价格
            $isGrandPrize   = GoodsPrizeTplService::isMainKeyFinal( $prizeKey );
            Debug::debug( '$isGrandPrize', $isGrandPrize );
            $buyerPrize     = GoodsPrizeService::buyerPrize( $goodsId, $prizeKey );
            Debug::debug( '$buyerPrize', $buyerPrize );
            if($isGrandPrize){
                $payPrize   = OrderService::getInstance( $this->uuid )->fPayPrize();                
                $buyerPrize = floatval($buyerPrize) - floatval($payPrize);
            }
            $finalPrize = $buyerPrize;
        }
        //供应商
        if( $role == 'seller'){
            //是否最终价格
            $isGrandPrize   = GoodsPrizeTplService::isPrizeKeyFinal( $prizeKey );
            $sellerPrize     = GoodsPrizeService::sellerPrize( $goodsId , $prizeKey );
            if($isGrandPrize){
                $payPrize    = OrderService::getInstance( $this->uuid)->fOutcomePrize();
                $sellerPrize = $sellerPrize - abs($payPrize);
            }
            $finalPrize = -1 * $sellerPrize;
        }
        return $finalPrize;
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
     * 店铺id
     */
    public function fShopId() {
        return $this->getFFieldValue(__FUNCTION__);
    }

    /**
     * 商品id（单商品可用）
     */
    public function fGoodsId() {
        return $this->getFFieldValue(__FUNCTION__);
    }

    /**
     * 订单json
     */
    public function fVal() {
        return $this->getFFieldValue(__FUNCTION__);
    }

    /**
     * 归属部门id
     */
    public function fDeptId() {
        return $this->getFFieldValue(__FUNCTION__);
    }

    /**
     * 订单类型：tm_auth；tm_rent；tm_buy；os_buy；公证noary
     */
    public function fOrderType() {
        return $this->getFFieldValue(__FUNCTION__);
    }
    /**
     * 商品名称
     */
    public function fGoodsName() {
        return $this->getFFieldValue(__FUNCTION__);
    }
    public function fGoodsTableId() {
        return $this->getFFieldValue(__FUNCTION__);
    }
    /**
     * 下单客户类型：customer；personal
     */
    public function fRoleType() {
        return $this->getFFieldValue(__FUNCTION__);
    }

    /**
     * 订单号
     */
    public function fOrderSn() {
        return $this->getFFieldValue(__FUNCTION__);
    }

    /**
     * 前序订单id
     */
    public function fPreOrderId() {
        return $this->getFFieldValue(__FUNCTION__);
    }

    /**
     * 销售客户id（适用于中介平台）
     */
    public function fSellerCustomerId() {
        return $this->getFFieldValue(__FUNCTION__);
    }

    /**
     * 销售用户id
     */
    public function fSellerUserId() {
        return $this->getFFieldValue(__FUNCTION__);
    }

    /**
     * 下单客户id，customer表id
     */
    public function fCustomerId() {
        return $this->getFFieldValue(__FUNCTION__);
    }
    //业务员id
    public function fBusierId() {
        return $this->getFFieldValue(__FUNCTION__);
    }
    /**
     * 下单客户部门id
     */
    public function fCustomerDeptId() {
        return $this->getFFieldValue(__FUNCTION__);
    }

    /**
     * 下单用户id，user表id
     */
    public function fUserId() {
        return $this->getFFieldValue(__FUNCTION__);
    }

    /**
     * 推荐人id，user表id
     */
    public function fRecUserId() {
        return $this->getFFieldValue(__FUNCTION__);
    }

    /**
     * 订单列表图标，单图
     */
    public function fCoverPic() {
        return $this->getFFieldValue(__FUNCTION__);
    }

    /**
     * 订单状态：
      needpay待支付
      processing进行中、
      finish已完成、
      close已关闭
     */
    public function fOrderStatus() {
        return $this->getFFieldValue(__FUNCTION__);
    }

    /**
     * 订单子状态：
     */
    public function fSubOrderStatus() {
        return $this->getFFieldValue(__FUNCTION__);
    }

    /**
     * 最小定金，关联发车付款进度
     */
    public function fPrePrize() {
        return $this->getFFieldValue(__FUNCTION__);
    }

    /**
     * 订单金额，关联发车付款进度
     */
    public function fOrderPrize() {
        return $this->getFFieldValue(__FUNCTION__);
    }

    /**
     * 已支付金额
     */
    public function fPayPrize() {
        return $this->getFFieldValue(__FUNCTION__);
    }

    /**
     * 已退款金额
     */
    public function fRefundPrize() {
        return $this->getFFieldValue(__FUNCTION__);
    }
    
    public function fOutcomePrize() {
        return $this->getFFieldValue(__FUNCTION__);
    }    
    /**
     * 已分派金额
     */
    public function fDistriPrize() {
        return $this->getFFieldValue(__FUNCTION__);
    }

    /**
     * 付款进度
     */
    public function fPayProgress() {
        return $this->getFFieldValue(__FUNCTION__);
    }

    /**
     * 订单执行所需付款进度
     */
    public function fDoPayProgress() {
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
