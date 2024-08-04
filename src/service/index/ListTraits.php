<?php

namespace xjryanse\order\service\index;

// 20220906兼容前端，排车页面，是否有更优方案？？
use xjryanse\logic\Arrays;
use xjryanse\logic\Arrays2d;
use xjryanse\logic\DbOperate;
use xjryanse\logic\Datetime;
use xjryanse\logic\Number;
use xjryanse\logic\ModelQueryCon;
use app\order\service\OrderBaoBusService;
use think\Db;
use think\facade\Request;
/**
 * 分页复用列表
 */
trait ListTraits{

    /**
     * 客户聚合，提取待支付明细
     * 用于呆账跟踪：2023-11-02
     */
    public static function listBaoNoPayGroupByCustomerId($param){
        $con    = [];
        $con[]  = ['order_type','=','bao'];

        return self::listNopay();
    }
    
    protected static function listNopay($con = []){
        $fields     = [];
        $fields[]   = 'count( 1 ) AS orderCount';
        $fields[]   = 'sum( remainNeedPay ) AS noPay';
        $fields[]   = 'customer_id';
        
        $con[] = ['order_type','=','bao'];
        $con[] = ['remainNeedPay','>',0];
        
        $lists = self::where($con)
                ->field(implode(',',$fields))
                ->group('customer_id')
                ->order('noPay desc')
                ->cache(1)
                // ->limit(10)
                ->select();
        
        $listsArr = $lists ? $lists->toArray():[];
        return $listsArr;
    }
    
    /**
     * 20231207:列表
     * 单订单运营成本分析表
     * 
     */
    public static function listBaoFeeStatics($param){
        $orderId = Arrays::value($param, 'id');
        // dump($param);
        $con[]  = ['order_id','in',$orderId];
        // $fields = ['id','bus_type_id','bus_id','prize','diao_prize','driver_name'];
        $baoBuses = OrderBaoBusService::where($con)->select();
        $baoBusesArr = $baoBuses ? $baoBuses->toArray() : [];
        
        foreach($baoBusesArr as &$v){
            $v['onroadTime'] = '1时20分测';
            // 营业单价：元/公里
            $v['perMilePrize'] = '5';
            
            // 额定油耗：元/公里
            $v['perMileOilVolumnRated'] = '5';
            // 订单额定油耗
            $v['oilVolumnRated']    = 999;
            // 出场是否满油
            $v['isOutOilFull']      = 1;

            // 实际油单耗：元/公里
            $v['perMileOilVolumn']  = 6;
            // 订单实际油耗
            $v['oilVolumn']         = 999;

            // 高速费
            $v['highwayFee']        = 98;
            // 加油
            $v['oilFee']            = 97;
            // 报销
            $v['financeStaffFee']   = 96;
            
            // 毛利
            $v['finalPrize']        = 95;
            // 毛利率
            $v['finalRate']         = '50%';
        }
        
        return $baoBusesArr;
    }
    /*
     * 20240106:业务员统计
     * 20240323：更换为sql
     * 
     */
    public static function listBaoBusierStatics(){

        $groupFields    = ['tA.busier_id'];

        $con = [];
        return self::_listBaoStatics($groupFields, $con);
    }
    /*
     * 20240106:业务员统计(只查我的业务)
     */
    public static function listMyBaoBusierStatics(){
        $groupFields    = ['tA.busier_id'];

        $con    = [];
        $con[]  = ['tA.busier_id','=',session(SESSION_USER_ID)];
        return self::_listBaoStatics($groupFields, $con);
    }
    /*
     * 20240106:客户统计
     */
    public static function listBaoCustomerStatics(){
        $groupFields    = ['tA.customer_id','tA.busier_id'];
        $con            = [];

        return self::_listBaoStatics($groupFields, $con);
    }

    /*
     * 20240106:业务员统计
     */
    private static function _listBaoStatics($groupFields = [], $con = []){
        $param          = Request::param('table_data');
        // 月份聚合
        $fields = [];
        if(Arrays::value($param, 'groupMonth')){
            $groupFields[] = "date_format( tB.start_time, '%Y-%m' )";
            $fields[] = "date_format( tB.start_time, '%Y-%m' ) as yearmonth";
        }

        // 查询条件
        $qFields             = [];
        $qFields['equal']    = ['customer_id','bus_id','busier_id','status'];
        $con     = array_merge($con, ModelQueryCon::queryCon($param, $qFields));
        // 时间条件
        $scopeTimeArr   = Datetime::paramScopeTime($param);
        if($scopeTimeArr){
            $con[]  = ['tB.start_time','>=',$scopeTimeArr[0]];
            $con[]  = ['tB.start_time','<=',$scopeTimeArr[1]];
        }

        // $groupFields    = ['tA.busier_id'];

        $sql = self::orderBaoStaticsSql($con, $groupFields, $fields);
        $arr = Db::query($sql);
        foreach($arr as &$v){
            // 回款率
            $v['incomeRate']     = intval($v['prize']) ? Number::rate($v['pay_prize'], $v['prize']) : '';
        }

        Arrays2d::pushTimeField($arr, $param);
        // Arrays2d::pushDataFields($arr, $param,['busier_id']);

        if(Arrays::value($param, 'groupMonth')){
            Arrays2d::sort($arr, 'yearmonth','desc');            
        } else {
            Arrays2d::sort($arr, 'prize','desc');
        }
        
        return $arr;
    }
    
    /**
     * 部门车辆统计列表
     * @return type
     */
    private static function orderBaoStaticsSql($con = [],$groupFields = [], $fields = []){

        $orderTable         = 'w_order';
        $orderBaoBusTable   = 'w_order_bao_bus';

        $arr            = [];
        $arr[]          = ['table_name'=>$orderTable, 'alias'=>'tA'];
        $arr[]          = ['table_name'=>$orderBaoBusTable, 'alias'=>'tB','join_type'=>'inner','on'=>'tA.id = tB.order_id'];

        $fields         = array_merge($fields, $groupFields);
        // $fields[]       = 'tA.busier_id';
        $fields[]       = 'count(distinct tA.busier_id) as busierCount';
        $fields[]       = 'count(distinct tA.customer_id) as customerCount';
        $fields[]       = 'count(distinct tA.id) as orderCount';
        $fields[]       = 'count(distinct tB.id) as tangCount';
        $fields[]       = 'sum(tB.prize) as prize';
        $fields[]       = 'sum(tB.pay_prize) as pay_prize';
        $fields[]       = 'sum(tB.remainNeedPay) as remainNeedPay';
        // 报销项目

        // $groupFields    = ['tB.bus_id','tB.dept_id'];
        // $groupFields    = ['tA.busier_id'];

        $con[]          = ['tA.company_id','=',session(SESSION_COMPANY_ID)];
        $sql            = DbOperate::generateJoinSql($fields,$arr,$groupFields, $con);

        return $sql;
    }

}
