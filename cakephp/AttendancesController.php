<?php
// 多次元配列への要素の組み込み


namespace App\Controller;

use App\Controller\AppController;
use App\Lib\Service\ResultService;
use Cake\Event\Event;


/**
 * Attendances Controller
 *
 * @property \App\Model\Table\AttendancesTable       $Attendances
 * @property \App\Model\Table\AttendanceDetailsTable $AttendanceDetails
 * @property \App\Model\Table\TimeSiteBasesTable     $TimeSiteBases
 * @property \App\Model\Table\StampRemindTimesTable  $StampRemindTimes
 * @property \App\Model\Table\DailySiteAttendanceLocksTable $DailySiteAttendanceLocks
 */
class AttendancesController extends AppController
{
    // 検索結果の上限数
    const SEARCH_RESULT_LIMIT = 20;
    // 同一出勤日の登録上限件数
    const DETAIL_SAVE_LIMIT = 10;

    public function __construct($request, $response)
    {
        parent::__construct($request, $response);
    }

    public function initialize()
    {
        parent::initialize();
        $this->loadComponent('RequestHandler');
        $this->loadComponent('Paginator');
        $this->loadModel('Attendances');
        $this->loadModel('AttendanceDetails');
        $this->loadModel('CrewWageMonthlyReports');
        $this->loadModel('TimeSiteBases');
        $this->loadModel('TimePartners');
        $this->loadModel('TimePlaces');
        $this->loadModel('DailySiteAttendanceLocks');

        $this->loadModel('StampRemindTimes');
        $this->autoRender = false;
        $this->viewBuilder()->setClassName('Json');
    }

    public function beforeFilter(Event $event)
    {
        parent::commonBeforeFilter($event);
    }

    /**
     * 勤怠情報取得
     */
    public function apiGet()
    {
        $resultJson = new ResultService();

        // 出勤日を入力パラメータから取得
        $workday = $this->request->getQuery('workday') ?: '';
        // 入力チェック
        $chkResult = $this->checkInputParamWorkday($workday);
        if (!empty($chkResult)) {
            // 入力チェックエラー
            $resultJson->setNgResult($chkResult);
            $this->response->getBody()->write($resultJson->getJsonArray());
            return $this->response->withType('application/json');
        }

        // Time利用可能プレイスのみの1次元配列を作成
        $places = array_keys($this->timeAvailablePlacesKeyPlace);
        // データ取得
        $headerAlias = $this->Attendances->getAlias();
        $detailAlias = $this->AttendanceDetails->getAlias();
        $result = $this->Attendances
            ->find()
            ->contain([
                $detailAlias => function ($q) use ($detailAlias, $places) {
                    return $q->where(["{$detailAlias}.place_id IN" => $places]);
                }
            ])
            ->where([
                "{$headerAlias}.workday" => $workday,
                "{$headerAlias}.user_id" => $this->execUserId,
                "{$headerAlias}.deleted" => 0,
            ])
            ->first();

        //出勤日と現場idが合致するレコードのロックステータスを取得
        $lockStatus = $this->DailySiteAttendanceLocks
            ->find()
            ->select(['lock_status'])
            ->where([
                'workday' => $workday,
                'site_id' => $result['attendance_details'][0]->site_id,
                'deleted' => 0
            ])
            ->first();

        $result['attendance_details'][0]['lock_status'] = $lockStatus['lock_status'];

        if (!empty($result) && !empty($result->attendance_details)) {
            // 出力用データ整形
            $this->formatOutput($result);
            // 取得データセット
            $resultJson->setResult($result->toArray());
        } else {
            // 詳細情報なし
            $resultJson->setNoData();
        }

        // JSONで出力する
        $this->response->getBody()->write($resultJson->getJsonArray());
        return $this->response->withType('application/json');
    }
}
