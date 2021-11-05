<?php
//現在時間から有効期限を作成
//try catch
//ORMのwhereInで取得プロパティの値を制限
//ORMのwhere内の比較演算子

namespace App\Http\Controllers\Api\Hrm\Client;

use App\Http\Controllers\Api\ApiController;
use App\Models\ClientCouponHistory;
use App\Models\CouponHistory;
use App\Models\ClientCoupon;
use App\Models\CouponType;
use Exception;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class CouponController extends ApiController
{
    // 回数券を増やすAPI
    public function createCoupon(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
          'coupon_type_id' => 'required|integer',
        ]);

        if ($validator->fails()) {
          Log::error('validation_error');
          return $this->respondNotValidated(
              $validator->errors()->first(),
              $validator->errors()->all()
          );
        }


        $now = Carbon::now();
        // 年月日の表示に変更
        $registration_date = $now->format('Y_m_d');
        // 有効期限を180日に設定している
        $validity_period = $now->addDays(180);

        // ここからtry catchのテンプレート
        DB::beginTransaction();
        try {
            //リクエストのidをデータベーステーブルの値を取得している。
            $number_of_coupons = CouponType::find($request->coupon_type_id)->number_of_sales;
            // 下で使うので変数を作っている
            $client_coupons = ClientCoupon::create([
              'user_id' => Auth::user()->id,
              'coupon_type_id' => $request->input('coupon_type_id'),
              'remaining_coupon' => $number_of_coupons,
              'usage_type' => ClientCoupon::USAGE_TYPE_BUY,//定義はモデルに記載
              'validity_period' => $validity_period,
            ]);
       
            CouponHistory::create([
              'user_id' => Auth::user()->id,
              'coupon_type_id' => $request->input('coupon_type_id'),
              'client_coupon_id' => $client_coupons->id,
              'usage_type' => CouponHistory::USAGE_TYPE_BUY,
              'number_of_coupons' => $number_of_coupons,
              'registration_date' => $registration_date,
              'validity_period' => $validity_period,
            ]);
            // テンプレ必ず入れる。入れないとデータが保存できない
            DB::commit();

            return response()->json([
              'message' => __('app.success'),
            ], 200);
        } catch (\Exception $exception) {
            // 途中でエラーが起きるとデータを元に戻す
            DB::rollBack();
            return $this->respondNotValidated(
                __('app.failed'),
                $exception->getMessage()
          );
        }
    }
  
    // 回数券を減らすAPI
    public function updateCoupon(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
          'id' => 'required|integer',
          'number_of_coupons' => 'required|integer',
        ]);

        if ($validator->fails()) {
          Log::error('validation_error');
          return $this->respondNotValidated(
              $validator->errors()->first(),
              $validator->errors()->all()
          );
        }

        DB::beginTransaction();
        try {
              //消費する予定の回数券が指定したクーポンより数が多かったらエラー
              if ($request->number_of_coupons > ClientCoupon::find($request->id)->remaining_coupon) {
                throw new Exception();  
              } else {
              
              //データを更新
              $client_coupon = ClientCoupon::find($request->id);
              $total_coupon = $client_coupon->remaining_coupon - $request->number_of_coupons;
              $client_coupon->remaining_coupon = $total_coupon;
              $client_coupon->usage_type = ClientCoupon::USAGE_TYPE_USE;

              $client_coupon->save();
              
              //履歴テーブルに新しいレコードを作成
              CouponHistory::create([
                'user_id' => Auth::user()->id,
                'client_coupon_id' => $request->id,
                'usage_type' => CouponHistory::USAGE_TYPE_USE,
                'number_of_coupons' => $request->input('number_of_coupons'), // 自然数でリクエストが来る。
                'registration_date' => Carbon::now()->format('Y_m_d'),
                'validity_period' => ClientCoupon::find($request->id)->validity_period,
              ]);
            }

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

    // 回数券の所持数を返すAPI
    public function getHistoryCoupon(): JsonResponse
    {
        //ログインしているユーザーのみ
        $remaining_coupons = ClientCoupon::where('user_id', Auth::user()->id)
                    // 指定キーの値が1, 2のみ
                    ->whereIn('usage_type', [1, 2])
                    // 有効期限と現在時間を比較
                    ->where('validity_period', '>', Carbon::now())
                    // 指定キーの値を足す
                    ->sum('remaining_coupon');
        
        $user_histories = CouponHistory::where('user_id', Auth::user()->id);
        // CouponHistoryからAuthユーザーの情報を全て取得して配列で返す。
        $coupon_history = $user_histories->orderBy('id', 'desc')->get();


          return response()->json([
            'message' => __('app.success'),
            'remaining_coupons' => $remaining_coupons,
            'coupon_hisotry' => $coupon_history,
        ], 200);
    }
}
