<?php
namespace xjryanse\order\service;

use app\station\service\StationService;
use app\circuit\service\CircuitBusService;  
use app\order\service\OrderBaoBusService;
use app\bus\service\BusService;
use xjryanse\user\service\UserPassengerService;
use xjryanse\finance\service\FinanceStatementOrderService;
use xjryanse\wechat\service\WechatWePubTemplateMsgLogService;
use xjryanse\order\service\OrderFlowNodeService;
use xjryanse\order\service\OrderService;
use xjryanse\user\service\UserService;
use xjryanse\logic\DataCheck;
use xjryanse\logic\Strings;
use xjryanse\logic\Arrays;
use xjryanse\logic\Arrays2d;
use xjryanse\logic\Debug;
use xjryanse\logic\Url;
use Exception;
//TEMP临时
use app\circuit\service\CircuitTicketStationService;
/**
 * 订单乘客
 */
class OrderPassengerService {
    use \xjryanse\traits\InstTrait;
    use \xjryanse\traits\MainModelTrait;
    use \xjryanse\traits\SubServiceTrait;    
    use \xjryanse\traits\ObjectAttrTrait;

    protected static $mainModel;
    protected static $mainModelClass = '\\xjryanse\\order\\model\\OrderPassenger';
    //直接执行后续触发动作
    protected static $directAfter = true;
    /**
     * 添加订单乘客
     * @param type $orderId
     * @param type $passengerIds
     * @return type
     * @throws Exception
     */
    public static function orderPassengerAdd($orderId, $passengerArr, $data = []){
        $cond[] = ['order_id','=',$orderId];
        $count = self::count($cond);
        if($count){
            throw new Exception('订单已有乘客，不可重复添加');
        }
        foreach( $passengerArr as $passenger){
            Debug::debug('$passenger',$passenger);
            if(!$passenger['seat_no']){
                throw new Exception('未选择或绑定座位号'.$passenger['id']);
            }
            $tmpData                    = $data;
            $tmpData['order_id']        = $orderId;
            $tmpData['seat_no']         = $passenger['seat_no'];
            $tmpData['passenger_id']    = $passenger['passenger_id'];
            $tmpData['prize']           = $passenger['prize'];
            $tmpData['tag']             = Arrays::value($passenger, 'tag');
            $res = self::save($tmpData);
            //座位绑定
        }
        return $res;
    }
    /**
     * 类似快照
     * @param type $data
     * @param type $uuid
     * @throws Exception
     */
    public static function extraPreSave(&$data, $uuid) {
        self::checkTransaction();
        // 20230215:包车趟次号
        if(Arrays::value($data, 'bao_bus_id')){
            $data['order_id'] = OrderBaoBusService::getInstance($data['bao_bus_id'])->fOrderId();
        }
        
        DataCheck::must($data,['order_id']);
        // DataCheck::must($data,['order_id','seat_no','passenger_id','prize']);

        $orderId        = Arrays::value($data, 'order_id');
        $orderInfo      = OrderService::getInstance($orderId)->get();
        if(!$orderInfo){
            throw new Exception( '订单信息不存在'. $orderInfo );
        }
        //班次
        $data['circuit_bus_id']     = $orderInfo['circuit_bus_id'];
        //线路
        $data['circuit_id']         = CircuitBusService::getInstance($orderInfo['circuit_bus_id'])->fCircuitId();
        Arrays::ifEmptyReplace($data, 'from_station_id', $orderInfo['from_station_id']);
        Arrays::ifEmptyReplace($data, 'to_station_id', $orderInfo['to_station_id']);
        $passengerId    = Arrays::value($data, 'passenger_id');
        // 20230215：移入内部判断
        if($passengerId){
            $passenger      = UserPassengerService::getInstance($passengerId)->get();
            if(!$passenger){
                throw new Exception( '乘车人不存在'. $passengerId );
            }
            $data['realname']           = $passenger['realname'];
            $data['id_no']              = $passenger['id_no'];
            $data['phone']              = $passenger['phone'];
        }
        
        // 2023-02-27
        $phone = Arrays::value($data, 'phone');
        if(!Strings::isPhone($phone)) {
            throw new Exception( '手机号码格式错误'. $phone );
        }
        // 通过手机号码关联出用户的id
        $data['user_id'] = UserService::phoneUserId( $phone );
        
        if(self::hasRegist(Arrays::value($data, 'order_id')
                , Arrays::value($data, 'circuit_bus_id')
                , Arrays::value($data, 'realname')
                , Arrays::value($data, 'id_no')
                , Arrays::value($data, 'phone'))){
            throw new Exception( '乘客'. Arrays::value($data, 'realname').'已在本趟次登记' );
        }

        Debug::debug('保存数据',$data);
        return $data;
    }

    public static function extraAfterSave(&$data, $uuid) {
        self::checkTransaction();
        $orderId        = Arrays::value($data, 'order_id');
        $orderInfo      = OrderService::getInstance($orderId)->get();
        if($orderInfo['order_type'] == 'pin'){
            Debug::debug('OrderPassenger的afterSave',$data);
            CircuitTicketStationService::seatBind($uuid);
        }
        // 2022-11-20
        if($orderId){
            OrderService::getInstance($orderId)->objAttrsPush('orderPassengers',$data);
        }
    }
    /**
     * 钩子-更新前
     */
    public static function extraPreUpdate(&$data, $uuid) {
        self::checkTransaction();
        $info = self::getInstance($uuid)->get();
        // 检票的逻辑
        if(isset($data['is_ticked']) && $data['is_ticked']){
            if($info['is_ticked']){
                throw new Exception('车票已检');
            }
            if($info['is_ref']){
                throw new Exception('车票已退不可检');
            }
            if(!OrderService::getInstance($info['order_id'])->hasPay()){
                throw new Exception('订单未付款');
            }
            $data['ticket_user_id'] = session(SESSION_USER_ID);
            $data['ticket_time']    = date('Y-m-d H:i:s');
        }
    }
    
    public static function extraAfterUpdate(&$data, $uuid) {
        $info = self::getInstance($uuid)->get();
        if(isset($data['is_ref']) && $data['is_ref']){
            if($info['is_ticked']){
                throw new Exception('车票已检，不可退票'.$info['id']);
            }
            //解绑座位
            CircuitTicketStationService::seatUnBind($uuid);
        }
        //触发订单流程
        if(isset($data['is_ticked']) && $data['is_ticked']){
            $info = self::getInstance($uuid)->get();
            // 触发订单后续动作
            OrderFlowNodeService::lastNodeFinishAndNext($info['order_id']);
        }
        // 20221120
        OrderService::getInstance($info['order_id'])->objAttrsUpdate('orderPassengers',$uuid, $data);
    }
        
    public function extraPreDelete(){
        $info = $this->get();
        if($info['is_ticked']){
            throw new Exception('车票已检，不可删');
        }
        if(!$info['is_ref']){
            throw new Exception('未退票，不可删');
        }
        $con[] = ['order_id','=',$info['order_id']];
        if(FinanceStatementOrderService::mainModel()->where($con)->count()){
            throw new Exception('请先删除订单收付款流水');
        }
    }
    
    public function extraAfterDelete()
    {
        self::checkTransaction();
        CircuitTicketStationService::seatUnBind($this->uuid);
    }
    
    public static function extraDetails($ids) {
        return self::commExtraDetails($ids, function($lists) use ($ids){
            $wechatWePubTemplateMsgLogCount = WechatWePubTemplateMsgLogService::groupBatchCount('from_table_id', $ids);
            foreach ($lists as &$item) {
                //消息发送数
                $item['wechatWePubTemplateMsgLogCount'] = Arrays::value($wechatWePubTemplateMsgLogCount, $item['id'],0);
            }            
            
            return $lists;
        });
    }
    /**
     * 下单校验乘客是否已买
     */
    public static function circuitBusOrderPassengerCheck($circuitBusId,$passengerIds){
        $con[] = ['circuit_bus_id','=',$circuitBusId];
        $con[] = ['passenger_id','in',$passengerIds];
        $con[] = ['is_ref','=',0];
        $info = self::find($con);
        if($info){
            // 2022-11-20：拆两个
            if($info['is_pay']){
                throw new Exception('乘客'.$info['realname'].'已购票成功，无需重复购票');
            } else {
                throw new Exception('乘客'.$info['realname'].'已下单未支付，请点击底部“订单”查询');
            }
        }
    }
    /**
     * 乘客购票信息统计
     * 用于列表
     */
    public static function circuitBusPassengerStatics($circuitBusIds){
        $cond[] = ['circuit_bus_id', 'in', $circuitBusIds];
        $cond[] = ['is_ref', '=', 0];
        $seatsRaw = OrderPassengerService::mainModel()
                ->where($cond)
                ->group('circuit_bus_id')
                // ->field('circuit_bus_id,count( DISTINCT seat_no ) AS saleSeats,sum( if(is_ticked ,1,0)) AS ticketSeats,sum( if(hasBus,1,0)) AS busArrangedSeats,sum( if(hasBus ,0,1)) AS busNoArrangeSeats')
                ->field('circuit_bus_id,count(1) AS saleSeats,sum( if(is_ticked ,1,0)) AS ticketSeats,sum( if(hasBus,1,0)) AS busArrangedSeats,sum( if(hasBus ,0,1)) AS busNoArrangeSeats')
                ->select();
        $seatsArr = $seatsRaw ? $seatsRaw->toArray() : [];
        return Arrays2d::fieldSetKey($seatsArr, 'circuit_bus_id');
    }
    /**
     * 特殊处理
     * @param type $orderIds
     * @return type
     */
    public static function orderGroupBatchSelect($orderIds){
        $con[] = ['a.order_id','in',$orderIds];
        $field = "a.id,a.order_id,a.passenger_id,a.bus_id,a.phone,a.prize,a.realname,a.seat_no,a.is_ticked,a.is_ref,a.tag,b.licence_plate as licencePlate";
        $busTable = BusService::getTable();
        $listsRaw = self::mainModel()
                ->alias('a')
                ->leftJoin($busTable.' b',"a.bus_id = b.id")
                ->where($con)
                ->field($field)
                ->select();
        $lists = $listsRaw ? $listsRaw->toArray() : [];
        //拼接
        $data = [];
        foreach($lists as &$v){
            //车牌号null处理
            $v['licencePlate'] = $v['licencePlate'] ? : "";
            $data[$v['order_id']][] = $v;
        }
        return $data;
    }
    
    /**
     * 最近n天内，多少乘客统计
     * 
     */
    public static function recentDatePassengerCount ( $circuitIds ){
        $cond[] = ['b.circuit_id','in',$circuitIds];
        $cond[] = ['a.is_ref','=',0];
        //$cond[] = ["date_format( `b`.`departure_time`, '%Y-%m-%d' )",'=',"2022-02-25"];
        $dateToday          = date('Y-m-d');
        $dateTomorrow       = date('Y-m-d',strtotime('+1 day'));
        $dateAfterTomorrow  = date('Y-m-d',strtotime('+2 day'));
                
        $dateArr = [$dateToday, $dateTomorrow, $dateAfterTomorrow];

        $circuitBusTable = CircuitBusService::getTable();
        $res = self::mainModel()->alias('a')
                ->join($circuitBusTable.' b','a.circuit_bus_id = b.id')
                ->field("b.circuit_id,date_format( `b`.`departure_time`, '%Y-%m-%d' ) as departureDate,count(1) as number")
                ->where($cond)
                ->whereRaw("date_format( `b`.`departure_time`, '%Y-%m-%d' ) in ('".implode("','",$dateArr)."')")
                ->group('b.circuit_id,departureDate')
                ->select();
        //线路id
        $dataRes = [];
        foreach($circuitIds as $circuitId){
            $dataRes[$circuitId]['todayCount'] = 0;
            $dataRes[$circuitId]['tomorrowCount'] = 0;
            $dataRes[$circuitId]['afterTomorrowCount'] = 0;
        }

        $key[$dateToday]            = "todayCount";
        $key[$dateTomorrow]         = "tomorrowCount";
        $key[$dateAfterTomorrow]    = "afterTomorrowCount";

        foreach($res as $v){
            $dataRes[$v['circuit_id']][$key[$v['departureDate']]] = $v['number'];
        }
        
        return $dataRes;
    }
    /*
     * 订单检票列表(提取检票页面所需的信息)
     */
    public static function orderTicketList($orderId){
        $orderInfo = OrderService::getInstance($orderId)->get();
        $con[]      = ['order_id','=',$orderId];
        $passengers = self::lists($con);
        foreach($passengers as &$v){
            $data['data'] = $v['id'];
            $url = url('qrcode/index/share',[],true,true);
            $v['ticketUrl']         = Url::addParam($url, $data);
            $v['fromStationName']   = StationService::getInstance($v['from_station_id'])->fStation();
            $v['toStationName']     = StationService::getInstance($v['to_station_id'])->fStation();
            $v['orderId']           = $orderId;
            // 订单下单时间
            $v['orderCreateTime']   = $orderInfo['create_time'];
            // 是否过期？过期不出二维码
            $v['isExpire']          = date('Ymd') > date('Ymd',strtotime($orderInfo['plan_start_time'])) ? 1 : 0 ;
            // 计划发车时间
            $v['orderPlanStartTime']= $orderInfo['plan_start_time'];
        }

        return $passengers;
    }
    /**
     * 检票统计结果
     * @param type $circuitBusIds   班线id
     */
    public static function ticketStatics($circuitBusIds = []){
        $con[] = ['is_ref','=',0];
        if($circuitBusIds){
            $con[] = ['circuit_bus_id','in',$circuitBusIds];
        }
        
        $res = self::mainModel()->where($con)
                ->field('circuit_bus_id,ticket_user_id,is_ticked,count(1) as number')
                ->group('ticket_user_id,circuit_bus_id')
                ->select();
        
        return $res ? $res->toArray() : [];
    }
    /**
     * 20221118:查询单趟的人数描述
     */
    public static function getTangBusDescBatch($circuitBusIds){
        if(!is_array($circuitBusIds)){
            $circuitBusIds = [$circuitBusIds];
        }
        $con[] = ['circuit_bus_id','in',$circuitBusIds];
        $lists = self::where($con)
                ->field('circuit_bus_id,from_station_id,to_station_id,bus_id,count(1) as numb')
                ->group('circuit_bus_id,from_station_id,to_station_id,bus_id')->order('bus_id')->select();
        $listsArrRaw       = $lists ? $lists->toArray() : [];
        
        $rCirArr = [];
        foreach($circuitBusIds as $circuitBusId){
            $conCB   = [['circuit_bus_id','=',$circuitBusId]];
            $listsArr = Arrays2d::listFilter($listsArrRaw, $conCB);
            // 有几个出发站
            $countFrom      = count(Arrays2d::uniqueColumn($listsArr, 'from_station_id'));
            // 有几个到站
            $countTo        = count(Arrays2d::uniqueColumn($listsArr, 'to_station_id'));

            $busIds         = Arrays2d::uniqueColumn($listsArr, 'bus_id');
            $stationIds     = array_unique(array_merge(array_column($listsArr, 'from_station_id'),array_column($listsArr, 'to_station_id')));

            $conSt[]        = ['id','in',$stationIds];
            $stationIdArr   = StationService::mainModel()->where($conSt)->column('station','id');

            $res = [];
            // 单车拼接：站点1→站点2 3人，站点2→站点3 4人
            foreach($busIds as &$v){
                $busArrCon      = [['bus_id','=',$v]];
                $liBusArr       = Arrays2d::listFilter($listsArr, $busArrCon);
                $strArr         = [];
                foreach($liBusArr as $vv){
                    $str = '';
                    if($countFrom > 1){
                        $str .= Arrays::value($stationIdArr, $vv['from_station_id']);
                    }
                    if($countTo > 1){
                        $str .= '→'.Arrays::value($stationIdArr, $vv['to_station_id']);
                    }
                    if($countFrom == 1 && $countTo == 1 ){
                        $str .= Arrays::value($stationIdArr, $vv['from_station_id']);
                        $str .= '→'.Arrays::value($stationIdArr, $vv['to_station_id']);
                    }
                    $str .= ':'.$vv['numb'].'人';
                    $strArr[] = $str;
                }

                $res[$v] = implode('，',$strArr);
            }
            $rCirArr[$circuitBusId] = $res;
        }
        
        return $rCirArr;
    }
    /**
     * 20230214：是否都有车辆
     */
    public static function allHasBus($dataArr){
        //dump($dataArr);
        $hasBus = true;
        foreach($dataArr as $v){
            if(!$v['bus_id']){
                $hasBus = false;
            }
        }
        return $hasBus;
    }
    /**
     * 2023-02-27乘客是否已登记
     * @param type $orderId         订单号
     * @param type $circuitBusId    班次号
     * @param type $realname        姓名
     * @param type $idNo            身份证号
     * @param type $phone           手机号
     */
    public static function hasRegist($orderId,$circuitBusId,$realname,$idNo,$phone){
        // 有班次号，用班次号当条件
        if($circuitBusId){
            $con[] = ['circuit_bus_id','=',$circuitBusId];
        } else {
            $con[] = ['order_id','=',$orderId];
        }
        // 有身份证号，用身份证号当条件
        if($idNo){
            $con[] = ['id_no','=',$idNo];
        } else {
            $con[] = ['realname','=',$realname];
            $con[] = ['phone','=',$phone];
        }
        // 2023-02-28:退再买
        $con[] = ['is_ref','=',0];

        $count = self::where($con)->count();
        return $count ? true : false;
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
    /**
     * 20221011：订单id，提取订单总金额（下单的金额）
     */
    public static function orderAllPrize($orderId){
        $con[] = ['order_id','=',$orderId];
        return self::where($con)->sum('prize');
    }
    /**
     * 20221011：提取订单有效金额（扣除退票）
     * @param type $orderId
     * @return type
     */
    public static function orderPrize($orderId){
        $con[] = ['order_id','=',$orderId];
        $con[] = ['is_ref','=',0];
        return self::where($con)->sum('prize');
    }
    /**
     * 20221011:提取订单退款金额
     * @param type $orderId
     * @return type
     */
    public static function orderRefPrize($orderId){
        $con[] = ['order_id','=',$orderId];
        $con[] = ['is_ref','=',1];
        return self::where($con)->sum('prize');
    }
}
