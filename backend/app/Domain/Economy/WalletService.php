<?php

namespace App\Domain\Economy;

use App\Domain\Ledger\LedgerService;
use App\Enums\LedgerAccountType;
use App\Models\Aivva;
use App\Models\LedgerAccount;
use App\Models\Wallet;
use Illuminate\Support\Str;

class WalletService
{
    public function __construct(
        private readonly LedgerService $ledger,
    ) {}

    public function ensureForAivva(Aivva $aivva): Wallet
    {
        $wallet = Wallet::query()->firstOrCreate(
            [
                'owner_type' => 'aivva',
                'owner_id' => $aivva->id,
                'currency' => config('aivva.currency'),
            ],
            ['available_balance' => 0, 'held_balance' => 0],
        );

        LedgerAccount::query()->firstOrCreate(
            ['code' => 'wallet:'.$wallet->id.':available'],
            [
                'name' => $aivva->name.' available',
                'type' => LedgerAccountType::Asset->value,
                'wallet_id' => $wallet->id,
            ],
        );

        LedgerAccount::query()->firstOrCreate(
            ['code' => 'wallet:'.$wallet->id.':held'],
            [
                'name' => $aivva->name.' held',
                'type' => LedgerAccountType::Asset->value,
                'wallet_id' => $wallet->id,
            ],
        );

        return $wallet->fresh();
    }

    public function issueStarterCredits(Aivva $aivva): Wallet
    {
        $wallet = $this->ensureForAivva($aivva);
        $amount = (int) config('aivva.starter_credits', 100);
        if ($amount > 0 && $wallet->available_balance === 0 && $wallet->held_balance === 0) {
            $this->ledger->issueToWallet(
                $wallet,
                $amount,
                "Starter credits issued to {$aivva->name}",
                'issue:starter:'.$aivva->id,
            );
            $wallet->refresh();
        }

        return $wallet;
    }

    /**
     * Owner-initiated funding, separate from the one-time starter grant —
     * an owner can top up their own AIVVA's wallet as many times as they want.
     */
    public function topUp(Aivva $aivva, int $amount): Wallet
    {
        $wallet = $this->ensureForAivva($aivva);
        $this->ledger->issueToWallet(
            $wallet,
            $amount,
            "Owner top-up for {$aivva->name}",
            'topup:'.$aivva->id.':'.now()->timestamp.':'.Str::random(8),
        );

        return $wallet->fresh();
    }
}
