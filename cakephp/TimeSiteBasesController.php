<?php

namespace App\Controller;

use App\Controller\AppController;
use App\Lib\Service\ResultService;
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
        $result = $this->TimeSiteBases
            ->find()
            ->select([
                'id',
                'place_id',
                'site_id',
                'name',
                'office_name',
                'manager_name',
                'start_date',
                'end_date',
                'achievement_construction_date',
                'achievement_complete_date',
                'contract_price',
                'floor_total',
                'standard_man_hours'
            ])
            ->where([
                'place_id' => $place_id,
                'site_id'  => $site_id,
                'deleted'  => 0,
            ])
            ->first();

        if (isset($result)) {
            // 出力用整形
            $this->formatOutput($result);
            // 現場基本情報取得
            $resultJson->setResult($result->toArray());

        } else {
            // 現場基本情報なし
            $resultJson->setNoData();

        }
    
        // Key名の変更
        $values = array_values($resultJson->data);
        $keys = array_keys($resultJson->data);
        $numberOfKey = array_search('name', $keys);
        $keys[$numberOfKey] = 'site_name';
        $array = array_combine($keys, $values);
        $resultJson->setResult($array);

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
