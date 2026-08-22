<?php

namespace App\Domain\Ledger;

use App\Enums\LedgerAccountType;
use App\Models\LedgerAccount;
use App\Models\LedgerEntry;
use App\Models\LedgerTransaction;
use App\Models\Wallet;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class LedgerService
{
    public function platformIssuanceAccount(): LedgerAccount
    {
        return LedgerAccount::query()->firstOrCreate(
            ['code' => 'platform:credits_issued'],
            [
                'name' => 'Credits Issued',
                'type' => LedgerAccountType::Equity->value,
            ],
        );
    }

    public function platformEscrowAccount(): LedgerAccount
    {
        return LedgerAccount::query()->firstOrCreate(
            ['code' => 'platform:escrow_clearing'],
            [
                'name' => 'Escrow Clearing',
                'type' => LedgerAccountType::Clearing->value,
            ],
        );
    }

    /**
     * @param  list<array{account: LedgerAccount, debit?: int, credit?: int}>  $lines
     */
    public function record(string $type, string $description, array $lines, ?string $reference = null, array $meta = []): LedgerTransaction
    {
        return DB::transaction(function () use ($type, $description, $lines, $reference, $meta) {
            $debits = 0;
            $credits = 0;
            foreach ($lines as $line) {
                $debits += (int) ($line['debit'] ?? 0);
                $credits += (int) ($line['credit'] ?? 0);
                if (((int) ($line['debit'] ?? 0)) > 0 && ((int) ($line['credit'] ?? 0)) > 0) {
                    throw new LedgerException('An entry cannot be both debit and credit.');
                }
            }

            if ($debits === 0 || $debits !== $credits) {
                throw new LedgerException("Ledger transaction does not balance: debit {$debits} credit {$credits}.");
            }

            $transaction = LedgerTransaction::query()->create([
                'type' => $type,
                'reference' => $reference ?? (string) Str::uuid(),
                'description' => $description,
                'meta' => $meta,
                'settled_at' => now(),
            ]);

            foreach ($lines as $line) {
                LedgerEntry::query()->create([
                    'ledger_transaction_id' => $transaction->id,
                    'ledger_account_id' => $line['account']->id,
                    'debit' => (int) ($line['debit'] ?? 0),
                    'credit' => (int) ($line['credit'] ?? 0),
                ]);
            }

            return $transaction->load('entries');
        });
    }

    public function issueToWallet(Wallet $wallet, int $amount, string $reason, ?string $reference = null): LedgerTransaction
    {
        if ($amount <= 0) {
            throw new LedgerException('Issuance amount must be positive.');
        }

        $transaction = $this->record('ISSUANCE', $reason, [
            ['account' => $wallet->availableAccount(), 'debit' => $amount],
            ['account' => $this->platformIssuanceAccount(), 'credit' => $amount],
        ], $reference, ['kind' => 'issuance']);

        $wallet->increment('available_balance', $amount);

        return $transaction;
    }

    public function transfer(Wallet $from, Wallet $to, int $amount, string $reason, ?string $reference = null, array $meta = []): LedgerTransaction
    {
        if ($amount <= 0) {
            throw new LedgerException('Transfer amount must be positive.');
        }
        if ($from->available_balance < $amount) {
            throw new LedgerException('Insufficient available balance.');
        }

        $transaction = $this->record('TRANSFER', $reason, [
            ['account' => $from->availableAccount(), 'credit' => $amount],
            ['account' => $to->availableAccount(), 'debit' => $amount],
        ], $reference, $meta);

        $from->decrement('available_balance', $amount);
        $to->increment('available_balance', $amount);

        return $transaction;
    }

    public function lockEscrow(Wallet $buyer, int $amount, string $reason, ?string $reference = null): LedgerTransaction
    {
        if ($amount <= 0) {
            throw new LedgerException('Escrow amount must be positive.');
        }
        if ($buyer->available_balance < $amount) {
            throw new LedgerException('Insufficient available balance to lock escrow.');
        }

        $transaction = $this->record('ESCROW_LOCK', $reason, [
            ['account' => $buyer->availableAccount(), 'credit' => $amount],
            ['account' => $this->platformEscrowAccount(), 'debit' => $amount],
        ], $reference);

        $buyer->decrement('available_balance', $amount);
        $buyer->increment('held_balance', $amount);

        return $transaction;
    }

    public function settleEscrow(Wallet $buyer, Wallet $seller, int $amount, string $reason, ?string $reference = null): LedgerTransaction
    {
        if ($buyer->held_balance < $amount) {
            throw new LedgerException('Escrow hold is smaller than settlement amount.');
        }

        $transaction = $this->record('ESCROW_SETTLE', $reason, [
            ['account' => $this->platformEscrowAccount(), 'credit' => $amount],
            ['account' => $seller->availableAccount(), 'debit' => $amount],
        ], $reference);

        $buyer->decrement('held_balance', $amount);
        $seller->increment('available_balance', $amount);

        return $transaction;
    }

    public function refundEscrow(Wallet $buyer, int $amount, string $reason, ?string $reference = null): LedgerTransaction
    {
        if ($buyer->held_balance < $amount) {
            throw new LedgerException('Escrow hold is smaller than refund amount.');
        }

        $transaction = $this->record('ESCROW_REFUND', $reason, [
            ['account' => $this->platformEscrowAccount(), 'credit' => $amount],
            ['account' => $buyer->availableAccount(), 'debit' => $amount],
        ], $reference);

        $buyer->decrement('held_balance', $amount);
        $buyer->increment('available_balance', $amount);

        return $transaction;
    }

    /**
     * @return array{balanced: bool, debit_total: int, credit_total: int}
     */
    public function integrity(): array
    {
        $debits = (int) LedgerEntry::query()->sum('debit');
        $credits = (int) LedgerEntry::query()->sum('credit');

        return [
            'balanced' => $debits === $credits,
            'debit_total' => $debits,
            'credit_total' => $credits,
        ];
    }
}
