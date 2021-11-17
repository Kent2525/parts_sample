<?php
// 定数
// try catch 例外
// エラーログ
// 更新（patchentity以外を使った、個別のカラムのデータ更新）

namespace App\Controller;

use App\Controller\AppController;
use App\Lib\Service\ResultService;
use App\Lib\Service\WorkApiService;
use App\Lib\Utility\AppUtility;
use App\Lib\Model\FindResultModel;
use Cake\Datasource\ConnectionManager;
use Cake\Event\Event;
use Cake\ORM\Query;
use Cake\Utility\Hash;
use Cake\ORM\TableRegistry;

/**
 * TimePartners Controller
 *
 * @property \App\Model\Table\TimePartnersTable $TimePartners
 * @property \App\Model\Table\TimeCrewsTable    $TimeCrews
 * @property \App\Model\Table\SiteBusinessTypeCountsTable    $SiteBusinessTypeCounts
 * @property \App\Model\Table\TimePartnerYmSettingsTable    $TimePartnerYmSettings
 */
class TimePartnersController extends AppController
{
    // 検索結果の上限数
    const SEARCH_RESULT_LIMIT = 20;
    // 業種を変更登録した際の現場別業種登録状況の登録会社数を操作する
    const CHANGE_BIZ_COUNT = 1;

    private $resultJson;

    public function __construct($request, $response)
    {
        parent::__construct($request, $response);
    }

    public function initialize()
    {
        parent::initialize();
        $this->loadComponent('RequestHandler');
        $this->loadComponent('SqlPaginator');
        $this->loadModel('TimePartners');
        $this->loadModel('TimePartnerYmSettings');
        $this->loadModel('PartnerBusinessTypes');
        $this->loadModel('SiteInvolvePartners');
        $this->loadModel('SiteBusinessTypeCounts');

        $this->autoRender = false;
        $this->viewBuilder()->setClassName('Json');

        $this->resultJson = new ResultService();
    }

    public function beforeFilter(Event $event)
    {
        parent::commonBeforeFilter($event);
    }

    /**
     * 会社情報登録(POST)
     *
     * @return \Cake\Http\Response|null
     */
    public function apiRegister()
    {   
        $place_id = $this->request->getData('place_id');
        $this->beforeValidCurrentPlace($place_id);

        $partner_id = $this->request->getData('partner_id');
        $this->beforeValidCurrentPartner($partner_id);

        $company_id = $this->beforeValidParam('company_id', '会社ID');
        $this->beforeValidParam('closing_day', '締日');
        // 業種は必須ではないので、nullや0がきたら「-1」とする
        if (empty($this->request->getData('business_type_id'))) {
            $this->request = $this->request->withData('business_type_id', UNDEFINED_VALUE);
        }
        $this->beforeValidParam('business_type_id', '業種ID');

        // SQL条件の基準
        $baseConditions = [
            'place_id'   => $place_id,
            'partner_id' => $partner_id,
            'company_id' => $company_id,
            'deleted'    => 0,
        ];

        try {
            // 既存データ取得
            $id = $this->getTargetId($this->request->getData(), 'TimePartners', true);
            $query = $this->TimePartners
                ->find()
                ->where($baseConditions)
                ->where([
                    'id' => $id,
                ])
                ->first();

            $prevBizTypeId = $query->business_type_id;
            if (isset($id) && !isset($query)) {
                $this->resultJson->setValidationError([
                    'TimePartners' => [
                        'id' => ['notEmptyTarget' => '更新対象が取得できませんでした。']
                    ]
                ]);
                $this->response->getBody()->write($this->resultJson->getJsonArray());
                return $this->response->withType('application/json');
            }

            if (isset($query)) {
                // UPDATE
                $this->TimePartners->getConnection()->begin();
                $timePartner = $this->TimePartners->patchEntity($query, $this->request->getData());

                $requestBizTypeId = $this->request->getData('business_type_id');
   
                if ($prevBizTypeId != $requestBizTypeId) {

                    $SiteInvolvePartners = $this->SiteInvolvePartners
                        ->find()
                        ->where([
                            'partner_id' => $query->partner_id
                        ])
                        ->first();

                    $prevRecord = $this->SiteBusinessTypeCounts
                        ->find()
                        ->where([
                            'site_id' => $SiteInvolvePartners->site_id,
                            'business_type_id' => $prevBizTypeId
                        ])
                        ->first();
                    // 例外処理
                    if (empty($prevRecord->partner_count)) {
                        throw new \PDOException();
                    }
                    // データの個別カラム値の更新
                    $SiteBizTypeCountsTable = TableRegistry::getTableLocator()->get('SiteBusinessTypeCounts');
                    $downTarget = $SiteBizTypeCountsTable->get($prevRecord->id);
                    // 定数
                    $downTarget->partner_count = $prevRecord->partner_count - self::CHANGE_BIZ_COUNT;
                    $SiteBizTypeCountsTable->save($downTarget);

                    $requestRecord = $this->SiteBusinessTypeCounts
                        ->find()
                        ->where([
                            'site_id' => $SiteInvolvePartners->site_id,
                            'business_type_id' => $requestBizTypeId
                        ])
                        ->first();

                    $upTarget = $SiteBizTypeCountsTable->get($requestRecord->id);
                    $upTarget->partner_count = $requestRecord->partner_count + self::CHANGE_BIZ_COUNT;
                    $SiteBizTypeCountsTable->save($upTarget);
                }
            } else {

                // INSERT
                $this->TimePartners->getConnection()->begin();
                $timePartner = $this->TimePartners->newEntity($this->request->getData());
                $timePartner->created_user_id = $this->execUserId;
                $timePartner->deleted = 0;

            }
            $timePartner->modified_user_id = $this->execUserId;

            $result = $this->TimePartners->save($timePartner);
            if ($result) {

                $businessTypeId = $this->request->getData('business_type_id');
                if (!empty($businessTypeId)) {
                    // 業種は空の場合もあるので、空でない場合のみ保存する
                    $queryMiddle = $this->PartnerBusinessTypes
                        ->find()
                        ->where([
                            'time_partner_id' => $result->id,
                            'deleted' => 0,
                        ])
                        ->first();
                    if (isset($queryMiddle)) {
                        // UPDATE
                        $PartnerBusinessTypes = $this->PartnerBusinessTypes->patchEntity($queryMiddle,
                            [
                                'business_type_id' => $businessTypeId,
                            ]
                        );
                    } else {
                        // INSERT
                        $PartnerBusinessTypes = $this->PartnerBusinessTypes->newEntity(
                            [
                                'time_partner_id' => $result->id,
                                'business_type_id' => $businessTypeId,
                                'created_user_id' => $this->execUserId,
                                'deleted' => 0,
                            ]
                        );
                    }
                    $PartnerBusinessTypes->modified_user_id = $this->execUserId;
                    $resultMiddle = $this->PartnerBusinessTypes->save($PartnerBusinessTypes);
                } else {
                    $resultMiddle = true;
                }
            } else {
                $resultMiddle = false;
            }

            if ($resultMiddle) {
                $this->TimePartners->getConnection()->commit();
                // 登録データをJSONレスポンスにセット
                $this->resultJson->setSaveResult($result->toArray());
            } else {
                $this->TimePartners->getConnection()->rollback();
                // バリデーションエラーセット
                $this->resultJson->setValidationError($timePartner->getErrors());
            }
        } catch (\PDOException $e) {
            $this->TimePartners->getConnection()->rollback();
            // レスポンス用エラーセット
            $this->resultJson->setAccident();
            // エラーログ出力
            \cake\log\log::error(__METHOD__ . ':' . __LINE__ . ' ' . $e->getMessage());
        }

        // JSONで出力する
        $this->response->getBody()->write($this->resultJson->getJsonArray());
        return $this->response->withType('application/json');
    }

}