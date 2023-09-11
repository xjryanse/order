<?php

namespace xjryanse\order\service\index;

use xjryanse\finance\service\FinanceStatementOrderService;
use xjryanse\order\service\OrderGoodsService;
use xjryanse\system\service\SystemFileService;
use xjryanse\logic\Arrays;
use xjryanse\logic\Arrays2d;
use xjryanse\logic\Cachex;
// 20220906兼容前端，排车页面，是否有更优方案？？

/**
 * 分页复用列表
 */
trait PaginateTraits{

    /**
     * 带了一些详情信息的订单列表，一般用于用户端口前端显示
     * @param type $con
     * @param type $order
     * @param type $perPage
     * @param type $having
     * @param type $field
     */
    public static function paginateWithInfo($con = [], $order = '', $perPage = 10, $having = '', $field = "*") {
        $lists = self::paginate($con, $order, $perPage, $having, 'id');
        $cond = [];
        $cond[] = ['order_id', 'in', array_column($lists['data'], 'id')];
        $orderGoodsListsRaw = OrderGoodsService::mainModel()->alias('a')
                ->join('w_goods b', 'a.goods_id = b.id')->where($cond)
                ->field("a.id,a.order_id,a.goods_id,a.goods_name,a.amount,a.unit_prize,a.totalPrize,b.goods_pic")
                ->select();
        $orderGoodsLists = $orderGoodsListsRaw ? $orderGoodsListsRaw->toArray() : [];
        //取图片
        foreach ($orderGoodsLists as &$v) {
            $picId = $v['goods_pic'];
            $v['goodsPic'] = Cachex::funcGet('FileData_' . $picId, function () use ($picId) {
                        return $picId && SystemFileService::mainModel()->where('id', $picId)->field('id,file_path,file_path as rawPath')->find() ?: [];
                    });
        }
        // 取账单
        $condStatement = [];
        $condStatement[] = ['order_id', 'in', array_column($lists['data'], 'id')];
        $condStatement[] = ['has_settle', '=', 0];
        $condStatement[] = ['statement_cate', '=', 'buyer'];
        $statementLists = FinanceStatementOrderService::mainModel()->where($condStatement)->field('id,need_pay_prize,order_id')->select();
        // 账单列表 
        // $statement = Arrays2d::fieldSetKey($statementLists ? $statementLists->toArray() : [], 'order_id');

        foreach ($lists['data'] as &$v) {
            // 拼接订单商品
            $orderId = $v['id'];
            $v['orderGoods'] = array_filter($orderGoodsLists, function ($orderGoods) use ($orderId) {
                return $orderGoods['order_id'] == $orderId;
            });
            //客户应支付账单
            foreach ($statementLists as $statementItem) {
                if ($statementItem['order_id'] == $v['id']) {
                    $v['buyerNeedPayStatements'][] = $statementItem;
                }
            }
            $v['plan_start_time'] = date('Y-m-d H:i', strtotime($v['plan_start_time']));
            $v['plan_finish_time'] = date('Y-m-d H:i', strtotime($v['plan_finish_time']));

            //减少传输带宽
            $lastFlowNode = $v['lastFlowNode'] ?: [];
            $v['lastFlowNode'] = Arrays::getByKeys($lastFlowNode, ['id', 'node_key', 'node_name', 'operate_role', 'flow_status', 'create_time']);
        }

        $con1[] = ['status', '=', 1];
        $con1[] = ['company_id', '=', session(SESSION_COMPANY_ID)];
        $con1[] = ['is_delete', '=', 0];
        $conM = array_merge($con, $con1);
        foreach ($conM as $key => $value) {
            if ($value[0] == 'order_status') {
                unset($conM[$key]);
            }
        }

        $statics = self::mainModel()->where($conM)->group('order_status')->field('count(1) as amount,order_status')->select();
        $lists['statics'] = Arrays2d::toKeyValue($statics ? $statics->toArray() : [], 'order_status', 'amount');
        return $lists;
    }
    
        /**
     * 20230324:不带数据权限（TODO更好？？）
     * 暂时给后台的订单列表用
     * @param type $con
     * @param type $order
     * @param type $perPage
     * @param type $having
     * @param type $field
     * @param type $withSum
     * @return type、
     */
    public static function paginateForAdmin($con = [], $order = '', $perPage = 10, $having = '', $field = "*", $withSum = false) {
        //默认带数据权限
        $conAll = array_merge($con, self::commCondition());
        // 查询条件单拎；适用于后台管理（客户权限，业务员权限）
        return self::paginateRaw($conAll, $order, $perPage, $having, $field, $withSum);
    }
}
