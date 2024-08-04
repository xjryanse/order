<?php

namespace xjryanse\order\service\index;

// 20220906兼容前端，排车页面，是否有更优方案？？
use app\view\service\ViewOrderBaoBusDriverService;
use xjryanse\logic\Arrays;
/**
 * 分页复用列表
 */
trait FindTraits{

    /**
     * 客户聚合，提取待支付明细
     * 用于呆账跟踪：2023-11-02
     */
    public static function driverStatics($param){
        
        $con[] = ['driver_id','=',session(SESSION_USER_ID)];
        $arr = ViewOrderBaoBusDriverService::mainModel()
                ->where($con)
                ->group('timeBelong')->column('count(1) as number','timeBelong');
        
        $data['today'] = Arrays::value($arr, '2');
        $data['after'] = Arrays::value($arr, '3');
        $data['pre'] = Arrays::value($arr, '1');

        return $data;
    }

}
