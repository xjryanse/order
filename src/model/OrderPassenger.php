<?php
namespace xjryanse\order\model;

use think\Db;
/**
 * 订单乘客
 */
class OrderPassenger extends Base
{
    /**
     * 多班合并排车辆
     * @param type $circuitBusIds   多线路id
     * @return type
     */
    public function getMultiCircuitBusData($circuitBusIds )
    {
        $fieldStrArr = [];
        $leftJoinArr = [];
        $orderByArr = [];
        $orderByArrStationFrom = [];
        $orderByArrStationTo = [];
        foreach($circuitBusIds as $k=>$circuitBusId){
            $key = $k+1;
            $alias = 'tb'.$key;
            $tmp = [];
            $tmp[] = $alias.'.circuit_bus_id as `circuitBus'.$key.'`';
            $tmp[] = $alias.'.from_station_id as `fromStationId'.$key.'`';
            $tmp[] = $alias.'.to_station_id as `toStationId'.$key.'`';
            $tmp[] = $alias.'.bus_id as `busId'.$key.'`';
            $fieldStrArr[] = implode(',',$tmp);
            $leftJoinArr[] = "LEFT JOIN ( SELECT * FROM w_order_passenger WHERE circuit_bus_id = '". $circuitBusId ."' AND is_ref = 0) AS ".$alias." ON a.passenger_id = ".$alias.".passenger_id";
            $orderByArr[] = "circuitBus".$key . ' desc';
            $orderByArrStationFrom[] = "fromStationId".$key ;
            $orderByArrStationTo[] = "toStationId".$key ;
        }
        $fieldStr = implode(',',$fieldStrArr);

        $sql = "SELECT a.passenger_id as id,".$fieldStr." FROM
                ( SELECT DISTINCT passenger_id FROM w_order_passenger WHERE circuit_bus_id IN (".implode(',',$circuitBusIds)." ) AND is_ref = 0 ) AS a "
                .implode(' ',$leftJoinArr). ' order by '.implode(',',$orderByArr).','.implode(',',$orderByArrStationFrom).','.implode(',',$orderByArrStationTo);
        //TODO,增加orderBy
        
        $res = Db::query($sql);
        return $res;
    }
    

}