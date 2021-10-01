<?php

namespace xjryanse\order\service;

use xjryanse\goods\service\GoodsPrizeKeyService;
use xjryanse\order\service\OrderFlowNodeTplService;
use xjryanse\order\service\OrderService;
use xjryanse\system\service\SystemConditionService;
use xjryanse\finance\service\FinanceStatementService;
use xjryanse\finance\service\FinanceStatementOrderService;
use xjryanse\logic\Debug;
use xjryanse\logic\Arrays;
use think\Db;
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
    //直接执行后续触发动作
    protected static $directAfter = true;
    /**
     * 获取订单时间
     * 付款时间：BuyerPay
     * 发货时间：SellerDeliverGoods
     * 收货时间：BuyerReceive
     * 成交时间：orderFinish
     */
    public static function getOrderTime($orderId, $timeKey){
        $con[] = ['order_id','=',$orderId];
        $con[] = ['node_key','like','%'.$timeKey];
        return self::mainModel()->where($con)->order('id desc')->value('finish_time');
    }
    /**
     * 获取订单时间数组
     * @param type $orderId
     * @param type $timeKeys
     * @return type
     */
    public static function getOrderTimeArr($orderId, $timeKeys){
        if(!is_array($timeKeys)){
            $timeKeys = [$timeKeys];
        }
        $lists    = self::orderNodeList($orderId);
        $listsRev = array_reverse($lists);
        
        $dataArr = array_fill_keys($timeKeys, null);
        foreach($listsRev as $info){
            foreach($timeKeys as $timeKey){
                // 字符串包含，说明匹配
                if(strstr($info['node_key'], $timeKey) && Arrays::value($info, 'finish_time')){
                    $dataArr[$timeKey] = $info['finish_time'];
                }
            }
        }
        return $dataArr;
    }

    
    /**
     * 额外详情信息
     */
    protected static function extraDetail( &$item ,$uuid )
    {
        //添加分表数据:按类型提取分表服务类
        if(!$item){
            return false;
        }
        $orderInfo = OrderService::getInstance($item['order_id'])->get();
        //订单状态是否取消
        $item['orderIsCancel'] = Arrays::value($orderInfo, 'is_cancel');//OrderService::getInstance($item['order_id'])->fIsCancel();
        //订单状态由谁取消
        $item['orderCancelBy'] = Arrays::value($orderInfo, 'cancel_by'); // OrderService::getInstance($item['order_id'])->fCancelBy();
        //订单类型
        $item['orderType'] = Arrays::value($orderInfo, 'order_type');  //OrderService::getInstance($item['order_id'])->fOrderType();
        return $item;
    }
    /*
     * 订单是否有已完成节点
     */
    public static function orderHasFinishNode( $orderId, $nodeKeys = [])
    {
        $con[] = ['order_id','=', $orderId];
        $con[] = ['node_key','in', $nodeKeys];
        $con[] = ['flow_status','=', 'finish'];
        return self::count($con);
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
        //设定订单预付金额
        OrderFlowNodePrizeTplService::setOrderPrePrize( $orderId, Arrays::value($data, 'node_key') );
        Debug::debug('正在保存订单节点信息',$data);        
        return $data;
    }
    
    public static function extraPreUpdate(&$data, $uuid) {
        self::checkTransaction();
        //订单更新节点（内存中追加）
        $info   = self::getInstance( $uuid )->get(0);
        $orderId = Arrays::value($info, 'order_id');
        //为了只更新传入的$data，故放在preUpdate;
        //OrderService::getInstance($orderId)->updateFlowNode($uuid, $data);
        OrderService::getInstance($orderId)->objAttrsUpdate('orderFlowNode',$uuid, $data);
        Debug::debug('OrderFlowNodeService的extraPreUpdate',$data);
        return $data;
    }
    /**
     * 更新，新增共用
     */
    protected static function afterOperate(&$data, $uuid)
    {
        $info               = self::getInstance( $uuid )->get();
        //步骤2：
        $saleTypeInst = self::orderSaleTypeInst(Arrays::value($info, 'order_id'));
        $preNode = $saleTypeInst->getPreNode(Arrays::value($data, 'node_key'));
        //$preNode            = OrderFlowNodeTplService::getPreNode( Arrays::value($data, 'node_key'));      
        Debug::debug('afterOperate的$preNode',$preNode);
        Debug::debug('afterOperate的$data',$data);
        // 没有前序节点，或者当前节点已完成，则递归下一个节点
        if( !$preNode || !$preNode['node_key'] || (Arrays::value($data, 'flow_status') && $data['flow_status'] == XJRYANSE_OP_FINISH)){
            Debug::debug('正在进行递归校验','');
            self::lastNodeFinishAndNext($info['order_id']);
        }

        return $data;
    }
    
    public static function save( $data ){
        $res = self::commSave($data);
        //没有待收待付记录，则添加一下：20210319（有涉及条件判断，必须放在self::afterOperate之前）
        OrderFlowNodeService::addFinanceStatementOrder( $res['id'] );
        return $res;
    }
    /**
     * 额外输入信息
     */
    public static function extraAfterSave(&$data, $uuid) {
        //订单追加节点（内存中追加）
        $orderId = Arrays::value($data, 'order_id');
        OrderService::getInstance($orderId)->objAttrsPush('orderFlowNode',$data);
        //修改订单状态，更新节点等操作
        $data = self::afterOperate($data, $uuid);
        
        return $data;
    }
    
    /**
     * 额外输入信息
     */
    public static function extraAfterUpdate(&$data, $uuid) {
        self::afterOperate($data, $uuid);
//        $info   = self::getInstance( $uuid )->get(0);
//        $orderId = Arrays::value($info, 'order_id');
//        $prizeKey = Arrays::value($info, 'prize_key');
//        Debug::debug('OrderFlowNodeService::extraAfterUpdate的$info',$info);
//        //步骤：删除关联的未结账单
//        if( Arrays::value($info, 'flow_status') == XJRYANSE_OP_CLOSE && $prizeKey){
//            //被关闭后，删除未结算的账单明细
//            $con[] = ['order_id','=',$orderId];
//            $con[] = ['statement_type','=',$prizeKey];
//            $con[] = ['has_settle','=',0];
//            $lists = FinanceStatementOrderService::mainModel()->where( $con )->select();
//            Debug::debug('OrderFlowNodeService::extraAfterUpdate的FinanceStatementOrderService的$lists',$lists);
//            foreach( $lists as $value){
//                //一个个删
//                if(!Arrays::value($value, 'statement_id')){
//                    //Debug::debug('执行了账单删除方法',$value);
//                    FinanceStatementOrderService::mainModel()->where( 'id',$value['id'] )->delete();
//                }
//            }
//        }
        return $data;
    }

    /**
     * 添加关联收付款明细
     */
    public static function addFinanceStatementOrder( $orderFlowNodeId )
    {
        $info           = self::getInstance( $orderFlowNodeId )->get(0);
        Debug::debug('addFinanceStatementOrder的$info',$info);
        $orderId        = Arrays::value($info, 'order_id');
        $orderInfo      = OrderService::getInstance( $orderId )->get(0);
        //无买家id，不生成账单（可能存在后台预下单，不可抛异常）
        if(!$orderInfo['user_id']){
            return false;
        }
        $prizeKeys      = Arrays::value($info, 'prize_key');
        Debug::debug('addFinanceStatementOrder的$orderId',$orderId);
        Debug::debug('addFinanceStatementOrder的$prizeKey',$prizeKeys);

        $prizeKeyArr = explode(',',$prizeKeys); //兼容多个价格的情况
        $res = [];
        foreach( $prizeKeyArr as $prizeKey){
            if(!$prizeKey){
                continue;
            }
            $prizeKeyRole = GoodsPrizeKeyService::keyBelongRole( $prizeKey );
            if($prizeKeyRole == 'rec_user' && !Arrays::value( $orderInfo , 'rec_user_id')){
                continue;
            }
            if($prizeKeyRole == 'busier' && !Arrays::value( $orderInfo , 'busier_id')){
                continue;
            }
            $goodsPrizeInfo         = GoodsPrizeKeyService::getByPrizeKey( $prizeKey );  //价格key取归属
            //订单id和价格key，取应付金额
            $needPayPrize = GoodsPrizeKeyService::orderPrizeKeyGetPrize($orderId, $prizeKey);
            //写入账单
            $res = FinanceStatementOrderService::prizeKeySave($prizeKey, $orderId, $needPayPrize);
            //如果是充值到余额的，直接处理;,'sec_share'分账会存在订单未处理完，再考虑其他异步解决方案
            if( in_array(Arrays::value($goodsPrizeInfo,'to_money'),['money']) ){
                $financeStatement = FinanceStatementService::statementGenerate( $res['id'] );
                FinanceStatementService::getInstance($financeStatement['id'])->doDirect();
            }
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
        $data['id']             = self::mainModel()->newId();
        //订单id
        $data['order_id']       = $orderId;
        //节点key
        $data['node_key']       = $nodeKey;
        //节点名称
        $data['node_name']      = $nodeName;
        //操作角色
        $data['operate_role']   = $operateRole;
        //流程状态:默认为待完成。
        if(!Arrays::value($data, 'flow_status')){
            $data['flow_status']    = XJRYANSE_OP_TODO;
        }
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
        Debug::debug('OrderFlowNodeService::addFlow', $data);
        //保存
        $res = self::save( $data );
        return $res;
    }
    
    /**
     * 根据订单模板id添加流程
     * @param type $orderId     订单id
     * @param type $tplId       模板id
     */
    protected static function addFlowByTplId( $orderId ,$tplId ,$data = [])
    {
        $nextNode       = OrderFlowNodeTplService::getInstance( $tplId )->get();
        $nextNodeKey    = $nextNode['next_node_key'];
        $nextNodeName   = $nextNode['next_node_name'];
        $operateRole    = $nextNode['operate_role'];
        Debug::debug('addFlowByTplId 的 $nextNode信息',$nextNode);
        //
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
    /*
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
     */
    /**
     * 20210920获取下一个待校验节点：
     * 包含不在已有节点中的开始节点；和当前未完成的末节点
     */
    public static function nextCheckNodes($orderId){
        $saleTypeInst = self::orderSaleTypeInst($orderId);
        $nodeLists = $saleTypeInst->grandNodeList();
        //$nodeLists = OrderFlowNodeTplService::grandNodeList($orderType,$companyId);        
        Debug::debug('祖宗节点key',$nodeLists);
        //订单类型获取订单的祖宗节点
        $grandNodeKeys = array_column($nodeLists,'next_node_key');
        //获取订单的全部节点
        $orderNodes = self::orderNodeList($orderId);
        //订单的全部节点key
        $orderNodeKeys = array_column($orderNodes, 'node_key');
        Debug::debug('$orderNodeKeys',$orderNodeKeys);
        //不在订单当前key中的祖宗节点
        $needCheckNodes = array_diff($grandNodeKeys, $orderNodeKeys);
        Debug::debug('$needCheckGrandNodes',$needCheckNodes);
        //获取订单的末个节点
        $lastNode = array_pop($orderNodes);
        if($lastNode && $lastNode['flow_status'] == XJRYANSE_OP_TODO){
            $needCheckNodes[] = $lastNode['node_key'];
        }
        //需要校验条件是否达成的节点列表
        return $needCheckNodes;
    }

    /**
     * 订单全部节点
     * @param type $orderId
     * @return type
     */
    public static function orderNodeList($orderId){
        return OrderService::getInstance($orderId)->objAttrsList('orderFlowNode');
    }
    
    // 20210920 进一步梳理业务逻辑
    // 获取订单的需判断节点：如果条件达成：
    //  ---是末个节点，末个节点设为完成；获取下一个节点；
    //  ---非末个节点，末个节点设为关闭；将当前完成节点添加写入，获取下一个节点，
    //  ---如果下一个节点唯一，则写入待处理
    public static function lastNodeFinishAndNext( $orderId ){
        // ①获取需校验判断的节点
        $nextCheckNodes = self::nextCheckNodes($orderId);
        Debug::debug('$nextCheckNodes', $nextCheckNodes);
        if(!$nextCheckNodes){
            return false;
        }
        // ②防止死循环
        self::$lastNodeFinishCount = self::$lastNodeFinishCount +1;
        $limitTimes = 20;
        if(self::$lastNodeFinishCount > $limitTimes){
            throw new Exception('lastNodeFinishAndNext 次数超限'.$limitTimes);
        }
        // ③获取订单信息，用于进行条件达成判断
        // $param = OrderService::mainModel()->master()->where('id',$orderId)->field('*,id as orderId')->find();
        $param = OrderService::getInstance( $orderId )->get();
        if(!$param){
            throw new Exception('订单信息不存在 '.$orderId);
        }
        $param['orderId'] = $param['id'];
        $lastNode = self::orderLastFlow($orderId);   
        //特殊处理：20210304
        $param['lastNodeId'] = $lastNode['id'];     //TODO,需优化
        Debug::debug('订单信息', $param);
        // 循环校验节点，只要有一个达成，就return
        foreach($nextCheckNodes as &$nodeKey){
            // 校验是否达成
            $isReached = SystemConditionService::isReachByItemKey( 'order', $nodeKey, $param->toArray() );
            Debug::debug('self::$lastNodeFinishCount_$nodeKey',$nodeKey);
            Debug::debug('self::$lastNodeFinishCount_$isReached',$isReached);
            if($isReached){
                self::orderNodeFinish($orderId, $nodeKey);
                //20210921此处递归；
                self::lastNodeFinishAndNext($orderId);
                // 只要有一个达成，就return
                return false;
            }
        }
        Debug::debug('self::$lastNodeFinishCount',self::$lastNodeFinishCount);
        //条件未达成，且查询多于一次，更新订单的状态
        if(self::$lastNodeFinishCount > 1){
            OrderService::getInstance($orderId)->orderDataSync();
        }
    }
    
    /**
     * 更新订单末个节点流程
     * @param type $orderId
     */
    /*
    public static function updateOrderLastNode( $orderId )
    {
        $lastInfo = self::orderLastFlow($orderId);        
        $lastFlowNodeRole   = $lastInfo && $lastInfo['flow_status'] == 'todo' ? $lastInfo['operate_role'] : "" ;
        $data['lastFlowNodeRole']   = $lastFlowNodeRole;
        $data['orderLastFlowNode']  = $lastInfo['node_key'];
        
        $res = OrderService::mainModel()->where('id', $orderId )->update( $data );  
        
        return $res;
    }
     */
    /**
     * 订单末节点数据，用于更新
     */
    public static function orderLastNodeData($orderId){
        $lastInfo = self::orderLastFlow($orderId);        
        $lastFlowNodeRole   = $lastInfo && $lastInfo['flow_status'] == 'todo' ? $lastInfo['operate_role'] : "" ;
        $data['lastFlowNodeRole']   = $lastFlowNodeRole;
        $data['orderLastFlowNode']  = $lastInfo['node_key'];
        return $data;
    }
    
    /**
     * 祖宗节点直接添加
     * @param type $orderId     订单id
     * @param type $itemType    订单类型
     * @param type $nextNodeKey 
     */
    /*
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
     */
    /**
     * 订单id，取销售类型的实例
     * @param type $orderId
     */
    protected static function orderSaleTypeInst($orderId){
        return OrderService::getInstance($orderId)->orderSaleTypeInst();
    }
    
    /**
     * 添加下一节点
     * @param type $orderId     订单id
     * @param type $thisNodeKey 当前节点
     * @param type $nextNodeKey 下一节点    可选
     */
    protected static function addNextNode( $orderId, $thisNodeKey)
    {
        //下一节点key（多个下级时需指定）
        $saleTypeInst = self::orderSaleTypeInst($orderId);
        Debug::debug( '当前节点',$thisNodeKey );
        if( $saleTypeInst->nextNodeCount( $thisNodeKey ) >1 ){
            //20210311测试
//                throw new Exception('存在多个下级流程!且未指定走向');
        }
        $nextNode    = $saleTypeInst->getNextNode($thisNodeKey); //OrderFlowNodeTplService::nextNodeFind( $thisNodeKey );
        Debug::debug( 'addNextNode下一节点信息',$nextNode );
        if(!$nextNode){
            //没有下一级流程
            return false;
        }

        //根据流程模板id，添加流程
        $res = self::addFlowByTplId($orderId, $nextNode['id']);
        Debug::debug( '添加流程：addNextNode',$res );        
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
     * TODO调为protected方法
     */
    public function setFinish() {
        //完成的数据
        $data = $this->getFinishData();
        //更新动作
        $res = $this->update( $data);
        return $res;
        //return self::mainModel()->where("id",$this->uuid)->update($data);
    }
    
    protected function getFinishData(){
        $data['flow_status'] = XJRYANSE_OP_FINISH;
        $data['finish_time'] = date('Y-m-d H:i:s');
        $nodeInfo = $this->get();
        if($nodeInfo['is_jump'] && time() - strtotime($nodeInfo['create_time']) <= 2 ){
            //在连续多订单判断过程中，删除可跳过的订单节点（软删除）
            $data['is_delete'] = 1;
        }
        return $data;
    }
    /**
     * 20210920节点设为关闭
     */
    protected function setClose(){
        $data['flow_status'] = XJRYANSE_OP_CLOSE;
        $this->setUuData($data);
        $res = self::mainModel()->where("id",$this->uuid)->update($data);
        //处理关联的账单信息
        $info   = $this->get();
        $orderId = Arrays::value($info, 'order_id');
        $prizeKey = Arrays::value($info, 'prize_key');
        if($prizeKey){
            $con[] = ['order_id','=',$orderId];
            $con[] = ['statement_type','=',$prizeKey];
            $con[] = ['has_settle','=',0];
            $lists = FinanceStatementOrderService::mainModel()->where( $con )->select();
            Debug::debug('OrderFlowNodeService::extraAfterUpdate的FinanceStatementOrderService的$lists',$lists);
            foreach( $lists as $value){
                //一个个删
                if(!Arrays::value($value, 'statement_id')){
                    //Debug::debug('执行了账单删除方法',$value);
                    FinanceStatementOrderService::mainModel()->where( 'id',$value['id'] )->delete();
                }
            }
        }

        return $res;
        //有关联的删除触发动作
        //20210923会触发死循环，慎开
        //return $this->update( $data);
    }
    /**
     * 将订单设为已完成
     * @param type $orderId
     * @param type $nodeKey
     */
    protected static function orderNodeFinish( $orderId ,$nodeKey ){
        //  ---是末个节点，末个节点设为完成；获取下一个节点；
        //  ---非末个节点，末个节点设为关闭；将当前完成节点添加写入，获取下一个节点，
        //  ---如果下一个节点唯一，则写入待处理
        $saleTypeInst = self::orderSaleTypeInst($orderId);        
        
        $lastNode = self::orderLastFlow($orderId);
        if($nodeKey == $lastNode['node_key']){
            // 是末个节点，节点设为完成
            self::getInstance($lastNode['id'])->setFinish();
        } else {
            $saleType = OrderService::getInstance($orderId)->fOrderType();
            // 将当前完成节点添加写入
            $tpl = $saleTypeInst->getPreNode($nodeKey);
            //$tpl = OrderFlowNodeTplService::getBySaleTypeAndNextNodeKey($saleType, $nodeKey, $companyId);
            if(!$tpl){
                throw new Exception('未找到流程模板'.$saleType.'-'.$nodeKey);
            }
            if($lastNode){
                // 非末个节点，节点设为关闭
                self::getInstance($lastNode['id'])->setClose();
            }
            //订单完成，写这两个数据:20210923：可能有bug
            $data['flow_status'] = XJRYANSE_OP_FINISH;
            $data['finish_time'] = date('Y-m-d H:i:s');
            //将当前完成节点添加写入             //将当前节点设为完成状态
            self::addFlowByTplId($orderId, $tpl['id'], $data);
        }
        // 添加下一个节点
        self::addNextNode($orderId, $nodeKey );
    }
    
    /**
     * 获取订单的末个流程节点
     */
    public static function orderLastFlow($orderId) {
        $nodes = OrderService::getInstance($orderId)->objAttrsList('orderFlowNode');
        return array_pop($nodes);
    }
    /**
     * 获取订单是否完成
     */
    public static function orderComplete($orderId){
        $con[] = ['order_id','=',$orderId];
        $sqlA = self::mainModel()->where($con)->order('id desc')->limit(1)->buildSql();
        $sqlB = OrderFlowNodeTplService::mainModel()->getTable();
        $sql = 'select a.id,a.node_key,a.flow_status,b.next_node_key from '.$sqlA . ' as a left join '.$sqlB.' as b on a.node_key = b.node_key';
        Debug::debug('$sql',$sql);
        $info = Db::query($sql);
        //末个节点完成，且无后续节点，说明订单完成
        return $info[0] && $info[0]['flow_status'] == 'finish' && !$info[0]['next_node_key'];
    }
    /*
     * 获取指定流程的前一个流程节点
     */
    public static function orderPreFlow($orderId, $operate) {
        $lists = OrderService::getInstance($orderId)->objAttrsList('orderFlowNode');
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
    /*
    public static function getAuditRollBackFlow($orderId, $operate) {
        //审核事项前一个节点
        $preFlow = self::orderPreFlow($orderId, $operate);
        //前一个节点之前没有模板节点，说明前一个节点是由用户操作触发的（例如取消订单）。
        //应该回滚到触发前的节点
        if (!OrderFlowNodeTplService::getPreNode($preFlow['node_key'])) {
            $preFlow = self::orderPreFlow($orderId, $preFlow['node_key']);
        }
        return $preFlow;
    }*/
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
