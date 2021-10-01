<?php

namespace xjryanse\order\service;

use xjryanse\goods\service\GoodsService;
use xjryanse\goods\service\GoodsPrizeService;
use xjryanse\goods\service\GoodsPrizeTplService;
use xjryanse\goods\service\GoodsPrizeKeyService;
use xjryanse\finance\service\FinanceStatementService;
use xjryanse\finance\service\FinanceStatementOrderService;
use xjryanse\order\service\OrderGoodsService;
use xjryanse\order\service\OrderIncomeDistributeService;
use xjryanse\order\service\OrderFlowNodeService;
use xjryanse\user\service\UserAccountLogService;
use xjryanse\system\service\SystemFileService;
use xjryanse\user\logic\ScoreLogic;
use xjryanse\order\logic\SaleTypeLogic;
use xjryanse\logic\Arrays;
use xjryanse\logic\Arrays2d;
use xjryanse\logic\Cachex;
use xjryanse\logic\Debug;
use think\Db;
use Exception;
/**
 * 订单总表
 */
class OrderService {

    use \xjryanse\traits\InstTrait;
    use \xjryanse\traits\MainModelTrait;
    use \xjryanse\traits\SubServiceTrait;    
    use \xjryanse\traits\ObjectAttrTrait;

    protected static $mainModel;
    protected static $mainModelClass = '\\xjryanse\\order\\model\\Order';
    //直接执行后续触发动作
    protected static $directAfter = true;
    //一经写入就不会改变的值
    protected static $fixedFields = ['company_id','creater','create_time','goods_id','amount'
        ,'goods_name','goods_table','goods_table_id','order_type','seller_user_id','user_id','creater','create_time'];
    
    
    ///从ObjectAttrTrait中来
    // 定义对象的属性
    protected $objAttrs = [];
    // 定义对象是否查询过的属性
    protected $hasObjAttrQuery = [];
    // 定义对象属性的配置数组
    protected static $objAttrConf = [
        'orderFlowNode'=>[
            'class'     =>'\\xjryanse\\order\\service\\OrderFlowNodeService',
            'keyField'  =>'order_id',
            'master'    =>true
        ],
        'orderGoods'=>[
            'class'     =>'\\xjryanse\\order\\service\\OrderGoodsService',
            'keyField'  =>'order_id',
            'master'    =>true
        ],
        'financeStatementOrder'=>[
            'class'     =>'\\xjryanse\\finance\\service\\FinanceStatementOrderService',
            'keyField'  =>'order_id',
            'master'    =>true
        ]
    ];
    
    
    //存储订单流程节点数组
    protected $orderFlowNodes = [];
    //是否有从数据库查过流程节点（刚写入订单时无数据，避免频繁查询）；
    protected $hasOrderFlowQuery = false;
    // 订单的商品列表
    protected $goodsList = [];
    protected $hasGoodsListQuery = false;
    /**
     * 返回销售类型实例
     * @return SaleTypeLogic
     */
    public function orderSaleTypeInst(): SaleTypeLogic{
        $saleType   = $this->fOrderType();
        $companyId  = $this->fCompanyId();
        SaleTypeLogic::setCompanyId($companyId);
        return SaleTypeLogic::getInstance($saleType);
    }
    /**
     * 带了一些详情信息的订单列表，一般用于用户端口前端显示
     * @param type $con
     * @param type $order
     * @param type $perPage
     * @param type $having
     * @param type $field
     */
    public static function paginateWithInfo( $con = [],$order='',$perPage=10,$having = '',$field="*" ){
        $lists  = self::paginate($con, $order, $perPage, $having, 'id');
        $cond   = [];
        $cond[] = ['order_id','in',array_column($lists['data'],'id')];
        $orderGoodsListsRaw = OrderGoodsService::mainModel()->alias('a')
                ->join('w_goods b','a.goods_id = b.id')->where($cond)
                ->field("a.id,a.order_id,a.goods_id,a.goods_name,a.amount,a.unit_prize,a.totalPrize,b.goods_pic")
                ->select();
        $orderGoodsLists = $orderGoodsListsRaw ? $orderGoodsListsRaw->toArray() : [];
        //取图片
        foreach( $orderGoodsLists as &$v){
            $picId = $v['goods_pic'];
            $v['goodsPic'] = Cachex::funcGet('FileData_'.$picId, function() use ($picId){
                return SystemFileService::mainModel()->where('id', $picId )->field('id,file_path,file_path as rawPath')->find()? : [];
            });
        }
        // 取账单
        $condStatement   = [];
        $condStatement[] = ['order_id','in',array_column($lists['data'],'id')];
        $condStatement[] = ['has_settle','=',0];
        $condStatement[] = ['statement_cate','=','buyer'];
        $statementLists = FinanceStatementOrderService::mainModel()->where($condStatement)->field('id,need_pay_prize,order_id')->select();
        // 账单列表 
        // $statement = Arrays2d::fieldSetKey($statementLists ? $statementLists->toArray() : [], 'order_id');

        foreach($lists['data'] as &$v ){
            // 拼接订单商品
            $orderId = $v['id'];
            $v['orderGoods'] = array_filter($orderGoodsLists, function($orderGoods) use($orderId){
                return $orderGoods['order_id'] == $orderId;
            });
            //客户应支付账单
            foreach($statementLists as $statementItem){
                if($statementItem['order_id'] == $v['id']){
                    $v['buyerNeedPayStatements'][] = $statementItem;
                }
            }
            
            //减少传输带宽
            $lastFlowNode       = $v['lastFlowNode'] ? : [];
            $v['lastFlowNode']  = Arrays::getByKeys($lastFlowNode, ['id','node_key','node_name','operate_role','flow_status','create_time']);
        }

        $con1[] = ['status','=',1];
        $con1[] = ['company_id','=',session(SESSION_COMPANY_ID)];
        $con1[] = ['is_delete','=',0];
        $conM = array_merge($con,$con1);
        foreach($conM as $key=>$value){
            if($value[0] == 'order_status'){
                unset($conM[$key]);
            }
        }
        
        $statics = OrderService::mainModel()->where($conM)->group('order_status')->field('count(1) as amount,order_status')->select();
        $lists['statics'] = Arrays2d::toKeyValue( $statics ? $statics->toArray() : [] , 'order_status', 'amount');        
        return $lists;
    }

    /**
     * TODo通用的下订单方法
     * $goodsArr: 商品id和数量二维数组，包含 goods_id, amount 属性
     */
    public static function order( $goodsArr ,$orderData = [] ){
        $orderGoodsName = '';
        //一次性取商品信息
        $goodsInfos = GoodsService::batchGet( array_column($goodsArr,'goods_id') );
        foreach( $goodsArr as &$v){
            $goodsInfo          = Arrays::value($goodsInfos, $v['goods_id'],[]);
            $v['goods_name']    = Arrays::value($goodsInfo,'goods_name');
            $v['unit_prize']    = Arrays::value($goodsInfo,'goodsPrize');
            if(!$orderGoodsName){
                $orderGoodsName = $v['goods_name'];
            }
        }
        //组织商品名称
        if( array_sum(array_column($goodsArr, 'amount')) > 1){
            $orderGoodsName .= ' 等'.array_sum(array_column($goodsArr, 'amount')).'件商品';
        }
        $orderData['goods_name'] = $orderGoodsName;

        if(count($goodsArr) == 1){
            //单商品
            $orderData['goods_id']      = $goodsArr[0]['goods_id'];
            $orderData['amount']        = $goodsArr[0]['amount'] ? : 1;
            $orderData['order_type']    = GoodsService::getInstance( $goodsArr[0]['goods_id'] )->fSaleType();
            // 适用于某表多个记录使用同一个商品下单的情况（比如开发需求）
            if(Arrays::value($goodsArr[0], 'goodsTableId')){
                $orderData['goods_table_id'] = Arrays::value($goodsArr[0], 'goodsTableId');
            }
        }
        $orderData['id'] = self::mainModel()->newId();
        Db::startTrans();
            //先保存明细（主订单的保存有触发动作会用到）
            OrderGoodsService::saveAll($goodsArr,['order_id'=>$orderData['id']],0);
            //再保存主订单
            $res    = self::save($orderData);
        Db::commit();
        return $res;
    }
    /**
     * 计算订单总价
     * @param type $goodsArr 商品id和数量二维数组，包含 goods_id, amount 属性
     */
    public static function calOrderPrize( $goodsArr ){
        $goodsIds   = array_column($goodsArr,'goods_id') ;
        $goodsInfos = GoodsService::batchGet( $goodsIds );
        $orderPrize = 0;
        foreach( $goodsArr as &$v){
            $goodsInfo          = Arrays::value($goodsInfos, $v['goods_id'],[]);
            $goodsPrize         = Arrays::value($goodsInfo,'goodsPrize');
            $orderPrize += $goodsPrize * $v['amount'];
        }
        return $orderPrize;
    }
    /**
     * 计算订单总价
     * @param type $goodsArr 商品id和数量二维数组，包含 goods_id, amount 属性
     */
    public function orderPrize( ){
        $orderId                = $this->uuid;
        // 订单总价
        //$data['order_prize']    = OrderGoodsService::orderGoodsPrize($orderId);
        $data['order_prize']    = GoodsPrizeKeyService::orderPrize($orderId);
        // 配送费:配送费专用key
        $data['deliver_prize']  = GoodsPrizeKeyService::orderPrizeKeyGetPrize($orderId, 'DeliverPrize');
        return $data;
    }
    
    /**
     * 取下单的商品总价（用于计算配送费）
     */
    public function orderGoodsPrize(){
        //$goodsList = $this->getOrderGoods();
        $goodsList = $this->objAttrsList('orderGoods');
        $money = 0;
        foreach($goodsList as $value){
            $amount     = Arrays::value($value, 'amount',0);
            $unitPrize  = Arrays::value($value, 'unit_prize',0);
            $money += $amount *  $unitPrize;
        }
        return $money;
    }
    
    /**
     * 客户下单（在已有订单上设定客户id）
     */
    public function custCheckOrder( $userId ){
        $con[] = ['id','=',$this->uuid];
        $con[] = ['user_id','=',''];
        $res = self::mainModel()->where($con)->update(['user_id'=>$userId]);
        if(!$res){
            throw new Exception('订单不存在或已被认领');
        }
        return $res;
    }
    
    /*********************************************************************************/
//    /**
//     * 获取订单商品
//     */
//    public function getOrderGoods(){
//        Debug::debug('获取前',$this->goodsList);
//        if(!$this->goodsList && !$this->hasGoodsListQuery){
//            $cond[]     = ['order_id','=',$this->uuid];
//            $lists      = OrderGoodsService::listSetUudata($cond);
//            $this->goodsList = $lists ? $lists->toArray() : [];
//            //已经有查过了就不再查了，即使为空
//            $this->hasGoodsListQuery = true;
//        }
//        return $this->goodsList;
//    }
//    
//    public function setOrderGoods($data){
//        $this->goodsList            = $data;
//        $this->hasGoodsListQuery    = true;
//    }
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
     * 获取订单的时间状态
     * @return type
     */
    public function orderTimeArr(){
        //用于流程识别
        $keysArr = ['BuyerReceive','SellerDeliverGoods','BuyerPay','orderFinish'];
        $timeArr = OrderFlowNodeService::getOrderTimeArr($this->uuid, $keysArr);
        //用于替换成order表中的字段
        $keys = [
                'BuyerReceive'=>'order_receive_time',
                'SellerDeliverGoods'=>'order_deliver_time',
                'BuyerPay'=>'last_pay_time',
                'orderFinish'=>'order_finish_time'
            ];
        //返回结果
        return Arrays::keyReplace($timeArr, $keys);        
    }
    
    /**
     * 额外输入信息
     */
    public static function extraPreSave(&$data, $uuid) {
        //20210812，测到金额bug注释:由::order方法控制
        $goodsId     = Arrays::value($data, 'goods_id');
        if($goodsId){
            $goodsInfo   = GoodsService::getInstance( $goodsId )->get(MASTER_DATA);
            $goodsName   = Arrays::value($goodsInfo,'goods_name');
            if(Arrays::value($goodsInfo,'goods_status') != 'onsale'){
                throw new Exception('商品'.$goodsName.'已经销售或未上架');
            }            
            $data['goods_name']             = $goodsName;
            $data['goods_table']            = Arrays::value($goodsInfo,'goods_table');
            //兼容开发需求（多记录使用同商品下单）
            $data['goods_table_id']         = $data['goods_table_id'] ? : Arrays::value($goodsInfo,'goods_table_id');
            $data['seller_customer_id']     = Arrays::value($goodsInfo,'customer_id');
            $data['seller_user_id']         = Arrays::value($goodsInfo,'seller_user_id');
            $data['order_type']             = Arrays::value($goodsInfo,'sale_type');
            $data['shop_id']                = Arrays::value($goodsInfo,'shop_id');
        }
        return $data;
    }
    
    public static function extraPreUpdate( &$data ,$uuid )
    {
        self::checkTransaction();
        $info = self::getInstance($uuid)->get();
        if(isset($data['is_cancel']) && $info['is_complete']){
            throw new Exception('已结订单不可取消');
        }

        return $data;
    }
    /**
     * 额外详情信息
     */
//    protected static function extraDetail( &$item ,$uuid )
//    {
////        return false;
//        //添加分表数据:按类型提取分表服务类
//        if(!$item){
//            return false;
//        }
//        //20210201性能优化调整
//        self::commExtraDetail($item,$uuid );
//        //订单末条流程
//        $item['lastFlowNode'] = OrderFlowNodeService::orderLastFlow( $uuid );
//        return $item;
//    }
    
    
    public static function extraDetails( $ids ){
        //数组返回多个，非数组返回一个
        $isMulti = is_array($ids);
        if(!is_array($ids)){
            $ids = [$ids];
        }
        //Debug::debug('入参id数组',$ids);
        $con[] = ['id','in',$ids];
        $listRaw = self::mainModel()->where($con)->select();
        //写入内存
        foreach($listRaw as $v){
            self::getInstance($v['id'])->setUuData($v,true);  //强制写入
        }
        $lists = $listRaw ? $listRaw->toArray() : [];
        // 获取订单流程
        $cond[] = ['order_id','in',$ids];
        $cond[] = ['is_delete','=',0];
        $orderFlowNodesRaw  = OrderFlowNodeService::mainModel()->master()->where($cond)->select();
        //Debug::debug('extraDetails中查询方法',$orderFlowNodesRaw);
        //写入内存
        foreach($orderFlowNodesRaw as $v){
            OrderFlowNodeService::getInstance($v['id'])->setUuData($v,true);  //强制写入
        }
        $orderFlowNodes     = $orderFlowNodesRaw ? $orderFlowNodesRaw->toArray() : [];
        foreach($ids as $id){
            $orderNodes = Arrays2d::listByFieldValue($orderFlowNodes, 'order_id', $id);
            //Debug::debug('setOrderFlowNodes的'.$id,$orderNodes);
            // self::getInstance($id)->setOrderFlowNodes($orderNodes);
            self::getInstance($id)->objAttrsSet('orderFlowNode',$orderNodes);
            //Debug::debug('setOrderFlowNodes后的实例'.$id,self::getInstance($id));
        }
        
        foreach($lists as &$item){
            $item['lastFlowNode'] = OrderFlowNodeService::orderLastFlow( $item['id'] );
        }
        return $isMulti ? $lists : $lists[0];
    }
    /**
     * 额外输入信息
     */
    public static function extraAfterSave(&$data, $uuid) {
        OrderFlowNodeService::lastNodeFinishAndNext($uuid);
        return $data;
    }
    
    /**
     * 额外输入信息
     */
    public static function extraAfterUpdate(&$data, $uuid) {
        $info   = self::getInstance( $uuid )->get();
        //尝试流程节点的更新
        OrderFlowNodeService::lastNodeFinishAndNext($uuid);
   
        //②写入订单子表
        $subService = self::getSubService( $info['order_type'] );
        if( $info['order_type'] && class_exists($subService) ){
            $subService::getInstance( $uuid )->update( $data );
        }
        //判定订单完成，给下单人赠送积分的触发动作
        ScoreLogic::score( $info['user_id'] );
        return $data;
    }
    /**
     * 订单数据同步
     * 一般用于各种操作完成后
     */
    public function orderDataSync(){
        // 更新订单的时间(TODO所有更新合成一个)
        $orderId    = $this->uuid;
        //订单时间
        $timeData   = $this->orderTimeArr();
        //订单金额
        $prizeData  = $this->orderPrize(); 
        //支付金额
        $moneyData  = FinanceStatementOrderService::orderMoneyData($orderId);
        //末流程节点数据
        $lastNodeData = OrderFlowNodeService::orderLastNodeData($orderId);
        $updData    = array_merge($timeData, $prizeData, $moneyData, $lastNodeData);
        //订单是否完成
        $updData['is_complete'] = booleanToNumber( OrderFlowNodeService::orderComplete($orderId) );
        Debug::debug('orderDataSync的data',$updData);
        //写入内存
        $this->setUuData($updData);
        // strict(false) TODO 优化
        $res = self::mainModel()->where('id',$this->uuid)->strict(false)->update($updData);
        //------------TODO是否可省略?②写入订单子表
        $info = $this->get();
        $subService = self::getSubService( $info['order_type'] );
        if( $info['order_type'] && class_exists($subService) ){
            $subService::getInstance( $orderId )->update( $updData );
        }
        //------------TODO是否可省略?②写入订单子表

        //②写入订单子表
        return $res;
    }
    
    public function extraPreDelete()
    {
        self::checkTransaction();
        $con[] = ['order_id','=',$this->uuid];
        $res = FinanceStatementOrderService::mainModel()->where($con)->count(1);
        if($res){
            throw new Exception('该订单有收付款账单，不可删除');
        }
        $fromTable = self::mainModel()->getTable();
        $userAccountHasLog = UserAccountLogService::hasLog($fromTable, $this->uuid);
        if($userAccountHasLog){
            throw new Exception('该订单有用户账户记录'.$userAccountHasLog['id'].'，不可删除');
        }
    }
    
    /**
     * 删除价格数据
     */
    public function extraAfterDelete()
    {
        self::checkTransaction();
        // 删流程
        $con[] = ['order_id','=',$this->uuid];
        OrderFlowNodeService::mainModel()->where( $con )->delete();
        // 删商品
        $goodsIds = OrderGoodsService::ids($con);
        foreach($goodsIds as $id){
            //为了使用触发器20210802
            OrderGoodsService::getInstance( $id )->delete();
        }
    }    
    /**
     * 订单取消
     */
    public function cancel(){
        self::checkTransaction();
         //对账单要先清
        FinanceStatementService::clearOrderNoDeal($this->uuid);
        //才能清对账单明细
        FinanceStatementOrderService::clearOrderNoDeal($this->uuid);
        //最后订单才能被删
        $res = $this->delete();
        return $res;
    }
    /**
     * 订单软删
     * @return type
     */
    public function delete()
    {
        //删除前
        if(method_exists( __CLASS__, 'extraPreDelete')){
            $this->extraPreDelete();      //注：id在preSaveData方法中生成
        }
        //删除
        $data['is_delete'] = 1;
        $res = $this->commUpdate($data);
        //删除后
        if(method_exists( __CLASS__, 'extraAfterDelete')){
            $this->extraAfterDelete();      //注：id在preSaveData方法中生成
        }
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
        // $goodsId    = $this->fGoodsId();
//        $con[] = ['order_id','=',$this->uuid];
//        Debug::debug( '$goodsLists的$con', $con );
//        $goodsLists = OrderGoodsService::mainModel()->where($con)->field('goods_id,amount')->select();
//        
        //$goodsLists = $this->getOrderGoods();
        $goodsLists = $this->objAttrsList('orderGoods');
        $role       = GoodsPrizeKeyService::keyBelongRole( $prizeKey );
        Debug::debug( 'prizeKeyGetPrize的$prizeKey', $prizeKey );
        Debug::debug( 'prizeKeyGetPrize的$role', $role );
        if( $role == 'buyer'){
            //是否最终价格
            $isGrandPrize   = GoodsPrizeTplService::isMainKeyFinal( $prizeKey );
            Debug::debug( '$isGrandPrize', $isGrandPrize );
            // 多商品的数组
            $buyerPrize     = GoodsPrizeService::goodsArrGetBuyerPrize( $goodsLists, $prizeKey );
            Debug::debug( '$goodsLists', $goodsLists );
            Debug::debug( '$buyerPrize', $buyerPrize );
            if($isGrandPrize){
//                $payPrize   = OrderService::getInstance( $this->uuid )->fPayPrize();                
//                Debug::debug( '$payPrize', $payPrize );
                //20210407修bug  已付金额=$prizeKey取全部子key，再查全部子key的已结。
                $childKeys  = GoodsPrizeKeyService::getChildKeys( $prizeKey , true);    //返回一个key一维数组
                $payPrize   = FinanceStatementOrderService::hasSettleMoney( $this->uuid, $childKeys);
                Debug::debug( '$payPrize', $payPrize );
                $buyerPrize = floatval($buyerPrize) - floatval($payPrize);
            }
            $finalPrize = $buyerPrize;
        }
        //供应商
        if( $role == 'seller'){
            //是否最终价格
            $isGrandPrize   = GoodsPrizeTplService::isPrizeKeyFinal( $prizeKey );
            // $sellerPrize     = GoodsPrizeService::keysPrize( $goodsId , $prizeKey );
            $sellerPrize     = GoodsPrizeService::goodsArrGetKeysPrize( $goodsLists , $prizeKey );
            if($isGrandPrize){
                //无缓存取价格
                $orderInfo   = OrderService::getInstance( $this->uuid)->get(0);
                $payPrize    = Arrays::value($orderInfo, 'outcome_prize');
                $sellerPrize = $sellerPrize - abs($payPrize);
            }
            $finalPrize = -1 * $sellerPrize;
        }
        //推荐人，业务员
        if( $role == "rec_user" || $role == "busier"){
            // $finalPrize = GoodsPrizeService::keysPrize( $goodsId , $prizeKey );
            $finalPrize = GoodsPrizeService::goodsArrGetKeysPrize( $goodsLists , $prizeKey );
        }
        return $finalPrize;
    }
    /**
     * 计算订单状态
     * 20210922，改为计算属性，此处无用
     * is_cancel=1;is_complete=0;=>cancel;订单取消中
     * is_cancel=1;is_complete=1;=>close;订单关闭
     * is_complete=1;=》finish订单完成
     * $info['pre_prize'] > $info['pay_prize']=》needpay 待支付
     * !$info['has_deliver']=》toDeliver 待发货
     * !$info['has_receive']=》toDeliver 待收货
     */
    public static function calOrderStatus($orderData){
        $isCancel   = Arrays::value($orderData, 'is_cancel');
        $isComplete = Arrays::value($orderData, 'is_complete');
        if($isCancel){
            //已完成：关闭；未完成：取消中
            return $isComplete ? 'close' : 'cancel';
        }
        if($isComplete){
            //订单完成
            return 'finish';
        }
        $prePrize = Arrays::value($orderData, 'pre_prize',0);
        $payPrize = Arrays::value($orderData, 'pay_prize',0);
        //待支付
        if($prePrize > $payPrize ){
            return 'needpay';
        }
        //待发货
        if(!Arrays::value($orderData, 'has_deliver')){
            return 'toDeliver';
        }
        //待收货
        if(!Arrays::value($orderData, 'has_receive')){
            return 'toReceive';
        }
        return 'processing';    //订单进行中
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
    //是否取消
    public function fIsCancel() {
        return $this->getFFieldValue(__FUNCTION__);
    }        
    //由谁取消
    public function fCancelBy() {
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

    public function fSource(){
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
