<?php

namespace xjryanse\order\service\passenger;

use xjryanse\logic\DataCheck;
use xjryanse\logic\Arrays;
//use xjryanse\circuit\service\CircuitBusService;
use app\circuit\service\CircuitBusService;
use think\Db;
/**
 * 
 */
trait ArrangeTraits{

    /**
     * 以包车趟，来进行排班
     * 20240606:替代原来的排车方法
     * 优点：可以关联到包车上，
     * 
     * fromStationId:出发站
     * toStationId：到达站
     * circuitBusId：班次
     * baoBusId：包车趟
     * 
     * @param type $param
     * @return bool
     */
    public static function doBaoBusBatchArrange($param){
        //所排车辆
        $ids = self::idsForArrange($param);

        $baoBusId   = Arrays::value($param, 'baoBusId');
        $circuitBusId   = Arrays::value($param, 'circuitBusId');
        
        Db::startTrans();
            //先将班次锁定不可退票
            $lockData['lock_ref'] = 1;
            CircuitBusService::getInstance($circuitBusId)->update($lockData);
            //更新
            $conde[] = ['id', 'in', $ids];
            $res = self::where($conde)->whereNull('bao_bus_id')->update(['bao_bus_id' => $baoBusId]);
        Db::commit();
        return $res;
    }
    /**
     * 20240606：清空排班
     * @param type $param
     * @return type
     */
    public static function baoBusClear($param) {
        $ids        = self::idsForArrange($param);
        //更新
        $conde[]    = ['id', 'in', $ids];
        $res        = OrderPassengerService::mainModel()->where($conde)->update(['bao_bus_id' => null]);

        return $this->dataReturn('清空排车', $res);
    }
    
    /**
     * 20240606：用于排车的id数组
     */
    protected static function idsForArrange($param){
        DataCheck::must($param, ['circuitBusId', 'fromStationId', 'toStationId']);
        $fromStationId  = Arrays::value($param, 'fromStationId');
        $toStationId    = Arrays::value($param, 'toStationId');
        $circuitBusId   = Arrays::value($param, 'circuitBusId');
        
        $con[] = ['is_ref', '=', 0];
        $con[] = ['from_station_id', '=', $fromStationId];
        $con[] = ['to_station_id', '=', $toStationId];
        $con[] = ['circuit_bus_id', '=', $circuitBusId];
        
        $inst = self::where($con)->whereNull('bao_bus_id')->order('tag,id');
        $number     = Arrays::value($param, 'number');
        if($number){
            $inst->limit($number);
        }

        return $inst->column('id');
    }
    
}
