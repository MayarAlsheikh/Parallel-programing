<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\Order;
use App\Models\DailySalesReport;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ProcessDailySalesReportBatch implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected string $targetDate;

    public function __construct($date = null)
    {
        $this->targetDate = $date ?? Carbon::yesterday()->toDateString();
    }

    public function handle(): void
    {
        $salesBuffer = [];

        Order::whereDate('created_at', $this->targetDate)
            ->where('status', 'paid')
            ->chunk(100, function ($orders) use (&$salesBuffer) {
                foreach ($orders as $order) {
                    $storeId = $order->store_id;

                    if (!isset($salesBuffer[$storeId])) {
                        $salesBuffer[$storeId] = [
                            'total_sales' => 0,
                            'orders_count' => 0
                        ];
                    }

                    $salesBuffer[$storeId]['total_sales'] += $order->total_price;
                    $salesBuffer[$storeId]['orders_count'] += 1;
                }
            });

        foreach ($salesBuffer as $storeId => $data) {
            DailySalesReport::updateOrCreate(
                [
                    'store_id' => $storeId,
                    'report_date' => $this->targetDate
                ],
                [
                    'total_sales' => $data['total_sales'],
                    'orders_count' => $data['orders_count']
                ]
            );
        }
    }
}