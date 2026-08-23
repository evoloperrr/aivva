<?php

namespace App\Domain\Economy;

use App\Domain\Brain\AivvaBrainInterface;
use App\Domain\Ledger\LedgerService;
use App\Enums\EscrowStatus;
use App\Models\Aivva;
use App\Models\CreatedWork;
use App\Models\Escrow;
use App\Models\MarketplaceRequest;
use App\Models\Order;
use App\Models\VerificationCase;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class OrderSettlementService
{
    public function __construct(
        private readonly LedgerService $ledger,
        private readonly WalletService $wallets,
    ) {}

    private function brain(): AivvaBrainInterface
    {
        return app(AivvaBrainInterface::class);
    }

    public function lockEscrow(Order $order): Escrow
    {
        return DB::transaction(function () use ($order) {
            $locked = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();
            $existing = $locked->escrow;
            if ($existing) {
                return $existing;
            }

            $buyer = $locked->buyer()->firstOrFail();
            $wallet = $this->wallets->ensureForAivva($buyer)->fresh();
            $this->ledger->lockEscrow(
                $wallet,
                $locked->amount,
                "Escrow locked for order {$locked->id}",
                'escrow:lock:'.$locked->id,
            );

            if ($locked->status === 'OPEN') {
                $locked->status = 'ESCROWED';
                $locked->save();
            }

            return Escrow::query()->create([
                'order_id' => $locked->id,
                'amount' => $locked->amount,
                'status' => EscrowStatus::Locked,
                'locked_at' => now(),
            ]);
        });
    }

    public function markDelivered(Order $order, CreatedWork $work): Order
    {
        $escrow = $order->escrow;
        if (! $escrow || $escrow->status !== EscrowStatus::Locked) {
            throw new RuntimeException('Escrow must be locked before delivery.');
        }

        $order->work_id = $work->id;
        $order->status = 'DELIVERED_PENDING_VERIFICATION';
        $order->save();

        return $order->fresh();
    }

    /**
     * Independent verification with a fresh context (brief + deliverable only).
     *
     * @return array{status: string, confidence: float, requirements_met: bool, issues: list<string>, case: VerificationCase}
     */
    public function verify(Order $order, Aivva $verifierRole): array
    {
        $order->load(['work', 'buyer', 'seller']);
        if ($verifierRole->id === $order->seller_aivva_id) {
            throw new RuntimeException('Seller cannot verify own work.');
        }
        if ($order->status !== 'DELIVERED_PENDING_VERIFICATION' || ! $order->work) {
            throw new RuntimeException('Order is not delivered pending verification.');
        }

        $requirements = $this->requestText($order);
        $workText = $this->workText($order->work);

        $decision = $this->brain()->verifyWork($verifierRole, [
            'requirements' => $requirements,
            'work' => $workText,
            'brief' => $requirements,
            'order_id' => $order->id,
            'prompt' => "Verify deliverable against requirements only.\nREQUIREMENTS:\n{$requirements}\nDELIVERABLE:\n{$workText}",
        ]);

        $status = strtoupper((string) ($decision->raw['status'] ?? $decision->action));
        $pass = $status === 'PASS';
        $issues = $decision->raw['issues'] ?? [];
        if (! is_array($issues)) {
            $issues = [$issues];
        }

        $case = VerificationCase::query()->create([
            'claim' => 'order:'.$order->id,
            'subject_type' => Order::class,
            'subject_id' => $order->id,
            'confidence' => (int) round($decision->confidence * 100),
            'report' => [
                'status' => $pass ? 'PASS' : 'FAIL',
                'confidence' => $decision->confidence,
                'requirements_met' => $pass,
                'issues' => array_values($issues),
                'role' => 'independent_verifier',
                'limitation' => 'Same provider family as creation if only one brain is configured.',
            ],
            'status' => $pass ? 'PASS' : 'FAIL',
        ]);

        return [
            'status' => $pass ? 'PASS' : 'FAIL',
            'confidence' => $decision->confidence,
            'requirements_met' => $pass,
            'issues' => array_values($issues),
            'case' => $case,
        ];
    }

    public function settle(Order $order, ?Aivva $actor = null): Order
    {
        if ($actor && $actor->id === $order->seller_aivva_id) {
            throw new RuntimeException('Seller cannot settle its own escrow.');
        }

        if ($order->status === 'COMPLETED') {
            return $order;
        }

        $delivered = $order->work_id
            && in_array($order->status, ['DELIVERED_PENDING_VERIFICATION', 'VERIFIED', 'COMPLETED'], true);
        if (! $delivered) {
            throw new RuntimeException('Order cannot settle before delivery.');
        }
        if ($order->status !== 'VERIFIED') {
            throw new RuntimeException('Order cannot settle before verification.');
        }

        $escrow = $order->escrow;
        if (! $escrow || $escrow->status !== EscrowStatus::Locked) {
            throw new RuntimeException('Escrow is not locked.');
        }

        $key = 'escrow:settle:'.$order->id;
        if ($escrow->settle_idempotency_key === $key || $escrow->status === EscrowStatus::Settled) {
            $order->status = 'COMPLETED';
            $order->save();

            return $order->fresh();
        }

        $buyer = $order->buyer()->firstOrFail();
        $seller = $order->seller()->firstOrFail();
        $this->ledger->settleEscrow(
            $this->wallets->ensureForAivva($buyer)->fresh(),
            $this->wallets->ensureForAivva($seller)->fresh(),
            $order->amount,
            "Settlement for order {$order->id}",
            $key,
        );

        $escrow->status = EscrowStatus::Settled;
        $escrow->settled_at = now();
        $escrow->settle_idempotency_key = $key;
        $escrow->save();
        $order->status = 'COMPLETED';
        $order->save();

        return $order->fresh('escrow');
    }

    public function refund(Order $order): Order
    {
        $escrow = $order->escrow;
        if (! $escrow) {
            throw new RuntimeException('No escrow to refund.');
        }
        $key = 'escrow:refund:'.$order->id;
        if ($escrow->refund_idempotency_key === $key || $escrow->status === EscrowStatus::Refunded) {
            $order->status = 'REFUNDED';
            $order->save();

            return $order->fresh();
        }
        if ($escrow->status !== EscrowStatus::Locked) {
            throw new RuntimeException('Only a locked escrow can be refunded.');
        }

        $buyer = $order->buyer()->firstOrFail();
        $this->ledger->refundEscrow(
            $this->wallets->ensureForAivva($buyer)->fresh(),
            $order->amount,
            "Refund for order {$order->id}",
            $key,
        );
        $escrow->status = EscrowStatus::Refunded;
        $escrow->refund_idempotency_key = $key;
        $escrow->save();
        $order->status = 'REFUNDED';
        $order->save();

        return $order->fresh('escrow');
    }

    public function markVerified(Order $order): Order
    {
        if ($order->status !== 'DELIVERED_PENDING_VERIFICATION') {
            throw new RuntimeException('Only a delivered order can be marked verified.');
        }
        $order->status = 'VERIFIED';
        $order->save();

        return $order->fresh();
    }

    private function requestText(Order $order): string
    {
        if ($order->request_id) {
            $request = MarketplaceRequest::query()->find($order->request_id);

            return trim(($request?->title ?? '').' '.($request?->description ?? ''));
        }

        return 'short promotional concept';
    }

    private function workText(CreatedWork $work): string
    {
        $body = $work->body;
        if (is_array($body)) {
            return trim(($work->title ?? '').' '.json_encode($body, JSON_UNESCAPED_UNICODE));
        }

        return (string) $work->title;
    }
}
