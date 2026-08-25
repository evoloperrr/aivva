<?php

namespace Tests\Unit;

use App\Domain\Economy\WalletService;
use App\Domain\Ledger\LedgerException;
use App\Domain\Ledger\LedgerService;
use App\Models\LedgerEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LedgerServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_issuance_and_transfer_balance(): void
    {
        $this->seedCivilization();
        $luna = $this->makeLivingAivva();
        $buyerOwner = User::factory()->create();
        $buyer = $this->makeLivingAivva($buyerOwner, ['name' => 'NOVA']);

        $ledger = app(LedgerService::class);
        $wallets = app(WalletService::class);
        $from = $wallets->ensureForAivva($buyer);
        $to = $wallets->ensureForAivva($luna);

        $ledger->transfer($from->fresh(), $to->fresh(), 35, 'Test purchase', 't-35');

        $this->assertSame(65, (int) $from->fresh()->available_balance);
        $this->assertSame(135, (int) $to->fresh()->available_balance);

        $debits = (int) LedgerEntry::query()->sum('debit');
        $credits = (int) LedgerEntry::query()->sum('credit');
        $this->assertSame($debits, $credits);
        $this->assertTrue($ledger->integrity()['balanced']);
    }

    public function test_wallet_cannot_go_negative(): void
    {
        $this->seedCivilization();
        $aivva = $this->makeLivingAivva();
        $other = $this->makeLivingAivva(User::factory()->create(), ['name' => 'KITE']);
        $ledger = app(LedgerService::class);
        $wallets = app(WalletService::class);

        $this->expectException(LedgerException::class);
        $ledger->transfer(
            $wallets->ensureForAivva($aivva)->fresh(),
            $wallets->ensureForAivva($other)->fresh(),
            10_000,
            'Overdraft attempt',
        );
    }

    public function test_escrow_settlement_is_idempotent(): void
    {
        $this->seedCivilization();
        $seller = $this->makeLivingAivva();
        $buyer = $this->makeLivingAivva(User::factory()->create(), ['name' => 'NOVA']);
        $ledger = app(LedgerService::class);
        $wallets = app(WalletService::class);
        $buyerWallet = $wallets->ensureForAivva($buyer)->fresh();
        $sellerWallet = $wallets->ensureForAivva($seller)->fresh();

        $ledger->lockEscrow($buyerWallet->fresh(), 35, 'lock', 'escrow:lock:order-1');
        $ledger->settleEscrow($buyerWallet->fresh(), $sellerWallet->fresh(), 35, 'settle', 'escrow:settle:order-1');

        $this->assertSame(65, (int) $buyerWallet->fresh()->available_balance);
        $this->assertSame(0, (int) $buyerWallet->fresh()->held_balance);
        $this->assertSame(135, (int) $sellerWallet->fresh()->available_balance);

        $this->expectException(LedgerException::class);
        $ledger->settleEscrow($buyerWallet->fresh(), $sellerWallet->fresh(), 35, 'settle again', 'escrow:settle:order-1');
    }
}
