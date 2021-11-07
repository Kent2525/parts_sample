<?php

//whereIn()取得する値を指定
//sum()指定したkeyの値の合計数を出す。
//skip()取得するデータの位置を指定。
//ソート
//定数を使用。定数はModelに定義。

namespace App\Http\Controllers\Api;

use App\Acme\Transformers\PaginationTransformer;
use App\Http\Controllers\ApiController;
use App\Models\ClientCoupon;
use App\Models\CouponHistory;
use App\Models\CouponType;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;


class CouponController extends ApiController
{
    private $paginate = 10;
    /**
     * @var PaginationTransformer
     */
    private $paginationTransformer;

    /**
     * CouponController constructor.
     * @param PaginationTransformer $paginationTransformer
     */
    public function __construct(PaginationTransformer $paginationTransformer)
    {
        $this->paginationTransformer = $paginationTransformer;
    }

    /**
     * @param Request $request
     * @return JsonResponse
     */
    //ユーザーの回数券履歴をリクエスト
    public function list(Request $request)
    {
        $now = Carbon::now();
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|integer',
        ]);

        if ($validator->fails()) {
            return $this->respondNotValidated(
                __('Validation failed'),
                $validator->errors()->first()
            );
        }
        //指定したidで指定した値で比較を行い、値の合計数を足す。
        $remaining_coupons = ClientCoupon::where('user_id', $request->user_id)
            ->whereIn('usage_type', [1, 2, 5])
            ->where('validity_period', '>', $now)
            ->sum('remaining_coupon');

        //ソート
        $coupon_history = CouponHistory::where('user_id', $request->user_id)->orderBy('id', 'desc')->get();

        return response()->json([
            'message' => __('app.success'),
            'remaining_coupons' => $remaining_coupons,
            'coupon_hisotry' => $coupon_history,
        ], 200);
    }

    /**
     * @param Request $request
     * @return JsonResponse
     */
    //選択したクーポンの詳細情報を取得するメソッド
    public function show(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'client_coupon_id' => 'required|integer',
        ]);

        if ($validator->fails()) {
            return $this->respondNotValidated(
                __('Validation failed'),
                $validator->errors()->first()
            );
        }

        $coupon_detail = ClientCoupon::find($request->client_coupon_id);
        // 履歴テーブルの指定したクーポンidのレコード数を数える
        $number_of_records = CouponHistory::where('client_coupon_id', $request->client_coupon_id)->count();
        // 有効期限更新のフラグがあり、かつクーポンidのレコードが他にない場合にエラーを出す
        if ($coupon_detail->usage_type === ClientCoupon::USAGE_TYPE_UPDATE_EXPIRED_DATE
        && $number_of_records <= 1 ) {
            return $this->respondNotValidated(
                __('Validation failed'),
                $validator->errors()->first() 
            );
        //有効期限更新のフラグがあったら、1つ前のレコードの有効期限を$prev_validity_periodに代入している。
        } elseif ($coupon_detail->usage_type === ClientCoupon::USAGE_TYPE_UPDATE_EXPIRED_DATE) {
            $prev_validity_period = CouponHistory::where('client_coupon_id', $request->client_coupon_id)
                ->orderBy('validity_period','desc')
                // データの取得する位置を指定している。
                ->skip(1)
                ->value('validity_period');
        } else {
            $prev_validity_period = null;
        }

        return response()->json([
            'message' => __('app.success'),
            'coupon_detail' => $coupon_detail,
            'prev_validity_period' => $prev_validity_period,
        ], 200);
    }

    /**
     * @param Request $request
     * @return JsonResponse
     */
    //管理側からキャンセル処理を実行するメソッド。支払いステータスの値が決まったらpayment_statusのコメントアウトを外す。
    public function updateCoupon(Request $request): JsonResponse
    {
        $now = Carbon::now();
        $validator = Validator::make($request->all(), [
            //返品対象の回数券のid
            'id' => 'required|int',
            // 'payment_status' => 'required|int',
        ]);

        if ($validator->fails()) {
            return $this->respondNotValidated(
                __('Validation failed'),
                $validator->errors()->first()
            );
        }
       
        $cancel_coupon = ClientCoupon::find($request->id);

        DB::beginTransaction();
        try {
            if ($cancel_coupon->coupon_type_id === 1) {
                $number_of_coupons = CouponType::NUMBER_OF_SALES_60MINUTES;
            }elseif ($cancel_coupon->coupon_type_id === 2) {
                $number_of_coupons = CouponType::NUMBER_OF_SALES_120MINUTES;
            }
            CouponHistory::create([
                'user_id' => $cancel_coupon->user_id,
                'client_coupon_id' => $cancel_coupon->id,
                'usage_type' => CouponHistory::USAGE_TYPE_CANCEL,
                'number_of_coupons' => $number_of_coupons,
                'registration_date' => $now->format('Y_m_d'),
              ]);

            $cancel_coupon->remaining_coupon = 0;
            $cancel_coupon->usage_type = ClientCoupon::USAGE_TYPE_CANCEL;
            $cancel_coupon->save();
            
            DB::commit();

            return response()->json([
                'message' => __('Success'),
            ], 200);

        } catch (\Exception $exception) {
            DB::rollBack();
            return $this->respondNotValidated(
                __('app.failed'),
                $exception->getMessage()
            );
        }
    }

    /**
     * @param Request $request
     * @return JsonResponse
     */
    //有効期限を変更するメソッド
    public function updateValidityPeriod(Request $request): JsonResponse
    {
        $now = Carbon::now();
        $validator = Validator::make($request->all(), [
            //返品対象の回数券のid
            'id' => 'required|int',
            'validity_period' => 'required|date',
        ]);

        if ($validator->fails()) {
            return $this->respondNotValidated(
                __('Validation failed'),
                $validator->errors()->first()
            );
        }

        $client_coupon = ClientCoupon::find($request->id);

        DB::beginTransaction();
        try {
            CouponHistory::create([
                'user_id' => $client_coupon->user_id,
                'client_coupon_id' => $client_coupon->id,
                'usage_type' => CouponHistory::USAGE_TYPE_UPDATE_EXPIRED_DATE,
                'number_of_coupons' => $client_coupon->remaining_coupon,
                'registration_date' => $now->format('Y_m_d'),
                'validity_period' => $request->validity_period,
            ]);

            // $client_coupon = ClientCoupon::find($request->id);
            $client_coupon->validity_period = $request->validity_period;
            $client_coupon->usage_type = CouponHistory::USAGE_TYPE_UPDATE_EXPIRED_DATE;
            $client_coupon->save();
            
            DB::commit();

        return response()->json([
            'message' => __('app.success'),
        ], 200);
        } catch (\Exception $exception) {
            DB::rollBack();
            return $this->respondNotValidated(
                __('app.failed'),
                $exception->getMessage()
            );
        }
    }
}
