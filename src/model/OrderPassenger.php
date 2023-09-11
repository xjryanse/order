<?php
namespace xjryanse\order\model;

use think\Db;
/**
 * 订单乘客
 */
class OrderPassenger extends Base
{
    use \xjryanse\traits\ModelUniTrait;
    // 20230516:数据表关联字段
    public static $uniFields = [
        [
            'field'     =>'circuit_bus_id',
            'uni_name'  =>'circuit_bus',
            'uni_field' =>'id',
            'in_list'   => false,
            'in_statics'=> true,
            'del_check' => true,
            'del_msg'   => '已有{$count}张订票记录'
        ],
        [
            'field'     =>'order_id',
            'uni_name'  =>'order',
            'uni_field' =>'id',
            'in_list'   => false,
            'in_statics'=> true,
            'del_check' => false,
        ],
        [
            'field'     =>'circuit_id',
            'uni_name'  =>'circuit',
            'uni_field' =>'id',
            'in_list'   => false,
            'in_statics'=> false,
            'in_exist'  => true,
            'del_check' => false,
        ],
        [
            'field'     =>'from_station_id',
            'uni_name'  =>'station',
            'uni_field' =>'id',
            'in_list'   => false,
            'in_statics'=> true,
            'in_exist'  => false,
            'del_check' => true,
            'del_msg'   => '已有{$count}张出发站订票记录',
            'property'  =>'fromOrderPassenger'
        ],
        [
            'field'     =>'to_station_id',
            'uni_name'  =>'station',
            'uni_field' =>'id',
            'in_list'   => false,
            'in_statics'=> true,
            'in_exist'  => false,
            'del_check' => true,
            'del_msg'   => '已有{$count}张到达站订票记录',
            'property'  =>'toOrderPassenger'
        ],
        [
            'field'     =>'passenger_id',
            'uni_name'  =>'user_passenger',
            'uni_field' =>'id',
            'in_list'   => false,
            'in_statics'=> true,
            'in_exist'  => false,
            'del_check' => false,
        ],
        [
            'field'     =>'user_id',
            'uni_name'  =>'user',
            'uni_field' =>'id',
            'in_list'   => false,
            'in_statics'=> false,
            'in_exist'  => true,
            'del_check' => true,
        ],
    ];
    
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