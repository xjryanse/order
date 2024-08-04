<?php

namespace xjryanse\order\service;

use app\station\service\StationService;
use app\circuit\service\CircuitBusService;
use app\circuit\service\CircuitBusBaoOrderService;
use xjryanse\tour\service\TourPassengerService;
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
use xjryanse\order\logic\SaleTypeLogic;
use xjryanse\wechat\service\WechatWePubTemplateMsgLogService;
use xjryanse\logic\DbOperate;
use xjryanse\logic\Arrays;
use xjryanse\logic\Arrays2d;
use xjryanse\logic\Strings;
use xjryanse\logic\Debug;
use xjryanse\logic\Functions;
// 20220906兼容前端，排车页面，是否有更优方案？？
// use xjryanse\logic\Device;
use think\Db;
use Exception;
/* * 临时，需要拆解 - 20211115 ** */
use app\order\service\OrderBaoBusService;

/**
 * 订单总表
 */
class OrderService {

    use \xjryanse\traits\InstTrait;
    use \xjryanse\traits\MainModelTrait;
    use \xjryanse\traits\MainModelRamTrait;
    use \xjryanse\traits\MainModelCacheTrait;
    use \xjryanse\traits\MainModelCheckTrait;
    use \xjryanse\traits\MainModelGroupTrait;
    use \xjryanse\traits\MainModelQueryTrait;
    use \xjryanse\traits\SubServiceTrait;
    use \xjryanse\traits\ObjectAttrTrait;

    protected static $mainModel;
    protected static $mainModelClass = '\\xjryanse\\order\\model\\Order';
    //直接执行后续触发动作
    protected static $directAfter = true;
    //一经写入就不会改变的值
    protected static $fixedFields = ['company_id', 'creater', 'create_time', 'goods_id', 'amount'
        , 'goods_name', 'goods_table', 'goods_table_id', 'order_type', 'seller_user_id', 'user_id', 'creater', 'create_time'];
    ///从ObjectAttrTrait中来
    // 定义对象的属性
    protected $objAttrs = [];
    // 定义对象是否查询过的属性
    protected $hasObjAttrQuery = [];
    // 定义对象属性的配置数组
    protected static $objAttrConf = [
        'orderFlowNode' => [
            'class' => '\\xjryanse\\order\\service\\OrderFlowNodeService',
            'keyField' => 'order_id',
            'master' => true
        ],
        'orderGoods' => [
            'class' => '\\xjryanse\\order\\service\\OrderGoodsService',
            'keyField' => 'order_id',
            'master' => true
        ],
        'orderBuses' => [
            'class' => '\\app\\order\\service\\OrderBaoBusService',
            'keyField' => 'order_id',
            'master' => true
        ],
        // 准备废弃，使用orderPassenger替代
        'orderPassengers' => [
            'class' => '\\xjryanse\\order\\service\\OrderPassengerService',
            'keyField' => 'order_id',
            'master' => true
        ],
        'tourPassengers' => [
            'class' => '\\xjryanse\\tour\\service\\TourPassengerService',
            'keyField' => 'order_id',
            'master' => true
        ],
//        'financeStatementOrder' => [
//            'class' => '\\xjryanse\\finance\\service\\FinanceStatementOrderService',
//            'keyField' => 'order_id',
//            'master' => true
//        ],
        'financeStaffFee' => [
            'class' => '\\xjryanse\\finance\\service\\FinanceStaffFeeService',
            'keyField' => 'order_id',
            'master' => true
        ]
    ];
    // 20230710：开启方法调用统计
    protected static $callStatics = true;

    // 分开比较好管理
    use \xjryanse\order\service\index\PaginateTraits;
    use \xjryanse\order\service\index\FieldTraits;
    use \xjryanse\order\service\index\TriggerTraits;
    use \xjryanse\order\service\index\ListTraits;
    use \xjryanse\order\service\index\FindTraits;
    
    use \xjryanse\order\service\index\ApprovalTraits;
    use \xjryanse\approval\traits\ApprovalOutTrait;
    /**
     * 20220918客户访问权限校验；增加安全性
     */
    public function customerAuthCheck() {
        $info = $this->get();
        if ($info['user_id'] == session(SESSION_USER_ID)) {
            return true;
        }
        $cond[] = ['is_manager', '=', 1];
        $cond[] = ['user_id', '=', session(SESSION_USER_ID)];
        $cond[] = ['customer_id', '=', $info['customer_id']];
        $isCustomer = CustomerUserService::mainModel()->where($cond)->count();
        return $isCustomer ? true : false;
    }

    /**
     * 避免外部直接调用save方法；
     * 如需下单，请调用OrderService::order();
     * @param type $data
     * @return type
     */
    protected static function save($data) {
        return self::commSave($data);
    }

    /**
     * 返回销售类型实例
     * @return SaleTypeLogic
     */
    public function orderSaleTypeInst(): SaleTypeLogic {
        $saleType = $this->fOrderType();
        $companyId = $this->fCompanyId();
        SaleTypeLogic::setCompanyId($companyId);
        return SaleTypeLogic::getInstance($saleType);
    }

    /**
     * 20220527
     * 查询提取账户类型
     */
    public function getFinanceAccountType() {
        $statementOrders = $this->objAttrsList('financeStatementOrder');
        //订单id，取statementOrder表的statementId;
        $con[] = ['order_id', '=', $this->uuid];
        //20220608加条件
        $con[] = ['statement_cate', '=', 'buyer'];
        $filterArr = Arrays2d::listFilter($statementOrders, $con);
        $statementIds = array_unique(array_column($filterArr, 'statement_id'));

        return FinanceAccountLogService::statementIdsGetAccountType($statementIds);
    }

    /**
     * 20220903:获取客户已付金额
     */
    public function getBuyerPayPrize() {
        $statementOrders = $this->objAttrsList('financeStatementOrder');

        $con[] = ['has_settle', '=', 1];
        $con[] = ['statement_cate', '=', 'buyer'];
        $filterArr = Arrays2d::listFilter($statementOrders, $con);
        $money = array_sum(array_column($filterArr, 'need_pay_prize'));
        return $money;
    }

    /**
     * 20220903:获取已付供应商金额
     */
    public function getPaySellerPrize() {
        $statementOrders = $this->objAttrsList('financeStatementOrder');

        $con[] = ['has_settle', '=', 1];
        $con[] = ['statement_cate', '=', 'seller'];
        $filterArr = Arrays2d::listFilter($statementOrders, $con);
        $money = array_sum(array_column($filterArr, 'need_pay_prize'));
        return $money;
    }

    /**
     * TODo通用的下订单方法
     * $goodsArr: 商品id和数量二维数组，包含 goods_id, amount 属性
     */
    public static function order($goodsArr, $orderData = []) {
        $orderGoodsName = '';
        //一次性取商品信息
        $goodsInfos = GoodsService::batchGet(array_column($goodsArr, 'goods_id'));
        foreach ($goodsArr as &$v) {
            $goodsInfo = Arrays::value($goodsInfos, $v['goods_id'], []);
            if (!isset($v['goods_name']) || !$v['goods_name']) {
                $v['goods_name'] = Arrays::value($goodsInfo, 'goods_name');
            }
            $v['unit_prize'] = Arrays::value($goodsInfo, 'goodsPrize');
            if (!$orderGoodsName) {
                $orderGoodsName = $v['goods_name'];
            }
        }
        //20220608:兼容可外部传入销售类型
        $saleType = $goodsArr ? GoodsService::getInstance($goodsArr[0]['goods_id'])->fSaleType() : '';
        //组织商品名称
        if (array_sum(array_column($goodsArr, 'amount')) > 1 && $saleType == 'normal') {
            $orderGoodsName .= ' 等' . array_sum(array_column($goodsArr, 'amount')) . '件商品';
        }
        $orderData['goods_name'] = $orderGoodsName;

        if (count($goodsArr) == 1) {
            //单商品
            $orderData['goods_id'] = $goodsArr[0]['goods_id'];
            $orderData['amount'] = $goodsArr[0]['amount'] ?: 1;
            $orderData['order_type'] = $saleType;
            // 适用于某表多个记录使用同一个商品下单的情况（比如开发需求）
            if (Arrays::value($goodsArr[0], 'goodsTableId')) {
                $orderData['goods_table_id'] = Arrays::value($goodsArr[0], 'goodsTableId');
            }
        }
        Db::startTrans();
        //先保存明细（主订单的保存有触发动作会用到）
        if (!isset($orderData['id']) || !self::getInstance($orderData['id'])->get()) {
            //会存在更新订单：20211105:
            //20220617,从事务外部搬到内部
            $orderData['id'] = self::mainModel()->newId();
            OrderGoodsService::saveAll($goodsArr, ['order_id' => $orderData['id']], 0);
        }
        //再保存主订单
        $res = self::saveGetId($orderData);
        Db::commit();
        return $res;
    }

    /**
     * 20220621，优化性能
     * @param type $goodsArr
     * @param type $orderData
     * @return type
     */
    public static function orderRam($goodsArr, $orderData = []) {
        $orderGoodsName = '';
        //一次性取商品信息
        $goodsInfos = GoodsService::batchGet(array_column($goodsArr, 'goods_id'));
        foreach ($goodsArr as &$v) {
            $goodsInfo = Arrays::value($goodsInfos, $v['goods_id'], []);
            if (!isset($v['goods_name']) || !$v['goods_name']) {
                $v['goods_name'] = Arrays::value($goodsInfo, 'goods_name');
            }
            $v['unit_prize'] = Arrays::value($goodsInfo, 'goodsPrize');
            if (!$orderGoodsName) {
                $orderGoodsName = $v['goods_name'];
            }
        }
        //20220608:兼容可外部传入销售类型
        $saleType = $goodsArr ? GoodsService::getInstance($goodsArr[0]['goods_id'])->fSaleType() : '';
        //组织商品名称
        if (array_sum(array_column($goodsArr, 'amount')) > 1 && $saleType == 'normal') {
            $orderGoodsName .= ' 等' . array_sum(array_column($goodsArr, 'amount')) . '件商品';
        }
        $orderData['goods_name'] = $orderGoodsName;

        if (count($goodsArr) == 1) {
            //单商品
            $orderData['goods_id'] = $goodsArr[0]['goods_id'];
            $orderData['amount'] = $goodsArr[0]['amount'] ?: 1;
            $orderData['order_type'] = $saleType;
            // 适用于某表多个记录使用同一个商品下单的情况（比如开发需求）
            if (Arrays::value($goodsArr[0], 'goodsTableId')) {
                $orderData['goods_table_id'] = Arrays::value($goodsArr[0], 'goodsTableId');
            }
        }
        //先保存明细（主订单的保存有触发动作会用到）
        if (!isset($orderData['id']) || !self::getInstance($orderData['id'])->get()) {
            //会存在更新订单：20211105:
            //20220617,从事务外部搬到内部
            $orderData['id'] = self::mainModel()->newId();
            OrderGoodsService::saveAllRam($goodsArr, ['order_id' => $orderData['id']], 0);
        }
        //再保存主订单
        $res = self::saveGetIdRam($orderData);

        return $res;
    }

    /**
     * 计算订单总价
     * @param type $goodsArr 商品id和数量二维数组，包含 goods_id, amount 属性
     */
    public static function calOrderPrize($goodsArr) {
        $goodsIds = array_column($goodsArr, 'goods_id');
        $goodsInfos = GoodsService::batchGet($goodsIds);
        Debug::debug(__CLASS__ . __FUNCTION__ . '$goodsInfos', $goodsInfos);
        $orderPrize = 0;
        foreach ($goodsArr as &$v) {
            $goodsInfo = Arrays::value($goodsInfos, $v['goods_id'], []);
            $goodsPrize = Arrays::value($goodsInfo, 'goodsPrize');
            $orderPrize += $goodsPrize * $v['amount'];
        }
        return $orderPrize;
    }

    /**
     * 计算订单总价
     * @param type $goodsArr 商品id和数量二维数组，包含 goods_id, amount 属性
     */
    public function orderPrize() {
        $orderId = $this->uuid;
        // 订单总价
        //$data['order_prize']    = OrderGoodsService::orderGoodsPrize($orderId);
        $orderInfo = $this->get();
        $classStr = self::orderTypeClass($orderInfo['order_type']);
        if (class_exists($classStr)) {
            //20220615:计算订单价格
            $data['order_prize'] = $classStr::getInstance($orderId)->calOrderPrize();
            $data['need_outcome_prize'] = $classStr::getInstance($orderId)->calNeedOutcomePrize();
        } else {
            $data['order_prize'] = GoodsPrizeKeyService::orderPrize($orderId);
            //TODO
            $data['need_outcome_prize'] = '999';
        }
        // 配送费:配送费专用key
        $data['deliver_prize'] = GoodsPrizeKeyService::orderPrizeKeyGetPrize($orderId, 'DeliverPrize');
        Debug::debug(__CLASS__ . '_' . __FUNCTION__ . '$data', $data);
        // Debug::dump($classStr);
        // Debug::dump($data);
        return $data;
    }

    /**
     * 20220615 订单类型取映射处理类库
     */
    public static function orderTypeClass($orderType) {
        $typeStr = Strings::uncamelize($orderType);
        $tableName = config('database.prefix') . 'order_' . $typeStr;
        return DbOperate::getService($tableName);
    }

    /**
     * 取下单的商品总价（用于计算配送费）
     */
    public function orderGoodsPrize() {
        //$goodsList = $this->getOrderGoods();
        $goodsList = $this->objAttrsList('orderGoods');
        $money = 0;
        foreach ($goodsList as $value) {
            $amount = Arrays::value($value, 'amount', 0);
            $unitPrize = Arrays::value($value, 'unit_prize', 0);
            $money += $amount * $unitPrize;
        }
        return $money;
    }

    /**
     * 客户下单（在已有订单上设定客户id）
     */
    public function custCheckOrder($userId) {
        $con[] = ['id', '=', $this->uuid];
        $con[] = ['user_id', '=', ''];
        $res = self::mainModel()->where($con)->update(['user_id' => $userId]);
        if (!$res) {
            throw new Exception('订单不存在或已被认领');
        }
        return $res;
    }

    /*     * ****************************************************************************** */

    /**
     * 是否有未完订单
     * @param type $goodsTable
     * @param type $goodsTableId
     * @return type
     */
    public static function hasNoFinish($goodsTable, $goodsTableId) {
        $con[] = ['goods_table', '=', $goodsTable];
        $con[] = ['goods_table_id', '=', $goodsTableId];
        $con[] = ['order_status', 'not in', ['close', 'finish']];
        return self::count($con);
    }

    /**
     * 订单已付？？
     */
    public function hasPay() {
        $info = $this->get();
        return $info && ($info['pay_prize'] - abs($info['refund_prize'])) >= $info['pre_prize'];
    }

    /**
     * 获取订单的时间状态
     * @return type
     */
    public function orderTimeArr() {
        //用于流程识别
        $keysArr = ['BuyerReceive', 'SellerDeliverGoods', 'BuyerPay', 'orderFinish'];
        $timeArr = OrderFlowNodeService::getOrderTimeArr($this->uuid, $keysArr);
        Debug::debug('orderTimeArr的$timeArr', $timeArr);
        foreach ($timeArr as &$v) {
            if ($v == '0000-00-00 00:00:00') {
                $v = null;
            }
        }
        //用于替换成order表中的字段
        $keys = [
            'BuyerReceive' => 'order_receive_time',
            'SellerDeliverGoods' => 'order_deliver_time',
            'BuyerPay' => 'last_pay_time',
            'orderFinish' => 'order_finish_time'
        ];
        //返回结果
        return Arrays::keyReplace($timeArr, $keys);
    }

    /**
     * 20240402:太复杂，准备废弃
     * @return bool
     */
    public function info() {
        $orderInfo = $this->commInfo(0);
        // 20230812:反馈新中单不显示
        if (!$orderInfo) {
            return [];
        }
        if ($orderInfo['is_delete']) {
            return [];
        }
        $id = $this->uuid;
        $con[] = ['order_id', '=', $id];
        //【取流程】
        $orderInfo['flowNodes'] = OrderFlowNodeService::lists($con, 'id desc');
        //【客户信息】
        $orderInfo['userInfo'] = UserService::mainModel()->where('id', $orderInfo['user_id'])->field('id,username,nickname,realname,headimg,phone')->find();
        //【订单商品】
        $orderInfo['orderGoods'] = OrderGoodsService::orderGoodsInfo($id);
        //【取费用】
        //$orderInfo['financeStatements']         = FinanceStatementOrderService::orderStatementLists($id);
        $orderInfo['financeStatements'] = OrderService::getInstance($id)->objAttrsList('financeStatementOrder');

        $condStatement = [];
        $condStatement[] = ['order_id', 'in', $id];
        $condStatement[] = ['has_settle', '=', 0];
        $condStatement[] = ['statement_cate', '=', 'buyer'];
        //20220902:增加改变类型
        $condStatement[] = ['change_type', '=', 1];
        $statementLists = FinanceStatementOrderService::mainModel()->where($condStatement)->field('id,need_pay_prize,order_id')->select();
        $orderInfo['buyerNeedPayStatements'] = $statementLists;
        // 支付剩余时间：15分钟：按下单时间计算
        $remainSeconds = strtotime($orderInfo['create_time']) - time() + 900;
        $orderInfo['payRemainSeconds'] = $remainSeconds > 0 ? $remainSeconds : 0;
        // 模板消息发送记录
        $cone[] = ['order_id', 'in', $id];
        $flowNodeIds = OrderFlowNodeService::mainModel()->where($cone)->column('id');
        $orderInfo['tplMsgs'] = WechatWePubTemplateMsgLogService::listByFromTableId(array_merge($flowNodeIds, [$id]));
        //是否超过时限；超过不可退款
        $orderInfo['isExpire'] = $orderInfo['plan_start_time'] && (time() - strtotime($orderInfo['plan_start_time'])) > 0 ? true : false;
        // 是否今日订单
        if ($orderInfo['company_id'] == '3') {
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
    public function inHours($hours) {
        if (!$hours) {
            return false;
        }
        $info = $this->get();
        Debug::debug('planStartTime', $info['plan_start_time']);
        Debug::debug('当前时间', date('Y-m-d H:i:s', strtotime('+' . $hours . ' hours')));

        if ($info['plan_start_time'] < date('Y-m-d H:i:s', strtotime('+' . $hours . ' hours'))) {
            return true;
        }
        return false;
    }

    /**
     * 
     * @param type $ids
     * @return type
     */
    public static function extraDetails($ids) {
        return self::commExtraDetails($ids, function ($lists) use ($ids) {
                    // 转化成数组
                    $ids = $ids ? (is_array($ids) ? $ids : [$ids] ) : [];
                    // 20230606优化
                    self::objAttrsListBatch('orderFlowNode', $ids);
                    self::objAttrsListBatch('orderBuses', $ids);
                    // 先批量查询一次
                    $circuitBusIds = Arrays2d::uniqueColumn($lists, 'circuit_bus_id');
                    $conCB[] = ['id', 'in', $circuitBusIds];
                    // 批量查询一次，提升性能
                    $circuitBusLists = CircuitBusService::listsArr($conCB);
                    //20211213;站点id数组
                    $stationIds = array_unique(array_merge(array_column($lists, 'from_station_id'), array_column($lists, 'to_station_id')));
                    $stationAttr = Arrays::isEmpty($stationIds) ? [] : StationService::mainModel()->where([['id', 'in', $stationIds]])->column('station', 'id');
                    //乘客
                    $passengerArr = OrderPassengerService::orderGroupBatchSelect($ids);
                    //乘客
                    $tourPassengerArr = TourPassengerService::groupBatchSelect('order_id', $ids);
                    //20230303:团客
                    $tourPassengerCount = TourPassengerService::groupBatchCount('order_id', $ids);
                    //订单车辆
                    // $baoOrderIds = CircuitBusService::baoOrderIds($circuitBusIds);
                    $baoOrderIds = Arrays2d::uniqueColumn($circuitBusLists, 'bao_order_id');
                    // 批量查询一次，提升性能
                    self::listsArr([['id', 'in', $baoOrderIds]]);
                    $orderBusArr = OrderBaoBusService::orderBusBatchSelect(array_merge($ids, $baoOrderIds));
                    //订单模板消息
                    // $wechatWePubTemplateMsgLogCount = WechatWePubTemplateMsgLogService::groupBatchCount('from_table_id', $ids);
                    //20220610,用于提取后向订单id
                    $conf[] = ['pre_order_id', 'in', $ids];
                    //20220615,修bug
                    $conf[] = ['is_delete', '=', 0];
                    $afterOrders = self::mainModel()->where($conf)->field('id,pre_order_id')->select();
                    $afterOrdersArr = $afterOrders ? $afterOrders->toArray() : [];
                    $afterOrderObj = Arrays2d::fieldSetKey($afterOrdersArr, 'pre_order_id');
                    //20220813：用户绑定统计
                    $wechatWePubBindCount = WechatWePubFansUserService::groupBatchCount('user_id', array_column($lists, 'user_id'));

                    $stOrderCount = FinanceStatementOrderService::groupBatchCount('order_id', $ids);
                    //订单商品记录数
                    $orderGoodsCount = OrderGoodsService::groupBatchCount('order_id', $ids);
                    // 关联的拼团趟次数量
                    $circuitBusBaoOrderCount = CircuitBusBaoOrderService::groupBatchCount('bao_order_id', $ids);
                    // 订单修改记录数
                    $orderChangeCount = OrderChangeLogService::groupBatchCount('order_id', $ids);

                    foreach ($lists as &$item) {
                        //团客数
                        $item['tourPassengerCount'] = Arrays::value($tourPassengerCount, $item['id'], 0);
                        //20220730兼容前端；TODO更优？？
                        $item['need_invoice'] = strval($item['need_invoice']);
                        $item['lastFlowNode'] = OrderFlowNodeService::orderLastFlow($item['id']);
                        //20211115:优化
                        if ($item['order_type'] == 'bao') {
                            //$item['baoBuses'] = OrderBaoBusService::orderBusList( $item['id'] );
                            $item['baoBuses'] = Arrays::value($orderBusArr, $item['id'], []);
                            // 20220904：趟数
                            $item['baoBusesCount'] = count($item['baoBuses']);
                            $item['route'] = count($item['baoBuses']) ? $item['baoBuses'][0]['route'] : '';
                            $item['route'] .= count($item['baoBuses']) > 1 ? '等' : '';
                            //20220815:是否有已排车辆（控制客户端订单不可取消）
                            $item['hasArrangedBus'] = $item['baoBuses'] && array_unique(array_column($item['baoBuses'], 'bus_id'))[0] != '' ? 1 : 0;
                            // 班线包车订单数
                            $item['circuitBusBaoOrderCount'] = Arrays::value($circuitBusBaoOrderCount, $item['id'], 0);
                        }
                        if (in_array($item['order_type'], ['bao', 'pin', 'ding'])) {
                            //订单乘客
                            $item['orderPassengers'] = Arrays::value($passengerArr, $item['id']) ?: [];
                            $item['orderPassengerCount'] = count($item['orderPassengers']);
                            $item['orderPassengerName'] = implode(',', array_column($item['orderPassengers'], 'realname'));
                            // 20230214:所有的乘客是否已排好了车辆
                            $item['orderPassengerAllHasBus'] = OrderPassengerService::allHasBus($item['orderPassengers']) ? 1 : 0;
                        }
                        // 20230314:旅游团
                        if (in_array($item['order_type'], ['tour'])) {
                            //订单乘客
                            $item['tourPassengers'] = Arrays::value($tourPassengerArr, $item['id']) ?: [];
                            $item['tourPassengerCount'] = count($item['tourPassengers']);
                        }
                        if (in_array($item['order_type'], ['pin'])) {
                            $baoOrderId = CircuitBusService::getInstance($item['circuit_bus_id'])->fBaoOrderId();
                            if ($baoOrderId) {
                                //订单乘客
                                $item['tempBaoOrderId'] = $baoOrderId;
                                $item['buses'] = $baoOrderId ? Arrays2d::getByKeys(Arrays::value($orderBusArr, $baoOrderId, []), ['id', 'licence_plate']) : [];
                            } else {
                                $item['tempBaoOrderId'] = '';
                                $item['buses'] = [];
                            }
                        }
                        $item['fromStationName'] = Arrays::value($stationAttr, $item['from_station_id']);
                        $item['toStationName'] = Arrays::value($stationAttr, $item['to_station_id']);
                        //微信模板消息数
                        // $item['wechatWePubTemplateMsgLogCount'] = Arrays::value($wechatWePubTemplateMsgLogCount, $item['id'], 0);
                        // 下单用户是否已绑定微信
                        $item['isUserBind'] = Arrays::value($wechatWePubBindCount, $item['user_id']) ? 1 : 0;
                        // 20220609 circuitBusId是否有值：用于控制页面显示
                        $item['hasCircuitBus'] = $item['circuit_bus_id'] ? 1 : 0;
                        $item['afterOrderId'] = isset($afterOrderObj[$item['id']]) ? $afterOrderObj[$item['id']]['id'] : '';
                        // 20220610是否有前序订单
                        $item['hasPreOrder'] = $item['pre_order_id'] ? 1 : 0;
                        // 20220610是否有后序订单（后序由前序计算）
                        $item['hasAfterOrder'] = $item['afterOrderId'] ? 1 : 0;
                        // 20220812:客户已签章
                        $item['hasBuyerSign'] = $item['buyer_sign'] ? 1 : 0;
                        // 20220812:供应商已签章
                        $item['hasSellerSign'] = $item['seller_sign'] ? 1 : 0;
                        // 订单时间是否已过：控制不可取消
                        $item['isTimePass'] = $item['plan_start_time'] && strtotime($item['plan_start_time']) < time() ? 1 : 0;
                        // 20220904：当前用户是否订单创建者。用于前台客户页面控制是否可删除
                        $item['isCreater'] = $item['creater'] == session(SESSION_USER_ID) ? 1 : 0;
                        // 是否有资金变动记录
                        $item['hasMoneyPay'] = abs($item['pay_prize']) || abs($item['refund_prize']) > 0 ? 1 : 0;
                        // 账单数
                        $item['statementOrderCount'] = Arrays::value($stOrderCount, $item['id'], 0);
                        // 订单商品记录数
                        $item['orderGoodsCount'] = Arrays::value($orderGoodsCount, $item['id'], 0);
                        // 订单修改记录数
                        $item['orderChangeCount'] = Arrays::value($orderChangeCount, $item['id'], 0);
                    }
                    return $lists;
                }, true);
    }

    /**
     * 更新订单的财务账信息
     */
    public function updateFinanceStatementRam() {
        $orderInfo = $this->get();
        $classStr = self::orderTypeClass($orderInfo['order_type']);

        //20220622：找个地方取订单金额：
        if (class_exists($classStr)) {
            //20220615:计算订单价格 
            $orderPrize = $classStr::getInstance($this->uuid)->calOrderPrize();
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
    public function orderDataSync() {
        // 更新订单的时间(TODO所有更新合成一个)
        $orderId = $this->uuid;
        //订单时间
        $timeData = $this->orderTimeArr();
        //订单金额
        $prizeData = $this->orderPrize();
        //支付金额
        $moneyData = FinanceStatementOrderService::orderMoneyData($orderId);
        //末流程节点数据
        $lastNodeData = OrderFlowNodeService::orderLastNodeData($orderId);
        $updData = array_merge($timeData, $prizeData, $moneyData, $lastNodeData);
        //订单是否完成
        $updData['is_complete'] = booleanToNumber(OrderFlowNodeService::orderComplete($orderId));
        //20220527:增加付款方式
        $updData['finance_account_type'] = $this->getFinanceAccountType();
        Debug::debug('orderDataSync的data', $updData);
        //写入内存
        $this->setUuData($updData);
        // strict(false) TODO 优化
        $res = self::mainModel()->where('id', $this->uuid)->strict(false)->update($updData);
        //------------TODO是否可省略?②写入订单子表
        $info = $this->get();
        $subService = self::getSubService($info['order_type']);
        if ($info['order_type'] && class_exists($subService)) {
            $subService::getInstance($orderId)->update($updData);
        }
        //20220413:更优化？更新拼车的付款状态
        if ($info['pay_prize'] >= $info['order_prize'] && $info['order_type'] == 'pin') {
            $cone = [];
            $cone[] = ['order_id', '=', $orderId];
            OrderPassengerService::mainModel()->where($cone)->update(['is_pay' => 1]);
        }
        //20220516:包车订单，更新单趟次的费用信息；
        if ($info['order_type'] == 'bao') {
            // 20240120?qu
            // OrderBaoBusService::updateFinancePrize($this->uuid);
        }

        return $res;
    }

    /**
     * 20220620订单数据同步
     * 一般用于各种操作完成后
     */
    public function orderDataSyncRam() {
        $prizeData = $this->orderPrize();
        $moneyData = FinanceStatementOrderService::orderMoneyData($this->uuid);
        //只更新数据，不执行触发
        //$updData    = array_merge($timeData, $prizeData, $moneyData, $lastNodeData);
        $updData = array_merge($prizeData, $moneyData);
        $updData['finance_account_type'] = $this->getFinanceAccountType();
        //20220624死循环？？
        $res = $this->doUpdateRam(array_merge($updData));
        //$res = $this->updateRam(array_merge($updData));
        $info = $this->get();
        //TODO,其他bug
        if ($info['order_type'] == 'bao') {
            OrderBaoBusService::updateFinancePrizeRam($this->uuid);
        }

        //20220621:递归更新前序订单
        $preOrderId = $this->fPreOrderId();
        if ($preOrderId) {
            self::getInstance($preOrderId)->orderDataSyncRam();
        }
        return $res;
    }

    /**
     * 优先级：是公司管理员，不加条件；
     * 如果是客户；仅查询该客户名下订单
     * TODO 如果都不是，仅查询该用户名下订单。
     * TODO 20220407如何兼容现有后台用户？？？
     * @return type
     */
    public static function extraDataAuthCond() {
        //20230324:
        $sessionUserInfo = session(SESSION_USER_INFO);
        if ($sessionUserInfo['admin_type'] == 'super') {
            return [];
        }
        $authCond = [];
        //过滤用户可查看的项目权限
        $userId = session(SESSION_USER_ID);
        $cond[] = ['user_id', '=', $userId];
        //20220809：增加管理员才能查看该客户下全部订单
        $cond[] = ['is_manager', '=', 1];
        $customerIds = CustomerUserService::mainModel()->where($cond)->column('customer_id');
        // 20220525发现后台管理员查看数据会被过滤，增加!$sessionUserInfo['isCompanyManage']判断
        if (!$sessionUserInfo['isCompanyManage']) {
            //20220422，TODO，如何处理？？会导致拼团查不到车票；但是客户端口又需要过滤数据
            //$authCond[] = ['customer_id','in',$customerIds];
            //20220430,临时使用，是否有更好的方法？？
            if ($customerIds) {
                $customerIds[] = session(SESSION_USER_ID);
                $authCond[] = ['custUser', 'in', $customerIds];
            } else {
                // 20220809非管理员，只过滤自己下单的记录
                $authCond[] = ['user_id', 'in', session(SESSION_USER_ID)];
            }
        }
        //TODO如果不是项目成员，只能查看自己提的需求
        return $authCond;
    }

    /**
     * 订单取消
     */
    public function cancel() {
        self::checkTransaction();
        //对账单要先清
        FinanceStatementService::clearOrderNoDeal($this->uuid);
        //才能清对账单明细
        FinanceStatementOrderService::clearOrderNoDeal($this->uuid);
        //最后订单才能被删
        $res = $this->delete();
        return $res;
    }

    /*
     * 20230424可以用通用方法的取消逻辑
     */

    public function doCancel($id) {
        return self::getInstance($id)->cancel();
    }

    /**
     * 订单软删
     * @return type
     */
    public function delete() {
        //删除前
        if (method_exists(__CLASS__, 'extraPreDelete')) {
            $this->extraPreDelete();      //注：id在preSaveData方法中生成
        }
        //删除
        $data['is_delete'] = 1;
        $res = $this->commUpdate($data);
        //删除后
        if (method_exists(__CLASS__, 'extraAfterDelete')) {
            $this->extraAfterDelete();      //注：id在preSaveData方法中生成
        }
        return $res;
    }

    /**
     * 20220609 真的删
     */
    public function destroy() {
        $info = self::mainModel()->where('id', $this->uuid)->find();
        if ($info['company_id'] != session(SESSION_COMPANY_ID)) {
            throw new Exception('访问入口与当前订单不匹配');
        }
        if (!$info['is_delete']) {
            throw new Exception('订单未删不可销毁' . $this->uuid);
        }
        $res = self::mainModel()->where('id', $this->uuid)->delete();
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
     * 20230417：校验订单类型
     * @param type $time
     */
    public static function checkFinanceTimeLockWithOrderType($time, $orderType) {
        if (in_array($orderType, ['bao'])) {
            self::checkFinanceTimeLock($time);
        }
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
    public static function statementOrders($con, $startTime, $endTime) {
        $con[] = ['create_time', '>=', $startTime];
        $con[] = ['create_time', '<=', $endTime];
        $res = self::mainModel()->where($con)->field('id as order_id')->select();
        return $res ? $res->toArray() : [];
    }

    /**
     * 根据价格key计算价格
     */
    public function prizeKeyGetPrize($prizeKey) {
        // $goodsId    = $this->fGoodsId();
//        $con[] = ['order_id','=',$this->uuid];
//        Debug::debug( '$goodsLists的$con', $con );
//        $goodsLists = OrderGoodsService::mainModel()->where($con)->field('goods_id,amount')->select();
//        
        //$goodsLists = $this->getOrderGoods();
        $goodsLists = $this->objAttrsList('orderGoods');
        $role = GoodsPrizeKeyService::keyBelongRole($prizeKey);
        Debug::debug('prizeKeyGetPrize的$prizeKey', $prizeKey);
        Debug::debug('prizeKeyGetPrize的$role', $role);
        if ($role == 'buyer') {
            //是否最终价格
            $isGrandPrize = GoodsPrizeTplService::isMainKeyFinal($prizeKey);
            Debug::debug('$isGrandPrize', $isGrandPrize);
            // 多商品的数组
            $buyerPrize = GoodsPrizeService::goodsArrGetBuyerPrize($goodsLists, $prizeKey);
            Debug::debug('$goodsLists', $goodsLists);
            Debug::debug('$buyerPrize', $buyerPrize);
            if ($isGrandPrize) {
//                $payPrize   = OrderService::getInstance( $this->uuid )->fPayPrize();                
//                Debug::debug( '$payPrize', $payPrize );
                //20210407修bug  已付金额=$prizeKey取全部子key，再查全部子key的已结。
                $childKeys = GoodsPrizeKeyService::getChildKeys($prizeKey, true);    //返回一个key一维数组
                $payPrize = FinanceStatementOrderService::hasSettleMoney($this->uuid, $childKeys);
                Debug::debug('$payPrize', $payPrize);
                $buyerPrize = floatval($buyerPrize) - floatval($payPrize);
            }
            $finalPrize = $buyerPrize;
        }
        //供应商
        if ($role == 'seller') {
            //是否最终价格
            $isGrandPrize = GoodsPrizeTplService::isPrizeKeyFinal($prizeKey);
            // $sellerPrize     = GoodsPrizeService::keysPrize( $goodsId , $prizeKey );
            $sellerPrize = GoodsPrizeService::goodsArrGetKeysPrize($goodsLists, $prizeKey);
            if ($isGrandPrize) {
                //无缓存取价格
                $orderInfo = OrderService::getInstance($this->uuid)->get(0);
                $payPrize = Arrays::value($orderInfo, 'outcome_prize');
                $sellerPrize = $sellerPrize - abs($payPrize);
            }
            $finalPrize = -1 * $sellerPrize;
        }
        //推荐人，业务员
        if ($role == "rec_user" || $role == "busier") {
            // $finalPrize = GoodsPrizeService::keysPrize( $goodsId , $prizeKey );
            $finalPrize = GoodsPrizeService::goodsArrGetKeysPrize($goodsLists, $prizeKey);
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
    public static function calOrderStatus($orderData) {
        $isCancel = Arrays::value($orderData, 'is_cancel');
        $isComplete = Arrays::value($orderData, 'is_complete');
        if ($isCancel) {
            //已完成：关闭；未完成：取消中
            return $isComplete ? 'close' : 'cancel';
        }
        if ($isComplete) {
            //订单完成
            return 'finish';
        }
        $prePrize = Arrays::value($orderData, 'pre_prize', 0);
        $payPrize = Arrays::value($orderData, 'pay_prize', 0);
        //待支付
        if ($prePrize > $payPrize) {
            return 'needpay';
        }
        //待发货
        if (!Arrays::value($orderData, 'has_deliver')) {
            return 'toDeliver';
        }
        //待收货
        if (!Arrays::value($orderData, 'has_receive')) {
            return 'toReceive';
        }
        return 'processing';    //订单进行中
    }

    /**
     * 0830：接单
     */
    public function accept() {
        $data['has_accept'] = 1;
        $data['accept_user_id'] = session(SESSION_USER_ID);
        $data['accept_time'] = date('Y-m-d H:i:s');
        return $this->update($data);
    }

    /**
     * 取消接单
     */
    public function cancelAccept() {
        $data['has_accept'] = 0;
        $data['accept_user_id'] = '';
        $data['accept_time'] = null;
        return $this->update($data);
    }

    /**
     * 20230530:获取额外存储的数据信息
     * @param type $orderType
     */
    public static function getETable($orderType) {
        // 驼峰转下划线
        $prefix = config('database.prefix');
        $tableName = $prefix . 'order_e_' . Strings::uncamelize($orderType);
        return DbOperate::isTableExist($tableName) ? $tableName : '';
    }

    public static function getEService($orderType) {
        $tableName = self::getETable($orderType);
        if (!$tableName) {
            return '';
        }

        $service = DbOperate::getService($tableName);
        return $service;
    }

    /**
     * TODO临时：提取未绑定用户的订单
     * @param type $circuitBusId
     * @return type
     */
    public static function circuitBusNobindArr($circuitBusId) {
        $con[] = ['circuit_bus_id', 'in', $circuitBusId];
        $con[] = ['is_cancel', '=', 0];
        $userIds = self::where($con)->column('user_id');
        // 提取未绑用户
        $conNo[] = ['user_id', 'in', $userIds];
        $bindUids = WechatWePubFansUserService::where($conNo)->column('user_id');
        $noBindUids = array_diff($userIds, $bindUids);
        $con[] = ['user_id', 'in', $noBindUids];

        $ids = self::where($con)->column('id');
        return self::extraDetails($ids);
    }

    /*
     * 20230809:获取批量账单id，用于合并支付
     */
    public static function statementGenerate($ids) {
        $method = __METHOD__;
        return Functions::anti($method, $ids, function ($ids) {
                    $con[] = ['order_id', 'in', $ids];
                    $con[] = ['has_settle', '=', 0];
                    $statementOrderIds = FinanceStatementOrderService::mainModel()->where($con)->column('id');
                    $statementId = FinanceStatementOrderService::getStatementIdWithGenerate($statementOrderIds, true);
                    return FinanceStatementService::getInstance($statementId)->get(MASTER_DATA);
                });
    }
}
