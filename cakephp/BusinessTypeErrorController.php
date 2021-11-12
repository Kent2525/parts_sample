<?php

// コレクションからカラム値を取得して配列として出す。extract()
// foreach ($test as $key => $value)。
// 配列の値を消す。index番号を参照して削除。
// array_diff、2つの配列の差分を出す。
// array_replace_recursive、２つの配列の値同士を同じ配列に並べる。

namespace App\Controller;

use App\Controller\AppController;
use App\Lib\Service\ResultService;
use Cake\Event\Event;
use Cake\Utility\Hash;
use Cake\Collection\Collection;

class BusinessTypeErrorController extends AppController
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
        $this->loadModel('SiteBusinessTypeCounts');
        $this->loadModel('BusinessTypes');

        $this->autoRender = false;
        $this->viewBuilder()->setClassName('Json');
    }

    public function beforeFilter(Event $event)
    {    
        parent::commonBeforeFilter($event);
    }
    
    /**
    * 画面上部未登録業種エラー取得(GET)
    *
    * @return \Cake\Http\Response|null
    */
    public function apiGet()
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

        // テーブルアクセス、レコード取得
        $timeSiteBases = $this->TimeSiteBases
            ->find()
            ->where([
                'place_id' => $place_id,
                'deleted'  => 0,
            ]);
        // コレクションからカラム値を取得して配列として出す。
        $collection = new Collection($timeSiteBases);
        $site_ids = $collection->extract('site_id')->toList();
        // フラグが立ってたら、取得した配列の値を削除。複数値を取得しているため、Keyのindex番号を参照して消す配列を指定している。
        foreach ($timeSiteBases as $key => $timeSiteBase) {
            if ($timeSiteBase->non_alert == 1) {
                unset($site_ids[$key]);
            }
        }
        // 取得が無かったら、処理終了
        if (empty($site_ids)) {
            $resultJson->setAccident('取得できるsite_idがありません。');
            $this->response->getBody()->write($resultJson->getJsonArray());
            return $this->response->withType('application/json');
        }

        foreach ($site_ids as $site_id) {
            $siteBusinessTypeCounts = $this->SiteBusinessTypeCounts
                ->find()
                ->where([
                    'site_id' => $site_id,
                    'deleted'  => 0
                ]);
            $collection = new Collection($siteBusinessTypeCounts);
            $partner_counts = $collection->extract('partner_count')->toList();
            // partner_countが0だったら、エラー            
            if (array_search(0, $partner_counts) !== false) {
                $resultJson->setAccident('登録会社数がないデータがあります。');
                $this->response->getBody()->write($resultJson->getJsonArray());
                return $this->response->withType('application/json');
            }   
            // ①
            $business_ids[$site_id] = $collection->extract('business_type_id')->toList(); 
        }

        $BusinessTypes = $this->BusinessTypes
            ->find()
            ->where([
                'place_id' => $place_id,
                'deleted'  => 0
            ]);
        $collection = new Collection($BusinessTypes);
        // ②
        $idOfBusinessTypes = $collection->extract('id')->toList();  

        // ①と②の配列の差分チェック。差分があったら差分のあったsite_idを取得する。
        $siteIdDiffs = [];
        foreach ($business_ids as $key => $business_id) {
            $arrayDiff = array_diff($idOfBusinessTypes, $business_id);
            if (count($arrayDiff) > 0) {
                $siteIdDiffs[] = $key;
            } 
        }
        // 差分のあったレコードのnameを取得。一度レコードを取得しているのでその変数を使い回す。
        foreach ($siteIdDiffs as $siteIdDiff) {
            $test = $timeSiteBases->where(['site_id', $siteIdDiff]);
            $arrayData = $test->toArray();
            $nameDiffs[] = $arrayData[0]->name;
        }
        if (empty($siteIdDiffs)) {
            $resultJson->setAccident('未登録業種エラーはありません。');
            $this->response->getBody()->write($resultJson->getJsonArray());
            return $this->response->withType('application/json');
        }

        // レスポンス用のデータを作成
        foreach ($siteIdDiffs as $siteIdDiff) {
            $responseSiteId['business_type_error'][] = ['site_id'=> $siteIdDiff];
        }
        // レスポンス用のデータを作成
        foreach ($nameDiffs as $nameDiff) {
            $responseName['business_type_error'][] = ['site_name'=> $nameDiff];
        }
        // ２つの配列の値同士を同じ配列に並べる。
        $result = array_replace_recursive($responseSiteId, $responseName);
        $resultJson->setResult($result);
        $this->response->getBody()->write($resultJson->getJsonArray());
        return $this->response->withType('application/json');
    }
}