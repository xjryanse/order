/*
 Navicat Premium Data Transfer

 Source Server         : ydzb_xiesemi_cn
 Source Server Type    : MySQL
 Source Server Version : 50648
 Source Host           : 121.204.207.95:3399
 Source Schema         : ydzb_xiesemi_cn

 Target Server Type    : MySQL
 Target Server Version : 50648
 File Encoding         : 65001

 Date: 15/10/2020 16:52:43
*/

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ----------------------------
-- Table structure for w_order
-- ----------------------------
CREATE TABLE `w_order` (
  `id` varchar(32) NOT NULL,
  `app_id` varchar(32) DEFAULT '',
  `company_id` varchar(32) DEFAULT '' COMMENT '公司id',
  `val` text COMMENT '订单json',
  `dept_id` varchar(32) DEFAULT '' COMMENT '归属部门id',
  `order_type` varchar(32) DEFAULT NULL COMMENT '订单类型：中文……',
  `order_sn` varchar(64) DEFAULT NULL COMMENT '订单号',
  `pre_order_id` varchar(64) DEFAULT NULL COMMENT '前序订单id',
  `seller_customer_id` varchar(32) DEFAULT NULL COMMENT '销售客户id（适用于中介平台）',
  `seller_user_id` varchar(32) DEFAULT NULL COMMENT '销售用户id',
  `customer_id` varchar(32) DEFAULT '' COMMENT '下单客户id，customer表id',
  `customer_dept_id` varchar(32) DEFAULT NULL COMMENT '下单客户部门id',
  `user_id` varchar(32) DEFAULT '' COMMENT '下单用户id，user表id',
  `rec_user_id` varchar(32) DEFAULT '' COMMENT '推荐人id，user表id',
  `cover_pic` varchar(32) DEFAULT NULL COMMENT '订单列表图标，单图',
  `order_status` varchar(32) DEFAULT '' COMMENT '订单状态：\r\nneedpay待支付\r\nprocessing进行中、\r\nfinish已完成、\r\nclose已关闭',
  `sub_order_status` varchar(32) DEFAULT '' COMMENT '订单子状态：',
  `pre_prize` float(10,2) DEFAULT NULL COMMENT '最小定金，关联发车付款进度',
  `order_prize` float(10,2) DEFAULT NULL COMMENT '订单金额，关联发车付款进度',
  `pay_prize` float(10,2) DEFAULT NULL COMMENT '已支付金额',
  `refund_prize` float(10,2) DEFAULT NULL COMMENT '已退款金额',
  `pay_progress` float(4,2) DEFAULT NULL COMMENT '付款进度',
  `do_pay_progress` float(4,2) DEFAULT NULL COMMENT '订单执行所需付款进度',
  `sort` int(11) DEFAULT '1000' COMMENT '排序',
  `status` tinyint(1) DEFAULT '1' COMMENT '状态(0禁用,1启用)',
  `has_used` tinyint(1) DEFAULT '0' COMMENT '有使用(0否,1是)',
  `is_lock` tinyint(1) DEFAULT '0' COMMENT '锁定（0：未锁，1：已锁）',
  `is_delete` tinyint(1) DEFAULT '0' COMMENT '锁定（0：未删，1：已删）',
  `remark` text COMMENT '备注',
  `creater` varchar(50) DEFAULT '' COMMENT '创建者，user表',
  `updater` varchar(50) DEFAULT '' COMMENT '更新者，user表',
  `create_time` datetime DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  `update_time` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
  PRIMARY KEY (`id`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='订单表';

-- ----------------------------
-- Table structure for w_order_flow_node
-- ----------------------------
CREATE TABLE `w_order_flow_node` (
  `id` varchar(32) NOT NULL,
  `app_id` varchar(32) DEFAULT '',
  `company_id` varchar(32) DEFAULT '',
  `order_id` varchar(32) DEFAULT NULL COMMENT '订单id',
  `node_key` varchar(32) DEFAULT NULL COMMENT '节点key',
  `node_name` varchar(64) DEFAULT NULL COMMENT '节点名称',
  `flow_status` varchar(32) DEFAULT NULL COMMENT '流程状态：todo待处理；doing进行中；finish已完成',
  `finish_time` datetime DEFAULT NULL COMMENT '完成时间',
  `operate_role` varchar(32) DEFAULT NULL COMMENT '等待操作用户角色',
  `operate_user_id` varchar(32) DEFAULT NULL COMMENT '等待操作用户角色',
  `sort` int(11) DEFAULT '1000' COMMENT '排序',
  `status` tinyint(1) DEFAULT '1' COMMENT '状态(0禁用,1启用)',
  `has_used` tinyint(1) DEFAULT '0' COMMENT '有使用(0否,1是)',
  `is_lock` tinyint(1) DEFAULT '0' COMMENT '锁定（0：未锁，1：已锁）',
  `is_delete` tinyint(1) DEFAULT '0' COMMENT '锁定（0：未删，1：已删）',
  `remark` text COMMENT '备注',
  `creater` varchar(50) DEFAULT '' COMMENT '创建者，user表',
  `updater` varchar(50) DEFAULT '' COMMENT '更新者，user表',
  `create_time` datetime DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  `update_time` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
  PRIMARY KEY (`id`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='订单流程节点表';

-- ----------------------------
-- 购物车
-- ----------------------------
CREATE TABLE `w_order_shopping_cart` (
  `id` varchar(32) NOT NULL,
  `app_id` varchar(32) DEFAULT '',
  `company_id` varchar(32) DEFAULT '',
  `user_id` varchar(50) DEFAULT '' COMMENT '用户id',
  `goods_id` varchar(100) DEFAULT NULL COMMENT '商品id',
  `goods_number` tinyint(1) DEFAULT NULL COMMENT '商品数量',
  `sort` int(11) DEFAULT '1000' COMMENT '排序',
  `status` tinyint(1) DEFAULT '1' COMMENT '状态(0禁用,1启用)',
  `has_used` tinyint(1) DEFAULT '0' COMMENT '有使用(0否,1是)',
  `is_lock` tinyint(1) DEFAULT '0' COMMENT '锁定（0：未锁，1：已锁）',
  `is_delete` tinyint(1) DEFAULT '0' COMMENT '锁定（0：未删，1：已删）',
  `remark` text COMMENT '备注',
  `creater` varchar(50) DEFAULT '' COMMENT '创建者，user表',
  `updater` varchar(50) DEFAULT '' COMMENT '更新者，user表',
  `create_time` datetime DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  `update_time` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
  PRIMARY KEY (`id`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='购物车，放订单模块';
