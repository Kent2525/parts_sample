<?php
//四捨五入、切り上げ、切り下げ
//他のファイルに処理を記載して、戻ってくる。

namespace App\Http\Controllers\Api\Hrm\Client;

use App\Acme\Transformers\ScheduleTransformer;
use App\Http\Controllers\Api\ApiController;
use App\Models\Schedule;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ScheduleController extends ApiController
{
    /**
     * Display the specified schedule.
     *
     * @param int $id
     * @return JsonResponse
     */
    public function show(int $id)
    {
        $schedule = Schedule::where('id', $id)->first();
        if (empty($schedule)) {
            return $this->respondNotFound(__('app.schedule.not_found', ['id' => $id]));
        }
        
        $round_up_time = $schedule->ChangeConsultationTimeToNumberOfCoupons();
        // $round_down_time = $schedule->ChangeConsultationTimeToNumberOfCoupons();
        // $round_time = $schedule->ChangeConsultationTimeToNumberOfCoupons();

        return response()->json([
            'message' => __('app.get.success'),
            'schedule' => $this->scheduleTransformer->transform($schedule->toArray()),
            'number_of_coupons' => $round_up_time,
            // 'number_of_coupons' => $round_down_time,
            // 'number_of_coupons' => $round_time,
        ], 200);
    }

    // オリジナル版はこの関数はモデルに書いてあった
    public function ChangeConsultationTimeToNumberOfCoupons()
    {
        $consultation_time = (strtotime($this->end_time) - strtotime($this->start_time))/60; 
        
        // 相談時間の分の1の位を切り上げる。例)32分→4, 36分→4
        $round_up_time = ceil($consultation_time/10);
        // 相談時間の分の1の位を切り下げる。例)32分→3, 36分→3
        // $round_down_time = floor($consultation_time/10);
        // 相談時間の分の1の位を四捨五入する。例)32分→3, 36分→4
        // $round_time = round($consultation_time/10);
      
        return $round_up_time;
        // return $round_down_time;
        // return $round_time;
    }
}
