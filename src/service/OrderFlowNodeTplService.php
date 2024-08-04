<?php

namespace xjryanse\order\service;

use xjryanse\system\interfaces\MainModelInterface;
use xjryanse\system\service\SystemConditionService;
use xjryanse\logic\Cachex;
use xjryanse\order\logic\SaleTypeLogic;

/**
 * 订单流程模板
 */
class OrderFlowNodeTplService implements MainModelInterface {

    use \xjryanse\traits\InstTrait;
    use \xjryanse\traits\MainModelTrait;
    use \xjryanse\traits\MainModelRamTrait;
    use \xjryanse\traits\MainModelCacheTrait;
    use \xjryanse\traits\MainModelCheckTrait;
    use \xjryanse\traits\MainModelGroupTrait;
    use \xjryanse\traits\MainModelQueryTrait;


    protected static $mainModel;
    protected static $mainModelClass = '\\xjryanse\\order\\model\\OrderFlowNodeTpl';

    /**
     * 额外详情信息
     */
    protected static function extraDetail(&$item, $uuid) {
        if (!$item) {
            return false;
        }
        //查看有多少个条件
        $con[] = ['item_key', '=', $item['node_key']];
        $item->SCcondition = SystemConditionService::count($con);

        return $item;
    }

    public static function listBySaleType($saleType, $companyId) {
        return Cachex::funcGet(__CLASS__ . '_' . __METHOD__ . $saleType . $companyId, function() use ($saleType, $companyId) {
                    $con[] = ['sale_type', '=', $saleType];
                    $con[] = ['company_id', '=', $companyId];
                    $con[] = ['is_delete', '=', 0];
                    return self::mainModel()->where($con)->select();
                });
    }

    /*
     * 当前节点有几个下级节点
     */
    /*
      public static function nextNodeCount( $nodeKey )
      {
      $con[] = [ 'node_key', '=', $nodeKey ];
      return self::count( $con );
      } */
    /*
     * 逐渐弃用
     * 查找下一个节点（一般为 nextNodeCount = 1 时使用）
     */
    /*
      public static function nextNodeFind( $nodeKey )
      {
      $con[] = [ 'node_key', '=', $nodeKey ];
      return self::find( $con );
      }
     * 
     */
    /**
     * 下一个节点列表（多节点使用）
     */
    /*
      public static function nextNodeList( $nodeKey )
      {
      $con[] = [ 'node_key', '=', $nodeKey ];
      return self::lists( $con );
      } */
    /*
     * 获取前一个处理节点
     */
    /*
      public static function getPreNode( $nodeKey )
      {
      $con[] = ['next_node_key','=',$nodeKey];
      return self::find( $con );
      } */
    /**
     * 祖宗节点列表
     * @param type $saleType
     * @return type
     */
    /*
      public static function grandNodeList($saleType, $companyId){
      $nodeLists = self::nodeLists($saleType, $companyId);
      $grandArr = [];
      foreach($nodeLists as &$v){
      if(!$v['node_key']){
      $grandArr[] = $v;
      }
      }
      return $grandArr;
      } */
    /**
     * 销售类型取全部节点列表
     * @param type $saleType
     * @return type
     */
    /*
      public static function nodeLists($saleType, $companyId){
      $inst = SaleTypeLogic::getInstance($saleType);
      $inst->setCompanyId($companyId);
      return $inst->getflowNodeTplLists();

      //        //带缓存
      //        $key = 'OrderFlowNodeTplService::nodeLists'.$companyId.$saleType;
      //        return Cachex::funcGet($key, function() use ($saleType, $companyId){
      //            $con[] = ['sale_type','=',$saleType];
      //            $con[] = ['company_id','=',$companyId];
      //            $lists = self::mainModel()->where( $con )->select();
      //            return $lists ? $lists->toArray() : [];
      //        });
      }
     *
     */
    /**
     * 销售类型和下节点key取当条记录信息
     * 当记录有多条时，数据不准，只能取出其中一条
     */
    /*
      public static function getBySaleTypeAndNextNodeKey($saleType, $nextNodeKey, $companyId){
      $nodeLists = self::nodeLists($saleType, $companyId);
      foreach($nodeLists as &$v){
      if($v['next_node_key'] == $nextNodeKey){
      return $v;
      }
      }
      return [];
      } */

    /**
     * 销售类型和当前节点key取当条记录信息
     * 当记录有多条时，不准，只能取出其中一条
     */
    /*
      public static function getBySaleTypeAndNodeKey($saleType, $nodeKey, $companyId){
      $nodeLists = self::nodeLists($saleType, $companyId);
      foreach($nodeLists as &$v){
      if($v['node_key'] == $nodeKey){
      return $v;
      }
      }
      return [];
      }
     */
}
