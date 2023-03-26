/*
 Navicat Premium Data Transfer

 Source Server         : 谢-云tenancy
 Source Server Type    : MySQL
 Source Server Version : 80018
 Source Host           : rm-bp1w1nmd4576u594cyo.mysql.rds.aliyuncs.com:3306
 Source Schema         : tenancy_xiesemi

 Target Server Type    : MySQL
 Target Server Version : 80018
 File Encoding         : 65001

 Date: 26/03/2023 10:03:37
*/

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ----------------------------
-- Table structure for w_order
-- ----------------------------
DROP TABLE IF EXISTS `w_order`;
CREATE TABLE `w_order`  (
  `id` char(19) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL,
  `company_id` int(11) NULL DEFAULT NULL COMMENT '公司id',
  `dept_id` char(32) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT '' COMMENT '归属部门id',
  `group_id` char(19) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT '' COMMENT '分组id；一般用于批量下单',
  `shop_id` char(19) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT '' COMMENT '店铺id',
  `goods_id` char(19) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL COMMENT '商品id（单商品可用）',
  `amount` decimal(10, 2) NULL DEFAULT NULL COMMENT '商品数量（单商品冗余存）',
  `order_desc` text CHARACTER SET utf8 COLLATE utf8_general_ci NULL COMMENT '订单描述[简单的概述，可用于后台人员拆单]',
  `goods_name` text CHARACTER SET utf8 COLLATE utf8_general_ci NULL COMMENT '商品名称：goods表来源，或外传',
  `goods_cate` varchar(32) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT '' COMMENT '[冗]商品类型：用于筛选',
  `goods_table` varchar(255) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT '' COMMENT '商品原始表',
  `goods_table_id` char(19) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT '' COMMENT '商品原始表id',
  `from_station_id` char(19) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL COMMENT '发货站点（上车站点）',
  `to_station_id` char(19) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL COMMENT '收货站点（下车站点）',
  `tour_time_id` char(19) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL COMMENT '20230303:本系统旅游团次',
  `circuit` text CHARACTER SET utf8 COLLATE utf8_general_ci NULL COMMENT '线路的描述，以stations表为基础',
  `circuit_bus_id` char(19) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT '' COMMENT '包车线路id',
  `tour_no` varchar(32) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL COMMENT '[20220908]旅行社用团号',
  `persons` int(11) NULL DEFAULT 0 COMMENT '订单人数( 一般是包车用 )',
  `kilometre` decimal(10, 2) NULL DEFAULT NULL COMMENT '公里数',
  `plan_start_time` datetime(0) NULL DEFAULT NULL COMMENT '订单开始时间（包车用）',
  `plan_finish_time` datetime(0) NULL DEFAULT NULL COMMENT '订单结束时间（包车用）',
  `number` int(11) NULL DEFAULT 1 COMMENT '商品数量',
  `val` text CHARACTER SET utf8 COLLATE utf8_general_ci NULL COMMENT '订单json',
  `order_type` varchar(32) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT 'normal' COMMENT '订单类型：tm_auth；tm_rent；tm_buy；os_buy；公证noary',
  `sub_order_type` varchar(32) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL COMMENT '子类型：如包车：单程往返单日多日',
  `role_type` varchar(32) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT 'personal' COMMENT '下单客户类型：customer；personal',
  `order_sn` varchar(64) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL COMMENT '订单号',
  `pre_order_id` char(19) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL COMMENT '前序订单id',
  `seller_customer_id` char(19) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL COMMENT '销售客户id（适用于中介平台）',
  `seller_user_id` char(19) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL COMMENT '销售用户id',
  `customer_id` char(19) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT '' COMMENT '下单客户id，customer表id',
  `customer_dept_id` char(19) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL COMMENT '下单客户部门id',
  `bil_company` varchar(64) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL COMMENT '[20220514]开票单位名称',
  `bil_prize` decimal(10, 2) NULL DEFAULT NULL COMMENT '[20220608]开票金额',
  `user_id` char(19) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT '' COMMENT '下单用户id，user表id',
  `user_realname` varchar(32) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL COMMENT '[0908手输的]，蛮存',
  `custUser` char(19) CHARACTER SET utf8 COLLATE utf8_general_ci GENERATED ALWAYS AS (if(`customer_id`,`customer_id`,`user_id`)) VIRTUAL COMMENT '20220430用于权限显示；临时，有否更佳？' NULL,
  `rec_user_id` char(19) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT '' COMMENT '推荐人id，user表id',
  `cover_pic` varchar(32) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL COMMENT '订单列表图标，单图',
  `isNeedPay` tinyint(1) GENERATED ALWAYS AS (if((`is_cancel` = 1),0,if((`pay_prize` < `pre_prize`),1,0))) VIRTUAL COMMENT '计算属性:是否等待付款状态' NULL,
  `isFullPay` tinyint(1) GENERATED ALWAYS AS (if((`pay_prize` >= `order_prize`),1,0)) VIRTUAL COMMENT '计算属性:是否全部付清' NULL,
  `order_status` varchar(32) CHARACTER SET utf8 COLLATE utf8_general_ci GENERATED ALWAYS AS (if(`is_cancel`,if(`is_complete`,_utf8mb4'close',_utf8mb4'cancel'),if(`is_complete`,_utf8mb4'finish',if((`pre_prize` > `pay_prize`),_utf8mb4'needpay',if((`has_deliver` = 0),_utf8mb4'toDeliver',if((`has_receive` = 0),_utf8mb4'toReceive',_utf8mb4'processing')))))) VIRTUAL COMMENT '订单状态：计算\r\n\r\n订单状态：\r\nneedpay待支付\r\nprocessing进行中、\r\nfinish已完成、\r\nclose已关闭' NULL,
  `source` varchar(32) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT '' COMMENT '来源：admin；wePub',
  `has_accept` tinyint(1) NULL DEFAULT 0 COMMENT '0830：后台是否已接单',
  `accept_user_id` char(19) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL COMMENT '0830:接单人',
  `accept_time` datetime(0) NULL DEFAULT NULL COMMENT '0830:接单时间',
  `order_by` tinyint(1) NULL DEFAULT 0 COMMENT '0830：由谁下单：1后勤；2业务员；9客户',
  `sub_order_status` varchar(32) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT '' COMMENT '订单子状态：',
  `need_invoice` tinyint(1) NULL DEFAULT 0 COMMENT '需开票?0否；1是',
  `coupon_id` char(19) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL COMMENT '用户使用优惠券时记录券id',
  `pre_prize` decimal(10, 2) NULL DEFAULT 0.00 COMMENT '(￥)最小定金，关联发车付款进度',
  `order_prize` decimal(10, 2) NULL DEFAULT 0.00 COMMENT '(￥)订单金额，关联发车付款进度',
  `deliver_prize` decimal(10, 2) NULL DEFAULT NULL COMMENT '(￥)收客户配送费，',
  `coupon_prize` decimal(10, 2) NULL DEFAULT NULL COMMENT '(￥)客户优惠金额，一般是优惠券的金额，折扣券为计算后的金额',
  `pay_prize` decimal(10, 2) NULL DEFAULT 0.00 COMMENT '(￥)已收金额',
  `finance_account_type` varchar(32) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL COMMENT '[20220527]收款方式',
  `remainNeedPay` decimal(10, 2) GENERATED ALWAYS AS ((`order_prize` - `pay_prize`)) VIRTUAL COMMENT '(￥)剩余应收' NULL,
  `need_outcome_prize` decimal(10, 2) NULL DEFAULT NULL COMMENT '(￥)总应付金额',
  `outcome_prize` decimal(10, 2) NULL DEFAULT 0.00 COMMENT '(￥)已付金额',
  `refund_prize` decimal(10, 2) NULL DEFAULT 0.00 COMMENT '(￥)收退金额',
  `outcome_refund_prize` decimal(10, 2) NULL DEFAULT 0.00 COMMENT '(￥)付退金额',
  `cost_prize` decimal(10, 2) NULL DEFAULT 0.00 COMMENT '(￥)成本金额',
  `distri_prize` decimal(10, 2) NULL DEFAULT 0.00 COMMENT '(￥)已分润金额',
  `final_prize` decimal(10, 2) NULL DEFAULT 0.00 COMMENT '(￥)毛利',
  `finalCate` tinyint(1) GENERATED ALWAYS AS (sign(`final_prize`)) VIRTUAL COMMENT '毛利状态：0不赚不亏；1赚；-1亏' NULL,
  `pay_progress` decimal(4, 2) NULL DEFAULT 0.00 COMMENT '付款进度',
  `do_pay_progress` decimal(4, 2) NULL DEFAULT NULL COMMENT '订单执行所需付款进度',
  `busier_id` char(19) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT '' COMMENT '业务员id，用户表',
  `contact` varchar(32) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL COMMENT '订单联系人姓名（用于实际执行订单使用）',
  `contact_phone` varchar(15) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL COMMENT '订单联系人电话（用于实际执行订单使用）',
  `is_complete` int(11) NULL DEFAULT 0 COMMENT '订单是否完结：包含finish和close（完结单不可取消）',
  `is_cancel` tinyint(1) NULL DEFAULT 0 COMMENT '订单取消？0未取消，1已取消',
  `has_distri` tinyint(1) NULL DEFAULT 0 COMMENT '已分润？0未分，1已分',
  `has_deliver` tinyint(1) NULL DEFAULT 0 COMMENT '[冗]已发货？0否，1是',
  `has_receive` tinyint(1) NULL DEFAULT 0 COMMENT '已收货？0否，1是',
  `receive_user_id` char(19) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL COMMENT '确认收货用户(确认收货按钮点击人)',
  `has_evaluate` tinyint(1) NULL DEFAULT 0 COMMENT '已评价？0否，1是',
  `is_contract_buyer` tinyint(1) NULL DEFAULT 0 COMMENT '已生成过买家合同',
  `is_contract_seller` tinyint(1) NULL DEFAULT 0 COMMENT '已生成过卖家合同',
  `cancel_reason` varchar(255) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT '' COMMENT '取消原因',
  `cancel_by` varchar(32) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL COMMENT '取消人：plate平台,buyer买家,seller卖家,mall天猫等',
  `cancel_time` datetime(0) NULL DEFAULT NULL COMMENT '20221118:订单取消时间',
  `has_seller_statement` tinyint(1) NULL DEFAULT 0 COMMENT '卖家对账？0未对，1已对',
  `has_seller_settle` tinyint(1) NULL DEFAULT 0 COMMENT '卖家已结？0未结，1已结',
  `has_buyer_statement` tinyint(1) NULL DEFAULT NULL COMMENT '买家对账？0未对，1已对',
  `has_buyer_settle` tinyint(1) NULL DEFAULT 0 COMMENT '买家已结？0未结，1已结',
  `lastFlowNodeRole` varchar(32) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT '',
  `orderLastFlowNode` varchar(32) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT '' COMMENT '冗余：订单末流程节点',
  `order_busier_time` datetime(0) NULL DEFAULT NULL COMMENT '订单可结佣时间：跟has_busier_settle有关联',
  `last_pay_time` datetime(0) NULL DEFAULT NULL COMMENT '订单末次付款时间',
  `order_deliver_time` datetime(0) NULL DEFAULT NULL COMMENT '订单发货时间',
  `order_receive_time` datetime(0) NULL DEFAULT NULL COMMENT '订单收货时间',
  `order_finish_time` datetime(0) NULL DEFAULT NULL COMMENT '订单结束时间',
  `deliver_type` tinyint(1) NULL DEFAULT NULL COMMENT '配送方式：1自取；2配送；自取无配送费',
  `addr_realname` varchar(32) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL COMMENT '收货人',
  `addr_phone` varchar(15) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL COMMENT '收货手机',
  `addr_province` varchar(32) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL COMMENT '收货省',
  `addr_city` varchar(32) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL COMMENT '收货市',
  `addr_county` varchar(32) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL COMMENT '收货县',
  `addr_address` text CHARACTER SET utf8 COLLATE utf8_general_ci NULL COMMENT '收货具体地址',
  `driver_eat` tinyint(1) NULL DEFAULT NULL COMMENT '【包车专用】司机用餐：0不包；1包',
  `driver_room` tinyint(1) NULL DEFAULT NULL COMMENT '【包车专用】司机住宿-多日：0不包；1包',
  `user_remark` text CHARACTER SET utf8 COLLATE utf8_general_ci NULL COMMENT '下单人备注',
  `is_buyer_notice` tinyint(1) NULL DEFAULT NULL COMMENT '客户已通知：0否；1是；',
  `is_buyer_read` tinyint(1) NULL DEFAULT NULL COMMENT '客户已读：0否；1是',
  `buyer_sign` char(19) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL COMMENT '客户签字内容',
  `buyerStatus` varchar(8) CHARACTER SET utf8 COLLATE utf8_general_ci GENERATED ALWAYS AS (if(`buyer_sign`,_utf8mb4'sign',if(`is_buyer_read`,_utf8mb4'read',if(`is_buyer_notice`,_utf8mb4'notice',_utf8mb4'todo')))) VIRTUAL COMMENT '20220813:客户信息状态' NULL,
  `is_seller_notice` tinyint(1) NULL DEFAULT NULL COMMENT '供应商已通知：0否；1是；',
  `is_seller_read` tinyint(1) NULL DEFAULT NULL COMMENT '供应商已读：0否；1是',
  `seller_sign` char(19) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL COMMENT '供应商签字内容',
  `sellerStatus` varchar(8) CHARACTER SET utf8 COLLATE utf8_general_ci GENERATED ALWAYS AS (if(`seller_sign`,_utf8mb4'sign',if(`is_seller_read`,_utf8mb4'read',if(`is_seller_notice`,_utf8mb4'notice',_utf8mb4'todo')))) VIRTUAL COMMENT '20220813:供应商信息状态' NULL,
  `source_id` char(19) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL COMMENT '20220702:来源id：适用于订单来自其他系统的情况',
  `sort` int(11) NULL DEFAULT 1000 COMMENT '排序',
  `status` tinyint(1) NULL DEFAULT 1 COMMENT '状态(0禁用,1启用)',
  `has_used` tinyint(1) NULL DEFAULT 0 COMMENT '有使用(0否,1是)',
  `is_lock` tinyint(1) NULL DEFAULT 0 COMMENT '锁定（0：未锁，1：已锁）',
  `is_delete` tinyint(1) NULL DEFAULT 0 COMMENT '锁定（0：未删，1：已删）',
  `remark` text CHARACTER SET utf8 COLLATE utf8_general_ci NULL COMMENT '备注',
  `creater` char(19) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL COMMENT '创建者，user表',
  `updater` char(19) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL COMMENT '更新者，user表',
  `create_time` datetime(0) NULL DEFAULT CURRENT_TIMESTAMP(0) COMMENT '创建时间',
  `update_time` datetime(0) NULL DEFAULT CURRENT_TIMESTAMP(0) ON UPDATE CURRENT_TIMESTAMP(0) COMMENT '更新时间',
  PRIMARY KEY (`id`) USING BTREE,
  INDEX `goods_id`(`goods_id`) USING BTREE,
  INDEX `dept_id`(`dept_id`) USING BTREE,
  INDEX `pre_order_id`(`pre_order_id`) USING BTREE,
  INDEX `seller_customer_id`(`seller_customer_id`) USING BTREE,
  INDEX `seller_user_id`(`seller_user_id`) USING BTREE,
  INDEX `customer_dept_id`(`customer_dept_id`) USING BTREE,
  INDEX `seller_customer_id_2`(`customer_id`) USING BTREE,
  INDEX `user_id`(`user_id`) USING BTREE,
  INDEX `rec_user_id`(`rec_user_id`) USING BTREE,
  INDEX `shop_id`(`shop_id`) USING BTREE,
  INDEX `goods_table_id`(`goods_table_id`) USING BTREE,
  INDEX `creater`(`creater`) USING BTREE,
  INDEX `custUser`(`custUser`) USING BTREE
) ENGINE = InnoDB CHARACTER SET = utf8 COLLATE = utf8_general_ci COMMENT = '订单表' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for w_order_bao_bus
-- ----------------------------
DROP TABLE IF EXISTS `w_order_bao_bus`;
CREATE TABLE `w_order_bao_bus`  (
  `id` char(19) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL,
  `company_id` char(19) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT '',
  `order_id` char(19) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT '0' COMMENT 'tr_order 表id',
  `bus_type_id` char(19) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT '0' COMMENT '车型',
  `bus_id` char(19) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT '' COMMENT '调度安排的车辆id',
  `bus_status` char(19) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT '0' COMMENT '车辆状态todo未执行，doing进行中，finished已到达，cancel已取消',
  `persons` int(11) NULL DEFAULT NULL COMMENT '20220720：人数；手输',
  `driver_persons` int(11) NULL DEFAULT NULL COMMENT '20220721：人数；手输',
  `prize` decimal(10, 2) NULL DEFAULT 0.00 COMMENT '业务拆单价',
  `cal_formula` varchar(255) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL COMMENT '20220824：计算价格的公式：根据计价规则生成',
  `cal_group_id` char(19) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT '' COMMENT '20220824：匹配的计价规则id',
  `pay_prize` decimal(10, 2) NULL DEFAULT 0.00 COMMENT '[20220516]已付金额',
  `pay_describe` text CHARACTER SET utf8 COLLATE utf8_general_ci NULL COMMENT '[20221214]【冗】付款说明，从账单备注来',
  `fault_prize` decimal(10, 2) NULL DEFAULT 0.00 COMMENT '[20220914]坏账金额(进坏账专户)',
  `remainNeedPay` decimal(10, 2) GENERATED ALWAYS AS (((`prize` - `pay_prize`) - `fault_prize`)) VIRTUAL NULL,
  `finance_account_type` varchar(32) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL COMMENT '[20220527]收款方式',
  `calc_prize` decimal(10, 2) NULL DEFAULT NULL COMMENT '核算价',
  `diao_prize` decimal(10, 2) NULL DEFAULT NULL COMMENT '外调价',
  `diao_pay_prize` decimal(10, 2) NULL DEFAULT NULL COMMENT '[20221215]外调已付',
  `highway_prize` decimal(10, 2) NULL DEFAULT NULL COMMENT '[20220525]高速费：手录',
  `start_time` datetime(0) NULL DEFAULT NULL COMMENT '开始时间',
  `end_time` datetime(0) NULL DEFAULT NULL COMMENT '结束时间',
  `real_start_time` datetime(0) NULL DEFAULT NULL COMMENT '【冗】实际发车时间',
  `real_end_time` datetime(0) NULL DEFAULT NULL COMMENT '【冗】实际结束时间',
  `scopeDate` int(11) GENERATED ALWAYS AS (if((`end_time` > `start_time`),((to_days(`end_time`) - to_days(`start_time`)) + 1),1)) VIRTUAL COMMENT '行程天数' NULL,
  `is_cancel` int(11) NULL DEFAULT 0 COMMENT '是否取消：0否，1是',
  `start_mile` decimal(10, 1) NULL DEFAULT NULL COMMENT '出场公里数',
  `end_mile` decimal(10, 1) UNSIGNED NULL DEFAULT NULL COMMENT '回场公里数',
  `thisMile` decimal(10, 1) GENERATED ALWAYS AS ((`end_mile` - `start_mile`)) VIRTUAL NULL,
  `start_gps_mile` decimal(10, 1) NULL DEFAULT NULL COMMENT '20221009:出场GPS公里数',
  `end_gps_mile` decimal(10, 1) NULL DEFAULT NULL COMMENT '20221009:回场GPS公里数',
  `thisGpsMile` decimal(10, 1) GENERATED ALWAYS AS ((`end_gps_mile` - `start_gps_mile`)) VIRTUAL COMMENT '20221009:' NULL,
  `cust_mile` decimal(10, 1) NULL DEFAULT NULL COMMENT '0901:给客户看的公里数，可能有水分',
  `map_mile` decimal(10, 1) NULL DEFAULT NULL COMMENT '20230307:地图计算的公里数',
  `map_duration` decimal(10, 1) NULL DEFAULT NULL COMMENT '20230307:地图计算的时长',
  `map_steps` text CHARACTER SET utf8 COLLATE utf8_general_ci NULL COMMENT '20230307:地图计算的行驶方式',
  `map_prize` decimal(10, 2) NULL DEFAULT NULL COMMENT '20230307:根据地图得出的报价',
  `map_prize_formula` text CHARACTER SET utf8 COLLATE utf8_general_ci NULL COMMENT '20230307:报价公式',
  `isDone` tinyint(1) GENERATED ALWAYS AS (if((`end_mile` > 0),1,0)) VIRTUAL NULL,
  `route` text CHARACTER SET utf8 COLLATE utf8_general_ci NULL COMMENT '【冗】线路；各站点串联',
  `route_start` text CHARACTER SET utf8 COLLATE utf8_general_ci NULL COMMENT '【冗】出发地',
  `route_end` text CHARACTER SET utf8 COLLATE utf8_general_ci NULL COMMENT '【冗】目的地',
  `route_pass` text CHARACTER SET utf8 COLLATE utf8_general_ci NULL COMMENT '20221031:途经地：GPS采集或手输',
  `driver_name` varchar(32) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL COMMENT '【冗】司机姓名',
  `calculate_prize` decimal(10, 2) NULL DEFAULT NULL COMMENT '【冗】佣金计算额',
  `rate` decimal(10, 2) NULL DEFAULT NULL COMMENT '【冗】抽佣比例',
  `grant_money` decimal(10, 2) NULL DEFAULT 0.00 COMMENT '【冗】司机抽点金额',
  `eat_money` decimal(10, 2) NULL DEFAULT 0.00 COMMENT '【冗】餐费',
  `other_money` decimal(10, 2) NULL DEFAULT 0.00 COMMENT '【冗】其他补贴',
  `moneyAll` decimal(10, 2) GENERATED ALWAYS AS (((`grant_money` + `eat_money`) + `other_money`)) VIRTUAL COMMENT '【冗】费用合计' NULL,
  `has_bill` tinyint(1) NULL DEFAULT 0 COMMENT '【冗】有否账单',
  `has_invoice` tinyint(1) NULL DEFAULT 0 COMMENT '【冗】有否发票',
  `cust_notice_str` varchar(255) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL COMMENT '[0829]通知客户信息',
  `has_auto_cust_notice` tinyint(1) NULL DEFAULT 0 COMMENT '[0830]是否已执行通知客户',
  `auto_cust_notice_str` varchar(255) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL COMMENT '[0830]自动通知客户回执',
  `start_mile_pic` char(19) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT '' COMMENT '[0831]发车公里数拍照',
  `end_mile_pic` char(19) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT '' COMMENT '[0831]结束公里数拍照',
  `pass_realname_pic` text CHARACTER SET utf8 COLLATE utf8_general_ci NULL COMMENT '20221005:乘客实名拍照',
  `bao_contract_pic` char(19) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL COMMENT '20221013:包车合同',
  `route_bill_pic` char(19) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL COMMENT '20221013:行车路单',
  `tang_pic` char(19) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL COMMENT '20221013:趟检单',
  `other_pic` text CHARACTER SET utf8 COLLATE utf8_general_ci NULL COMMENT '20221013:其他单据多图',
  `has_contract_on` tinyint(1) NULL DEFAULT 0 COMMENT '20221014:是否提交存档(包车合同)',
  `has_route_bill_on` tinyint(1) NULL DEFAULT 0 COMMENT '20221014:是否提交存档(行车路单)',
  `has_pass_realname_on` tinyint(1) NULL DEFAULT 0 COMMENT '20221014:是否提交存档(乘客实名)',
  `is_bill_on` tinyint(1) NULL DEFAULT 0 COMMENT '20221018:单据是否已交',
  `pre_driver_phone_notice` tinyint(1) NULL DEFAULT 0 COMMENT '20221018:发车前语音通知驾驶员标记',
  `pre_driver_phone_notice_str` text CHARACTER SET utf8 COLLATE utf8_general_ci NULL COMMENT '发车语音通知结果记录',
  `sort` int(11) NULL DEFAULT 1000 COMMENT '排序',
  `status` tinyint(1) NULL DEFAULT 1 COMMENT '状态(0禁用,1启用)',
  `has_used` tinyint(1) NULL DEFAULT 0 COMMENT '有使用(0否,1是)',
  `is_lock` tinyint(1) NULL DEFAULT 0 COMMENT '锁定（0：未锁，1：已锁）',
  `is_delete` tinyint(1) NULL DEFAULT 0 COMMENT '锁定（0：未删，1：已删）',
  `remark` text CHARACTER SET utf8 COLLATE utf8_general_ci NULL COMMENT '备注',
  `creater` varchar(50) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT '' COMMENT '创建者，user表',
  `updater` varchar(50) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT '' COMMENT '更新者，user表',
  `create_time` datetime(0) NULL DEFAULT CURRENT_TIMESTAMP(0) COMMENT '创建时间',
  `update_time` datetime(0) NULL DEFAULT CURRENT_TIMESTAMP(0) ON UPDATE CURRENT_TIMESTAMP(0) COMMENT '更新时间',
  PRIMARY KEY (`id`) USING BTREE,
  INDEX `order_id`(`order_id`) USING BTREE,
  INDEX `bus_type_id`(`bus_type_id`) USING BTREE,
  INDEX `bus_id`(`bus_id`) USING BTREE,
  INDEX `prize`(`prize`) USING BTREE,
  INDEX `start_time`(`start_time`) USING BTREE,
  INDEX `end_time`(`end_time`) USING BTREE
) ENGINE = InnoDB CHARACTER SET = utf8 COLLATE = utf8_general_ci COMMENT = '包车订单车辆' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for w_order_bao_bus_driver
-- ----------------------------
DROP TABLE IF EXISTS `w_order_bao_bus_driver`;
CREATE TABLE `w_order_bao_bus_driver`  (
  `id` char(19) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL,
  `company_id` char(19) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT '',
  `bao_bus_id` char(19) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT '' COMMENT '包车车辆id',
  `driver_id` char(19) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT '' COMMENT '司机id',
  `driver_type` tinyint(1) NULL DEFAULT 1 COMMENT '1:自有司机；2外调司机（根据出车时间，结合司机入职离职自动获取）',
  `this_mile` decimal(10, 2) NULL DEFAULT NULL COMMENT '本趟里程',
  `distribute_prize` decimal(10, 2) NULL DEFAULT NULL COMMENT '司机分派金额原始',
  `diao_prize` decimal(10, 2) NULL DEFAULT NULL COMMENT '外调金额',
  `calculate_prize` decimal(10, 2) NULL DEFAULT NULL COMMENT '抽佣计算金额',
  `rate` decimal(10, 2) NULL DEFAULT NULL COMMENT '抽佣比例',
  `grant_money` decimal(10, 2) NULL DEFAULT NULL COMMENT '司机抽点金额',
  `eat_money` decimal(10, 2) NULL DEFAULT NULL COMMENT '餐费',
  `other_money` decimal(10, 2) NULL DEFAULT 0.00 COMMENT '其他补贴',
  `moneyAll` decimal(10, 2) GENERATED ALWAYS AS (((`grant_money` + `eat_money`) + `other_money`)) VIRTUAL NULL,
  `is_grant` int(11) NULL DEFAULT 0 COMMENT '司机抽点是否发放0否，1是',
  `is_notice` tinyint(1) NULL DEFAULT 0 COMMENT '已通知?0否；1是',
  `is_read` tinyint(1) NULL DEFAULT 0 COMMENT '已读?0否；1是',
  `is_accept` tinyint(1) NULL DEFAULT 0 COMMENT '已接单?0否；1是',
  `is_start` tinyint(1) NULL DEFAULT 0 COMMENT '已发车?0否；1是',
  `is_finish` tinyint(1) NULL DEFAULT 0 COMMENT '已送达?0否；1是',
  `busStatus` varchar(32) CHARACTER SET utf8 COLLATE utf8_general_ci GENERATED ALWAYS AS (if(`is_finish`,_utf8mb4'finish',if(`is_start`,_utf8mb4'start',if(`is_accept`,_utf8mb4'accept',if(`is_read`,_utf8mb4'read',if(`is_notice`,_utf8mb4'notice',_utf8mb4'todo')))))) VIRTUAL NULL,
  `notice_time` datetime(0) NULL DEFAULT NULL COMMENT '20221010通知时间',
  `read_time` datetime(0) NULL DEFAULT NULL COMMENT '20221010读取时间',
  `accept_time` datetime(0) NULL DEFAULT NULL COMMENT '20221010接单时间',
  `start_time` datetime(0) NULL DEFAULT NULL COMMENT '20221010发车时间',
  `finish_time` datetime(0) NULL DEFAULT NULL COMMENT '20221010结束时间',
  `salary_item_id` char(19) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT '' COMMENT '0831:薪资项id',
  `has_salary` tinyint(1) GENERATED ALWAYS AS (if(`salary_item_id`,1,0)) VIRTUAL NULL,
  `status` varchar(16) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT '1' COMMENT '车辆状态todo未执行，doing进行中，finished已到达，cancel已取消',
  `remark` text CHARACTER SET utf8 COLLATE utf8_general_ci NULL COMMENT '备注',
  `create_time` datetime(0) NULL DEFAULT CURRENT_TIMESTAMP(0),
  `update_time` datetime(0) NULL DEFAULT CURRENT_TIMESTAMP(0) ON UPDATE CURRENT_TIMESTAMP(0),
  PRIMARY KEY (`id`) USING BTREE,
  INDEX `company_id`(`company_id`) USING BTREE,
  INDEX `driver_id`(`driver_id`) USING BTREE,
  INDEX `distribute_prize`(`distribute_prize`) USING BTREE,
  INDEX `calculate_prize`(`calculate_prize`) USING BTREE,
  INDEX `grant_money`(`grant_money`) USING BTREE,
  INDEX `eat_money`(`eat_money`) USING BTREE,
  INDEX `other_money`(`other_money`) USING BTREE,
  INDEX `is_grant`(`is_grant`) USING BTREE,
  INDEX `status`(`status`) USING BTREE,
  INDEX `bao_bus_id`(`bao_bus_id`) USING BTREE
) ENGINE = InnoDB CHARACTER SET = utf8 COLLATE = utf8_general_ci COMMENT = '包车车辆司机' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for w_order_bao_bus_driver_operate
-- ----------------------------
DROP TABLE IF EXISTS `w_order_bao_bus_driver_operate`;
CREATE TABLE `w_order_bao_bus_driver_operate`  (
  `id` char(19) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL,
  `company_id` int(4) NULL DEFAULT 0 COMMENT '已通知?0否；1是',
  `bao_bus_driver_id` char(19) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT '' COMMENT '包车车辆id',
  `driver_id` char(19) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT '' COMMENT '司机id',
  `operate_type` varchar(19) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT '' COMMENT '操作类型：notice;read;accept;start;finish',
  `creater` char(19) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL,
  `create_time` datetime(0) NULL DEFAULT CURRENT_TIMESTAMP(0),
  `update_time` datetime(0) NULL DEFAULT CURRENT_TIMESTAMP(0) ON UPDATE CURRENT_TIMESTAMP(0),
  PRIMARY KEY (`id`) USING BTREE,
  INDEX `company_id`(`company_id`) USING BTREE,
  INDEX `driver_id`(`driver_id`) USING BTREE
) ENGINE = InnoDB CHARACTER SET = utf8 COLLATE = utf8_general_ci COMMENT = '司机操作日志' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for w_order_bao_bus_station
-- ----------------------------
DROP TABLE IF EXISTS `w_order_bao_bus_station`;
CREATE TABLE `w_order_bao_bus_station`  (
  `id` char(19) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL,
  `company_id` int(11) NULL DEFAULT NULL,
  `order_id` char(19) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT '' COMMENT '包车订单id',
  `bao_bus_id` char(19) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT '' COMMENT '包车订单id',
  `name` varchar(128) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL COMMENT '站点名称',
  `latitude` decimal(10, 6) NULL DEFAULT NULL COMMENT '纬度',
  `longitude` decimal(10, 6) NULL DEFAULT 0.000000 COMMENT '经度',
  `address` varchar(255) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT '',
  `sort` int(11) NULL DEFAULT 1000 COMMENT '排序',
  `status` tinyint(1) NULL DEFAULT 1 COMMENT '状态(0禁用,1启用)',
  `has_used` tinyint(1) NULL DEFAULT 0 COMMENT '有使用(0否,1是)',
  `is_lock` tinyint(1) NULL DEFAULT 0 COMMENT '锁定（0：未锁，1：已锁）',
  `is_delete` tinyint(1) NULL DEFAULT 0 COMMENT '锁定（0：未删，1：已删）',
  `remark` text CHARACTER SET utf8 COLLATE utf8_general_ci NULL COMMENT '备注',
  `creater` varchar(50) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT '' COMMENT '创建者，user表',
  `updater` varchar(50) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT '' COMMENT '更新者，user表',
  `create_time` datetime(0) NULL DEFAULT CURRENT_TIMESTAMP(0) COMMENT '创建时间',
  `update_time` datetime(0) NULL DEFAULT CURRENT_TIMESTAMP(0) ON UPDATE CURRENT_TIMESTAMP(0) COMMENT '更新时间',
  PRIMARY KEY (`id`) USING BTREE,
  INDEX `order_id`(`order_id`) USING BTREE,
  INDEX `name`(`name`) USING BTREE,
  INDEX `latitude`(`latitude`) USING BTREE,
  INDEX `longitude`(`longitude`) USING BTREE,
  INDEX `address`(`address`) USING BTREE,
  INDEX `sort`(`sort`) USING BTREE
) ENGINE = InnoDB CHARACTER SET = utf8 COLLATE = utf8_general_ci COMMENT = '包车-站点表' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for w_order_bao_station
-- ----------------------------
DROP TABLE IF EXISTS `w_order_bao_station`;
CREATE TABLE `w_order_bao_station`  (
  `id` varchar(32) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL,
  `company_id` varchar(32) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT '',
  `order_id` varchar(11) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT '' COMMENT '包车订单id',
  `name` varchar(32) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL COMMENT '站点名称',
  `latitude` float(10, 6) NULL DEFAULT NULL COMMENT '纬度',
  `longitude` float(10, 6) NULL DEFAULT 0.000000 COMMENT '经度',
  `address` varchar(255) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT '',
  `sort` int(11) NULL DEFAULT 1000 COMMENT '排序',
  `status` tinyint(1) NULL DEFAULT 1 COMMENT '状态(0禁用,1启用)',
  `has_used` tinyint(1) NULL DEFAULT 0 COMMENT '有使用(0否,1是)',
  `is_lock` tinyint(1) NULL DEFAULT 0 COMMENT '锁定（0：未锁，1：已锁）',
  `is_delete` tinyint(1) NULL DEFAULT 0 COMMENT '锁定（0：未删，1：已删）',
  `remark` text CHARACTER SET utf8 COLLATE utf8_general_ci NULL COMMENT '备注',
  `creater` varchar(50) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT '' COMMENT '创建者，user表',
  `updater` varchar(50) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT '' COMMENT '更新者，user表',
  `create_time` datetime(0) NULL DEFAULT CURRENT_TIMESTAMP(0) COMMENT '创建时间',
  `update_time` datetime(0) NULL DEFAULT CURRENT_TIMESTAMP(0) ON UPDATE CURRENT_TIMESTAMP(0) COMMENT '更新时间',
  PRIMARY KEY (`id`) USING BTREE,
  INDEX `order_id`(`order_id`) USING BTREE,
  INDEX `name`(`name`) USING BTREE,
  INDEX `latitude`(`latitude`) USING BTREE,
  INDEX `longitude`(`longitude`) USING BTREE,
  INDEX `address`(`address`) USING BTREE,
  INDEX `sort`(`sort`) USING BTREE
) ENGINE = InnoDB CHARACTER SET = utf8 COLLATE = utf8_general_ci COMMENT = '包车-站点表' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for w_order_bill
-- ----------------------------
DROP TABLE IF EXISTS `w_order_bill`;
CREATE TABLE `w_order_bill`  (
  `id` varchar(32) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL,
  `company_id` varchar(32) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT '',
  `group_type` varchar(32) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL COMMENT '分组类型:buyer按客户;seller:供应商',
  `customer_id` varchar(32) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL COMMENT '客户/供应商：不一定用',
  `user_id` varchar(32) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL COMMENT '客户/供应商对接人',
  `order_type` varchar(32) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL COMMENT '订单类型：不一定用',
  `bill_name` varchar(255) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL COMMENT '对账单名称',
  `describe` text CHARACTER SET utf8 COLLATE utf8_general_ci NULL COMMENT '订单草稿本',
  `sort` int(11) NULL DEFAULT 1000 COMMENT '排序',
  `status` tinyint(1) NULL DEFAULT 1 COMMENT '状态(0禁用,1启用)',
  `has_used` tinyint(1) NULL DEFAULT 0 COMMENT '有使用(0否,1是)',
  `is_lock` tinyint(1) NULL DEFAULT 0 COMMENT '锁定（0：未锁，1：已锁）',
  `is_delete` tinyint(1) NULL DEFAULT 0 COMMENT '锁定（0：未删，1：已删）',
  `remark` text CHARACTER SET utf8 COLLATE utf8_general_ci NULL COMMENT '备注',
  `creater` varchar(50) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT '' COMMENT '创建者，user表',
  `updater` varchar(50) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT '' COMMENT '更新者，user表',
  `create_time` datetime(0) NULL DEFAULT CURRENT_TIMESTAMP(0) COMMENT '创建时间',
  `update_time` datetime(0) NULL DEFAULT CURRENT_TIMESTAMP(0) ON UPDATE CURRENT_TIMESTAMP(0) COMMENT '更新时间',
  PRIMARY KEY (`id`) USING BTREE,
  INDEX `company_id`(`company_id`) USING BTREE,
  INDEX `create_time`(`create_time`) USING BTREE,
  INDEX `update_time`(`update_time`) USING BTREE
) ENGINE = InnoDB CHARACTER SET = utf8 COLLATE = utf8_general_ci COMMENT = '订单对账表（对账单），不一定按对账单付款' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for w_order_bill_order
-- ----------------------------
DROP TABLE IF EXISTS `w_order_bill_order`;
CREATE TABLE `w_order_bill_order`  (
  `id` char(19) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL,
  `company_id` int(11) NULL DEFAULT NULL,
  `bill_id` char(19) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL COMMENT '对账单id',
  `order_id` char(19) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL COMMENT '订单id',
  `sub_order_id` char(19) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL COMMENT '20220803 子id',
  `sort` int(11) NULL DEFAULT 1000 COMMENT '排序',
  `status` tinyint(1) NULL DEFAULT 1 COMMENT '状态(0禁用,1启用)',
  `has_used` tinyint(1) NULL DEFAULT 0 COMMENT '有使用(0否,1是)',
  `is_lock` tinyint(1) NULL DEFAULT 0 COMMENT '锁定（0：未锁，1：已锁）',
  `is_delete` tinyint(1) NULL DEFAULT 0 COMMENT '锁定（0：未删，1：已删）',
  `remark` text CHARACTER SET utf8 COLLATE utf8_general_ci NULL COMMENT '备注',
  `creater` varchar(50) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT '' COMMENT '创建者，user表',
  `updater` varchar(50) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT '' COMMENT '更新者，user表',
  `create_time` datetime(0) NULL DEFAULT CURRENT_TIMESTAMP(0) COMMENT '创建时间',
  `update_time` datetime(0) NULL DEFAULT CURRENT_TIMESTAMP(0) ON UPDATE CURRENT_TIMESTAMP(0) COMMENT '更新时间',
  PRIMARY KEY (`id`) USING BTREE,
  INDEX `company_id`(`company_id`) USING BTREE,
  INDEX `order_id`(`order_id`) USING BTREE,
  INDEX `create_time`(`create_time`) USING BTREE,
  INDEX `update_time`(`update_time`) USING BTREE
) ENGINE = InnoDB CHARACTER SET = utf8 COLLATE = utf8_general_ci COMMENT = '对账单订单表' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for w_order_change_log
-- ----------------------------
DROP TABLE IF EXISTS `w_order_change_log`;
CREATE TABLE `w_order_change_log`  (
  `id` char(19) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL,
  `company_id` int(11) NULL DEFAULT NULL,
  `order_id` char(19) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT '0' COMMENT 'tr_order 表id',
  `sub_order_id` char(19) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL,
  `change_type` varchar(50) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT '' COMMENT '修改类型',
  `log` text CHARACTER SET utf8 COLLATE utf8_general_ci NULL COMMENT '修改日志',
  `remark` text CHARACTER SET utf8 COLLATE utf8_general_ci NULL COMMENT '备注',
  `creater` varchar(50) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT '' COMMENT '创建者，user表',
  `updater` varchar(50) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT '' COMMENT '更新者，user表',
  `create_time` datetime(0) NULL DEFAULT CURRENT_TIMESTAMP(0) COMMENT '创建时间',
  `update_time` datetime(0) NULL DEFAULT CURRENT_TIMESTAMP(0) ON UPDATE CURRENT_TIMESTAMP(0) COMMENT '更新时间',
  PRIMARY KEY (`id`) USING BTREE
) ENGINE = InnoDB CHARACTER SET = utf8 COLLATE = utf8_general_ci COMMENT = '订单变更记录' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for w_order_express
-- ----------------------------
DROP TABLE IF EXISTS `w_order_express`;
CREATE TABLE `w_order_express`  (
  `id` varchar(32) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL DEFAULT '0',
  `company_id` varchar(32) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT '',
  `order_id` varchar(32) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT '' COMMENT '订单id',
  `type` tinyint(1) NULL DEFAULT 0 COMMENT '1收件；2寄件',
  `from_realname` varchar(32) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL COMMENT '寄件人姓名',
  `from_phone` varchar(32) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL COMMENT '寄件人手机',
  `from_address` varchar(64) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL COMMENT '寄件人地址',
  `to_realname` varchar(32) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT '' COMMENT '收件人姓名',
  `to_phone` varchar(15) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT '' COMMENT '收件人手机',
  `to_address` varchar(64) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT '' COMMENT '收件人地址',
  `money` decimal(10, 2) NULL DEFAULT 0.00 COMMENT '邮费',
  `exp_company` varchar(32) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT '' COMMENT '快递公司',
  `exp_sn` varchar(64) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT '' COMMENT '快递单号',
  `user_id` varchar(64) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT '' COMMENT '登记人id',
  `user_role` varchar(32) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT '' COMMENT '登记人角色：卖家、平台、买家',
  `file_id` varchar(32) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL COMMENT '快递单号附件',
  `sort` int(11) NULL DEFAULT 1000 COMMENT '排序',
  `status` tinyint(1) NULL DEFAULT 1 COMMENT '状态(0禁用,1启用)',
  `has_used` tinyint(1) NULL DEFAULT 0 COMMENT '有使用(0否,1是)',
  `is_lock` tinyint(1) NULL DEFAULT 0 COMMENT '锁定（0：未锁，1：已锁）',
  `is_delete` tinyint(1) NULL DEFAULT 0 COMMENT '锁定（0：未删，1：已删）',
  `remark` text CHARACTER SET utf8 COLLATE utf8_general_ci NULL COMMENT '备注',
  `creater` varchar(50) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT '' COMMENT '创建者，user表',
  `updater` varchar(50) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT '' COMMENT '更新者，user表',
  `create_time` datetime(0) NULL DEFAULT CURRENT_TIMESTAMP(0) COMMENT '创建时间',
  `update_time` datetime(0) NULL DEFAULT CURRENT_TIMESTAMP(0) ON UPDATE CURRENT_TIMESTAMP(0) COMMENT '更新时间',
  PRIMARY KEY (`id`) USING BTREE,
  INDEX `company_id`(`company_id`) USING BTREE,
  INDEX `user_id`(`user_id`) USING BTREE,
  INDEX `order_id`(`order_id`) USING BTREE,
  INDEX `create_time`(`create_time`) USING BTREE,
  INDEX `update_time`(`update_time`) USING BTREE
) ENGINE = InnoDB CHARACTER SET = utf8 COLLATE = utf8_general_ci COMMENT = '快递表' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for w_order_extra_prize_tpl
-- ----------------------------
DROP TABLE IF EXISTS `w_order_extra_prize_tpl`;
CREATE TABLE `w_order_extra_prize_tpl`  (
  `id` char(19) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL DEFAULT '0',
  `company_id` int(11) NULL DEFAULT NULL,
  `order_type` varchar(32) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT '' COMMENT '订单类型',
  `prize_key` varchar(32) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT '' COMMENT '费用key',
  `prize_name` varchar(32) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT '' COMMENT '费用名称',
  `describe` varchar(32) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT '' COMMENT '费用描述',
  `belong_role` varchar(32) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT '' COMMENT '归属角色',
  `prize` decimal(10, 2) UNSIGNED NULL DEFAULT NULL COMMENT '报价',
  `sort` int(11) NULL DEFAULT 1000 COMMENT '排序',
  `status` tinyint(1) NULL DEFAULT 1 COMMENT '状态(0禁用,1启用)',
  `has_used` tinyint(1) NULL DEFAULT 0 COMMENT '有使用(0否,1是)',
  `is_lock` tinyint(1) NULL DEFAULT 0 COMMENT '锁定（0：未锁，1：已锁）',
  `is_delete` tinyint(1) NULL DEFAULT 0 COMMENT '锁定（0：未删，1：已删）',
  `remark` text CHARACTER SET utf8 COLLATE utf8_general_ci NULL COMMENT '备注',
  `creater` char(19) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT '' COMMENT '创建者，user表',
  `updater` char(19) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT '' COMMENT '更新者，user表',
  `create_time` datetime(0) NULL DEFAULT CURRENT_TIMESTAMP(0) COMMENT '创建时间',
  `update_time` datetime(0) NULL DEFAULT CURRENT_TIMESTAMP(0) ON UPDATE CURRENT_TIMESTAMP(0) COMMENT '更新时间',
  PRIMARY KEY (`id`) USING BTREE,
  INDEX `create_time`(`create_time`) USING BTREE,
  INDEX `update_time`(`update_time`) USING BTREE
) ENGINE = InnoDB CHARACTER SET = utf8 COLLATE = utf8_general_ci COMMENT = '订单加收价格模板' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for w_order_flow_node
-- ----------------------------
DROP TABLE IF EXISTS `w_order_flow_node`;
CREATE TABLE `w_order_flow_node`  (
  `id` char(19) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL,
  `company_id` int(11) NULL DEFAULT NULL,
  `from_table` varchar(32) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL COMMENT '来源表',
  `order_id` char(19) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL COMMENT '订单id；20211028：逐步过渡为广义的id（不能仅局限于订单）',
  `order_type` varchar(32) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT '' COMMENT '订单类型',
  `node_key` varchar(32) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL COMMENT '节点key',
  `node_name` varchar(64) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL COMMENT '节点名称',
  `node_describe` text CHARACTER SET utf8 COLLATE utf8_general_ci NULL COMMENT '节点描述',
  `flow_status` varchar(32) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL COMMENT '流程状态：\r\ntodo待处理；\r\ndoing进行中；\r\nfinish已完成；\r\nclose已关闭（订单取消，关闭上一个待处理节点）',
  `prize_key` text CHARACTER SET utf8 COLLATE utf8_general_ci NULL COMMENT '关联价格key，可多个逗号隔开',
  `plan_finish_time` datetime(0) NULL DEFAULT NULL COMMENT '预计完成时间',
  `finish_time` datetime(0) NULL DEFAULT NULL COMMENT '完成时间',
  `operate_role` varchar(32) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL COMMENT '等待操作用户角色',
  `operate_user_id` varchar(32) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT '' COMMENT '等待操作用户',
  `operate_customer_id` varchar(32) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL COMMENT '等待操作客户',
  `statement_id` varchar(32) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT '' COMMENT '对账单id：finance_statement 表的id',
  `node_value` varchar(255) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT '' COMMENT '步骤暂存值',
  `direction` tinyint(1) NULL DEFAULT 1 COMMENT '1向前；2向后',
  `is_jump` tinyint(1) NULL DEFAULT NULL COMMENT '是否跳过（0否，1是）',
  `sort` int(11) NULL DEFAULT 1000 COMMENT '排序',
  `status` tinyint(1) NULL DEFAULT 1 COMMENT '状态(0禁用,1启用)',
  `has_used` tinyint(1) NULL DEFAULT 0 COMMENT '有使用(0否,1是)',
  `is_lock` tinyint(1) NULL DEFAULT 0 COMMENT '锁定（0：未锁，1：已锁）',
  `is_delete` tinyint(1) NULL DEFAULT 0 COMMENT '锁定（0：未删，1：已删）',
  `remark` text CHARACTER SET utf8 COLLATE utf8_general_ci NULL COMMENT '备注',
  `creater` varchar(50) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT '' COMMENT '创建者，user表',
  `updater` varchar(50) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT '' COMMENT '更新者，user表',
  `create_time` datetime(0) NULL DEFAULT CURRENT_TIMESTAMP(0) COMMENT '创建时间',
  `update_time` datetime(0) NULL DEFAULT CURRENT_TIMESTAMP(0) ON UPDATE CURRENT_TIMESTAMP(0) COMMENT '更新时间',
  PRIMARY KEY (`id`) USING BTREE,
  INDEX `company_id`(`company_id`) USING BTREE,
  INDEX `order_id`(`order_id`) USING BTREE,
  INDEX `create_time`(`create_time`) USING BTREE,
  INDEX `update_time`(`update_time`) USING BTREE,
  INDEX `node_key`(`node_key`) USING BTREE,
  INDEX `flow_status`(`flow_status`) USING BTREE,
  INDEX `status`(`status`) USING BTREE
) ENGINE = InnoDB CHARACTER SET = utf8 COLLATE = utf8_general_ci COMMENT = '订单流程节点表' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for w_order_flow_node_prize_tpl
-- ----------------------------
DROP TABLE IF EXISTS `w_order_flow_node_prize_tpl`;
CREATE TABLE `w_order_flow_node_prize_tpl`  (
  `id` varchar(32) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL,
  `sale_type` varchar(32) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL COMMENT '销售类型',
  `node_key` varchar(32) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL COMMENT '节点key',
  `prize_keys` text CHARACTER SET utf8 COLLATE utf8_general_ci NULL COMMENT '价格key，逗号分隔',
  `sort` int(11) NULL DEFAULT 1000 COMMENT '排序',
  `status` tinyint(1) NULL DEFAULT 1 COMMENT '状态(0禁用,1启用)',
  `has_used` tinyint(1) NULL DEFAULT 0 COMMENT '有使用(0否,1是)',
  `is_lock` tinyint(1) NULL DEFAULT 0 COMMENT '锁定（0：未锁，1：已锁）',
  `is_delete` tinyint(1) NULL DEFAULT 0 COMMENT '锁定（0：未删，1：已删）',
  `remark` text CHARACTER SET utf8 COLLATE utf8_general_ci NULL COMMENT '备注',
  `creater` varchar(50) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT '' COMMENT '创建者，user表',
  `updater` varchar(50) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT '' COMMENT '更新者，user表',
  `create_time` datetime(0) NULL DEFAULT CURRENT_TIMESTAMP(0) COMMENT '创建时间',
  `update_time` datetime(0) NULL DEFAULT CURRENT_TIMESTAMP(0) ON UPDATE CURRENT_TIMESTAMP(0) COMMENT '更新时间',
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE INDEX `node_key`(`node_key`) USING BTREE,
  INDEX `create_time`(`create_time`) USING BTREE,
  INDEX `update_time`(`update_time`) USING BTREE
) ENGINE = InnoDB CHARACTER SET = utf8 COLLATE = utf8_general_ci COMMENT = '订单执行到指定流程节点后，\r\n变更订单动态可执行的预付金额，\r\norder 表的pre_prize字段。' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for w_order_flow_node_tpl
-- ----------------------------
DROP TABLE IF EXISTS `w_order_flow_node_tpl`;
CREATE TABLE `w_order_flow_node_tpl`  (
  `id` varchar(32) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL,
  `company_id` varchar(32) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT '',
  `sale_type` varchar(32) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL COMMENT '销售类型',
  `node_key` varchar(32) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL COMMENT '节点key',
  `next_node_key` varchar(32) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL COMMENT '下一个节点key',
  `group_id` varchar(32) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT '1' COMMENT '流程分组：分组相同表示并行流程',
  `next_node_name` varchar(32) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL COMMENT '下一个节点名称',
  `next_node_desc` text CHARACTER SET utf8 COLLATE utf8_general_ci NULL COMMENT '下一节点说明',
  `next_node_status` varchar(32) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT 'processing' COMMENT '下一个流程时的订单状态',
  `plan_finish_minutes` int(11) NULL DEFAULT NULL COMMENT '预估完成时长（分）',
  `operate_role` varchar(32) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL COMMENT '等待操作用户角色',
  `prize_key` text CHARACTER SET utf8 COLLATE utf8_general_ci NULL COMMENT '关联价格key',
  `is_jump` tinyint(1) NULL DEFAULT 0 COMMENT '是否跳过（0否，1是）',
  `sort` int(11) NULL DEFAULT 1000 COMMENT '排序',
  `status` tinyint(1) NULL DEFAULT 1 COMMENT '状态(0禁用,1启用)',
  `has_used` tinyint(1) NULL DEFAULT 0 COMMENT '有使用(0否,1是)',
  `is_lock` tinyint(1) NULL DEFAULT 0 COMMENT '锁定（0：未锁，1：已锁）',
  `is_delete` tinyint(1) NULL DEFAULT 0 COMMENT '锁定（0：未删，1：已删）',
  `remark` text CHARACTER SET utf8 COLLATE utf8_general_ci NULL COMMENT '备注',
  `creater` varchar(50) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT '' COMMENT '创建者，user表',
  `updater` varchar(50) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT '' COMMENT '更新者，user表',
  `create_time` datetime(0) NULL DEFAULT CURRENT_TIMESTAMP(0) COMMENT '创建时间',
  `update_time` datetime(0) NULL DEFAULT CURRENT_TIMESTAMP(0) ON UPDATE CURRENT_TIMESTAMP(0) COMMENT '更新时间',
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE INDEX `node_key`(`node_key`, `next_node_key`, `company_id`) USING BTREE,
  INDEX `company_id`(`company_id`) USING BTREE,
  INDEX `create_time`(`create_time`) USING BTREE,
  INDEX `update_time`(`update_time`) USING BTREE
) ENGINE = InnoDB CHARACTER SET = utf8 COLLATE = utf8_general_ci COMMENT = '订单流程节点表：处理逻辑模板' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for w_order_free_bus
-- ----------------------------
DROP TABLE IF EXISTS `w_order_free_bus`;
CREATE TABLE `w_order_free_bus`  (
  `id` char(19) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL,
  `company_id` char(19) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT '',
  `order_id` char(19) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL COMMENT '订单id',
  `order_passenger_id` char(19) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT '0' COMMENT 'w_order_passenger表id',
  `bus_id` char(19) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT '' COMMENT '公交车车辆id',
  `sort` int(11) NULL DEFAULT 1000 COMMENT '排序',
  `status` tinyint(1) NULL DEFAULT 1 COMMENT '状态(0禁用,1启用)',
  `has_used` tinyint(1) NULL DEFAULT 0 COMMENT '有使用(0否,1是)',
  `is_lock` tinyint(1) NULL DEFAULT 0 COMMENT '锁定（0：未锁，1：已锁）',
  `is_delete` tinyint(1) NULL DEFAULT 0 COMMENT '锁定（0：未删，1：已删）',
  `remark` text CHARACTER SET utf8 COLLATE utf8_general_ci NULL COMMENT '备注',
  `creater` varchar(50) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT '' COMMENT '创建者，user表',
  `updater` varchar(50) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT '' COMMENT '更新者，user表',
  `create_time` datetime(0) NULL DEFAULT CURRENT_TIMESTAMP(0) COMMENT '创建时间',
  `update_time` datetime(0) NULL DEFAULT CURRENT_TIMESTAMP(0) ON UPDATE CURRENT_TIMESTAMP(0) COMMENT '更新时间',
  PRIMARY KEY (`id`) USING BTREE,
  INDEX `company_id`(`company_id`) USING BTREE,
  INDEX `bus_id`(`bus_id`) USING BTREE,
  INDEX `status`(`status`) USING BTREE
) ENGINE = InnoDB CHARACTER SET = utf8 COLLATE = utf8_general_ci COMMENT = '发车当天，订单免费公交' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for w_order_goods
-- ----------------------------
DROP TABLE IF EXISTS `w_order_goods`;
CREATE TABLE `w_order_goods`  (
  `id` char(19) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL,
  `company_id` int(11) NULL DEFAULT NULL,
  `order_id` char(19) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL COMMENT '订单id',
  `goods_id` char(19) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL COMMENT '商品id',
  `goods_name` text CHARACTER SET utf8 COLLATE utf8_general_ci NULL COMMENT '【冗】商品名称',
  `amount` decimal(10, 2) NULL DEFAULT 1000.00 COMMENT '数量',
  `unit_prize` decimal(10, 2) NULL DEFAULT 0.00 COMMENT '单价',
  `totalPrize` decimal(10, 2) GENERATED ALWAYS AS ((`amount` * ifnull(`unit_prize`,0))) VIRTUAL NULL,
  `has_deliver` tinyint(1) NULL DEFAULT NULL COMMENT '已发货？1已发货；0未发货',
  `sort` int(11) NULL DEFAULT 1000 COMMENT '排序',
  `status` tinyint(1) NULL DEFAULT 1 COMMENT '状态(0禁用,1启用)',
  `has_used` tinyint(1) NULL DEFAULT 0 COMMENT '有使用(0否,1是)',
  `is_lock` tinyint(1) NULL DEFAULT 0 COMMENT '锁定（0：未锁，1：已锁）',
  `is_delete` tinyint(1) NULL DEFAULT 0 COMMENT '锁定（0：未删，1：已删）',
  `remark` text CHARACTER SET utf8 COLLATE utf8_general_ci NULL COMMENT '备注',
  `creater` char(19) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT '' COMMENT '创建者，user表',
  `updater` char(19) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT '' COMMENT '更新者，user表',
  `create_time` datetime(0) NULL DEFAULT CURRENT_TIMESTAMP(0) COMMENT '创建时间',
  `update_time` datetime(0) NULL DEFAULT CURRENT_TIMESTAMP(0) ON UPDATE CURRENT_TIMESTAMP(0) COMMENT '更新时间',
  PRIMARY KEY (`id`) USING BTREE,
  INDEX `company_id`(`company_id`) USING BTREE,
  INDEX `order_id`(`order_id`) USING BTREE,
  INDEX `create_time`(`create_time`) USING BTREE,
  INDEX `update_time`(`update_time`) USING BTREE,
  INDEX `status`(`status`) USING BTREE,
  INDEX `is_delete`(`is_delete`) USING BTREE,
  INDEX `has_used`(`has_used`) USING BTREE,
  INDEX `is_lock`(`is_lock`) USING BTREE
) ENGINE = InnoDB CHARACTER SET = utf8 COLLATE = utf8_general_ci COMMENT = '订单商品表' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for w_order_group
-- ----------------------------
DROP TABLE IF EXISTS `w_order_group`;
CREATE TABLE `w_order_group`  (
  `id` varchar(32) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL,
  `company_id` varchar(32) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT '',
  `group_type` varchar(32) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL COMMENT '分组类型:buyer按客户;seller:供应商',
  `customer_id` varchar(32) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL COMMENT '客户/供应商：不一定用',
  `user_id` varchar(32) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL COMMENT '客户/供应商对接人',
  `order_type` varchar(32) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL COMMENT '订单类型：不一定用',
  `group_name` varchar(255) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL COMMENT '分组名称',
  `contract_id` varchar(32) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL COMMENT '合同id',
  `describe` text CHARACTER SET utf8 COLLATE utf8_general_ci NULL COMMENT '订单草稿本',
  `sort` int(11) NULL DEFAULT 1000 COMMENT '排序',
  `status` tinyint(1) NULL DEFAULT 1 COMMENT '状态(0禁用,1启用)',
  `has_used` tinyint(1) NULL DEFAULT 0 COMMENT '有使用(0否,1是)',
  `is_lock` tinyint(1) NULL DEFAULT 0 COMMENT '锁定（0：未锁，1：已锁）',
  `is_delete` tinyint(1) NULL DEFAULT 0 COMMENT '锁定（0：未删，1：已删）',
  `remark` text CHARACTER SET utf8 COLLATE utf8_general_ci NULL COMMENT '备注',
  `creater` varchar(50) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT '' COMMENT '创建者，user表',
  `updater` varchar(50) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT '' COMMENT '更新者，user表',
  `create_time` datetime(0) NULL DEFAULT CURRENT_TIMESTAMP(0) COMMENT '创建时间',
  `update_time` datetime(0) NULL DEFAULT CURRENT_TIMESTAMP(0) ON UPDATE CURRENT_TIMESTAMP(0) COMMENT '更新时间',
  PRIMARY KEY (`id`) USING BTREE,
  INDEX `company_id`(`company_id`) USING BTREE,
  INDEX `create_time`(`create_time`) USING BTREE,
  INDEX `update_time`(`update_time`) USING BTREE
) ENGINE = InnoDB CHARACTER SET = utf8 COLLATE = utf8_general_ci COMMENT = '订单分组表' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for w_order_group_order
-- ----------------------------
DROP TABLE IF EXISTS `w_order_group_order`;
CREATE TABLE `w_order_group_order`  (
  `id` varchar(32) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL,
  `company_id` varchar(32) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT '',
  `group_id` varchar(32) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL COMMENT '分组id',
  `order_id` varchar(32) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL COMMENT '订单id',
  `sort` int(11) NULL DEFAULT 1000 COMMENT '排序',
  `status` tinyint(1) NULL DEFAULT 1 COMMENT '状态(0禁用,1启用)',
  `has_used` tinyint(1) NULL DEFAULT 0 COMMENT '有使用(0否,1是)',
  `is_lock` tinyint(1) NULL DEFAULT 0 COMMENT '锁定（0：未锁，1：已锁）',
  `is_delete` tinyint(1) NULL DEFAULT 0 COMMENT '锁定（0：未删，1：已删）',
  `remark` text CHARACTER SET utf8 COLLATE utf8_general_ci NULL COMMENT '备注',
  `creater` varchar(50) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT '' COMMENT '创建者，user表',
  `updater` varchar(50) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT '' COMMENT '更新者，user表',
  `create_time` datetime(0) NULL DEFAULT CURRENT_TIMESTAMP(0) COMMENT '创建时间',
  `update_time` datetime(0) NULL DEFAULT CURRENT_TIMESTAMP(0) ON UPDATE CURRENT_TIMESTAMP(0) COMMENT '更新时间',
  PRIMARY KEY (`id`) USING BTREE,
  INDEX `company_id`(`company_id`) USING BTREE,
  INDEX `order_id`(`order_id`) USING BTREE,
  INDEX `create_time`(`create_time`) USING BTREE,
  INDEX `update_time`(`update_time`) USING BTREE
) ENGINE = InnoDB CHARACTER SET = utf8 COLLATE = utf8_general_ci COMMENT = '分组订单表' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for w_order_info_buyer
-- ----------------------------
DROP TABLE IF EXISTS `w_order_info_buyer`;
CREATE TABLE `w_order_info_buyer`  (
  `id` varchar(32) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL COMMENT 'w_border表的id',
  `company_id` varchar(32) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT '',
  `buyer_type` varchar(32) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL COMMENT '买家类型',
  `order_id` varchar(32) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT '' COMMENT '订单id',
  `buyer_id` varchar(32) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL COMMENT '持有人id',
  `customer_name` varchar(255) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL COMMENT '公司-名称',
  `customer_address` varchar(255) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL COMMENT '公司-地址',
  `customer_type` varchar(32) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL COMMENT '公司-类型：大陆公司；海外公司；个体户',
  `licence` varchar(255) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL COMMENT '公司-营业执照编号',
  `licence_pic` varchar(32) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL COMMENT '公司-营业执照图片',
  `official_pic` varchar(32) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL COMMENT '公司-企业公章',
  `auth_plate` varchar(255) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT '天猫' COMMENT '授权平台',
  `user_id` varchar(32) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT '' COMMENT '个人-用户id',
  `phone` varchar(32) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT '' COMMENT '个人-手机号码',
  `realname` varchar(255) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL COMMENT '个人-真实姓名',
  `id_no` varchar(18) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT '' COMMENT '个人-身份证号码',
  `address` varchar(255) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL COMMENT '个人-身份证地址',
  `pic_face` varchar(32) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT '' COMMENT '个人-身份证正面',
  `pic_back` varchar(32) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT '' COMMENT '个人-身份证反面',
  `real_face` varchar(32) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL COMMENT '个人-人脸照片',
  `audit_status` int(11) NULL DEFAULT 0 COMMENT '0待审核；1已同意；2已拒绝',
  `has_buyer_download` int(11) NULL DEFAULT NULL COMMENT '买家是否已下载资料：0否，1是[todo挪到买家表]',
  `brand_get` varchar(32) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT '' COMMENT '是否通知品牌认领：yes通知，no不通知，空 未选择',
  `brand_get_confirm` tinyint(1) NULL DEFAULT 0 COMMENT '品牌认领确认:0未认领，1已认领',
  `auth_pic_confirm` tinyint(1) NULL DEFAULT NULL COMMENT '授权书确认:0未确认，1已确认',
  `shop_confirm_status` varchar(32) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT '' COMMENT '下店情况：success 成功; extend 延期; fail 失败',
  `shop_ext_times` int(11) NULL DEFAULT 0 COMMENT '下店延期次数',
  `noary_confirm` int(11) NULL DEFAULT 0 COMMENT '公证主体信息确认：0未确认，1已确认',
  `order_finish_confirm` int(11) NULL DEFAULT 0 COMMENT '买家确认交易完成：0未确认，1已确认',
  `start_transfer` int(11) NULL DEFAULT NULL COMMENT '买家点击开始过户：0未开始，1已开始',
  `shop_apply` int(11) NULL DEFAULT 0 COMMENT '网店购买申请验店：0未申请，1已申请',
  `shop_confirm` int(11) NULL DEFAULT 0 COMMENT '网店购买买家验店：0未验店，1已验店',
  `sort` int(11) NULL DEFAULT 1000 COMMENT '排序',
  `status` tinyint(1) NULL DEFAULT 1 COMMENT '状态(0禁用,1启用)',
  `has_used` tinyint(1) NULL DEFAULT 0 COMMENT '有使用(0否,1是)',
  `is_lock` tinyint(1) NULL DEFAULT 0 COMMENT '锁定（0：未锁，1：已锁）',
  `is_delete` tinyint(1) NULL DEFAULT 0 COMMENT '锁定（0：未删，1：已删）',
  `remark` text CHARACTER SET utf8 COLLATE utf8_general_ci NULL COMMENT '备注',
  `creater` varchar(50) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT '' COMMENT '创建者，user表',
  `updater` varchar(50) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT '' COMMENT '更新者，user表',
  `create_time` datetime(0) NULL DEFAULT CURRENT_TIMESTAMP(0) COMMENT '创建时间',
  `update_time` datetime(0) NULL DEFAULT CURRENT_TIMESTAMP(0) ON UPDATE CURRENT_TIMESTAMP(0) COMMENT '更新时间',
  `buyer_licence_pic` varchar(32) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL COMMENT '营业执照',
  `buyer_tm_wts_pic` varchar(32) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL COMMENT '商标代理委托书',
  `buyer_tm_dzxk_pic` varchar(32) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL COMMENT '商标独占许可使用授权书：卖家传',
  `buyer_tm_tyzr_pic` varchar(32) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL COMMENT '商标同意转让证明',
  `buyer_tm_kdsq_pic` varchar(32) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL COMMENT '商标开店授权',
  PRIMARY KEY (`id`) USING BTREE,
  INDEX `company_id`(`company_id`) USING BTREE,
  INDEX `create_time`(`create_time`) USING BTREE,
  INDEX `update_time`(`update_time`) USING BTREE
) ENGINE = InnoDB CHARACTER SET = utf8 COLLATE = utf8_general_ci COMMENT = '买家信息' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for w_order_info_plate
-- ----------------------------
DROP TABLE IF EXISTS `w_order_info_plate`;
CREATE TABLE `w_order_info_plate`  (
  `id` varchar(32) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL COMMENT 'w_border表的id',
  `company_id` varchar(32) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT '',
  `order_id` varchar(32) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT '' COMMENT '订单id',
  `order_audit` int(11) NULL DEFAULT 0 COMMENT '商标状态确认',
  `has_doc_receive` int(11) NULL DEFAULT 0 COMMENT '买家和卖家已邮寄资料给平台：0未收齐，1已收齐',
  `tm_trans_accept_pic` varchar(32) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL COMMENT '商标转让受理通知书',
  `tm_agree_trans_pic` varchar(32) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL COMMENT '商标同意转让证明图',
  `tm_start_trans` int(11) NULL DEFAULT 0 COMMENT '商标开始过户：0未开始，1已开始',
  `tm_trans_finish` int(11) NULL DEFAULT 0 COMMENT '商标过户完成：0未完成，1已完成',
  `order_finish_confirm` int(11) NULL DEFAULT NULL COMMENT '平台确认交易完成：0未确认，1已确认',
  `order_finish_audit` tinyint(1) NULL DEFAULT NULL COMMENT '平台总管审核订单完成',
  `exp_accept_buyer` varchar(255) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL COMMENT '收件拍照留底-买家',
  `exp_accept_seller` varchar(255) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL COMMENT '收件拍照留底-卖家',
  `exp_send` varchar(255) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL COMMENT '寄件拍照留底',
  `sort` int(11) NULL DEFAULT 1000 COMMENT '排序',
  `status` tinyint(1) NULL DEFAULT 1 COMMENT '状态(0禁用,1启用)',
  `has_used` tinyint(1) NULL DEFAULT 0 COMMENT '有使用(0否,1是)',
  `is_lock` tinyint(1) NULL DEFAULT 0 COMMENT '锁定（0：未锁，1：已锁）',
  `is_delete` tinyint(1) NULL DEFAULT 0 COMMENT '锁定（0：未删，1：已删）',
  `remark` text CHARACTER SET utf8 COLLATE utf8_general_ci NULL COMMENT '备注',
  `creater` varchar(50) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT '' COMMENT '创建者，user表',
  `updater` varchar(50) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT '' COMMENT '更新者，user表',
  `create_time` datetime(0) NULL DEFAULT CURRENT_TIMESTAMP(0) COMMENT '创建时间',
  `update_time` datetime(0) NULL DEFAULT CURRENT_TIMESTAMP(0) ON UPDATE CURRENT_TIMESTAMP(0) COMMENT '更新时间',
  PRIMARY KEY (`id`) USING BTREE,
  INDEX `company_id`(`company_id`) USING BTREE,
  INDEX `create_time`(`create_time`) USING BTREE,
  INDEX `update_time`(`update_time`) USING BTREE
) ENGINE = InnoDB CHARACTER SET = utf8 COLLATE = utf8_general_ci COMMENT = '买家信息' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for w_order_info_seller
-- ----------------------------
DROP TABLE IF EXISTS `w_order_info_seller`;
CREATE TABLE `w_order_info_seller`  (
  `id` varchar(32) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL COMMENT 'w_border表的id',
  `company_id` varchar(32) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT '',
  `seller_type` varchar(32) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL COMMENT '卖家类型',
  `order_id` varchar(32) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT '' COMMENT '订单id',
  `holder_id` varchar(32) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT '' COMMENT '购买人id',
  `customer_name` varchar(255) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL COMMENT '公司-名称',
  `customer_address` varchar(255) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL COMMENT '公司-地址',
  `customer_type` varchar(32) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL COMMENT '公司-类型：大陆公司；海外公司；个体户',
  `licence` varchar(255) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL COMMENT '公司-营业执照编号',
  `licence_pic` varchar(32) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL COMMENT '公司-营业执照图片',
  `official_pic` varchar(32) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL COMMENT '公司-企业公章',
  `auth_plate` varchar(255) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL COMMENT '授权平台',
  `auth_pic` varchar(255) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL COMMENT '授权书',
  `noary_start` varchar(32) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT '0' COMMENT '卖家点击开始公证:0未点击，1已点击',
  `seller_user_id` varchar(32) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT '' COMMENT '个人-用户id',
  `has_download` int(11) NULL DEFAULT NULL COMMENT '卖家是否已下载资料：0否，1是',
  `phone` varchar(32) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT '' COMMENT '个人-手机号码',
  `realname` varchar(255) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL COMMENT '个人-真实姓名',
  `id_no` varchar(18) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT '' COMMENT '个人-身份证号码',
  `address` varchar(255) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL COMMENT '个人-身份证地址',
  `pic_face` varchar(32) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT '' COMMENT '个人-身份证正面',
  `pic_back` varchar(32) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT '' COMMENT '个人-身份证反面',
  `real_face` varchar(32) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL COMMENT '个人-人脸照片',
  `tm_kdsq` varchar(32) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL COMMENT '商标开店授权pic',
  `audit_status` int(11) NULL DEFAULT 0 COMMENT '0待审核；1已同意；2已拒绝',
  `order_finish_confirm` int(11) NULL DEFAULT NULL COMMENT '卖家确认交易完成：0未确认，1已确认',
  `tm_rent_monthes` int(11) NULL DEFAULT NULL COMMENT '商标租用月数',
  `tm_rent_plate` varchar(32) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL COMMENT '商标租用平台',
  `shop_confirm` int(11) NULL DEFAULT 0 COMMENT '网店购买卖家验店：0未验店，1已验店',
  `sort` int(11) NULL DEFAULT 1000 COMMENT '排序',
  `status` tinyint(1) NULL DEFAULT 1 COMMENT '状态(0禁用,1启用)',
  `has_used` tinyint(1) NULL DEFAULT 0 COMMENT '有使用(0否,1是)',
  `is_lock` tinyint(1) NULL DEFAULT 0 COMMENT '锁定（0：未锁，1：已锁）',
  `is_delete` tinyint(1) NULL DEFAULT 0 COMMENT '锁定（0：未删，1：已删）',
  `remark` text CHARACTER SET utf8 COLLATE utf8_general_ci NULL COMMENT '备注',
  `creater` varchar(50) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT '' COMMENT '创建者，user表',
  `updater` varchar(50) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT '' COMMENT '更新者，user表',
  `create_time` datetime(0) NULL DEFAULT CURRENT_TIMESTAMP(0) COMMENT '创建时间',
  `update_time` datetime(0) NULL DEFAULT CURRENT_TIMESTAMP(0) ON UPDATE CURRENT_TIMESTAMP(0) COMMENT '更新时间',
  `seller_licence_pic` varchar(32) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL COMMENT '营业执照',
  `seller_tm_wts_pic` varchar(32) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT '' COMMENT '商标代理委托书',
  `seller_tm_dzxk_pic` varchar(32) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT '' COMMENT '商标独占许可使用授权书：卖家传',
  `seller_tm_tyzr_pic` varchar(32) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT '' COMMENT '商标同意转让证明',
  `seller_tm_kdsq_pic` varchar(32) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT '' COMMENT '商标开店授权',
  `seller_tm_pic` varchar(32) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT '' COMMENT '商标证',
  `seller_tm_noary_pic` varchar(32) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT '' COMMENT '商标公证书',
  `os_trans_licence_pic` varchar(32) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT '' COMMENT '变更后的营业执照',
  PRIMARY KEY (`id`) USING BTREE,
  INDEX `company_id`(`company_id`) USING BTREE,
  INDEX `create_time`(`create_time`) USING BTREE,
  INDEX `update_time`(`update_time`) USING BTREE
) ENGINE = InnoDB CHARACTER SET = utf8 COLLATE = utf8_general_ci COMMENT = '卖家信息' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for w_order_manage
-- ----------------------------
DROP TABLE IF EXISTS `w_order_manage`;
CREATE TABLE `w_order_manage`  (
  `id` varchar(32) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL,
  `company_id` varchar(32) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT '',
  `order_type` varchar(32) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL COMMENT '订单类型',
  `manage_user_id` varchar(32) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL COMMENT '管理员',
  `accept_msg` tinyint(1) NULL DEFAULT 0 COMMENT '接收推送消息',
  `sort` int(11) NULL DEFAULT 1000 COMMENT '排序',
  `status` tinyint(1) NULL DEFAULT 1 COMMENT '状态(0禁用,1启用)',
  `has_used` tinyint(1) NULL DEFAULT 0 COMMENT '有使用(0否,1是)',
  `is_lock` tinyint(1) NULL DEFAULT 0 COMMENT '锁定（0：未锁，1：已锁）',
  `is_delete` tinyint(1) NULL DEFAULT 0 COMMENT '锁定（0：未删，1：已删）',
  `remark` text CHARACTER SET utf8 COLLATE utf8_general_ci NULL COMMENT '备注',
  `creater` varchar(50) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT '' COMMENT '创建者，user表',
  `updater` varchar(50) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT '' COMMENT '更新者，user表',
  `create_time` datetime(0) NULL DEFAULT CURRENT_TIMESTAMP(0) COMMENT '创建时间',
  `update_time` datetime(0) NULL DEFAULT CURRENT_TIMESTAMP(0) ON UPDATE CURRENT_TIMESTAMP(0) COMMENT '更新时间',
  PRIMARY KEY (`id`) USING BTREE,
  INDEX `company_id`(`company_id`) USING BTREE,
  INDEX `create_time`(`create_time`) USING BTREE,
  INDEX `update_time`(`update_time`) USING BTREE
) ENGINE = InnoDB CHARACTER SET = utf8 COLLATE = utf8_general_ci COMMENT = '订单管理员' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for w_order_passenger
-- ----------------------------
DROP TABLE IF EXISTS `w_order_passenger`;
CREATE TABLE `w_order_passenger`  (
  `id` char(19) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL,
  `company_id` char(19) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT '',
  `order_id` char(19) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL COMMENT '订单表id',
  `circuit_bus_id` char(19) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL COMMENT '班次：冗余',
  `circuit_id` char(32) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL COMMENT '线路id：冗余',
  `user_id` char(19) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL COMMENT '20230215:乘客捆绑用户',
  `passenger_id` char(19) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL COMMENT '乘客，逗号隔',
  `realname` varchar(64) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL COMMENT '【冗】姓名',
  `school_no` int(11) NULL DEFAULT NULL COMMENT '[20220427]学校号数',
  `id_no` varchar(18) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL COMMENT '【冗】身份证号',
  `phone` varchar(15) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL COMMENT '【冗】手机号码',
  `tag` varchar(255) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL COMMENT '购票标签',
  `from_station_id` char(19) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL,
  `to_station_id` char(19) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL,
  `from_station_str` varchar(64) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT '' COMMENT '20220910上车站名(包车报名)',
  `seat_no` int(8) NULL DEFAULT NULL COMMENT '座位号',
  `to_station_str` varchar(64) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL COMMENT '20220910下车站名(包车报名)',
  `prize` decimal(10, 2) NULL DEFAULT NULL COMMENT '订单总价',
  `is_pay` tinyint(1) NULL DEFAULT 0 COMMENT '已支付',
  `is_ticked` int(11) NULL DEFAULT 0 COMMENT '0未检，1已检',
  `is_ref` tinyint(1) NULL DEFAULT 0 COMMENT '是否退票；0否；1是',
  `ticket_user_id` char(19) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL COMMENT '检票用户id',
  `ticket_arrange_id` char(19) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL COMMENT 'bus_arrange表',
  `ticket_time` datetime(0) NULL DEFAULT NULL COMMENT '检票时间',
  `ticket_source` varchar(32) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL COMMENT '0909检票来源[scan:扫码；check:点名：admin:后台]',
  `ticket_notice` tinyint(1) NULL DEFAULT 0 COMMENT '20220923:检票通知客户状态',
  `bao_bus_id` char(19) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL COMMENT '20230215:趟次编号',
  `bus_id` char(19) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL COMMENT '乘坐车辆',
  `hasBus` tinyint(1) GENERATED ALWAYS AS (if(`bus_id`,1,0)) VIRTUAL COMMENT '有排车' NULL,
  `sort` int(11) NULL DEFAULT 1000 COMMENT '排序',
  `status` tinyint(1) NULL DEFAULT 1 COMMENT '状态(0禁用,1启用)',
  `has_used` tinyint(1) NULL DEFAULT 0 COMMENT '有使用(0否,1是)',
  `is_lock` tinyint(1) NULL DEFAULT 0 COMMENT '锁定（0：未锁，1：已锁）',
  `is_delete` tinyint(1) NULL DEFAULT 0 COMMENT '锁定（0：未删，1：已删）',
  `remark` text CHARACTER SET utf8 COLLATE utf8_general_ci NULL COMMENT '备注',
  `creater` char(19) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT '' COMMENT '创建者，user表',
  `updater` char(19) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT '' COMMENT '更新者，user表',
  `create_time` datetime(0) NULL DEFAULT CURRENT_TIMESTAMP(0) COMMENT '创建时间',
  `update_time` datetime(0) NULL DEFAULT CURRENT_TIMESTAMP(0) ON UPDATE CURRENT_TIMESTAMP(0) COMMENT '更新时间',
  PRIMARY KEY (`id`) USING BTREE,
  INDEX `circuit_id`(`circuit_id`) USING BTREE,
  INDEX `circuit_bus_id`(`circuit_bus_id`) USING BTREE,
  INDEX `passenger_id`(`passenger_id`) USING BTREE,
  INDEX `from_station_id`(`from_station_id`) USING BTREE,
  INDEX `to_station_id`(`to_station_id`) USING BTREE,
  INDEX `id_no`(`id_no`) USING BTREE,
  INDEX `order_id`(`order_id`) USING BTREE
) ENGINE = InnoDB CHARACTER SET = utf8 COLLATE = utf8_general_ci COMMENT = '订单乘客表' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for w_order_shopping_cart
-- ----------------------------
DROP TABLE IF EXISTS `w_order_shopping_cart`;
CREATE TABLE `w_order_shopping_cart`  (
  `id` varchar(32) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL,
  `company_id` varchar(32) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT '',
  `user_id` varchar(32) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT '' COMMENT '用户id',
  `shop_id` varchar(32) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT '' COMMENT '店铺id',
  `goods_id` varchar(32) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL COMMENT '商品id',
  `goods_number` int(11) NULL DEFAULT NULL COMMENT '商品数量',
  `is_valid` int(11) NULL DEFAULT 1 COMMENT '商品是否可用，0否1是',
  `sort` int(11) NULL DEFAULT 1000 COMMENT '排序',
  `status` tinyint(1) NULL DEFAULT 1 COMMENT '状态(0禁用,1启用)',
  `has_used` tinyint(1) NULL DEFAULT 0 COMMENT '有使用(0否,1是)',
  `is_lock` tinyint(1) NULL DEFAULT 0 COMMENT '锁定（0：未锁，1：已锁）',
  `is_delete` tinyint(1) NULL DEFAULT 0 COMMENT '锁定（0：未删，1：已删）',
  `remark` text CHARACTER SET utf8 COLLATE utf8_general_ci NULL COMMENT '备注',
  `creater` varchar(32) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT '' COMMENT '创建者，user表',
  `updater` varchar(32) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT '' COMMENT '更新者，user表',
  `create_time` datetime(0) NULL DEFAULT CURRENT_TIMESTAMP(0) COMMENT '创建时间',
  `update_time` datetime(0) NULL DEFAULT CURRENT_TIMESTAMP(0) ON UPDATE CURRENT_TIMESTAMP(0) COMMENT '更新时间',
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE INDEX `user_id_2`(`user_id`, `goods_id`) USING BTREE COMMENT '每个用户每商品只一条记录，多的按数量',
  INDEX `company_id`(`company_id`) USING BTREE,
  INDEX `user_id`(`user_id`) USING BTREE,
  INDEX `shop_id`(`shop_id`) USING BTREE,
  INDEX `goods_id`(`goods_id`) USING BTREE,
  INDEX `create_time`(`create_time`) USING BTREE,
  INDEX `update_time`(`update_time`) USING BTREE
) ENGINE = InnoDB CHARACTER SET = utf8 COLLATE = utf8_general_ci COMMENT = '购物车，放订单模块' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for w_order_type
-- ----------------------------
DROP TABLE IF EXISTS `w_order_type`;
CREATE TABLE `w_order_type`  (
  `id` char(19) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL,
  `company_id` int(11) NULL DEFAULT NULL COMMENT '公司id',
  `type_key` char(19) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT '' COMMENT '订单类型key',
  `type_name` char(19) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT '',
  `sort` int(11) NULL DEFAULT 1000 COMMENT '排序',
  `status` tinyint(1) NULL DEFAULT 1 COMMENT '状态(0禁用,1启用)',
  `has_used` tinyint(1) NULL DEFAULT 0 COMMENT '有使用(0否,1是)',
  `is_lock` tinyint(1) NULL DEFAULT 0 COMMENT '锁定（0：未锁，1：已锁）',
  `is_delete` tinyint(1) NULL DEFAULT 0 COMMENT '锁定（0：未删，1：已删）',
  `remark` text CHARACTER SET utf8 COLLATE utf8_general_ci NULL COMMENT '备注',
  `creater` varchar(50) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT '' COMMENT '创建者，user表',
  `updater` varchar(50) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT '' COMMENT '更新者，user表',
  `create_time` datetime(0) NULL DEFAULT CURRENT_TIMESTAMP(0) COMMENT '创建时间',
  `update_time` datetime(0) NULL DEFAULT CURRENT_TIMESTAMP(0) ON UPDATE CURRENT_TIMESTAMP(0) COMMENT '更新时间',
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE INDEX `company_id_2`(`company_id`, `type_key`) USING BTREE,
  INDEX `company_id`(`company_id`) USING BTREE
) ENGINE = InnoDB CHARACTER SET = utf8 COLLATE = utf8_general_ci COMMENT = '订单类型：每个客户有不同的订单类型' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for w_order_user_ext
-- ----------------------------
DROP TABLE IF EXISTS `w_order_user_ext`;
CREATE TABLE `w_order_user_ext`  (
  `id` char(19) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL,
  PRIMARY KEY (`id`) USING BTREE
) ENGINE = InnoDB CHARACTER SET = utf8 COLLATE = utf8_general_ci ROW_FORMAT = Dynamic;

SET FOREIGN_KEY_CHECKS = 1;
