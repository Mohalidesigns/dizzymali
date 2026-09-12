<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\MoneyResource;
use App\Models\FabricVariant;
use App\Models\MeasurementProfile;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(): Response
    {
        $paidStatuses = array_map(
            fn (OrderStatus $s) => $s->value,
            array_filter(OrderStatus::cases(), fn (OrderStatus $s) => $s->isPaid()),
        );

        $revenue = fn ($from) => (int) Order::query()
            ->whereIn('status', $paidStatuses)
            ->where('placed_at', '>=', $from)
            ->sum('total_kobo');

        return Inertia::render('admin/Dashboard', [
            'revenue' => [
                'today' => MoneyResource::make($revenue(today())),
                'week' => MoneyResource::make($revenue(now()->subWeek())),
                'month' => MoneyResource::make($revenue(now()->subMonth())),
            ],
            'ordersByStage' => Order::query()
                ->select('status', DB::raw('count(*) as total'))
                ->whereNotIn('status', [OrderStatus::Draft->value])
                ->groupBy('status')
                ->pluck('total', 'status')
                ->mapWithKeys(fn ($count, $status) => [
                    $status => ['label' => OrderStatus::from($status)->label(), 'count' => $count],
                ]),
            'lowStock' => FabricVariant::query()
                ->with('fabric')
                ->where('is_active', true)
                ->whereRaw('(stock_yards - reserved_yards) <= low_stock_threshold_yards')
                ->limit(10)
                ->get()
                ->map(fn (FabricVariant $v) => [
                    'id' => $v->id,
                    'name' => $v->displayName(),
                    'available_yards' => round($v->availableYards(), 2),
                    'threshold' => (float) $v->low_stock_threshold_yards,
                ]),
            'overdue' => Order::query()
                ->with('user:id,name')
                ->whereNotNull('promised_at')
                ->whereDate('promised_at', '<', today())
                ->whereNotIn('status', [
                    OrderStatus::Delivered->value, OrderStatus::Closed->value,
                    OrderStatus::Cancelled->value, OrderStatus::Refunded->value,
                    OrderStatus::Draft->value,
                ])
                ->limit(10)
                ->get()
                ->map(fn (Order $o) => [
                    'id' => $o->id,
                    'reference' => $o->reference,
                    'customer' => $o->user?->name,
                    'promised_at' => $o->promised_at?->toDateString(),
                    'status' => $o->status->label(),
                ]),
            'pendingMeasurementReviews' => MeasurementProfile::query()
                ->where('review_status', 'pending_review')
                ->count(),
            'newCustomers' => User::query()
                ->whereHas('orders')
                ->where('created_at', '>=', now()->subMonth())
                ->count(),
        ]);
    }
}
