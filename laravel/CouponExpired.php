<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\ClientCoupon;
use App\Models\CouponHistory;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class CouponExpired extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'coupon:expired-check';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Batch to monitor expired records';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $now = Carbon::now();
        // 有効期限が切れていて、まだ有効期限切れのフラグが立っていないデータを代入
        $expired_data = ClientCoupon::where('validity_period', '<', $now)
            ->where('usage_type', '!=', CouponHistory::USAGE_TYPE_EXPIRED);
        $get_expired_data = $expired_data->get();

        DB::beginTransaction();
        try {
            $expired_data->update([
                'remaining_coupon' => 0,
                'usage_type' => CouponHistory::USAGE_TYPE_EXPIRED,
            ]);
            
            foreach ($get_expired_data as $record) {
                CouponHistory::create([
                    'user_id' => $record->user_id,
                    'client_coupon_id' => $record->id,
                    'usage_type' => CouponHistory::USAGE_TYPE_EXPIRED,
                    'number_of_coupons' => $record->remaining_coupon,
                    'registration_date' => $now->format('Y_m_d'),
                    'validity_period' => $record->validity_period,
                ]);
            }
            
            DB::commit();
            
        } catch (\Exception $exception) {
            DB::rollBack();
            return $this->respondNotValidated(
                __('app.failed'),
                $exception->getMessage()
            );
        }
    }
}