<?php
namespace xjryanse\order\service\index;

use xjryanse\approval\service\ApprovalThingService;
use xjryanse\user\service\UserService;
use xjryanse\customer\service\CustomerService;
use think\facade\Request;
use xjryanse\logic\Arrays;
/**
 * 分页复用列表
 */
trait ApprovalTraits {
    
    /**
     * 20230704:接口规范写法
     * @return type
     */
    public function approvalAdd() {
        $infoArr    = $this->get();
        $exiApprId  = ApprovalThingService::belongTableIdToId($this->uuid);
        // 20240407
        $rqParam    = Request::param('table_data') ? : Request::param();
        $infoArr['nextAuditUserId'] = Arrays::value($rqParam, 'nextAuditUserId');
        //已有直接写，没有的加审批
        $data['approval_thing_id']  = $exiApprId ?: self::addAppr($infoArr);
        $data['need_appr']          = 1;
        return $this->updateRam($data);
    }
    
    /**
     * 事项提交去审批
     */
    protected static function addAppr($data) {
        $sData                      = Arrays::getByKeys($data, ['customer_id','dept_id','nextAuditUserId']);
        $sData['user_id']           = session(SESSION_USER_ID);
        $sData['belong_table']      = self::getTable();
        $sData['belong_table_id']   = $data['id'];
        $sData['customerName']       = CustomerService::getInstance($sData['customer_id'])->fCustomerName();
        $sData['userName']          = UserService::getInstance($sData['user_id'])->fRealName();

        // 20230907:改成ram
        return ApprovalThingService::thingCateAddApprRam('orderApply', $data['user_id'], $sData);
    }
}
