<?php

namespace xjryanse\order\service;

use app\station\service\StationService;
use app\circuit\service\CircuitBusService;
use app\tour\service\TourPassengerService;
use xjryanse\customer\service\CustomerUserService;
use xjryanse\goods\service\GoodsService;
use xjryanse\goods\service\GoodsPrizeService;
use xjryanse\goods\service\GoodsPrizeTplService;
use xjryanse\goods\service\GoodsPrizeKeyService;
use xjryanse\finance\service\FinanceStatementService;
use xjryanse\finance\service\FinanceStatementOrderService;
use xjryanse\finance\service\FinanceAccountLogService;
use xjryanse\wechat\service\WechatWePubFansUserService;
use xjryanse\order\service\OrderPassengerService;
use xjryanse\order\service\OrderGoodsService;
use xjryanse\order\service\OrderIncomeDistributeService;
use xjryanse\order\service\OrderFlowNodeService;
use xjryanse\user\service\UserService;
use xjryanse\user\service\UserAccountLogService;
use xjryanse\system\service\SystemFileService;
use xjryanse\user\logic\ScoreLogic;
use xjryanse\order\logic\SaleTypeLogic;
use xjryanse\wechat\service\WechatWePubTemplateMsgLogService;
use xjryanse\logic\DataCheck;
use xjryanse\logic\DbOperate;
use xjryanse\logic\Arrays;
use xjryanse\logic\Arrays2d;
use xjryanse\logic\Strings;
use xjryanse\logic\Cachex;
use xjryanse\logic\Debug;
// 20220906兼容前端，排车页面，是否有更优方案？？
// use xjryanse\logic\Device;
use think\Db;
use Exception;
/**临时，需要拆解 - 20211115 ***/
use app\order\service\OrderBaoBusService;

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
        'orderBuses'=>[
            'class'     =>'\\app\\order\\service\\OrderBaoBusService',
            'keyField'  =>'order_id',
            'master'    =>true
        ],
        'orderPassengers'=>[
            'class'     =>'\\xjryanse\\order\\service\\OrderPassengerService',
            'keyField'  =>'order_id',
            'master'    =>true
        ],
        'tourPassengers'=>[
            'class'     =>'\\app\\tour\\service\\TourPassengerService',
            'keyField'  =>'order_id',
            'master'    =>true
        ],
        'financeStatementOrder'=>[
            'class'     =>'\\xjryanse\\finance\\service\\FinanceStatementOrderService',
            'keyField'  =>'order_id',
            'master'    =>true
        ],
        'financeStaffFee'=>[
            'class'     =>'\\xjryanse\\finance\\service\\FinanceStaffFeeService',
            'keyField'  =>'order_id',
            'master'    =>true
        ]
    ];
    /**
     * 20220918客户访问权限校验；增加安全性
     */
    public function customerAuthCheck(){
        $info       = $this->get();
        if($info['user_id'] == session(SESSION_USER_ID)){
            return true;
        }
        $cond[] = ['is_manager','=',1];
        $cond[] = ['user_id','=',session(SESSION_USER_ID)];
        $cond[] = ['customer_id','=',$info['customer_id']];
        $isCustomer = CustomerUserService::mainModel()->where($cond)->count();
        return $isCustomer ? true :false;
    }
    /**
     * 避免外部直接调用save方法；
     * 如需下单，请调用OrderService::order();
     * @param type $data
     * @return type
     */
    protected static function save( $data){
        return self::commSave($data);
    }
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
     * 20220527
     * 查询提取账户类型
     */
    public function getFinanceAccountType(){
        $statementOrders = $this->objAttrsList('financeStatementOrder');
        //订单id，取statementOrder表的statementId;
        $con[] = ['order_id','=',$this->uuid];
        //20220608加条件
        $con[] = ['statement_cate','=','buyer'];                 
        $filterArr = Arrays2d::listFilter($statementOrders, $con);
        $statementIds = array_unique(array_column($filterArr,'statement_id'));

        return FinanceAccountLogService::statementIdsGetAccountType($statementIds);
    }
    /**
     * 20220903:获取客户已付金额
     */
    public function getBuyerPayPrize(){
        $statementOrders = $this->objAttrsList('financeStatementOrder');
        
        $con[]     = ['has_settle','=',1];
        $con[]     = ['statement_cate','=','buyer'];
        $filterArr = Arrays2d::listFilter($statementOrders, $con);
        $money = array_sum(array_column($filterArr,'need_pay_prize'));
        return $money;
    }
    /**
     * 20220903:获取已付供应商金额
     */
    public function getPaySellerPrize(){
        $statementOrders = $this->objAttrsList('financeStatementOrder');
        
        $con[]     = ['has_settle','=',1];
        $con[]     = ['statement_cate','=','seller'];
        $filterArr = Arrays2d::listFilter($statementOrders, $con);
        $money = array_sum(array_column($filterArr,'need_pay_prize'));
        return $money;
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
                return $picId && SystemFileService::mainModel()->where('id', $picId )->field('id,file_path,file_path as rawPath')->find()? : [];
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
            $v['plan_start_time'] = date('Y-m-d H:i',strtotime($v['plan_start_time']));
            $v['plan_finish_time'] = date('Y-m-d H:i',strtotime($v['plan_finish_time']));

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
            if(!isset($v['goods_name']) || !$v['goods_name']){
                $v['goods_name']    = Arrays::value($goodsInfo,'goods_name');
            }
            $v['unit_prize']    = Arrays::value($goodsInfo,'goodsPrize');
            if(!$orderGoodsName){
                $orderGoodsName = $v['goods_name'];
            }
        }
        //20220608:兼容可外部传入销售类型
        $saleType = $goodsArr ? GoodsService::getInstance( $goodsArr[0]['goods_id'] )->fSaleType() : '';
        //组织商品名称
        if( array_sum(array_column($goodsArr, 'amount')) > 1 && $saleType == 'normal' ){
            $orderGoodsName .= ' 等'.array_sum(array_column($goodsArr, 'amount')).'件商品';
        }
        $orderData['goods_name'] = $orderGoodsName;

        if(count($goodsArr) == 1){
            //单商品
            $orderData['goods_id']      = $goodsArr[0]['goods_id'];
            $orderData['amount']        = $goodsArr[0]['amount'] ? : 1;
            $orderData['order_type']    = $saleType;
            // 适用于某表多个记录使用同一个商品下单的情况（比如开发需求）
            if(Arrays::value($goodsArr[0], 'goodsTableId')){
                $orderData['goods_table_id'] = Arrays::value($goodsArr[0], 'goodsTableId');
            }
        }
        Db::startTrans();
            //先保存明细（主订单的保存有触发动作会用到）
            if( !isset($orderData['id']) || !self::getInstance($orderData['id'])->get() ) {
                //会存在更新订单：20211105:
                //20220617,从事务外部搬到内部
                $orderData['id'] = self::mainModel()->newId();
                OrderGoodsService::saveAll($goodsArr, ['order_id'=>$orderData['id']], 0 );
            }
            //再保存主订单
            $res    = self::saveGetId($orderData);
        Db::commit();
        return $res;
    }
    /**
     * 20220621，优化性能
     * @param type $goodsArr
     * @param type $orderData
     * @return type
     */
    public static function orderRam( $goodsArr ,$orderData = [] ){
        $orderGoodsName = '';
        //一次性取商品信息
        $goodsInfos = GoodsService::batchGet( array_column($goodsArr,'goods_id') );
        foreach( $goodsArr as &$v){
            $goodsInfo          = Arrays::value($goodsInfos, $v['goods_id'],[]);
            if(!isset($v['goods_name']) || !$v['goods_name']){
                $v['goods_name']    = Arrays::value($goodsInfo,'goods_name');
            }
            $v['unit_prize']    = Arrays::value($goodsInfo,'goodsPrize');
            if(!$orderGoodsName){
                $orderGoodsName = $v['goods_name'];
            }
        }
        //20220608:兼容可外部传入销售类型
        $saleType = $goodsArr ? GoodsService::getInstance( $goodsArr[0]['goods_id'] )->fSaleType() : '';
        //组织商品名称
        if( array_sum(array_column($goodsArr, 'amount')) > 1 && $saleType == 'normal' ){
            $orderGoodsName .= ' 等'.array_sum(array_column($goodsArr, 'amount')).'件商品';
        }
        $orderData['goods_name'] = $orderGoodsName;

        if(count($goodsArr) == 1){
            //单商品
            $orderData['goods_id']      = $goodsArr[0]['goods_id'];
            $orderData['amount']        = $goodsArr[0]['amount'] ? : 1;
            $orderData['order_type']    = $saleType;
            // 适用于某表多个记录使用同一个商品下单的情况（比如开发需求）
            if(Arrays::value($goodsArr[0], 'goodsTableId')){
                $orderData['goods_table_id'] = Arrays::value($goodsArr[0], 'goodsTableId');
            }
        }
        //先保存明细（主订单的保存有触发动作会用到）
        if( !isset($orderData['id']) || !self::getInstance($orderData['id'])->get() ) {
            //会存在更新订单：20211105:
            //20220617,从事务外部搬到内部
            $orderData['id'] = self::mainModel()->newId();
            OrderGoodsService::saveAllRam($goodsArr, ['order_id'=>$orderData['id']], 0 );
        }
        //再保存主订单
        $res    = self::saveGetIdRam($orderData);

        return $res;
    }
    /**
     * 计算订单总价
     * @param type $goodsArr 商品id和数量二维数组，包含 goods_id, amount 属性
     */
    public static function calOrderPrize( $goodsArr ){
        $goodsIds   = array_column($goodsArr,'goods_id') ;
        $goodsInfos = GoodsService::batchGet( $goodsIds );
        Debug::debug(__CLASS__.__FUNCTION__.'$goodsInfos',$goodsInfos);
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
        $orderInfo = $this->get();
        $classStr = self::orderTypeClass($orderInfo['order_type']);
        if(class_exists($classStr)){
            //20220615:计算订单价格
            $data['order_prize'] = $classStr::getInstance($orderId)->calOrderPrize();
            $data['need_outcome_prize'] = $classStr::getInstance($orderId)->calNeedOutcomePrize();
        } else {
            $data['order_prize'] = GoodsPrizeKeyService::orderPrize($orderId);
            //TODO
            $data['need_outcome_prize'] = '999';
        }
        // 配送费:配送费专用key
        $data['deliver_prize']  = GoodsPrizeKeyService::orderPrizeKeyGetPrize($orderId, 'DeliverPrize');
        Debug::debug(__CLASS__.'_'.__FUNCTION__.'$data',$data);
        return $data;
    }
    /**
     * 20220615 订单类型取映射处理类库
     */
    public static function orderTypeClass($orderType){
        $typeStr    = Strings::uncamelize($orderType);
        $tableName  = config('database.prefix').'order_'.$typeStr;
        return DbOperate::getService($tableName);
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
     * 订单已付？？
     */
    public function hasPay(){
        $info = $this->get();
        return $info && ($info['pay_prize'] - abs( $info['refund_prize'])) >= $info['pre_prize'];
    }
    /**
     * 获取订单的时间状态
     * @return type
     */
    public function orderTimeArr(){
        //用于流程识别
        $keysArr = ['BuyerReceive','SellerDeliverGoods','BuyerPay','orderFinish'];
        $timeArr = OrderFlowNodeService::getOrderTimeArr($this->uuid, $keysArr);
        Debug::debug('orderTimeArr的$timeArr',$timeArr);
        foreach($timeArr as &$v){
            if($v == '0000-00-00 00:00:00'){
                $v = null;
            }
        }
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
    
    public function info( $cache = 2  )
    {
        $orderInfo = $this->commInfo( $cache );
        if($orderInfo['is_delete']){
            return [];
        }
        $id = $this->uuid;
        $con[] = ['order_id','=',$id];
        //【取流程】
        $orderInfo['flowNodes']     = OrderFlowNodeService::lists($con,'id desc');
        //【客户信息】
        $orderInfo['userInfo']      = UserService::mainModel()->where('id',$orderInfo['user_id'])->field('id,username,nickname,realname,headimg,phone')->find();
        //【订单商品】
        $orderInfo['orderGoods']    = OrderGoodsService::orderGoodsInfo($id);
        //【取费用】
        //$orderInfo['financeStatements']         = FinanceStatementOrderService::orderStatementLists($id);
        $orderInfo['financeStatements'] = OrderService::getInstance($id)->objAttrsList('financeStatementOrder');

        $condStatement   = [];
        $condStatement[] = ['order_id','in',$id];
        $condStatement[] = ['has_settle','=',0];
        $condStatement[] = ['statement_cate','=','buyer'];
        //20220902:增加改变类型
        $condStatement[] = ['change_type','=',1];
        $statementLists = FinanceStatementOrderService::mainModel()->where($condStatement)->field('id,need_pay_prize,order_id')->select();
        $orderInfo['buyerNeedPayStatements']    = $statementLists;
        // 支付剩余时间：15分钟：按下单时间计算
        $remainSeconds = strtotime($orderInfo['create_time']) - time() + 900 ;
        $orderInfo['payRemainSeconds']  = $remainSeconds > 0 ? $remainSeconds : 0;
        // 模板消息发送记录
        $cone[] = ['order_id','in',$id];
        $flowNodeIds = OrderFlowNodeService::mainModel()->where($cone)->column('id');
        $orderInfo['tplMsgs'] = WechatWePubTemplateMsgLogService::listByFromTableId( array_merge($flowNodeIds,[$id]) );
        //是否超过时限；超过不可退款
        $orderInfo['isExpire'] = $orderInfo['plan_start_time'] && (time() - strtotime($orderInfo['plan_start_time'])) > 0 ? true :false ;
        // 是否今日订单
        if($orderInfo['company_id'] == '3'){
            $orderInfo['isToday'] = date('Y-m-d', strtotime($orderInfo['plan_start_time'])) == date('Y-m-d');
        } else {
            $orderInfo['isToday'] = false;
        }
        //20220906:控制业务员平板显示
        //$orderInfo['isIpad']    = Device::isIpad() ? 1: 0;
        return $orderInfo;
    }
    
    /**
     * 是否指定小时内小时内
     */
    public function inHours( $hours )
    {
        if(!$hours){
            return false;
        }
        $info = $this->get();
        Debug::debug('planStartTime',$info['plan_start_time']);
        Debug::debug('当前时间',date('Y-m-d H:i:s',strtotime('+'. $hours .' hours')));

        if( $info['plan_start_time']  < date('Y-m-d H:i:s',strtotime('+'. $hours .' hours'))){
            return true;
        }
        return false;
    }
    /**
     * 额外输入信息
     */
    public static function extraPreSave(&$data, $uuid) {
        DataCheck::must($data, ['user_id']);
        self::checkFinanceTimeLock(Arrays::value($data, 'plan_start_time'));
        UserService::getInstance($data['user_id'])->checkUserPhone();
        //20210812，测到金额bug注释:由::order方法控制
        $goodsId     = Arrays::value($data, 'goods_id');
        if($goodsId){
            $goodsInfo   = GoodsService::getInstance( $goodsId )->get(MASTER_DATA);
            $goodsName   = Arrays::value($goodsInfo,'goods_name');
            if(Arrays::value($goodsInfo,'goods_status') != 'onsale'){
                throw new Exception('商品'.$goodsName.'已经销售或未上架');
            }
            // 20220213可外部传，兼容支付账单体现订单概要信息
            if(!isset($data['goods_name'] ) || !$data['goods_name']){
                $data['goods_name']             = $goodsName;
            }
            $data['goods_table']            = Arrays::value($goodsInfo,'goods_table');
            //兼容开发需求（多记录使用同商品下单）
            $data['goods_table_id']         = Arrays::value($data,'goods_table_id') ? : Arrays::value($goodsInfo,'goods_table_id');
            $data['seller_customer_id']     = Arrays::value($data,'seller_customer_id') ? : Arrays::value($goodsInfo,'customer_id');
            $data['seller_user_id']         = Arrays::value($data,'seller_user_id') ? : Arrays::value($goodsInfo,'seller_user_id');
            $data['order_type']             = Arrays::value($goodsInfo,'sale_type');
            $data['shop_id']                = Arrays::value($goodsInfo,'shop_id');
        }
        //客户和用户进行绑定,方便下次下单
        if($data['customer_id'] && $data['user_id']){
            CustomerUserService::bind($data['customer_id'], $data['user_id']);
        }
        //
        Debug::debug(__CLASS__.__FUNCTION__,$data);
        
        return $data;
    }
    
    public static function extraPreUpdate( &$data ,$uuid )
    {
        self::checkTransaction();
        $info = self::getInstance($uuid)->get();

        self::checkFinanceTimeLock(Arrays::value($info, 'plan_start_time'));
        if(Arrays::value($data, 'plan_start_time')){
            self::checkFinanceTimeLock(Arrays::value($data, 'plan_start_time'));
        }

        if(isset($data['is_cancel']) && $data['is_cancel'] && $data['is_cancel'] != $info['is_cancel']){
            if($info['is_complete']){
                throw new Exception('已结订单不可取消'.$uuid);
            }
            if(!isset($data['cancel_by'])){
                throw new Exception('未指定取消人cancel_by'.$uuid);
            }
        }
        //②写入订单子表
        $subService = self::getSubService( $info['order_type'] );
        Debug::debug('$subService',$subService);
        if( $info['order_type'] && class_exists($subService) ){
            $subService::getInstance( $uuid )->update( $data );
        }
        $infoArr = $info ? $info->toArray() : [];
        // 20221115：获取差异数组
        $diffInfo = Arrays::diffArr($infoArr, $data);
        OrderChangeLogService::log('orderChange', $uuid, '', $diffInfo);

        return $data;
    }    
    
    public static function extraDetails( $ids ){
        return self::commExtraDetails($ids, function($lists) use ($ids){
            // 转化成数组
            $ids = $ids ? (is_array($ids) ? $ids : [$ids] ) : [];
            //Debug::debug('入参id数组',$ids);
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
            // 先批量查询一次
            $circuitBusIds = Arrays2d::uniqueColumn($lists, 'circuit_bus_id');
            $conCB[] = ['id','in',$circuitBusIds];
            // 批量查询一次，提升性能
            $circuitBusLists = CircuitBusService::listsArr($conCB);
            //20211213;站点id数组
            $stationIds     = array_unique(array_merge(array_column($lists,'from_station_id'),array_column($lists,'to_station_id')));
            $stationAttr    = Arrays::isEmpty($stationIds) ? [] : StationService::mainModel()->where([['id','in',$stationIds]])->column('station','id');
            //乘客
            $passengerArr   = OrderPassengerService::orderGroupBatchSelect($ids);
            //乘客
            $tourPassengerArr   = TourPassengerService::groupBatchSelect('order_id',$ids);
            //20230303:团客
            $tourPassengerCount = TourPassengerService::groupBatchCount('order_id',$ids);
            //订单车辆
            // $baoOrderIds = CircuitBusService::baoOrderIds($circuitBusIds);
            $baoOrderIds    = Arrays2d::uniqueColumn($circuitBusLists, 'bao_order_id');
            // 批量查询一次，提升性能
            self::listsArr([['id','in',$baoOrderIds]]);
            $orderBusArr    = OrderBaoBusService::orderBusBatchSelect(array_merge($ids,$baoOrderIds));
            //订单模板消息
            $wechatWePubTemplateMsgLogCount = WechatWePubTemplateMsgLogService::groupBatchCount('from_table_id', $ids);
            //20220610,用于提取后向订单id
            $conf[] = ['pre_order_id','in',$ids] ; 
            //20220615,修bug
            $conf[] = ['is_delete','=',0] ; 
            $afterOrders    = self::mainModel()->where($conf)->field('id,pre_order_id')->select();
            $afterOrdersArr = $afterOrders ? $afterOrders->toArray() : [];
            $afterOrderObj  = Arrays2d::fieldSetKey($afterOrdersArr, 'pre_order_id');
            //20220813：用户绑定统计
            $wechatWePubBindCount   = WechatWePubFansUserService::groupBatchCount('user_id', array_column($lists,'user_id'));
            
            $stOrderCount           = FinanceStatementOrderService::groupBatchCount('order_id', $ids);
            //订单商品记录数
            $orderGoodsCount        = OrderGoodsService::groupBatchCount('order_id', $ids);

            foreach($lists as &$item){
                //团客数
                $item['tourPassengerCount'] = Arrays::value($tourPassengerCount, $item['id'],0);
                //20220730兼容前端；TODO更优？？
                $item['need_invoice'] = strval($item['need_invoice']);
                $item['lastFlowNode'] = OrderFlowNodeService::orderLastFlow( $item['id'] );
                //20211115:优化
                if($item['order_type'] == 'bao'){
                    //$item['baoBuses'] = OrderBaoBusService::orderBusList( $item['id'] );
                    $item['baoBuses'] = Arrays::value($orderBusArr, $item['id'],[]);
                    // 20220904：趟数
                    $item['baoBusesCount']  = count($item['baoBuses']);
                    $item['route']          = count($item['baoBuses']) ? $item['baoBuses'][0]['route'] : '';
                    $item['route']         .= count($item['baoBuses']) > 1 ? '等' : '';
                    //20220815:是否有已排车辆（控制客户端订单不可取消）
                    $item['hasArrangedBus'] = $item['baoBuses'] && array_unique(array_column($item['baoBuses'],'bus_id'))[0] != '' ? 1 :0;
                }
                if(in_array($item['order_type'],['bao','pin','ding'])){
                    //订单乘客
                    $item['orderPassengers']        = Arrays::value($passengerArr, $item['id']) ? : [];
                    $item['orderPassengerCount']    = count($item['orderPassengers']);
                    $item['orderPassengerName']     = implode(',',array_column($item['orderPassengers'],'realname'));
                    // 20230214:所有的乘客是否已排好了车辆
                    $item['orderPassengerAllHasBus'] = OrderPassengerService::allHasBus($item['orderPassengers']) ? 1: 0;
                }
                // 20230314:旅游团
                if(in_array($item['order_type'],['tour'])){
                    //订单乘客
                    $item['tourPassengers']         = Arrays::value($tourPassengerArr, $item['id']) ? : [];
                    $item['tourPassengerCount']     = count($item['tourPassengers']);
                }
                if(in_array($item['order_type'],['pin'])){
                    $baoOrderId = CircuitBusService::getInstance($item['circuit_bus_id'])->fBaoOrderId();
                    if($baoOrderId){
                        //订单乘客
                        $item['tempBaoOrderId'] = $baoOrderId;
                        $item['buses'] = $baoOrderId 
                                ? Arrays2d::getByKeys(Arrays::value($orderBusArr, $baoOrderId,[]),['id','licence_plate']) 
                                : [] ;
                    } else {
                        $item['tempBaoOrderId'] = '';
                        $item['buses'] = [] ;
                    }
                }
                $item['fromStationName']    =   Arrays::value($stationAttr, $item['from_station_id']);
                $item['toStationName']      =   Arrays::value($stationAttr, $item['to_station_id']);
                //微信模板消息数
                $item['wechatWePubTemplateMsgLogCount'] = Arrays::value($wechatWePubTemplateMsgLogCount, $item['id'],0);
                // 下单用户是否已绑定微信
                $item['isUserBind']         = Arrays::value($wechatWePubBindCount, $item['user_id']) ? 1 : 0;
                // 20220609 circuitBusId是否有值：用于控制页面显示
                $item['hasCircuitBus']      = $item['circuit_bus_id'] ? 1 : 0;
                $item['afterOrderId']       = isset($afterOrderObj[$item['id']]) ? $afterOrderObj[$item['id']]['id'] : '';
                // 20220610是否有前序订单
                $item['hasPreOrder']        = $item['pre_order_id'] ? 1: 0;
                // 20220610是否有后序订单（后序由前序计算）
                $item['hasAfterOrder']      = $item['afterOrderId'] ? 1: 0;
                // 20220812:客户已签章
                $item['hasBuyerSign']       = $item['buyer_sign'] ? 1: 0;
                // 20220812:供应商已签章
                $item['hasSellerSign']          = $item['seller_sign'] ? 1: 0;
                // 订单时间是否已过：控制不可取消
                $item['isTimePass']             = $item['plan_start_time'] && strtotime($item['plan_start_time']) < time() ? 1 :0;
                // 20220904：当前用户是否订单创建者。用于前台客户页面控制是否可删除
                $item['isCreater']              = $item['creater'] == session(SESSION_USER_ID) ? 1: 0;
                // 是否有资金变动记录
                $item['hasMoneyPay']            = abs($item['pay_prize']) || abs($item['refund_prize']) > 0 ? 1: 0;
                // 账单数
                $item['statementOrderCount']    = Arrays::value($stOrderCount, $item['id'],0);
                // 订单商品记录数
                $item['orderGoodsCount']        = Arrays::value($orderGoodsCount, $item['id'],0);
            }
            return $lists;
        });
        
        
    }
    /**
     * 额外输入信息
     */
    public static function extraAfterSave(&$data, $uuid) {
        OrderFlowNodeService::lastNodeFinishAndNext($uuid);
        //②写入订单子表
        $subService = self::getSubService( $data['order_type'] );
        if( $data['order_type'] && class_exists($subService) ){
            $data['id'] = $uuid;
            $subService::getInstance( $uuid )->save( $data );
        }
        return $data;
    }
    
    /**
     * 额外输入信息
     */
    public static function extraAfterUpdate(&$data, $uuid) {
        Debug::debug(__CLASS__.__FUNCTION__,$data);        
        $info   = self::getInstance( $uuid )->get();
        //尝试流程节点的更新
        OrderFlowNodeService::lastNodeFinishAndNext($uuid);
        //②写入订单子表
        $subService = self::getSubService( $info['order_type'] );
        if( $info['order_type'] && class_exists($subService) ){
            $subService::getInstance( $uuid )->update( $data );
        }
        //20211215订单取消，解绑座位
        if($data['is_cancel']){
            $passengers = OrderPassengerService::mainModel()->where('order_id',$uuid)->select();
            foreach($passengers as &$v){
                OrderPassengerService::getInstance($v['id'])->update(['is_ref'=>1]);
            }
        } else {
            //判定订单完成，给下单人赠送积分的触发动作
            ScoreLogic::score( $info['user_id'] );
        }
        
        //20220318：TODO更优化的功能
        //20220615：取消包可否？？？
        if(isset($data['order_prize']) && $info['order_type'] == 'bao'){
        //if(isset($data['order_prize'])){
            FinanceStatementOrderService::updateOrderMoney($uuid, $data['order_prize']);
        }
        //20220630??
        self::getInstance($uuid)->orderDataSync();
        return $data;
    }
    
    /**
     * 20220619
     * @param type $data
     * @param type $uuid
     * @return type
     */
    public static function ramAfterUpdate(&$data, $uuid) {
        Debug::debug(__CLASS__.__FUNCTION__,$data);        
        $info   = self::getInstance( $uuid )->get();
        //TODO:去除事务校验再开启尝试流程节点的更新
        // OrderFlowNodeService::lastNodeFinishAndNext($uuid);
        //②写入订单子表
        $subService = self::getSubService( $info['order_type'] );
        if( $info['order_type'] && class_exists($subService) ){
            $subService::getInstance( $uuid )->updateRam( $data );
        }
        //20211215订单取消，解绑座位
        if(isset($data['is_cancel']) && $data['is_cancel']){
            $passengers = OrderPassengerService::mainModel()->where('order_id',$uuid)->select();
            foreach($passengers as &$v){
                OrderPassengerService::getInstance($v['id'])->updateRam(['is_ref'=>1]);
            }
        }

        //判定订单完成，给下单人赠送积分的触发动作
        ScoreLogic::score( $info['user_id'] );
        //20220622:更新订单的关联账单(含收付)
        self::getInstance($uuid)->updateFinanceStatementRam();
//        //20220622:增加同步订单数据
//        self::getInstance($uuid)->orderDataSyncRam();

        return $data;
    }
    
    public static function ramAfterSave(&$data, $uuid) {
        //OrderFlowNodeService::lastNodeFinishAndNext($uuid);
        //②写入订单子表
        $subService = self::getSubService( $data['order_type'] );
        if( $data['order_type'] && class_exists($subService) ){
            $data['id'] = $uuid;
            $subService::getInstance( $uuid )->saveRam( $data );
        }
        /*
        //20220622:更新订单的关联账单(含收付)
        self::getInstance($uuid)->updateFinanceStatementRam();
        //20220622:增加同步订单数据
        self::getInstance($uuid)->orderDataSyncRam();
         */

        return $data;
    }
    /**
     * 20220622优化性能
     * @param type $data
     * @param type $uuid
     * @return type
     * @throws Exception
     */
    public static function ramPreSave(&$data, $uuid) {
        DataCheck::must($data, ['user_id']);
        self::checkFinanceTimeLock(Arrays::value($data, 'plan_start_time'));
        UserService::getInstance($data['user_id'])->checkUserPhone();
        //20210812，测到金额bug注释:由::order方法控制
        $goodsId     = Arrays::value($data, 'goods_id');
        if($goodsId){
            $goodsInfo   = GoodsService::getInstance( $goodsId )->get(MASTER_DATA);
            $goodsName   = Arrays::value($goodsInfo,'goods_name');
            if(Arrays::value($goodsInfo,'goods_status') != 'onsale'){
                throw new Exception('商品'.$goodsName.'已经销售或未上架');
            }
            // 20220213可外部传，兼容支付账单体现订单概要信息
            if(!isset($data['goods_name'] ) || !$data['goods_name']){
                $data['goods_name']             = $goodsName;
            }
            $data['goods_table']            = Arrays::value($goodsInfo,'goods_table');
            //兼容开发需求（多记录使用同商品下单）
            $data['goods_table_id']         = Arrays::value($data,'goods_table_id') ? : Arrays::value($goodsInfo,'goods_table_id');
            $data['seller_customer_id']     = Arrays::value($data,'seller_customer_id') ? : Arrays::value($goodsInfo,'customer_id');
            $data['seller_user_id']         = Arrays::value($data,'seller_user_id') ? : Arrays::value($goodsInfo,'seller_user_id');
            $data['order_type']             = Arrays::value($goodsInfo,'sale_type');
            $data['shop_id']                = Arrays::value($goodsInfo,'shop_id');
        }
        //客户和用户进行绑定,方便下次下单
        if(Arrays::value($data,'customer_id')&& Arrays::value($data,'user_id')){
            CustomerUserService::bind($data['customer_id'], $data['user_id']);
        }

        return $data;
    }
    /**
     * 20220622
     * @throws Exception
     */
    public function ramPreDelete()
    {
        $info = $this->get();
        $classStr = self::orderTypeClass($info['order_type']);
        if(class_exists($classStr)){
            //20220623:关联删除
            $classStr::getInstance($this->uuid)->uniDelete();
        }
        
        if($info['plan_start_time']){
            self::checkFinanceTimeLock($info['plan_start_time']);
        }
        //对账单要先清
        FinanceStatementService::clearOrderNoDealRam($this->uuid);
        //才能清对账单明细
        FinanceStatementOrderService::clearOrderNoDealRam($this->uuid);
        
        $con[] = ['order_id','=',$this->uuid];
        $con[] = ['has_settle','=',1];
        $res = FinanceStatementOrderService::mainModel()->master()->where($con)->count(1);
        if($res){
            throw new Exception('该订单有收付款账单，不可删除,订单号'.$this->uuid);
        }
        $fromTable = self::mainModel()->getTable();
        $userAccountHasLog = UserAccountLogService::hasLog($fromTable, $this->uuid);
        if($userAccountHasLog){
            throw new Exception('该订单有用户账户记录'.$userAccountHasLog['id'].'，不可删除');
        }
        $conB[] = ['order_id','=',$this->uuid];
        // 包车订单适用
        $baoBusIds = OrderBaoBusService::mainModel()->master()->where($conB)->column('id');
        if($baoBusIds){
            //删除订单用车
            foreach($baoBusIds as $baoBusId){
                OrderBaoBusService::getInstance($baoBusId)->deleteRam();
            }
        }
        //20220610：有后向订单的不可删；有前向订单不影响；
        $conf[] = ['is_delete','=',0];
        $conf[] = ['pre_order_id','=',$this->uuid];
        $afterOrderId = self::mainModel()->where($conf)->value('id');
        if($afterOrderId && !DbOperate::isGlobalDelete($fromTable, $afterOrderId)){
            throw new Exception('该订单有后向订单'.$afterOrderId.'，不可删');
        }
        $conPin[] = ['order_id','=',$this->uuid];
        // 拼团订单适用
        $passengerIds = OrderPassengerService::mainModel()->master()->where($conPin)->column('id');
        if($passengerIds){
            //删除订单用车
            foreach($passengerIds as $passengerId){
                OrderPassengerService::getInstance($passengerId)->deleteRam();
            }
        }
    }
    
    /**
     * 删除价格数据
     */
    public function ramAfterDelete()
    {
        // 删流程
        $con[] = ['order_id','=',$this->uuid];
        $nodeLists = OrderService::getInstance($this->uuid)->objAttrsList('orderFlowNode');
        foreach($nodeLists as $v){
            OrderFlowNodeService::getInstance($v['id'])->deleteRam();
        }

        // 20220601拼团对应单置为未提交排班
        $cone[] = ['bao_order_id','=',$this->uuid];
        $circuitBusId = CircuitBusService::mainModel()->where($cone)->value('id');
        if($circuitBusId){
            CircuitBusService::getInstance($circuitBusId)->updateRam(['bao_order_id'=>'']);
            $info = $this->get();
            self::getInstance($info['pre_order_id'])->deleteRam();
        }
        // 删商品
        $goodsIds = OrderGoodsService::ids($con);
        foreach($goodsIds as $id){
            //为了使用触发器20210802
            OrderGoodsService::getInstance( $id )->deleteRam();
        }
    }
    
    /**
     * 更新订单的财务账信息
     */
    public function updateFinanceStatementRam(){
        $orderInfo = $this->get();
        $classStr = self::orderTypeClass($orderInfo['order_type']);
        //20220622：找个地方取订单金额：
        if(class_exists($classStr)){
            //20220615:计算订单价格
            $orderPrize     = $classStr::getInstance($this->uuid)->calOrderPrize();
            $sellerPrizeArr = $classStr::getInstance($this->uuid)->calSellerPrizeArr();
        } else {
            $orderPrize = GoodsPrizeKeyService::orderPrize($this->uuid);
            $sellerPrizeArr = [];
        }
        //收客户钱
        FinanceStatementOrderService::updateOrderMoneyRam($this->uuid, $orderPrize);
        //付供应商钱
        FinanceStatementOrderService::updateNeedOutcomePrizeRam($this->uuid, $sellerPrizeArr);
        // 同步订单数据
        self::getInstance($this->uuid)->orderDataSyncRam();
        return true;
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
        //20220527:增加付款方式
        $updData['finance_account_type'] = $this->getFinanceAccountType();
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
        //20220413:更优化？更新拼车的付款状态
        if($info['pay_prize'] >= $info['order_prize'] && $info['order_type'] == 'pin'){
            $cone   = [];
            $cone[] = ['order_id','=',$orderId];
            OrderPassengerService::mainModel()->where($cone)->update(['is_pay'=>1]);
        }
        //20220516:包车订单，更新单趟次的费用信息；
        if($info['order_type'] == 'bao'){
            OrderBaoBusService::updateFinancePrize($this->uuid);
        }
        
        return $res;
    }
    
    /**
     * 20220620订单数据同步
     * 一般用于各种操作完成后
     */
    public function orderDataSyncRam(){
        $prizeData  = $this->orderPrize();
        $moneyData  = FinanceStatementOrderService::orderMoneyData($this->uuid);
        //只更新数据，不执行触发
        //$updData    = array_merge($timeData, $prizeData, $moneyData, $lastNodeData);
        $updData    = array_merge( $prizeData, $moneyData);
        $updData['finance_account_type'] = $this->getFinanceAccountType();
        //20220624死循环？？
        $res = $this->doUpdateRam(array_merge($updData));
        //$res = $this->updateRam(array_merge($updData));
        $info = $this->get();
        //TODO,其他bug
        if($info['order_type'] == 'bao'){
            OrderBaoBusService::updateFinancePrizeRam($this->uuid);
        }

        //20220621:递归更新前序订单
        $preOrderId = $this->fPreOrderId();
        if($preOrderId){
            self::getInstance($preOrderId)->orderDataSyncRam();
        }
        return $res;
    }
    /**
     * 20230324:不带数据权限（TODO更好？？）
     * 暂时给后台的订单列表用
     * @param type $con
     * @param type $order
     * @param type $perPage
     * @param type $having
     * @param type $field
     * @param type $withSum
     * @return type、
     */
    public static function paginateForAdmin($con = [], $order = '', $perPage = 10, $having = '', $field = "*", $withSum = false) {
        //默认带数据权限
        $conAll = array_merge($con, self::commCondition());
        // 查询条件单拎；适用于后台管理（客户权限，业务员权限）
        return self::paginateRaw($conAll, $order, $perPage, $having, $field, $withSum);
    }
    /**
     * 优先级：是公司管理员，不加条件；
     * 如果是客户；仅查询该客户名下订单
     * TODO 如果都不是，仅查询该用户名下订单。
     * TODO 20220407如何兼容现有后台用户？？？
     * @return type
     */
    public static function extraDataAuthCond(){
        //20230324:
        $sessionUserInfo = session(SESSION_USER_INFO);
        if($sessionUserInfo['admin_type'] == 'super'){
            return [];
        }
        $authCond = [];
        //过滤用户可查看的项目权限
        $userId = session(SESSION_USER_ID);
        $cond[] = ['user_id','=',$userId];
        //20220809：增加管理员才能查看该客户下全部订单
        $cond[] = ['is_manager','=',1];
        $customerIds = CustomerUserService::mainModel()->where($cond)->column('customer_id');
        // 20220525发现后台管理员查看数据会被过滤，增加!$sessionUserInfo['isCompanyManage']判断
        if(!$sessionUserInfo['isCompanyManage']){
            //20220422，TODO，如何处理？？会导致拼团查不到车票；但是客户端口又需要过滤数据
            //$authCond[] = ['customer_id','in',$customerIds];
            //20220430,临时使用，是否有更好的方法？？
            if($customerIds){
                $customerIds[]  = session(SESSION_USER_ID);
                $authCond[]     = ['custUser','in',$customerIds];
            } else {
                // 20220809非管理员，只过滤自己下单的记录
                $authCond[]     = ['user_id','in',session(SESSION_USER_ID)];
            }
        }
        //TODO如果不是项目成员，只能查看自己提的需求
        return $authCond;
    }
    
    public function extraPreDelete()
    {
        self::checkTransaction();
        $info = $this->get();
        if($info['plan_start_time']){
            self::checkFinanceTimeLock($info['plan_start_time']);
        }
        //对账单要先清
        FinanceStatementService::clearOrderNoDeal($this->uuid);
        //才能清对账单明细
        FinanceStatementOrderService::clearOrderNoDeal($this->uuid);
        
        $con[] = ['order_id','=',$this->uuid];
        $res = FinanceStatementOrderService::mainModel()->master()->where($con)->count(1);
        if($res){
            throw new Exception('该订单有收付款账单，不可删除,订单号'.$this->uuid);
        }
        $fromTable = self::mainModel()->getTable();
        $userAccountHasLog = UserAccountLogService::hasLog($fromTable, $this->uuid);
        if($userAccountHasLog){
            throw new Exception('该订单有用户账户记录'.$userAccountHasLog['id'].'，不可删除');
        }
        // 包车订单适用
        $baoBusIds = OrderBaoBusService::mainModel()->master()->where($con)->column('id');
        if($baoBusIds){
            //删除订单用车
            foreach($baoBusIds as $baoBusId){
                OrderBaoBusService::getInstance($baoBusId)->delete();
            }
        }
        //20220610：有后向订单的不可删；有前向订单不影响；
        $conf[] = ['is_delete','=',0];
        $conf[] = ['pre_order_id','=',$this->uuid];
        $hasAfterOrder = self::mainModel()->where($conf)->count();
        if($hasAfterOrder){
            throw new Exception('该订单有后向订单，不可删');
        }
        
        // 拼团订单适用
        $passengerIds = OrderPassengerService::mainModel()->master()->where($con)->column('id');
        if($passengerIds){
            //删除订单用车
            foreach($passengerIds as $passengerId){
                OrderPassengerService::getInstance($passengerId)->delete();
            }
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
        // 20220601拼团对应单置为未提交排班
        CircuitBusService::mainModel()->where('bao_order_id',$this->uuid)->update(['bao_order_id'=>'']);
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
     * 20220609 真的删
     */
    public function destroy(){
        $info = self::mainModel()->where('id',$this->uuid)->find();
        if($info['company_id'] != session(SESSION_COMPANY_ID)){
            throw new Exception('访问入口与当前订单不匹配');
        }
        if(!$info['is_delete']){
            throw new Exception('订单未删不可销毁'.$this->uuid);
        }
        $res = self::mainModel()->where('id',$this->uuid)->delete();
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
     * 0830：接单
     */
    public function accept(){
        $data['has_accept']     = 1;
        $data['accept_user_id'] = session(SESSION_USER_ID);
        $data['accept_time']    = date('Y-m-d H:i:s');
        return $this->update($data);
    }
    /**
     * 取消接单
     */
    public function cancelAccept(){
        $data['has_accept']     = 0;
        $data['accept_user_id'] = '';
        $data['accept_time']    = null;
        return $this->update($data);
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
     * 后续订单id
     */
    public function cAfterOrderId(){
        $con[] = ['pre_order_id','=',$this->uuid];
        $con[] = ['is_delete','=',0] ; 
        return self::mainModel()->where($con)->value('id');
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
    //是否结单
    public function fIsComplete() {
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
