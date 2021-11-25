<?php
// construct(コンストラクター)を増やす。
// compact
// キーワード検索、正規表現、filter()、preg_match

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Classes\Facades\ManagePageFacade;
use App\Classes\Services\Jobs\SiteSyncTimeJobService;
use App\Classes\Facades\SyncOrderFacade;

class SyncSiteController extends Controller
{
    //$facade -> $managePageFacadeと$syncOrderFacadeに分ける。
    private $managePageFacade;
    private $syncOrderFacade;
    protected $siteSyncTimeJobService;
    // 引数を追加
    public function __construct(SiteSyncTimeJobService $siteSyncTimeJobService, SyncOrderFacade $syncOrderFacade)
    {
      //$facade -> $managePageFacadeと$syncOrderFacadeに分ける。
        $this->managePageFacade = new ManagePageFacade();
        $this->syncOrderFacade = $syncOrderFacade; 
        $this->siteSyncTimeJobService = $siteSyncTimeJobService;
    }

    public function showPlaces(Request $request) {
        $data = $this->managePageFacade->getDTimeAvailablePlace(); 
        return view("sitePlace", compact('data'));
    }

    public function showSites(Request $request) {
        $value = $request->only('place');
        $placeId = $value['place'];
        $sites = $this->managePageFacade->getSiteListByPlace($placeId);
        
        return view("site", compact('sites', 'placeId'));
    }

    public function searchSites(Request $request) {
        $value = $request->only('place');
        $placeId = $value['place'];
        $sites = $this->managePageFacade->getSiteListByPlace($placeId);
     
        //request->site_nameをキーワード検索をかける。
        $collection = collect($sites);
        $target = '/' . $request->site_name . '/';
        $sites = $collection->filter(function($value) use ($target){
            return preg_match($target, $value['site_name']);
        });
        
        return view("site", compact('sites', 'placeId'));
    }

    public function showSyncSites(Request $request)
    {
        $syncSite = $this->managePageFacade->getSiteAndPlaceSyncConfirm($request->site_id); 

        return view('syncSite',compact('syncSite'));
    }

    public function storeSites(Request $request)
    {
        $this->syncOrderFacade->addDataSyncOrder($request->all()); 

        return redirect()->route('home')
            ->with('success', '登録は成功しました。');
    }
}

// 元のデータコンストラクターが増やせず詰まった。
// <?php

// namespace App\Http\Controllers;

// use Illuminate\Http\Request;
// use App\Classes\Facades\ManagePageFacade;
// use App\Classes\Services\Jobs\SiteSyncTimeJobService;
// use App\Classes\Facades\SyncOrderFacade;
// use App\Classes\Services\DataSyncOrderService;

// class SyncSiteController extends Controller
// {
//     private $facade;
//     protected $siteSyncTimeJobService;

//     public function __construct(SiteSyncTimeJobService $siteSyncTimeJobService)
//     {
//         $this->facade = new ManagePageFacade();
//         $this->siteSyncTimeJobService = $siteSyncTimeJobService;
//     }

//     public function showPlaces(Request $request) {
//         $data = $this->facade->getDTimeAvailablePlace();
//         return view("sitePlace", compact('data'));
//     }
   
//     public function showSyncSites(Request $request)
//     {
//         $syncSite = $this->facade->getSiteAndPlaceSyncConfirm($request->site_sync);

//         return view('syncSite',compact('syncSite'));
//     }

//     public function storeSites(Request $request)
//     {
//         $this->facade->addDataSyncOrder($request->all());

//         return redirect()->route('home')
//             ->with('success', '登録は成功しました。');
//     }
// }
