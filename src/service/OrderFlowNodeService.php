<?php

namespace xjryanse\order\service;

use xjryanse\goods\service\GoodsService;
use xjryanse\goods\service\GoodsPrizeKeyService;
use xjryanse\goods\service\GoodsPrizeRefTplService;
use xjryanse\order\service\OrderFlowNodeTplService;
use xjryanse\order\service\OrderService;
use xjryanse\system\service\SystemConditionService;
use xjryanse\finance\service\FinanceStatementOrderService;
use xjryanse\logic\Debug;
use xjryanse\logic\Arrays;
use think\facade\Request;
use Exception;
/**
 * 订单流程
 */
class OrderFlowNodeService {
    use \xjryanse\traits\InstTrait;
    use \xjryanse\traits\MainModelTrait;

    public static $lastNodeFinishCount   = 0 ;   //末个节点执行次数
    protected static $nextNodeKey           = '' ;  //下一个流程节点
    protected static $mainModel;
    protected static $mainModelClass = '\\xjryanse\\order\\model\\OrderFlowNode';

    /**
     * 额外详情信息
     */
    protected static function extraDetail( &$item ,$uuid )
    {
        //添加分表数据:按类型提取分表服务类
        if(!$item){
            return false;
        }
        //订单状态是否取消
        $item['orderIsCancel'] = OrderService::getInstance($item['order_id'])->fIsCancel();
        //订单状态由谁取消
        $item['orderCancelBy'] = OrderService::getInstance($item['order_id'])->fCancelBy();
        return $item;
    }
    
    /**
     * 流程节点删除
     * @return type
     */
    public function delete(){
        //校验事务
        self::checkTransaction();
        //获取信息
        $info           = $this->get();
        $orderLastFlow  = self::orderLastFlow($info['order_id']);
        if( $info['id'] != $orderLastFlow['id'] ){
            throw new Exception('请先删除最后一个节点');
        }
        $con[] = ['order_id','=',$info['order_id']];
        $count = self::mainModel()->where( $con )->count();
        if($count == 1){
            throw new Exception('第一节点不可删');
        }
        //删除
        $res = $this->commDelete();
        //最后一个节点更新为待处理。
        $orderLastFlow2 = self::orderLastFlow( $info['order_id'] );
        //已软删节点，此处直接删除
        if($orderLastFlow2['is_delete'] == 1){
            self::getInstance($orderLastFlow2['id'])->delete();
        } else {
            //更新状态
            self::mainModel()->where("id",$orderLastFlow2['id'])->update(['flow_status'=>"todo"]);
        }
        //结果返回
        return $res;
    }
    /**
     * 额外输入信息
     */
    public static function extraPreSave(&$data, $uuid) {
        self::checkTransaction();
        $orderId = Arrays::value($data, 'order_id');
        $data['order_type'] = OrderService::getInstance( $orderId )->fOrderType();
        Debug::debug('正在保存订单节点信息',$data);        
        return $data;
    }
    
    public static function extraPreUpdate(&$data, $uuid) {
        self::checkTransaction();
        return $data;
    }
    /**
     * 更新，新增共用
     */
    protected static function afterOperate(&$data, $uuid)
    {
        $info               = self::getInstance( $uuid )->get();
        //步骤2：
        $preNode            = OrderFlowNodeTplService::getPreNode( Arrays::value($data, 'node_key'));            
        $orderUpdateData    = [];

        if( !$preNode || (Arrays::value($data, 'flow_status') && $data['flow_status'] == XJRYANSE_OP_FINISH)){
            self::lastNodeFinishAndNext($info['order_id']);
            //TODO:拆分公共
            if( $info['node_key'] == 'orderClose'){
                //20210326注释
//                $orderInfo = OrderService::getInstance($info['order_id'])->get();
//                GoodsService::setOnSaleByGoodsTableId($orderInfo['goods_table_id'], $orderInfo['goods_table']);
                $orderUpdateData['order_status']    = ORDER_CLOSE;
//                OrderService::getInstance($info['order_id'])->update(['order_status'=>ORDER_CLOSE]);    //交易关闭
            }
        }
        //订单完成：修改订单状态
        if( $info['node_key'] == 'orderFinish'){
            $orderUpdateData['order_status']    = ORDER_FINISH;
//            OrderService::getInstance($info['order_id'])->update(['order_status'=>ORDER_FINISH]);   //交易完成
        }
        //更新最后节点状态
        if( Arrays::value($data, 'node_key') ){
            $orderUpdateData['lastFlowNodeRole']   = Arrays::value($data, 'operate_role');
            $orderUpdateData['orderLastFlowNode']  = Arrays::value($data, 'node_key');
        }
        //TODO校验影响20210309
        OrderService::mainModel()->where('id',$info['order_id'])->update( $orderUpdateData );    //交易关闭
        
        //20210326：后更新，增加订单结束判断
        if( $info['node_key'] == 'orderClose'){
            $orderInfo = OrderService::getInstance($info['order_id'])->get();
            GoodsService::setOnSaleByGoodsTableId($orderInfo['goods_table_id'], $orderInfo['goods_table']);
        }

        return $data;
    }
    /**
     * 额外输入信息
     */
    public static function extraAfterSave(&$data, $uuid) {
        $info               = self::getInstance( $uuid )->get();
        //步骤1：TODO校验影响20210311：更新定金的金额（要放在下一个流程节点判断的前面）
        Debug::debug('尝试更新订单的预付金额data',$data);
        Debug::debug('尝试更新订单的预付金额',Arrays::value($data, 'node_key'));
        OrderFlowNodePrizeTplService::setOrderPrePrize( $info['order_id'], Arrays::value($data, 'node_key') );
        //没有待收待付记录，则添加一下：20210319（有涉及条件判断，必须放在self::afterOperate之前）
        OrderFlowNodeService::addFinanceStatementOrder( $uuid );
        //修改订单状态，更新节点等操作
        $data = self::afterOperate($data, $uuid);
        return $data;
    }
    
    /**
     * 额外输入信息
     */
    public static function extraAfterUpdate(&$data, $uuid) {
        $res    = self::afterOperate($data, $uuid);
        $info   = self::getInstance( $uuid )->get(0);
        //步骤：删除关联的未结账单
        if( Arrays::value($info, 'flow_status') == XJRYANSE_OP_CLOSE && Arrays::value($info, 'prize_key')){
            //被关闭后，删除未结算的账单明细
            $con[] = ['order_id','=',Arrays::value($info, 'order_id')];
            $con[] = ['statement_type','=',Arrays::value($info, 'prize_key')];
            $con[] = ['has_settle','=',0];
            $lists = FinanceStatementOrderService::mainModel()->where( $con )->select();
            foreach( $lists as $key=>$value){
                //一个个删
                if(!Arrays::value($value, 'statement_id')){
                    FinanceStatementOrderService::mainModel()->where( 'id',$value['id'] )->delete();
                }
            }
        }
        return $res;
    }

    /**
     * 添加关联收付款明细
     */
    public static function addFinanceStatementOrder( $orderFlowNodeId )
    {
        $info           = self::getInstance( $orderFlowNodeId )->get(0);
        Debug::debug('addFinanceStatementOrder的$info',$info);
        $orderId        = Arrays::value($info, 'order_id');
        $prizeKeys      = Arrays::value($info, 'prize_key');
        Debug::debug('addFinanceStatementOrder的$orderId',$orderId);
        Debug::debug('addFinanceStatementOrder的$prizeKey',$prizeKeys);

        $prizeKeyArr = explode(',',$prizeKeys); //兼容多个价格的情况
        foreach( $prizeKeyArr as $prizeKey){
            //价格账单是否已存在
            $con[] = ['order_id','=',$orderId];
            $con[] = ['statement_type','=',$prizeKey];
            $hasStatementOrder  = FinanceStatementOrderService::count($con);
            Debug::debug('addFinanceStatementOrder的$hasStatementOrder',$hasStatementOrder);
            Debug::debug('addFinanceStatementOrder的$prizeKey',$prizeKey);
            $goodsPrizeInfo         = GoodsPrizeKeyService::getByPrizeKey( $prizeKey );  //价格key取归属
            //有key，无记录，则自动添加一条；有key，且key不可重复，则不添加
            if(!$prizeKey || ($hasStatementOrder && !Arrays::value($goodsPrizeInfo,'is_duplicate'))){
                return false;
            }

            if( Arrays::value($goodsPrizeInfo,'type') == 'ref' ){
                //【退款】 ref
                //todo,优化合并，在退款规则中再找一找：20210319
                $orderInfo      = OrderService::getInstance( $orderId )->get(0);
                Debug::debug('addFinanceStatementOrder，从退款获得的$prizeKey',$prizeKey);
                $needPayPrize   = GoodsPrizeRefTplService::orderGetRef($orderId, $prizeKey, Arrays::value($orderInfo , 'cancel_by'));
                Debug::debug('addFinanceStatementOrder，从退款获得的'.$prizeKey.'的$needPayPrize',$needPayPrize);
            } else {
                //【付款】 pay
                $needPayPrize           = OrderService::getInstance( $orderId )->prizeKeyGetPrize( $prizeKey );
                Debug::debug('addFinanceStatementOrder，从订单获得的$needPayPrize',$needPayPrize);
                if(!$needPayPrize){
                    //从请求中来：兼容自己输入价格的情况。
                    $needPayPrize = Request::param( $prizeKey ,0);
                    Debug::debug('addFinanceStatementOrder，从请求获得的$needPayPrize',$needPayPrize);
                }
            }
            //有价格才添加
            if(!$needPayPrize){
                return false;
            }
            $data['order_id']       = $orderId;
            $data['change_type']    = Arrays::value($goodsPrizeInfo,'change_type') ;
            $data['statement_cate'] = GoodsPrizeKeyService::keyBelongRole( $prizeKey );  //价格key取归属
            $data['need_pay_prize'] = $needPayPrize;
            $data['statement_type'] = $prizeKey;
            //增加是否退款的判断
            $data['is_ref'] = Arrays::value($goodsPrizeInfo,'type') == 'ref' ? 1 :  0;
            Debug::debug('【最终添加】addFinanceStatementOrder，的data',$data);
            $res = FinanceStatementOrderService::save( $data );
        }
        return $res;
    }
    
    
    
    /**
     * 给订单添加流程【参数有优化】
     * @param type $orderId
     * @param type $nodeKey
     * @param type $nodeName
     * @param type $operateRole
     * @param type $flowStatus
     * @param array $data
     * @return type
     */
    public static function addFlow( $orderId ,$nodeKey,$nodeName,$operateRole, array $data=[])
    {
        //订单id
        $data['order_id']       = $orderId;
        //节点key
        $data['node_key']       = $nodeKey;
        //节点名称
        $data['node_name']      = $nodeName;
        //操作角色
        $data['operate_role']   = $operateRole;
        //流程状态:默认为待完成。
        $data['flow_status']    = XJRYANSE_OP_TODO;
        
        //订单信息
        $orderInfo  = OrderService::getInstance( $orderId )->get();    
        $data['company_id'] = Arrays::value($orderInfo, 'company_id');
        //TODO，增加映射条件进行取数据

        //卖家
        if( $operateRole == 'seller' ){
            $data['operate_user_id']        = $orderInfo['seller_user_id'];
            $data['operate_customer_id']    = $orderInfo['seller_customer_id'];
        } else if( $operateRole == 'buyer' ){
            //买家
            $data['operate_user_id']        = $orderInfo['user_id'];
            $data['operate_customer_id']    = $orderInfo['customer_id'];
        } else {
            $data['operate_user_id'] = OrderService::getInstance( $orderId )->fBusierId();
        }
        //保存
        $res = self::save( $data );
        return $res;
    }
    
    /**
     * 根据订单模板id添加流程
     * @param type $orderId     订单id
     * @param type $tplId       模板id
     */
    protected static function addFlowByTplId( $orderId ,$tplId )
    {
        $nextNode       = OrderFlowNodeTplService::getInstance( $tplId )->get();
        $nextNodeKey    = $nextNode['next_node_key'];
        $nextNodeName   = $nextNode['next_node_name'];
        $operateRole    = $nextNode['operate_role'];
        Debug::debug('addFlowByTplId 的 $nextNode信息',$nextNode);
        //
        $data = [];
        if( isset($nextNode['plan_finish_minutes']) ){
            $data['plan_finish_time']   = date('Y-m-d H:i:s',time() + $nextNode['plan_finish_minutes'] * 60);
        }
        if( isset($nextNode['next_node_desc']) ){
            $data['node_describe']      = $nextNode['next_node_desc'];
        }
        if( isset($nextNode['is_jump']) ){
            $data['is_jump']      = $nextNode['is_jump'];
        }
        if( isset($nextNode['prize_key']) ){
            $data['prize_key']    = $nextNode['prize_key'];
        }        

        return self::addFlow( $orderId , $nextNodeKey , $nextNodeName, $operateRole,$data );
    }
    
    /**
     * 验证末个节点是否完成，若完成则进入下一节点
     * @param type $orderId
     * @param type $itemType        
     * @param type $nextNodeKey      下一节点key，适用于多个后续节点选一
     * @return boolean
     */
    public static function checkLastNodeFinishAndNext( $orderId,$itemType="order",  $nextNodeKey='')
    {
        //归置末节点次数：20210326
        self::$lastNodeFinishCount = 0;
        if(!$nextNodeKey){
            //从请求参数中获取一下。nextNodeKey;
            $nextNodeKey = Request::param('nextNodeKey','');
            self::$nextNodeKey = $nextNodeKey;
            Debug::debug( '下一个节点key，来自于请求',$nextNodeKey );
        }
        //校验事务
        self::checkTransaction();
        //校验是否祖宗节点，如果祖宗节点直接添加
        self::grandNodeDirectAdd($orderId, $nextNodeKey);
        //校验末节点
        $res = self::lastNodeFinishAndNext($orderId, $itemType, $nextNodeKey);
        return $res;
    }
    
    /**
     * 验证末个节点是否完成，若完成则进入下一节点
     * @param type $orderId
     * @param type $itemType        
     * @param type $nextNodeKey      下一节点key，适用于多个后续节点选一
     * @return boolean
     */
    public static function lastNodeFinishAndNext( $orderId,$itemType="order",  $nextNodeKey='',$limitTimes = 30)
    {
        //TODO优化逻辑20210312
        if(!$nextNodeKey && !self::$nextNodeKey){
            //从请求参数中获取一下。nextNodeKey;
            $nextNodeKey = Request::param('nextNodeKey','');
            self::$nextNodeKey = $nextNodeKey;            
        }
        
        self::$lastNodeFinishCount = self::$lastNodeFinishCount +1;
        if(self::$lastNodeFinishCount > $limitTimes){
            throw new Exception('lastNodeFinishAndNext 次数超限');
        }
        //nextNodeKey没有父节点，表示他是可以打断订单流程的节点（比如取消订单，不依赖于其他流程节点）。        
        $lastNode = self::orderLastFlow($orderId);    
        //更新订单的末个节点信息
        if(self::mainModel()->hasField('orderLastFlowNode')){
            //更新订单的末个节点信息
            self::updateOrderLastNode( $orderId );
//            OrderService::mainModel()->where('id', $orderId )->update(['orderLastFlowNode'=>$lastNode['node_key']]);        
        }
            Debug::debug('订单：'.$orderId.'末条流程', $lastNode);
        if(!$lastNode || $lastNode['flow_status'] != XJRYANSE_OP_TODO){
            return false;
        }
        $param = OrderService::mainModel()->where('id',$orderId)->field('*,id as orderId')->find();
        if(!$param){
            throw new Exception('订单信息不存在 '.$orderId);
        }
        //特殊处理：20210304
        $param['lastNodeId'] = $lastNode['id'];     //TODO,需优化
            Debug::debug('订单信息', $param);

        //判断节点是否完成
        $isReached = SystemConditionService::isReachByItemKey( $itemType, $lastNode['node_key'], $param->toArray() );
        if(!$isReached){
            return false;
        }
        //如果已完成，设为完成
        self::getInstance($lastNode['id'])->setFinish();
//        dump($lastNode);
        //20210311:如果节点可跳过，且已经达成，且$lastNodeFinishCount >1(表示非首次执行的订单节点)，则删除原有节点（没有用的节点）
        if($lastNode['is_jump'] && time() - strtotime($lastNode['create_time']) <= 2 ){
            //在连续多订单判断过程中，删除可跳过的订单节点（软删除）
            self::mainModel()->where( 'id',$lastNode['id'] )->update( ['is_delete'=>1] );
        }
        //添加下一节点
            Debug::debug('添加节点执行结果1', $lastNode['node_key'] );
            Debug::debug('添加节点执行结果2', $nextNodeKey );
        //节点向前
        if(Arrays::value($lastNode, "direction") == 1){
            $res = self::addNextNode($orderId, $lastNode['node_key'], $nextNodeKey );
                Debug::debug('添加节点执行结果3', $res );
        } else {
            //节点向后：离开递归
            $res = self::backPreNode($orderId, $lastNode['id'] );
            return $res;
        }
//递归一下下一节点是否完成
        Debug::debug('---循环节点key', $itemType );        
        self::lastNodeFinishAndNext($orderId,$itemType);
        return $res;
    }
    /**
     * 更新订单末个节点流程
     * @param type $orderId
     */
    public static function updateOrderLastNode( $orderId )
    {
        $con[] = ['order_id','=',$orderId];
        $con[] = ['is_delete','=',0];
        $lastInfo           = self::mainModel()->where( $con )->order('id desc')->find();
        $lastFlowNodeRole   = $lastInfo && $lastInfo['flow_status'] == 'todo' ? $lastInfo['operate_role'] : "" ;
        $data['lastFlowNodeRole']   = $lastFlowNodeRole;
        $data['orderLastFlowNode']  = $lastInfo['node_key'];
        
        $res = OrderService::mainModel()->where('id', $orderId )->update( $data );  
        
        return $res;
    }
    /**
     * 祖宗节点直接添加
     * @param type $orderId     订单id
     * @param type $itemType    订单类型
     * @param type $nextNodeKey 
     */
    protected static function grandNodeDirectAdd( $orderId, $nextNodeKey)
    {
        if(!$nextNodeKey){
            return false;
        }
        //前一个流程节点
        $preNode = OrderFlowNodeTplService::getPreNode($nextNodeKey);
        Debug::debug( '祖宗节点key',$nextNodeKey );
        Debug::debug( '祖宗节点信息',$preNode );
        if( $preNode && $preNode['node_key'] ){
            return false;   //非祖宗节点返回
        }
        //添加一条流程
        //1分钟内有新增了该条流程，则跳过
        $con[] = ['order_id','=',$orderId ] ;
        $con[] = ['node_key','=',$nextNodeKey ] ;
        $con[] = ['create_time','>=',date('Y-m-d H:i:s',strtotime('-1 minute')) ] ;
        //在嵌套流程中有添加过了节点
        if( self::find($con,0) ){
            return false;
        }        
        //关闭上一个节点
        $lastNode = self::orderLastFlow($orderId);  
        if($lastNode){
            self::getInstance( $lastNode['id'])->update(['flow_status'=> XJRYANSE_OP_CLOSE ]);
        }
        //根据流程模板id，直接添加流程
        $res = self::addFlowByTplId($orderId, $preNode['id']);
        Debug::debug( '添加流程：grandNodeDirectAdd',$res );
        return $res;
    }
    /**
     * 添加下一节点
     * @param type $orderId     订单id
     * @param type $thisNodeKey 当前节点
     * @param type $nextNodeKey 下一节点    可选
     */
    protected static function addNextNode( $orderId, $thisNodeKey ,$nextNodeKey = '')
    {
        //下一节点key（多个下级时需指定）
        //获取下一个节点key
        if(! $nextNodeKey ){
            Debug::debug( '当前节点',$thisNodeKey );
            if( OrderFlowNodeTplService::nextNodeCount( $thisNodeKey ) >1 ){
                //20210311测试
//                throw new Exception('存在多个下级流程!且未指定走向');
            }
            $nextNode    = OrderFlowNodeTplService::nextNodeFind( $thisNodeKey );
            Debug::debug( 'addNextNode下一节点信息',$nextNode );
        } else {
            $con    = [];
            $con[] = ['node_key','=',$thisNodeKey];
            $con[] = ['next_node_key','=',$nextNodeKey];
            $nextNode = OrderFlowNodeTplService::find( $con );
            //有nextNode，没找到，尝试用无nextNode找记录
            if(!$nextNode){
                $con    = [];
                $con[]  = ['node_key','=',$thisNodeKey];
                $nextNode = OrderFlowNodeTplService::find( $con );
            }
        }
        
        Debug::debug( '下一个节点条件',$con );
        Debug::debug( '下一个节点信息',$nextNode );

        if(!$nextNode){
            //没有下一级流程
            return false;
        }

        //根据流程模板id，添加流程
        $res = self::addFlowByTplId($orderId, $nextNode['id']);
        Debug::debug( '添加流程：addNextNode',$res );        
        return $res;
    }
    /**
     * 驳回上一个节点
     * @param type $orderId
     * @param type $thisNodeId
     */
    protected static function backPreNode( $orderId, $thisNodeId )
    {
        //若前一个流程节点是删除节点，则再次回滚
        $thisNode           = self::getInstance( $thisNodeId )->get();
        $orderPreFlow       = self::orderPreFlow($orderId, $thisNode['node_key']);
        if($orderPreFlow){
            $orderPreFlow = $orderPreFlow->toArray();
        }
        $orderPreFlow['id'] = self::mainModel()->newId();
        $orderPreFlow['flow_status'] = "todo";
        $orderPreFlow['create_time'] = date('Y-m-d H:i:s');
        $orderPreFlow['update_time'] = date('Y-m-d H:i:s');
        $res = self::save($orderPreFlow);
        return $res;
    }

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
        $data['flow_status'] = XJRYANSE_OP_FINISH;
        $data['finish_time'] = date('Y-m-d H:i:s');
        return $this->update( $data);
//        return self::mainModel()->where("id",$this->uuid)->update($data);
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
        $con[] = ['is_delete', '=', 0];
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
