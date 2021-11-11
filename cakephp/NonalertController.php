<?php

//複数の指定した値が来た時以外の処理
//try catch
//データ更新

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

        $this->autoRender = false;
        $this->viewBuilder()->setClassName('Json');
    }

    public function beforeFilter(Event $event)
    {
        parent::commonBeforeFilter($event);
    }
    /**
     * アラート表示更新(POST)
     *
     * @return \Cake\Http\Response|null
     */
    public function apiAlert()
    {
        $resultJson = new ResultService();
        
        // リクエストの指定したカラム値を取得
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
        // 複数の指定した値以外が来た時の処理
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
            // データ更新
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
