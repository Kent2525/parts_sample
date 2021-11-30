<?php
//foreachの中にforeach
//参照渡し
//配列の階層を上げる
//Key名を変更
//Keyが正しいかチェック。array_diff
//POST, try catch, エラーログ
//配列の枠を先に作る。
//更新処理

namespace App\Controller;

use App\Controller\AppController;
use App\Lib\Service\ResultService;
use App\Lib\Service\WorkApiService;
use App\Lib\Utility\AppUtility;
use App\Model\Entity\TimeSiteBase;
use Cake\Core\Configure;
use Cake\Event\Event;
use Cake\Utility\Hash;

/**
 * TimeSiteBases Controller
 *
 * @property \App\Model\Table\TimeSiteBasesTable $TimeSiteBases
 */
class TimeSiteBasesController extends AppController
{
    public function __construct($request, $response)
    {
        parent::__construct($request, $response);
    }

    public function initialize()
    {
        parent::initialize();
        $this->loadComponent('RequestHandler');
        $this->loadModel('TimeSiteBases');
        $this->loadModel('SiteInvolvePartners');
        $this->loadModel('TimePartners');
        $this->loadModel('BusinessTypes');

        $this->autoRender = false;
        $this->viewBuilder()->setClassName('Json');
    }

    public function beforeFilter(Event $event)
    {
        parent::commonBeforeFilter($event);
    }

    /**
     * 現場基本情報取得(GET)
     *
     * @return \Cake\Http\Response|null
     */
    public function apiGet()
    {
        $resultJson = new ResultService();

        $place_id = $this->request->getQuery('place_id');
        // 事前プレイスID入力必須確認
        $this->beforeCheckRequirePlaceId($place_id);

        // リクエストデータの「place_id」が「所属かつTime利用可能なプレイスのみ」かをチェック
        $placeList[] = $place_id;
        $validTimePlace = $this->checkTimeAvailablePlace($placeList);
        if ($validTimePlace) {
            // バリデーションエラーとして返す
            $resultJson->setValidationError($validTimePlace);
            $this->response->getBody()->write($resultJson->getJsonArray());
            return $this->response->withType('application/json');
        }

        // 現場取得
        $site_id = $this->request->getQuery('site_id');
        if (empty($site_id)) {
            $resultJson->setNgResult(['site_id' => '現場を指定してください。']);
            $this->response->getBody()->write($resultJson->getJsonArray());
            return $this->response->withType('application/json');
        }

        // レコード取得
        $crewsAlias = $this->TimeCrews->getAlias();
        $result = $this->TimeSiteBases
            ->find()
            ->join([
                'table' => 'time_crews',
                'alias' => 'tc',
                'type' => 'INNER',
                'conditions' => 'tc.crew_id = TimeSiteBases.manager_id',
            ])
            ->select([
                'id',
                'place_id',
                'site_id',
                'name',
                'office_name',
                'tc.name',
                'start_date',
                'end_date',
                'achievement_construction_date',
                'achievement_complete_date',
                'contract_price',
                'floor_total',
                'standard_man_hours'
            ])
            ->where([
                'TimeSiteBases.place_id' => $place_id,
                'TimeSiteBases.site_id'  => $site_id,
                'TimeSiteBases.deleted'  => 0,
            ])
            ->first();

        if (isset($result)) {
            
            // 出力用整形
            //管理者名はtime_crewsから取得する
            $result['manager_name'] = $result['tc']['name'];
            unset($result['tc']);
            $this->log($result,'debug');
            $this->formatOutput($result);
            // 現場基本情報取得
            $resultJson->setResult($result->toArray());

             // Key名の変更
            $values = array_values($resultJson->data);
            $keys = array_keys($resultJson->data);
            $numberOfKey = array_search('name', $keys);
            $keys[$numberOfKey] = 'site_name';
            $array = array_combine($keys, $values);
            $resultJson->setResult($array);

        } else {
            // 現場基本情報なし
            $resultJson->setNoData();
        }

        // JSONで出力する
        $this->response->getBody()->write($resultJson->getJsonArray());
        return $this->response->withType('application/json');
    }

    /**
     * 現場基本情報登録(POST)
     *
     * @return \Cake\Http\Response|null
     */
    public function apiPost()
    {
        $resultJson = new ResultService();
        
        // プレイスID
        $place_id = $this->request->getData('place_id');
        // 事前プレイスID入力必須確認
        $this->beforeCheckRequirePlaceId($place_id);

        // リクエストデータの「place_id」が「所属かつTime利用可能なプレイスのみ」かをチェック
        $placeList[] = $place_id;
        $validTimePlace = $this->checkTimeAvailablePlace($placeList);
        if ($validTimePlace) {
            // バリデーションエラーとして返す
            $resultJson->setValidationError($validTimePlace);
            $this->response->getBody()->write($resultJson->getJsonArray());
            return $this->response->withType('application/json');
        }

        // 現場取得
        $site_id = $this->request->getData('site_id');
        if (empty($site_id)) {
            $resultJson->setNgResult(['site_id' => '現場を指定してください。']);
            $this->response->getBody()->write($resultJson->getJsonArray());
            return $this->response->withType('application/json');
        }

        //リクエストKeyチェック
        $keys = array_keys($this->request->getData());
        $required_keys = [
            'place_id',
            'site_id',
            'start_date',
            'end_date',
            'achievement_construction_date',
            'achievement_complete_date',
            'floor_total',
            'standard_man_hours',
            'contract_price'
        ];
        $check_key = array_diff($keys, $required_keys);
        if ($check_key != null ) {
            $resultJson->setNgResult(['不正なリクエストKeyが存在します。']);
            $this->response->getBody()->write($resultJson->getJsonArray());
            return $this->response->withType('application/json');
        }

        try {
            $query = $this->TimeSiteBases
                ->find()
                ->where([
                    'place_id' => $place_id,
                    'site_id'  => $site_id,
                    'deleted'  => 0,
                ])
                ->first();
    
            // 存在チェック
            if (!isset($query)) {
                $resultJson->setAccident('存在しない現場基本情報IDです。');
                $this->response->getBody()->write($resultJson->getJsonArray());
                return $this->response->withType('application/json');
            }

            // 更新
            $this->TimeSiteBases->getConnection()->begin();
            $timeSiteBases = $this->TimeSiteBases->patchEntity($query, $this->request->getData());       

            $result = $this->TimeSiteBases->save($timeSiteBases);
            if ($result) {
                $this->TimeSiteBases->getConnection()->commit();
                // 出力用整形
                $this->formatOutput($result);
                // レスポンスを生成
                $managerData = $this->TimeCrews->find()->where(['crew_id' => $query->manager_id])->toArray();
                $responseData = [
                    'id'=> $result->id,
                    'place_id' => $result->place_id,
                    'site_id' => $result->site_id,
                    'site_name' => $result->name,
                    'office_name' => $result->office_name,
                    'manager_name' => $managerData[0]->name,
                    'start_date' => $result->start_date,
                    'end_date' => $result->end_date,
                    'achievement_construction_date' => $result->achievement_construction_date,
                    'achievement_complete_date' => $result->achievement_complete_date,
                    'floor_total' => $result->floor_total,
                    'standard_man_hours' => $result->standard_man_hours,
                    'contract_price' => $result->contract_price
                ];
                
                // 登録データをJSONレスポンスにセット
                $resultJson->setSaveResult($responseData);

            } else {
                $this->TimeSiteBases->getConnection()->rollback();
                // バリデーションエラーセット
                $resultJson->setValidationError($timeSiteBases->getErrors());
            }

        } catch (\PDOException $e) {
            $this->TimeSiteBases->getConnection()->rollback();
            // レスポンス用エラーセット
            $resultJson->setAccident();
            // エラーログ出力
            \cake\log\log::error(
                __METHOD__ . ':' . __LINE__ . ' ' . $e->getMessage()
            );
        }

        // JSONで出力する
        $this->response->getBody()->write($resultJson->getJsonArray());
        return $this->response->withType('application/json');
    }

    /**
     * 現場基本情報一覧取得
     *
     * @return \Cake\Http\Response|null
     */
    public function apiGetList()
    {
        $resultJson = new ResultService();

        // ----------------------------------------
        // TODO:Phase2協力会社暫定対応：カレントクルーが「元請け以外」は実行できない
        // ----------------------------------------
        if (!$this->isAdminCrewMotouke) {
            $resultJson->setAccident('実行できません。');
            $this->response->getBody()->write($resultJson->getJsonArray());
            return $this->response->withType('application/json');
        }

        // プレイスID事前チェック
        $place_id = $this->request->getQuery('place_id');
        // 事前プレイスID入力必須確認
        $this->beforeValidCurrentPlace($place_id);

        // TODO:Phase2協力会社暫定対応：元請け以外のプレイス入力はエラー
        // 「タイム利用可能プレイス」かつ「元請け かつ タイム管理者」のプレイス配列を作成
        $motoukeTimeAdminPlaces = Hash::extract(
            $this->dwLoginInfo['crews'],
            '{n}[user_level=' . self::DW_USER_LEVEL_MOTOUKE . '][time_option=true][time_admin=true].place_id'
        );
        if (!in_array($place_id, $motoukeTimeAdminPlaces)) {
            $resultJson->setValidationError([
                'TimePartners' => [
                    'partner_id' => ['notAdmin' => '入力されたプレイスの管理者ではありません。']
                ]
            ]);
            $this->response->getBody()->write($resultJson->getJsonArray());
            return $this->response->withType('application/json');
        }

        // 検索条件作成
        $addConditions = [];
        $site_id = $this->request->getQuery('site_id');
        if (!empty($site_id)) {
            $num = 0;
            if (is_numeric($site_id)) {
                $num = $site_id;
            }
            $addConditions[] = [
                'TimeSiteBases.site_id' => $num,
            ];
        }

        // レコード取得
        $results = $this->TimeSiteBases
            ->find('all')
            ->where([
                'place_id' => $place_id,
                'deleted'  => 0,
            ])
            ->where($addConditions)
            ->order([
                'TimeSiteBases.site_id' => 'ASC'
            ]);

        if (!$results->isEmpty()) {
            // 現場基本情報取得
            foreach ($results as $result) {
                // 出力用整形
                $this->formatOutput($result);
            }
            $resultJson->setResult($results->toArray());

        } else {
            // 現場基本情報なし
            $resultJson->setNoData();
        }

        // JSONで出力する
        $this->response->getBody()->write($resultJson->getJsonArray());
        return $this->response->withType('application/json');
    }

    /**
     * アラート表示更新(POST)
     *
     * @return \Cake\Http\Response|null
     */
    public function apiAlert()
    {
        $resultJson = new ResultService();
        
        // リクエストのサイトidチェック
        $site_id = $this->request->getData('site_id');
        if (empty($site_id)) {
            $resultJson->setNgResult(['site_id' => '現場を指定してください。']);
            $this->response->getBody()->write($resultJson->getJsonArray());
            return $this->response->withType('application/json');
        }       
        // リクエストのアラート表示フラグチェック
        $non_alert = $this->request->getData('non_alert');
        if (is_null($non_alert)) {
            $resultJson->setNgResult(['non_alert' => 'アラート表示フラグを指定してください']);
            $this->response->getBody()->write($resultJson->getJsonArray());
            return $this->response->withType('application/json');
        } 
        elseif ($non_alert != 0 && $non_alert != 1) {
            $resultJson->setNgResult(['non_alert' => 'アラートフラグの数値に誤りがあります']);
            $this->response->getBody()->write($resultJson->getJsonArray());
            return $this->response->withType('application/json');
        }

        $query = $this->TimeSiteBases
            ->find()->where(['site_id'  => $site_id])->first();
        // 存在チェック
        if (!isset($query)) {
            $resultJson->setAccident('存在しない現場基本情報IDです。');
            $this->response->getBody()->write($resultJson->getJsonArray());
            return $this->response->withType('application/json');
        }
        try {
            // 更新
            $this->TimeSiteBases->getConnection()->begin();
            $timeSiteBases = $this->TimeSiteBases->patchEntity($query, $this->request->getData());
            $result = $this->TimeSiteBases->save($timeSiteBases);
            if ($result) {                
                //レスポンスのキー指定
                $responseData[] = [
                    'id'=> $query->id,
                    'place_id' => $query->name,
                    'site_id' => $query->site_id,
                    'non_alert' => $query->non_alert
                ];
                // 登録データをJSONレスポンスにセット
                $resultJson->setSaveResult($responseData);
                $this->TimeSiteBases->getConnection()->commit();
            } else {
                $this->TimeSiteBases->getConnection()->rollback();
                // バリデーションエラーセット
                $resultJson->setValidationError($timeSiteBases->getErrors());
            }
        } catch (\Exception $e) {
            $this->TimeSiteBases->getConnection()->rollback();
            // レスポンス用エラーセット
            $resultJson->setAccident();
            // エラーログ出力
            \cake\log\log::error(
                __METHOD__ . ':' . __LINE__ . ' ' . $e->getMessage()
            );
        }
        // JSONで出力する
        $this->response->getBody()->write($resultJson->getJsonArray());
        return $this->response->withType('application/json');
    }
    /**
     * 関連会社一覧取得(GET)
     *
     * @return \Cake\Http\Response|null
     */
    public function apiGetInvolvePartners()
    {
        $resultJson = new ResultService();

        if (!$this->isAdminCrewMotouke) {
            $resultJson->setAccident('実行できません。');
            $this->response->getBody()->write($resultJson->getJsonArray());
            return $this->response->withType('application/json');
        }

        // プレイスID事前チェック
        $place_id = $this->request->getQuery('place_id');
        // 事前プレイスID入力必須確認
        $this->beforeValidCurrentPlace($place_id);

        // 「タイム利用可能プレイス」かつ「元請け かつ タイム管理者」のプレイス配列を作成
        $motoukeTimeAdminPlaces = Hash::extract(
            $this->dwLoginInfo['crews'],
            '{n}[user_level=' . self::DW_USER_LEVEL_MOTOUKE . '][time_option=true][time_admin=true].place_id'
        );
        if (!in_array($place_id, $motoukeTimeAdminPlaces)) {
            $resultJson->setValidationError([
                'TimePartners' => [
                    'partner_id' => ['notAdmin' => '入力されたプレイスの管理者ではありません。']
                ]
            ]);
            $this->response->getBody()->write($resultJson->getJsonArray());
            return $this->response->withType('application/json');
        }

        $site_id = $this->request->getQuery('site_id');

        // レスポンス用の現場基本情報を取得
        $timeSiteBase = $this->TimeSiteBases
            ->find()
            ->where([
                'site_id' => $site_id,
                'place_id' => $place_id
            ])->first();
        $timeSiteBaseData = [
            'id' => $timeSiteBase->id,
            'place_id' => $timeSiteBase->place_id,
            'site_id' => $timeSiteBase->site_id
        ];

        //リクエストのplace_idに合致した業種情報を全て取得する。
        $businessTypes = $this->BusinessTypes
            ->find()
            ->where([
                'place_id' => $place_id,
                'deleted' => 0,
            ])
            ->select([
                'id', 'place_id', 'name', 'ordinal'
            ])
            ->order([
                'BusinessTypes.ordinal' => 'ASC'
            ])->toArray();

        // リクエストのsite_idに合致したレコード取得
        $siteInvolvePartners = $this->SiteInvolvePartners
            ->find()
            ->where([
                'site_id' => $site_id,
                'deleted'  => 0,
            ])
            ->order([
                'SiteInvolvePartners.site_id' => 'ASC'
            ]);

        // 現場に紐づいている関連会社を全て取得する。
        foreach ($siteInvolvePartners as $siteInvolvePartner) {
            $timePartners = $this->TimePartners
               ->find()
               ->where([
                   'partner_id' => $siteInvolvePartner->partner_id,
                   'deleted' => 0,
               ]);
            $arrTimePartners[] = $timePartners->toArray();
        }
       $resultTimePartners = call_user_func_array("array_merge", $arrTimePartners);

        // 業種情報を整形
        foreach($businessTypes as $business_type) {
            $bizIdAndName =
                [
                'business_type_id' => $business_type->id,
                'business_type_name' => $business_type->name,
                ];
            $arrBizIdAndName[] = $bizIdAndName;
        }

        // 最初に枠だけを作る
        $involvePartnerData = ['involve_partners' => []];
        foreach ($arrBizIdAndName as $bizIdAndName) {
            $involvePartnerData['involve_partners'][] =
                ['business_type_id' => $bizIdAndName['business_type_id']
                ,'business_type_name' => $bizIdAndName['business_type_name']
                ,'partners' => []];
        }
        //$resultTimePartners と$involvePartnerDateにそれぞれデータを検証したい場合にforeachの中にforeachを使用する。
        //$resultTimePartners = [1, 2, 3] で$involvePartnerDate = [A. B]があったら、1A,1B, 2A, 2B ...といった組み合わせで処理をしていく。
        //この処理は下の参照渡しを使わなかったバージョン。ループの中で参照渡しを使うと思わぬバグが発生していまうため。
        foreach ($resultTimePartners as $timePartner) {
            foreach ($involvePartnerData['involve_partners'] as $key => $involvePartner) {
                if ($timePartner['business_type_id'] === $involvePartner['business_type_id']) {
                    $partner = [];
                    $partner['partner_id'] = $timePartner->partner_id;
                    $partner['partner_name'] = $timePartner->name;
                    $involvePartner['partners'][] = $partner;
                    $involvePartnerData['involve_partners'][$key]['partners'][] = $partner;
                }
            }
        }
        // こちらは結果として使用はしなかったが、参照渡しを使ったので載せておく。&が参照渡しの記号。参照渡しを使わないと変数を書き換えられないので動かない。
        // foreach ($resultTimePartners as $record) {
        //     foreach ($arrResult['involve_partners'] as &$involvePartner) {
        //         if ($record->business_type_id === $involvePartner['business_type_id']) {
        //             $partner = [];
        //             $partner['partner_id'] = $record->partner_id;
        //             $partner['name'] = $record->name;
        //             $involvePartner['partners'][] = $partner;
        //         }
        //     }
        // }

        // 枠をforeachの中で作っていく（我流）最初に枠を作っていた方が良いので不採用。
        // foreach($resultTimePartners as $record) {
        //     foreach($arrBizIdAndName as $bizId) {
        //         if($record->business_type_id === $bizId['business_type_id']) {
        //             $results = 
        //             ['business_type_id' => $bizId['business_type_id'],
        //                 'business_type_name' => $bizId['business_type_name'],
        //                 ['partners' => 
        //                     [
        //                     'partner_id' => $record->partner_id,
        //                     'partner_name' => $record->name
        //                     ]
        //                 ]
        //             ];
        //         }
        //     }
        //     $arrResult["involve_partners"][] = $results;
        // }
        // レスポンス用の現場基本情報と関連会社情報を結合させる。
        $results = $timeSiteBaseData + $involvePartnerData;
        if (isset($results)) {
            $resultJson->setResult($results);
        } else {
            $resultJson->setNoData();
        }

        // JSONで出力する
        $this->response->getBody()->write($resultJson->getJsonArray());
        return $this->response->withType('application/json');
    }

    /**
     * 現場基本情報の出力用整形
     *
     * @param object $siteBaseData 現場基本情報
     */
    public function formatOutput(object &$siteBaseData)
    {
        // 出力用整形
        // 日付フォーマット
        $formatDate = Configure::read('DateTimeFormat.Date');

        // 実績着工日
        $siteBaseData->achievement_construction_date = !empty($siteBaseData->achievement_construction_date)
            ? $siteBaseData->achievement_construction_date->format($formatDate) : null;
        // 実績完工日
        $siteBaseData->achievement_complete_date = !empty($siteBaseData->achievement_complete_date)
            ? $siteBaseData->achievement_complete_date->format($formatDate) : null;
        // 予定着工日
        $siteBaseData->start_date = !empty($siteBaseData->start_date)
            ? $siteBaseData->start_date->format($formatDate) : null;
        // 予定完工日
        $siteBaseData->end_date = !empty($siteBaseData->end_date)
            ? $siteBaseData->end_date->format($formatDate) : null;
    }
}
