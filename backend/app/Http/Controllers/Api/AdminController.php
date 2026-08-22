<?php

namespace App\Http\Controllers\Api;

use App\Domain\Ledger\LedgerService;
use App\Http\Controllers\Controller;
use App\Models\AiProviderRequest;
use App\Models\Aivva;
use App\Models\LedgerTransaction;
use App\Models\MarketplaceRequest;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminController extends Controller
{
    public function health(Request $request, LedgerService $ledger): JsonResponse
    {
        abort_unless($request->user()->is_admin, 403);

        return response()->json([
            'active_aivvas' => Aivva::query()->whereNotIn('status', ['DORMANT', 'PAUSED'])->count(),
            'paused' => Aivva::query()->where('status', 'PAUSED')->count(),
            'open_requests' => MarketplaceRequest::query()->where('status', 'OPEN')->count(),
            'settled_orders' => Order::query()->where('status', 'SETTLED')->count(),
            'ledger' => $ledger->integrity(),
            'recent_ai' => AiProviderRequest::query()->latest()->limit(15)->get(),
            'recent_ledger' => LedgerTransaction::query()->latest()->limit(15)->get(),
        ]);
    }
}
