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

 Date: 01/10/2021 23:00:58
*/

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ----------------------------
-- Table structure for w_order
-- ----------------------------
DROP TABLE IF EXISTS `w_order`;
CREATE TABLE `w_order`  (
  `id` char(19) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL,
  `company_id` char(19) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT '' COMMENT '公司id',
  `shop_id` char(19) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT '' COMMENT '店铺id',
  `goods_id` char(19) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL COMMENT '商品id（单商品可用）',
  `amount` decimal(10, 2) NULL DEFAULT NULL COMMENT '商品数量（单商品冗余存）',
  `goods_name` varchar(32) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL COMMENT '商品名称：goods表来源',
  `goods_cate` varchar(32) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT '' COMMENT '[冗]商品类型：用于筛选',
  `goods_table` varchar(255) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT '' COMMENT '商品原始表',
  `goods_table_id` char(19) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT '' COMMENT '商品原始表id',
  `from_station_id` char(19) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL COMMENT '发货站点（上车站点）',
  `to_station_id` char(19) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL COMMENT '收货站点（下车站点）',
  `kilometre` decimal(10, 2) NULL DEFAULT NULL COMMENT '公里数',
  `plan_start_time` datetime(0) NULL DEFAULT NULL COMMENT '订单开始时间（包车用）',
  `plan_finish_time` datetime(0) NULL DEFAULT NULL COMMENT '订单结束时间（包车用）',
  `number` int(11) NULL DEFAULT 1 COMMENT '商品数量',
  `val` text CHARACTER SET utf8 COLLATE utf8_general_ci NULL COMMENT '订单json',
  `dept_id` char(32) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT '' COMMENT '归属部门id',
  `order_type` varchar(32) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT 'normal' COMMENT '订单类型：tm_auth；tm_rent；tm_buy；os_buy；公证noary',
  `role_type` varchar(32) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT 'personal' COMMENT '下单客户类型：customer；personal',
  `order_sn` varchar(64) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL COMMENT '订单号',
  `pre_order_id` char(19) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL COMMENT '前序订单id',
  `seller_customer_id` char(19) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL COMMENT '销售客户id（适用于中介平台）',
  `seller_user_id` char(19) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL COMMENT '销售用户id',
  `customer_id` char(19) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT '' COMMENT '下单客户id，customer表id',
  `customer_dept_id` char(19) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL COMMENT '下单客户部门id',
  `user_id` char(19) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT '' COMMENT '下单用户id，user表id',
  `rec_user_id` char(19) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT '' COMMENT '推荐人id，user表id',
  `cover_pic` varchar(32) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL COMMENT '订单列表图标，单图',
  `order_status` varchar(32) CHARACTER SET utf8 COLLATE utf8_general_ci GENERATED ALWAYS AS (if(`is_cancel`,if(`is_complete`,_utf8mb4'close',_utf8mb4'cancel'),if(`is_complete`,_utf8mb4'finish',if((`pre_prize` > `pay_prize`),_utf8mb4'needpay',if((`has_deliver` = 0),_utf8mb4'toDeliver',if((`has_receive` = 0),_utf8mb4'toReceive',_utf8mb4'processing')))))) VIRTUAL COMMENT '订单状态：计算\r\n\r\n订单状态：\r\nneedpay待支付\r\nprocessing进行中、\r\nfinish已完成、\r\nclose已关闭' NULL,
  `source` varchar(32) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT '' COMMENT '来源：admin；wePub',
  `sub_order_status` varchar(32) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT '' COMMENT '订单子状态：',
  `need_invoice` tinyint(1) NULL DEFAULT NULL COMMENT '需开票?0否；1是',
  `coupon_id` char(19) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL COMMENT '用户使用优惠券时记录券id',
  `pre_prize` decimal(10, 2) NULL DEFAULT 0.00 COMMENT '(￥)最小定金，关联发车付款进度',
  `order_prize` decimal(10, 2) NULL DEFAULT 0.00 COMMENT '(￥)订单金额，关联发车付款进度',
  `deliver_prize` decimal(10, 2) NULL DEFAULT NULL COMMENT '(￥)收客户配送费，',
  `coupon_prize` decimal(10, 2) NULL DEFAULT NULL COMMENT '(￥)客户优惠金额，一般是优惠券的金额，折扣券为计算后的金额',
  `pay_prize` decimal(10, 2) NULL DEFAULT 0.00 COMMENT '(￥)已收金额',
  `outcome_prize` decimal(10, 2) NULL DEFAULT 0.00 COMMENT '(￥)已付金额',
  `refund_prize` decimal(10, 2) NULL DEFAULT 0.00 COMMENT '(￥)收退金额',
  `outcome_refund_prize` decimal(10, 2) NULL DEFAULT 0.00 COMMENT '(￥)付退金额',
  `cost_prize` decimal(10, 2) NULL DEFAULT 0.00 COMMENT '(￥)成本金额',
  `distri_prize` decimal(10, 2) NULL DEFAULT 0.00 COMMENT '(￥)已分润金额',
  `final_prize` decimal(10, 2) NULL DEFAULT 0.00 COMMENT '(￥)毛利',
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
  `addr_realname` varchar(32) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL COMMENT '收货人',
  `addr_phone` varchar(15) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL COMMENT '收货手机',
  `addr_province` varchar(32) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL COMMENT '收货省',
  `addr_city` varchar(32) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL COMMENT '收货市',
  `addr_county` varchar(32) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL COMMENT '收货县',
  `addr_address` text CHARACTER SET utf8 COLLATE utf8_general_ci NULL COMMENT '收货具体地址',
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
  INDEX `company_id`(`company_id`) USING BTREE,
  INDEX `goods_id`(`goods_id`) USING BTREE,
  INDEX `dept_id`(`dept_id`) USING BTREE,
  INDEX `order_sn`(`order_sn`) USING BTREE,
  INDEX `pre_order_id`(`pre_order_id`) USING BTREE,
  INDEX `seller_customer_id`(`seller_customer_id`) USING BTREE,
  INDEX `seller_user_id`(`seller_user_id`) USING BTREE,
  INDEX `customer_dept_id`(`customer_dept_id`) USING BTREE,
  INDEX `seller_customer_id_2`(`customer_id`) USING BTREE,
  INDEX `user_id`(`user_id`) USING BTREE,
  INDEX `rec_user_id`(`rec_user_id`) USING BTREE,
  INDEX `shop_id`(`shop_id`) USING BTREE,
  INDEX `create_time`(`create_time`) USING BTREE,
  INDEX `update_time`(`update_time`) USING BTREE,
  INDEX `order_type`(`order_type`) USING BTREE,
  INDEX `goods_table_id`(`goods_table_id`) USING BTREE,
  INDEX `is_cancel`(`is_cancel`) USING BTREE,
  INDEX `is_delete`(`is_delete`) USING BTREE
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
  `prize` decimal(10, 2) NULL DEFAULT 0.00 COMMENT '业务拆单价',
  `start_time` datetime(0) NULL DEFAULT NULL COMMENT '开始时间',
  `end_time` datetime(0) NULL DEFAULT NULL COMMENT '结束时间',
  `is_cancel` int(11) NULL DEFAULT 0 COMMENT '是否取消：0否，1是',
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
  INDEX `bus_type_id`(`bus_type_id`) USING BTREE,
  INDEX `bus_id`(`bus_id`) USING BTREE,
  INDEX `status`(`status`) USING BTREE,
  INDEX `prize`(`prize`) USING BTREE,
  INDEX `is_cancel`(`is_cancel`) USING BTREE,
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
  `start_time` datetime(0) NULL DEFAULT NULL COMMENT '发车时间',
  `end_time` datetime(0) NULL DEFAULT NULL COMMENT '结束时间',
  `bus_id` char(19) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT '' COMMENT '车辆id',
  `driver_id` char(19) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT '' COMMENT '司机id',
  `distribute_prize` decimal(10, 2) NULL DEFAULT NULL COMMENT '司机分派金额原始',
  `calculate_prize` decimal(10, 2) NULL DEFAULT NULL COMMENT '抽佣计算金额',
  `rate` decimal(10, 2) NULL DEFAULT NULL COMMENT '抽佣比例',
  `grant_money` decimal(10, 2) NULL DEFAULT NULL COMMENT '司机抽点金额',
  `eat_money` decimal(10, 2) NULL DEFAULT NULL COMMENT '餐费',
  `other_money` varchar(255) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT '0' COMMENT '其他补贴',
  `is_grant` int(1) NULL DEFAULT 0 COMMENT '司机抽点是否发放0否，1是',
  `status` varchar(16) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT '0' COMMENT '车辆状态todo未执行，doing进行中，finished已到达，cancel已取消',
  `remark` text CHARACTER SET utf8 COLLATE utf8_general_ci NULL COMMENT '备注',
  `create_time` datetime(0) NULL DEFAULT CURRENT_TIMESTAMP(0),
  `update_time` datetime(0) NULL DEFAULT CURRENT_TIMESTAMP(0) ON UPDATE CURRENT_TIMESTAMP(0),
  PRIMARY KEY (`id`) USING BTREE,
  INDEX `company_id`(`company_id`) USING BTREE,
  INDEX `start_time`(`start_time`) USING BTREE,
  INDEX `end_time`(`end_time`) USING BTREE,
  INDEX `bus_id`(`bus_id`) USING BTREE,
  INDEX `driver_id`(`driver_id`) USING BTREE,
  INDEX `distribute_prize`(`distribute_prize`) USING BTREE,
  INDEX `calculate_prize`(`calculate_prize`) USING BTREE,
  INDEX `grant_money`(`grant_money`) USING BTREE,
  INDEX `eat_money`(`eat_money`) USING BTREE,
  INDEX `other_money`(`other_money`) USING BTREE,
  INDEX `is_grant`(`is_grant`) USING BTREE,
  INDEX `status`(`status`) USING BTREE
) ENGINE = InnoDB CHARACTER SET = utf8 COLLATE = utf8_general_ci COMMENT = '包车车辆司机' ROW_FORMAT = Dynamic;

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
  `order_id` char(19) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL COMMENT '订单id',
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
  `company_id` varchar(32) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT '',
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
  INDEX `company_id`(`company_id`) USING BTREE,
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
-- Table structure for w_order_goods
-- ----------------------------
DROP TABLE IF EXISTS `w_order_goods`;
CREATE TABLE `w_order_goods`  (
  `id` char(19) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL,
  `company_id` int(11) NULL DEFAULT NULL,
  `order_id` char(19) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL COMMENT '订单id',
  `goods_id` char(19) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL COMMENT '商品id',
  `goods_name` varchar(32) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL COMMENT '【冗】商品名称',
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
-- Table structure for w_order_passenger
-- ----------------------------
DROP TABLE IF EXISTS `w_order_passenger`;
CREATE TABLE `w_order_passenger`  (
  `id` char(19) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL,
  `company_id` char(19) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT '',
  `order_id` char(19) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL COMMENT '订单表id',
  `circuit_bus_id` char(19) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL COMMENT '班次：冗余',
  `circuit_id` char(32) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL COMMENT '线路id：冗余',
  `passenger_id` char(19) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL COMMENT '乘客，逗号隔',
  `from_station_id` int(11) NULL DEFAULT NULL,
  `to_station_id` int(11) NULL DEFAULT NULL,
  `seat_id` int(11) NULL DEFAULT NULL COMMENT '座位号',
  `prize` float(10, 2) NULL DEFAULT NULL COMMENT '订单总价',
  `is_ticked` int(1) NULL DEFAULT 0 COMMENT '0未检，1已检',
  `ticket_user_id` int(11) NULL DEFAULT NULL COMMENT '检票用户id',
  `ticket_arrange_id` int(11) NULL DEFAULT NULL COMMENT 'bus_arrange表',
  `ticket_time` datetime(0) NULL DEFAULT NULL COMMENT '检票时间',
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
  INDEX `create_time`(`create_time`) USING BTREE,
  INDEX `update_time`(`update_time`) USING BTREE,
  INDEX `status`(`status`) USING BTREE
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
-- Table structure for w_order_user_ext
-- ----------------------------
DROP TABLE IF EXISTS `w_order_user_ext`;
CREATE TABLE `w_order_user_ext`  (
  `id` char(19) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL,
  PRIMARY KEY (`id`) USING BTREE
) ENGINE = InnoDB CHARACTER SET = utf8 COLLATE = utf8_general_ci ROW_FORMAT = Dynamic;

SET FOREIGN_KEY_CHECKS = 1;
