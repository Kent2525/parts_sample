<?php

namespace App\Controller;

use App\Controller\AppController;
use App\Lib\Service\ResultService;
use Cake\Event\Event;
use Cake\Utility\Hash;
use Cake\I18n\FrozenTime;

/**
 * AchievementDateError Controller
 *
 */
class AchievementDateErrorController extends AppController
{
    /**
    * 画面上部実績日入力エラー取得(GET)
    *
    * @return \Cake\Http\Response|null
    */
    public function apiGet()
    {
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
            // レコード取得 
            $results = $this->TimeSiteBases
                ->find()
                ->where([
                    'achievement_construction_date IS NULL',
                    'place_id' => $place_id,
                    'deleted'  => 0,
                ])
                ->orWhere([
                    'achievement_complete_date IS NULL',
                    'place_id' => $place_id,
                    'deleted'  => 0,
                ])
                ->order([
                    'TimeSiteBases.site_id' => 'ASC'
                ]);
            
            $frozenTime = new FrozenTime();
            $now = $frozenTime->format('Y-m-d');
            $responseData = [];
            if (!$results->isEmpty()) {
                // 未入力エラーレスポンスを作成
                foreach ($results as $result) {
                    if (isset($result->start_date)
                        && strtotime($result->start_date) < strtotime($now)
                        && empty($result->achievement_construction_date)) {
                            $responseData['start_date_error'][] = ['site_id'=> $result->site_id, 'site_name' => $result->name];
                    } 
                }
                // 未入力エラーレスポンスを作成
                foreach ($results as $result) {
                    if (isset($result->end_date)
                        && strtotime($result->end_date) < strtotime($now)
                        && empty($result->achievement_complete_date)) {
                            $responseData['end_date_error'][] = ['site_id'=> $result->site_id, 'site_name' => $result->name];
                    } 
                }
                $resultJson->setResult($responseData);

            } else {
                $resultJson->setNoData();
            }
            // JSONで出力する
            $this->response->getBody()->write($resultJson->getJsonArray());
            return $this->response->withType('application/json');
        }
    }
}
