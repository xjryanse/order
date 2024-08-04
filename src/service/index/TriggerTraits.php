<?php

namespace xjryanse\order\service\index;

use app\circuit\service\CircuitBusService;
use app\bao\service\BaoApplyService;
use xjryanse\customer\service\CustomerUserService;
use xjryanse\goods\service\GoodsService;
use xjryanse\finance\service\FinanceStatementService;
use xjryanse\finance\service\FinanceStatementOrderService;
use xjryanse\order\service\OrderPassengerService;
use xjryanse\order\service\OrderGoodsService;
use xjryanse\order\service\OrderFlowNodeService;
use xjryanse\order\service\OrderChangeLogService;
use xjryanse\system\service\SystemCompanyService;
use xjryanse\system\service\SystemCompanyDeptService;
use xjryanse\user\service\UserService;
use xjryanse\user\service\UserAccountLogService;
use xjryanse\user\logic\ScoreLogic;
use xjryanse\logic\Strings;
use xjryanse\logic\DataCheck;
use xjryanse\logic\DbOperate;
use xjryanse\logic\Arrays;
use xjryanse\logic\Debug;
// 20220906兼容前端，排车页面，是否有更优方案？？
// use xjryanse\logic\Device;
use Exception;
/* * 临时，需要拆解 - 20211115 ** */
use app\order\service\OrderBaoBusService;

/**
 * 分页复用列表
 */
trait TriggerTraits {

    /**
     * 
     * @throws Exception
     */
    public function extraPreDelete() {
        self::checkTransaction();
        $info = $this->get();
        if ($info['plan_start_time']) {
            self::checkFinanceTimeLockWithOrderType($info['plan_start_time'], $info['order_type']);
        }
        //对账单要先清
        FinanceStatementService::clearOrderNoDeal($this->uuid);
        //才能清对账单明细
        FinanceStatementOrderService::clearOrderNoDeal($this->uuid);

        $con[] = ['order_id', '=', $this->uuid];
        $res = FinanceStatementOrderService::mainModel()->master()->where($con)->count(1);
        if ($res) {
            throw new Exception('该订单有收付款账单，不可删除,订单号' . $this->uuid);
        }
        $fromTable = self::mainModel()->getTable();
        $userAccountHasLog = UserAccountLogService::hasLog($fromTable, $this->uuid);
        if ($userAccountHasLog) {
            throw new Exception('该订单有用户账户记录' . $userAccountHasLog['id'] . '，不可删除');
        }
        // 包车订单适用
        $baoBusIds = OrderBaoBusService::mainModel()->master()->where($con)->column('id');
        if ($baoBusIds) {
            //删除订单用车
            foreach ($baoBusIds as $baoBusId) {
                OrderBaoBusService::getInstance($baoBusId)->delete();
            }
        }
        //20220610：有后向订单的不可删；有前向订单不影响；
        $conf[] = ['is_delete', '=', 0];
        $conf[] = ['pre_order_id', '=', $this->uuid];
        $hasAfterOrder = self::mainModel()->where($conf)->count();
        if ($hasAfterOrder) {
            throw new Exception('该订单有后向订单，不可删');
        }

        // 拼团订单适用
        $passengerIds = OrderPassengerService::mainModel()->master()->where($con)->column('id');
        if ($passengerIds) {
            //删除订单用车
            foreach ($passengerIds as $passengerId) {
                OrderPassengerService::getInstance($passengerId)->delete();
            }
        }
    }

    /**
     * 删除价格数据
     */
    public function extraAfterDelete() {
        self::checkTransaction();
        // 删流程
        $con[] = ['order_id', '=', $this->uuid];
        OrderFlowNodeService::mainModel()->where($con)->delete();
        // 20220601拼团对应单置为未提交排班
        CircuitBusService::mainModel()->where('bao_order_id', $this->uuid)->update(['bao_order_id' => '']);
        //20230418:用车申请单对应设置为未提交排班
        BaoApplyService::mainModel()->where('bao_order_id', $this->uuid)->update(['bao_order_id' => '']);
        // 删商品
        $goodsIds = OrderGoodsService::ids($con);
        foreach ($goodsIds as $id) {
            //为了使用触发器20210802
            OrderGoodsService::getInstance($id)->delete();
        }
    }

    /**
     * 额外输入信息
     */
    public static function extraAfterSave(&$data, $uuid) {
        OrderFlowNodeService::lastNodeFinishAndNext($uuid);
        //②写入订单子表
        $subService = self::getSubService($data['order_type']);
        if ($data['order_type'] && class_exists($subService)) {
            $data['id'] = $uuid;
            $subService::getInstance($uuid)->save($data);
        }

        // 20230530:eService:准备替代上述subService方法
        $eService = self::getEService($data['order_type']);
        if ($eService && class_exists($eService)) {
            $eService::save($data);
        }

        return $data;
    }

    /**
     * 额外输入信息
     */
    public static function extraAfterUpdate(&$data, $uuid) {
        Debug::debug(__CLASS__ . __FUNCTION__, $data);
        $info = self::getInstance($uuid)->get();
        //尝试流程节点的更新
        OrderFlowNodeService::lastNodeFinishAndNext($uuid);
        //②写入订单子表
        $subService = self::getSubService($info['order_type']);
        if ($info['order_type'] && class_exists($subService)) {
            $subService::getInstance($uuid)->update($data);
        }
        // 20230530:eService:准备替代上述subService方法
        $eService = self::getEService($info['order_type']);
        if ($eService && class_exists($eService)) {
            $eService::getInstance($uuid)->update($data);
        }

        //20211215订单取消，解绑座位
        if ($data['is_cancel']) {
            $passengers = OrderPassengerService::mainModel()->where('order_id', $uuid)->select();
            foreach ($passengers as &$v) {
                OrderPassengerService::getInstance($v['id'])->update(['is_ref' => 1]);
            }
        } else {
            //判定订单完成，给下单人赠送积分的触发动作
            ScoreLogic::score($info['user_id']);
        }

        //20220318：TODO更优化的功能
        //20220615：取消包可否？？？
        // 20240215:明细编辑后，再点击包车下单，会出现账单明细被清理的bug
        // if (isset($data['order_prize']) && $info['order_type'] == 'bao') {
        //     FinanceStatementOrderService::updateOrderMoney($uuid, $data['order_prize']);
        // }
        //20220630??
        self::getInstance($uuid)->orderDataSync();
        return $data;
    }

    /**
     * 
     * @param type $data
     * @param type $uuid
     * @return type
     */
    public static function ramPreUpdate(&$data, $uuid) {
        self::queryCountCheck(__METHOD__);
    }    
    
    /**
     * 20220619
     * @param type $data
     * @param type $uuid
     * @return type
     */
    public static function ramAfterUpdate(&$data, $uuid) {
        Debug::debug(__CLASS__ . __FUNCTION__, $data);
        $info = self::getInstance($uuid)->get();
        //TODO:去除事务校验再开启尝试流程节点的更新
        // OrderFlowNodeService::lastNodeFinishAndNext($uuid);
        //②写入订单子表
        $subService = self::getSubService($info['order_type']);
        if ($info['order_type'] && class_exists($subService)) {
            $subService::getInstance($uuid)->updateRam($data);
        }

        // 20230530:eService:准备替代上述subService方法
        $eService = self::getEService($info['order_type']);
        if ($eService && class_exists($eService)) {
            $eService::getInstance($uuid)->updateRam($data);
        }

        //20211215订单取消，解绑座位
        if (isset($data['is_cancel']) && $data['is_cancel']) {
            $passengers = OrderPassengerService::mainModel()->where('order_id', $uuid)->select();
            foreach ($passengers as &$v) {
                OrderPassengerService::getInstance($v['id'])->updateRam(['is_ref' => 1]);
            }
        }

        //判定订单完成，给下单人赠送积分的触发动作
        ScoreLogic::score($info['user_id']);
        //20220622:更新订单的关联账单(含收付)
        // 20240121：发现捣乱
        self::getInstance($uuid)->updateFinanceStatementRam();
//        //20220622:增加同步订单数据
//        self::getInstance($uuid)->orderDataSyncRam();

        return $data;
    }
    
    /**
     * 
     * @param type $data
     * @param type $uuid
     * @return type
     */
    public static function ramAfterSave(&$data, $uuid) {
        $orderType = Arrays::value($data, 'order_type');
        
        //OrderFlowNodeService::lastNodeFinishAndNext($uuid);
        // 20240121:逐步淘汰，不适应了
        // 20240411:增加pin淘汰
        if($orderType!='bao' && $orderType!='pin'){
            //②写入订单子表
            $subService = self::getSubService($data['order_type']);
            if ($data['order_type'] && class_exists($subService)) {
                $data['id'] = $uuid;
                $subService::getInstance($uuid)->saveRam($data);
            }
            // 20230530:eService:准备替代上述subService方法
            $eService = self::getEService($data['order_type']);
            if ($eService && class_exists($eService)) {
                $eService::saveRam($data);
            }
        }
        
        /*
          //20220622:更新订单的关联账单(含收付)
          self::getInstance($uuid)->updateFinanceStatementRam();
          //20220622:增加同步订单数据
          self::getInstance($uuid)->orderDataSyncRam();
         */

        return $data;
    }

    /**
     * 20220622优化性能
     * @param type $data
     * @param type $uuid
     * @return type
     * @throws Exception
     */
    public static function ramPreSave(&$data, $uuid) {
        DataCheck::must($data, ['user_id']);
        // self::checkFinanceTimeLock(Arrays::value($data, 'plan_start_time'));
        self::checkFinanceTimeLockWithOrderType(Arrays::value($data, 'plan_start_time'), Arrays::value($data, 'order_type'));
        // 防止未绑定账号的用户下单，导致无法联系到人,程序应在下单前，让用户进行绑定动作
        UserService::getInstance($data['user_id'])->checkUserPhone();
        //20210812，测到金额bug注释:由::order方法控制
        $goodsId = Arrays::value($data, 'goods_id');
        if ($goodsId) {
            $goodsInfo = GoodsService::getInstance($goodsId)->get(MASTER_DATA);
            $goodsName = Arrays::value($goodsInfo, 'goods_name');
            if (Arrays::value($goodsInfo, 'goods_status') != 'onsale') {
                throw new Exception('商品' . $goodsName . '已经销售或未上架');
            }
            // 20220213可外部传，兼容支付账单体现订单概要信息
            if (!isset($data['goods_name']) || !$data['goods_name']) {
                $data['goods_name'] = $goodsName;
            }
            $data['goods_table'] = Arrays::value($goodsInfo, 'goods_table');
            //兼容开发需求（多记录使用同商品下单）
            $data['goods_table_id'] = Arrays::value($data, 'goods_table_id') ?: Arrays::value($goodsInfo, 'goods_table_id');
            $data['seller_customer_id'] = Arrays::value($data, 'seller_customer_id') ?: Arrays::value($goodsInfo, 'customer_id');
            $data['seller_user_id'] = Arrays::value($data, 'seller_user_id') ?: Arrays::value($goodsInfo, 'seller_user_id');
            $data['order_type'] = Arrays::value($goodsInfo, 'sale_type');
            $data['shop_id'] = Arrays::value($goodsInfo, 'shop_id');
        }
        //客户和用户进行绑定,方便下次下单
        if (Arrays::value($data, 'customer_id') && Arrays::value($data, 'user_id')) {
            CustomerUserService::bind($data['customer_id'], $data['user_id']);
        }
        return $data;
    }

    /**
     * 20220622
     * @throws Exception
     */
    public function ramPreDelete() {
        // 20231031:验证账单是否能删
        $this->checkCanDelete();

        $info = $this->get();
        $classStr = self::orderTypeClass($info['order_type']);
        // 20231123:增加method_exists($classStr, 'uniDelete')
        if (class_exists($classStr) && method_exists($classStr, 'uniDelete')) {
            //20220623:关联删除
            $classStr::getInstance($this->uuid)->uniDelete();
        }

        if ($info['plan_start_time']) {
            // self::checkFinanceTimeLock($info['plan_start_time']);
            self::checkFinanceTimeLockWithOrderType($info['plan_start_time'], $info['order_type']);
        }
        //对账单要先清
        FinanceStatementService::clearOrderNoDealRam($this->uuid);
        //才能清对账单明细
        FinanceStatementOrderService::clearOrderNoDealRam($this->uuid);

        $con[] = ['order_id', '=', $this->uuid];
        $con[] = ['has_settle', '=', 1];
        $res = FinanceStatementOrderService::mainModel()->master()->where($con)->count(1);
        if ($res) {
            throw new Exception('该订单有收付款账单，不可删除,订单号' . $this->uuid);
        }
        $fromTable = self::mainModel()->getTable();
        $userAccountHasLog = UserAccountLogService::hasLog($fromTable, $this->uuid);
        if ($userAccountHasLog) {
            throw new Exception('该订单有用户账户记录' . $userAccountHasLog['id'] . '，不可删除');
        }
        $conB[] = ['order_id', '=', $this->uuid];
        // 包车订单适用
        $baoBusIds = OrderBaoBusService::mainModel()->master()->where($conB)->column('id');
        if ($baoBusIds) {
            //删除订单用车
            foreach ($baoBusIds as $baoBusId) {
                OrderBaoBusService::getInstance($baoBusId)->deleteRam();
            }
        }
        //20220610：有后向订单的不可删；有前向订单不影响；
        $conf[] = ['is_delete', '=', 0];
        $conf[] = ['pre_order_id', '=', $this->uuid];
        $afterOrderId = self::mainModel()->where($conf)->value('id');
        if ($afterOrderId && !DbOperate::isGlobalDelete($fromTable, $afterOrderId)) {
            throw new Exception('该订单有后向订单' . $afterOrderId . '，不可删');
        }
        $conPin[] = ['order_id', '=', $this->uuid];
        // 拼团订单适用
        $passengerIds = OrderPassengerService::mainModel()->master()->where($conPin)->column('id');
        if ($passengerIds) {
            //删除订单用车
            foreach ($passengerIds as $passengerId) {
                OrderPassengerService::getInstance($passengerId)->deleteRam();
            }
        }
    }

    /**
     * 删除价格数据
     */
    public function ramAfterDelete() {
        // 删流程
        $con[] = ['order_id', '=', $this->uuid];
        $nodeLists = self::getInstance($this->uuid)->objAttrsList('orderFlowNode');
        foreach ($nodeLists as $v) {
            OrderFlowNodeService::getInstance($v['id'])->deleteRam();
        }

        // 20220601拼团对应单置为未提交排班
        $cone[] = ['bao_order_id', '=', $this->uuid];
        $circuitBusId = CircuitBusService::mainModel()->where($cone)->value('id');
        if ($circuitBusId) {
            CircuitBusService::getInstance($circuitBusId)->updateRam(['bao_order_id' => '']);
            $info = $this->get();
            self::getInstance($info['pre_order_id'])->deleteRam();
        }
        //20230418:用车申请单对应设置为未提交排班
        BaoApplyService::mainModel()->where($cone)->update(['bao_order_id' => '']);
        // 删商品
        $goodsIds = OrderGoodsService::ids($con);
        foreach ($goodsIds as $id) {
            //为了使用触发器20210802
            OrderGoodsService::getInstance($id)->deleteRam();
        }
    }

    /**
     * 额外输入信息
     */
    public static function extraPreSave(&$data, $uuid) {
        DataCheck::must($data, ['user_id']);
        // self::checkFinanceTimeLock(Arrays::value($data, 'plan_start_time'));
        self::checkFinanceTimeLockWithOrderType(Arrays::value($data, 'plan_start_time'), Arrays::value($data, 'order_type'));
        UserService::getInstance($data['user_id'])->checkUserPhone();
        //20210812，测到金额bug注释:由::order方法控制
        $goodsId = Arrays::value($data, 'goods_id');
        if ($goodsId) {
            $goodsInfo = GoodsService::getInstance($goodsId)->get(MASTER_DATA);
            $goodsName = Arrays::value($goodsInfo, 'goods_name');
            if (Arrays::value($goodsInfo, 'goods_status') != 'onsale') {
                throw new Exception('商品' . $goodsName . '已经销售或未上架');
            }
            // 20220213可外部传，兼容支付账单体现订单概要信息
            if (!isset($data['goods_name']) || !$data['goods_name']) {
                $data['goods_name'] = $goodsName;
            }
            $data['goods_table'] = Arrays::value($goodsInfo, 'goods_table');
            //兼容开发需求（多记录使用同商品下单）
            $data['goods_table_id'] = Arrays::value($data, 'goods_table_id') ?: Arrays::value($goodsInfo, 'goods_table_id');
            $data['seller_customer_id'] = Arrays::value($data, 'seller_customer_id') ?: Arrays::value($goodsInfo, 'customer_id');
            $data['seller_user_id'] = Arrays::value($data, 'seller_user_id') ?: Arrays::value($goodsInfo, 'seller_user_id');
            $data['order_type'] = Arrays::value($goodsInfo, 'sale_type');
            $data['shop_id'] = Arrays::value($goodsInfo, 'shop_id');
        }
        //客户和用户进行绑定,方便下次下单
        if ($data['customer_id'] && $data['user_id']) {
            CustomerUserService::bind($data['customer_id'], $data['user_id']);
        }
        //
        Debug::debug(__CLASS__ . __FUNCTION__, $data);
        // 20240309:增加订单号
        $data['order_sn'] = self::newSn($data);
        return $data;
    }
    /**
     * 20240309:获取新的包车单号
     * @param type $data    下单数据：包含订单类型；部门等信息
     * @return type
     */
    public static function newSn($data){
        $orderType  = Arrays::value($data, 'order_type');
        $deptId     = Arrays::value($data, 'dept_id');
        $createTime = Arrays::value($data, 'create_time') ? : date('Y-m-d H:i:s');
        
        // 
        $companyId  = Arrays::value($data, 'company_id') ? : session(SESSION_COMPANY_ID);
        $prefix     = SystemCompanyService::getInstance($companyId)->fCompanyNo();

        $deptCode   = $deptId 
                ? SystemCompanyDeptService::getInstance($deptId)->fDeptCode()
                :'';

        // $oType = ucfirst(mb_substr($orderType, 0, 1));
        // 包车；拼团
        $oTypeCovArr = ['bao'=>'B','pin'=>'P'];
        $oType = Arrays::value($oTypeCovArr, $orderType) ;

        $date = date('Ymd',strtotime($createTime));
        // 提取本次下单的流水号
        $serCode = self::dailyThisSnNo($data);
        // 20240309:观察一下
//        if($orderType == 'pin'){
//            $date .= Strings::rand(4);
//        }
        
        return $prefix.$deptCode.$oType.$date.$serCode;
    }

    public static function dailyLastSn($data){
        $orderType  = Arrays::value($data, 'order_type');
        $deptId     = Arrays::value($data, 'dept_id');
        $createTime = Arrays::value($data, 'create_time') ? : date('Y-m-d H:i:s');

        $con        = [];
        $con[]      = ['order_type','=',$orderType];
        if($deptId){
            $con[]      = ['dept_id','=',$deptId];
        }
        $con[]      = ['order_type','=',$orderType];

        $con[]      = ['create_time','>=',date('Y-m-d 00:00:00',strtotime($createTime))];
        $con[]      = ['create_time','<=',date('Y-m-d 23:59:59',strtotime($createTime))];

        $lastSn = self::where($con)->order('order_sn desc')->value('order_sn');
        return $lastSn;
    }
    /**
     * 本次流水号
     * @param type $data
     * @return type
     */
    public static function dailyThisSnNo($data, $length = 4){
        $lastSn = self::dailyLastSn($data);
        // Debug::dump($data);
        $number = $lastSn ? intval(substr($lastSn,-4)) : 0;
        return Strings::preKeepLength($number + 1, $length);
    }
    /*
    public static function testOp($param){
        $lists = self::where()->whereNull('order_sn')->order('create_time')->limit(1000)->select();
        foreach($lists as $v){
            $oSn = self::newSn($v);
            self::getInstance($v['id'])->doUpdateRam(['order_sn'=>$oSn]);
            DbOperate::dealGlobal();
        }        
    }
     */
    /**
     * 
     * @param type $data
     * @param type $uuid
     * @return type
     * @throws Exception
     */
    public static function extraPreUpdate(&$data, $uuid) {
        self::checkTransaction();
        $info = self::getInstance($uuid)->get();
        // 20230815:获取有变化的内容
        // 20230815:校验增加字段判断
        $diffInfo = Arrays::diffArr($info, $data);
        $diffKeys = array_keys($diffInfo);
        //财务锁账字段
        $fLockKeys = ['customer_id', 'user_id', 'dept_id', 'shop_id', 'plan_start_time'
            , 'order_type', 'goods_id', 'order_prize', 'seller_user_id', 'seller_customer_id'];
        if (array_intersect($diffKeys, $fLockKeys)) {
            self::checkFinanceTimeLockWithOrderType(Arrays::value($info, 'plan_start_time'), $info['order_type']);
            if (Arrays::value($data, 'plan_start_time')) {
                self::checkFinanceTimeLockWithOrderType(Arrays::value($data, 'plan_start_time'), $info['order_type']);
            }
        }

        if (isset($data['is_cancel']) && $data['is_cancel'] && $data['is_cancel'] != $info['is_cancel']) {
            if ($info['is_complete']) {
                throw new Exception('已结订单不可取消' . $uuid);
            }
            if (!isset($data['cancel_by'])) {
                throw new Exception('未指定取消人cancel_by' . $uuid);
            }
        }
        //②写入订单子表
        $subService = self::getSubService($info['order_type']);
        Debug::debug('$subService', $subService);
        if ($info['order_type'] && class_exists($subService)) {
            $subService::getInstance($uuid)->update($data);
        }
        OrderChangeLogService::log('orderChange', $uuid, '', $diffInfo);
        return $data;
    }

    /**
     * 验证账单是否能删
     */
    protected function checkCanDelete() {
        
    }
}
