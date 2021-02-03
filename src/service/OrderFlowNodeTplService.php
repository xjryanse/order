<?php
namespace xjryanse\order\service;

use xjryanse\system\interfaces\MainModelInterface;
use xjryanse\system\service\SystemConditionService;

/**
 * 订单流程模板
 */
class OrderFlowNodeTplService implements MainModelInterface
{
    use \xjryanse\traits\InstTrait;
    use \xjryanse\traits\MainModelTrait;

    protected static $mainModel;
    protected static $mainModelClass    = '\\xjryanse\\order\\model\\OrderFlowNodeTpl';

    /**
     * 额外详情信息
     */
    protected static function extraDetail( &$item ,$uuid )
    {
        if(!$item){ return false;}
        //查看有多少个条件
        $con[] = ['item_key','=',$item['node_key']];
        $item->SCcondition      = SystemConditionService::count( $con );
        
        return $item;
    }    
    /*
     * 当前节点有几个下级节点
     */
    public static function nextNodeCount( $nodeKey )
    {
        $con[] = [ 'node_key', '=', $nodeKey ];
        return self::count( $con );
    }
    /*
     * 查找下一个节点（一般为 nextNodeCount = 1 时使用）
     */
    public static function nextNodeFind( $nodeKey )
    {
        $con[] = [ 'node_key', '=', $nodeKey ];
        return self::find( $con );
    }
    /**
     * 下一个节点列表（多节点使用）
     */
    public static function nextNodeList( $nodeKey )
    {
        $con[] = [ 'node_key', '=', $nodeKey ];
        return self::lists( $con );
    }
    /*
     * 获取前一个处理节点
     */
    public static function getPreNode( $nodeKey )
    {
        $con[] = ['next_node_key','=',$nodeKey];
        return self::find( $con );
    }    
}
