<?php

namespace xjryanse\order\service;

use xjryanse\order\service\OrderFlowNodeTplService;
use xjryanse\order\service\OrderService;
use xjryanse\goods\service\GoodsService;
use xjryanse\system\service\SystemConditionService;
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

    protected static $lastNodeFinishCount;  //末个节点执行次数
    protected static $mainModel;
    protected static $mainModelClass = '\\xjryanse\\order\\model\\OrderFlowNode';

    /**
     * 额外输入信息
     */
    public static function extraPreSave(&$data, $uuid) {
        $orderId = Arrays::value($data, 'order_id');
        $data['order_type'] = OrderService::getInstance( $orderId )->fOrderType();

        return $data;
    }
    /**
     * 额外输入信息
     */
    public static function extraAfterSave(&$data, $uuid) {
        $info = self::getInstance( $uuid )->get();
        $preNode = OrderFlowNodeTplService::getPreNode( Arrays::value($data, 'node_key'));            
        if( !$preNode || (Arrays::value($data, 'flow_status') && $data['flow_status'] == XJRYANSE_OP_FINISH)){
            self::lastNodeFinishAndNext($info['order_id']);
            //TODO:拆分公共
            if( $info['node_key'] == 'orderClose'){
                $orderInfo = OrderService::getInstance($info['order_id'])->get();
                GoodsService::setOnSaleByGoodsTableId($orderInfo['goods_table_id'], $orderInfo['goods_table']);
                OrderService::getInstance($info['order_id'])->update(['order_status'=>ORDER_CLOSE]);    //交易关闭
            }
//        } else {
//            try{
//                self::lastNodeFinishAndNext($info['order_id'],'order','',1);
//            } catch(\Exception $e){}
        }
        //订单完成：修改订单状态
        if( $info['node_key'] == 'orderFinish'){
            OrderService::getInstance($info['order_id'])->update(['order_status'=>ORDER_FINISH]);   //交易完成
        }
        
        return $data;
    }
    
    /**
     * 额外输入信息
     */
    public static function extraAfterUpdate(&$data, $uuid) {
        return self::extraAfterSave($data, $uuid);
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
            $data['operate_user_id'] = session(SESSION_USER_ID);
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
        return self::addFlow( $orderId , $nextNodeKey , $nextNodeName, $operateRole );
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
        if(!$nextNodeKey){
            //从请求参数中获取一下。nextNodeKey;
            $nextNodeKey = Request::param('nextNodeKey','');
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
    public static function lastNodeFinishAndNext( $orderId,$itemType="order",  $nextNodeKey='',$limitTimes = 20)
    {
        self::$lastNodeFinishCount = self::$lastNodeFinishCount +1;
        if(self::$lastNodeFinishCount > $limitTimes){
            throw new Exception('lastNodeFinishAndNext 次数超限');
        }
        //nextNodeKey没有父节点，表示他是可以打断订单流程的节点（比如取消订单，不依赖于其他流程节点）。        
        $lastNode = self::orderLastFlow($orderId);    
        //更新订单的末个节点信息
        OrderService::mainModel()->where('id', $orderId )->update(['orderLastFlowNode'=>$lastNode['node_key']]);
            Debug::debug('订单：'.$orderId.'末条流程', $lastNode);
        if(!$lastNode || $lastNode['flow_status'] != XJRYANSE_OP_TODO){
            return false;
        }
        $param = OrderService::mainModel()->where('id',$orderId)->field('*,id as orderId')->find();
        if(!$param){
            throw new Exception('订单信息不存在 '.$orderId);
        }
            Debug::debug('订单信息', $param);

        //判断节点是否完成
        $isReached = SystemConditionService::isReachByItemKey( $itemType, $lastNode['node_key'], $param->toArray() );
        if(!$isReached){
            return false;
        }
        //如果已完成，设为完成
        self::getInstance($lastNode['id'])->setFinish();
        //添加下一节点
            Debug::debug('添加节点执行结果1', $lastNode['node_key'] );
            Debug::debug('添加节点执行结果2', $nextNodeKey );
        $res = self::addNextNode($orderId, $lastNode['node_key'], $nextNodeKey );
            Debug::debug('添加节点执行结果3', $res );
//递归一下下一节点是否完成
        self::lastNodeFinishAndNext($orderId,$itemType);
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
        return self::addFlowByTplId($orderId, $preNode['id']);
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
                throw new Exception('存在多个下级流程!且未指定走向');
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
        return self::addFlowByTplId($orderId, $nextNode['id']);
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
        $this->setField('flow_status', XJRYANSE_OP_FINISH);
        $this->setField('finish_time', date('Y-m-d H:i:s'));
        return true;
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
