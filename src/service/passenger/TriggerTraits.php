<?php

namespace xjryanse\order\service\passenger;

use xjryanse\order\service\OrderService;
use app\circuit\service\CircuitBusService;
use xjryanse\user\service\UserPassengerService;
use xjryanse\logic\Arrays;
use xjryanse\logic\DataCheck;
use Exception;
/**
 * 
 */
trait TriggerTraits{

    public static function ramPreSave(&$data, $uuid) {
        // 预留一单多客不同起终点，from_station_id和to_station_id必传
        DataCheck::must($data, ['order_id','passenger_id','from_station_id','to_station_id']);
        self::redunFields($data, $uuid);
        // 快照不进redunFields
        $passengerId = Arrays::value($data, 'passenger_id');
        $passenger = UserPassengerService::getInstance($passengerId)->get();
        if (!$passenger) {
            throw new Exception('乘车人不存在' . $passengerId);
        }

        $data['realname']   = Arrays::value($passenger, 'realname');
        $data['id_no']      = Arrays::value($passenger, 'id_no');
        $data['phone']      = Arrays::value($passenger, 'phone');

        return $data;
    }
    /**
     * 保存后，更新订单
     * @param type $data
     * @param type $uuid
     */
    public static function ramAfterSave(&$data, $uuid) {
        $orderId = Arrays::value($data, 'order_id');
        if($orderId){
            OrderService::getInstance($orderId)->orderDataSyncRam();
            OrderService::getInstance($orderId)->updateFinanceStatementRam();
        }
    }
    public static function ramPreUpdate(&$data, $uuid) {
        throw new Exception(json_encode($data,true));
    }
    
    protected static function redunFields(&$data, $uuid){
        
        $orderId = Arrays::value($data, 'order_id');
        if($orderId){
            $orderInfo              = OrderService::getInstance($orderId)->get();
            $data['circuit_bus_id'] = Arrays::value($orderInfo, 'circuit_bus_id');
            $data['user_id']        = Arrays::value($orderInfo, 'user_id');

            $circuitBusInfo         = CircuitBusService::getInstance($data['circuit_bus_id'])->get();
            $data['circuit_id']     = Arrays::value($circuitBusInfo, 'circuit_id');
        }
        return $data;
    }

}
