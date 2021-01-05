<?php
namespace xjryanse\order\logic;

use xjryanse\order\service\OrderService;
use xjryanse\order\service\OrderFlowNodeService;
use xjryanse\order\service\OrderFlowNodeTplService;
use xjryanse\system\service\SystemConditionService;
use app\order\logic\OnProcessLogic;
use app\order\logic\OnCancelLogic;
use Exception;
/**
 * 订单流程节点
 */
class FlowNodeLogic
{
    //$this->uuid:订单id
    use \xjryanse\traits\InstTrait;
    use \xjryanse\traits\DebugTrait;
    /**
     * 给订单添加流程
     */
    public static function addFlow( $orderId ,$nodeKey,$nodeName,$operateRole, $flowStatus='todo',array $data=[])
    {
        //订单id
        $data['order_id']       = $orderId;
        //节点key
        $data['node_key']       = $nodeKey;
        //节点名称
        $data['node_name']      = $nodeName;
        //操作角色
        $data['operate_role']   = $operateRole;
        //流程状态
        $data['flow_status']    = $flowStatus;
        
        if($flowStatus == XJRYANSE_OP_FINISH ){
            $data['finish_time'] = date('Y-m-d H:i:s');
        }
        
        //订单信息
        $orderInfo  = OrderService::getInstance( $orderId )->get();        
        //卖家
        if( $operateRole == YDZB_ROLE_CATE_SELLER ){
            $data['operate_user_id'] = $orderInfo['seller_user_id'];
        }
        //卖家
        if( $operateRole == YDZB_ROLE_CATE_BUYER ){
            $data['operate_user_id'] = $orderInfo['user_id'];
        }

        //保存
        $res = OrderFlowNodeService::save( $data );
        return $res;
    }
    /**
     * 校验流程节点是否完成
     * @param type $orderId     订单id
     * @param type $flowNodeId  流程节点id
     * @param type $nodeKey     节点key
     * @param type $param       不同订单类型所需额外参数，外部处理后传入
     * @return boolean
     */
    public static function checkNodeFinish( $orderId, $flowNodeId, $nodeKey, $param = [] )
    {
        $info = OrderService::getInstance( $orderId )->get(0);
        self::debug(__METHOD__.'-订单信息$info',$info);

        $param[ 'goodsId' ]     = $info['goods_id'];     //订单id
        $param[ 'orderId' ]     = $orderId;     //订单id
        $param[ 'flowNodeId' ]  = $flowNodeId;  //节点id

        //判断节点是否完成
        $isReached = SystemConditionService::isReachByItemKey( 'order', $nodeKey, $param );
        self::debug(__METHOD__.'-$isReached',$isReached);
        self::debug(__METHOD__.'-$isReached$nodeKey',$nodeKey);
        self::debug(__METHOD__.'-$isReached$param',$param);
        
        //节点完成进入下一个节点
        if( $isReached ){
            return self::setFinish( $flowNodeId );
        } else {
            return false;
        }
    }
    /**
     * 校验订单末个流程节点是否完成
     */
    public static function lastNodeFinish( $orderId )
    {
        $lastNode = OrderFlowNodeService::orderLastFlow( $orderId );
        self::debug(__METHOD__.'-$lastNode',$lastNode);
        return self::checkNodeFinish($orderId, $lastNode['id'], $lastNode['node_key']);
    }
    
    /*
     * 节点完成进入下一个节点
     * @param type $nodeId          节点id；order_flow_node表
     * @param type $nextNodeKey     下一个节点key
     * @param type $nextNodeName    下一个节点名（当$nextNodeKey有值时生效）
     * @param type $param
     * @return type
     * @throws Exception
     */
    private static function setFinish( $nodeId ,$nextNodeKey = "",$nextNodeName="" ,$param = [] )
    {
        //事务中才能操作
        OrderFlowNodeService::checkTransaction();
        //获取节点信息
        $nodeInfo   = OrderFlowNodeService::getInstance( $nodeId )->get();
        $lastFlow   = OrderFlowNodeService::orderLastFlow($nodeInfo['order_id']);
        if($lastFlow['id'] != $nodeId ){
            throw new Exception('当前节点不是订单末个节点，不能操作');
        }
        //当前节点设为完成
        OrderFlowNodeService::getInstance( $nodeId )->setFinish();
        //获取下一个节点key
        $operateRole    = "";   //操作角色
        if(! $nextNodeKey){
            if( OrderFlowNodeTplService::nextNodeCount( $nodeInfo['node_key']) >1 ){
                throw new Exception('存在多个下级流程!');
            }
            $nextNode    = OrderFlowNodeTplService::nextNodeFind( $nodeInfo['node_key'] );
            if( $nextNode ){
                $nextNodeKey    = $nextNode['next_node_key'];
                $nextNodeName   = $nextNode['next_node_name'];
                $operateRole    = $nextNode['operate_role'];
            }
        }
        //下一个节点还有后续节点，则流程未结束，没有后续节点，则流程结束
        $nextNextNode = OrderFlowNodeTplService::nextNodeFind( $nextNodeKey );
        $operateState = $nextNextNode ? XJRYANSE_OP_TODO : XJRYANSE_OP_FINISH;
        self::debug(__METHOD__.'-$nextNodeKey',$nextNodeKey );
        self::debug(__METHOD__.'-nextNodeFind',OrderFlowNodeTplService::nextNodeFind( $nextNodeKey ) );
        self::debug(__METHOD__.'-$operateState',$operateState);
        //没有下级节点，则流程可能已结束，根据nodeKey值更新一下订单状态
        if(!$nextNextNode ){ self::setOrderStatusByNode( $nodeInfo['order_id'], $nextNodeKey ); }
        //下一个节点写入数据库
        $res = self::addFlow( $nodeInfo['order_id'] , $nextNodeKey , $nextNodeName, $operateRole, $operateState , $param );
        return $res;
    }

    /**
     * 根据节点设定订单状态
     * @param type $orderId     订单id
     * @param type $nodeKey     节点key
     */
    private static function setOrderStatusByNode($orderId,$nodeKey = "")
    {
        //订单完成
        if($nodeKey == ORDER_FINISH){
            OnProcessLogic::getInstance( $orderId )->setOrderStatus( YDZB_ORDER_FINISH );
        }
        //交易关闭
        if($nodeKey == ORDER_CLOSE ){
            OnProcessLogic::getInstance( $orderId )->setOrderStatus( YDZB_ORDER_CLOSE );
            OnCancelLogic::getInstance( $orderId )->setAllGoodsOnSale();
        }
        //其他情况不做处理
    }
           

}
