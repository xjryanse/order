<?php
namespace xjryanse\order\logic;

use xjryanse\order\service\OrderFlowNodeTplService;
use xjryanse\goods\service\GoodsTypePrizeKeyService;
use xjryanse\goods\service\GoodsPrizeKeyService;
use xjryanse\logic\Debug;
/**
 * 销售类型逻辑
 */
class SaleTypeLogic
{
    use \xjryanse\traits\InstTrait;
    
    protected static $companyId;    
    /**
     *w_order_flow_node_tpl,按销售类型查询出的列表
     * @var type 
     */
    protected $flowNodeTplLists = [];

    public static function setCompanyId($companyId){
        self::$companyId = $companyId;
    }
    /**
     * 销售类型取流程模板
     * @return type
     */
    public function getflowNodeTplLists(){
        if(!$this->flowNodeTplLists){
            $companyId = self::$companyId ? : session(SESSION_COMPANY_ID);
            $lists = OrderFlowNodeTplService::listBySaleType($this->uuid, $companyId);
            //写入内存
            foreach($lists as $v){
                OrderFlowNodeTplService::getInstance($v['id'])->setUuData($v,true);  //强制写入
            }
            
            $this->flowNodeTplLists = $lists ? $lists->toArray() : [];
        }
        Debug::debug('当前的$this->flowNodeTplLists',$this->flowNodeTplLists);
        return $this->flowNodeTplLists;
    }
    /**
     * 指定节点key的下一个节点
     * @param type $nodeKey
     * @return type
     */
    public function getNextNode( $nodeKey ){
        $flowNodeTplLists = $this->getflowNodeTplLists();
        foreach($flowNodeTplLists as &$v){
            if($v['node_key'] == $nodeKey){
                return $v;
            }
        }
        return [];
    }
    
    /**
     * 下一个节点列表（多节点使用）
     */
    public function nextNodeList( $nodeKey )
    {
        $flowNodeTplLists = $this->getflowNodeTplLists();
        $tempArr = [];
        foreach($flowNodeTplLists as &$v){
            if($v['node_key'] == $nodeKey){
                $tempArr[] = $v;
            }
        }
        return $tempArr;
    }
    /**
     * 当前节点有几个下节点
     * @param type $nodeKey
     */
    public function nextNodeCount($nodeKey){
        $nextNodeList = $this->nextNodeList($nodeKey);
        return count($nextNodeList);
    }
    
    /*
     * 获取前一个处理节点
     */
    public function getPreNode( $nodeKey )
    {
        $flowNodeTplLists = $this->getflowNodeTplLists();
        foreach($flowNodeTplLists as &$v){
            if($v['next_node_key'] == $nodeKey){
                return $v;
            }
        }
        return [];
    }
    /**
     * 祖宗节点列表
     */
    public function grandNodeList(){
        $flowNodeTplLists = $this->getflowNodeTplLists();
        $tempArr = [];
        foreach($flowNodeTplLists as &$v){
            if(!$v['node_key']){
                $tempArr[] = $v;
            }
        }
        return $tempArr;
    }
    
    /**
     * 销售类型是否有价格
     */
    public function hasPrizeKey($prizeKey){
        $prizeKeys = GoodsTypePrizeKeyService::getPrizeKeys($this->uuid);
        return in_array($prizeKey, $prizeKeys);        
    }
    /**
     * 获取买家支付的价格key；用于计算订单总价；包含了商品价格；配送费；包装费等
     */
    public function buyerPayPrizeKey(){
        $prizeKeys = GoodsTypePrizeKeyService::getPrizeKeys($this->uuid);
        $con[] = ['from_role','=','buyer'];
        $con[] = ['prize_key','in',$prizeKeys];
        $con[] = ['change_type','=',1];
        $con[] = ['company_id','=',self::$companyId];

        return GoodsPrizeKeyService::mainModel()->where($con)->column('prize_key');
    }
}
