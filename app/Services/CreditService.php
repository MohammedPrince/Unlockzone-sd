<?php

namespace App\Services;

use App\Exceptions\WorkflowException;
use App\Models\CreditTransaction;
use App\Models\ServiceRequest;
use App\Models\User;
use App\Repositories\ActivityLogRepository;
use App\Repositories\CreditRepository;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * The only place a wallet balance changes.
 * Every change runs in a transaction, locks the wallet row, refuses to go below zero,
 * and writes a ledger entry with the balance before and after.
 */
class CreditService
{
    public function __construct(
        private CreditRepository $credits,
        private ActivityLogRepository $logs,
    ) {
    }

    /** Admin adds credit to a customer. */
    public function recharge(User $customer, mixed $amount, ?string $reference, ?string $note, ?int $adminId): CreditTransaction
    {
        $cents = to_cents($amount);

        if ($cents <= 0) {
            throw new WorkflowException('The recharge amount must be greater than zero.');
        }

        $entry = $this->move($customer->id, 'recharge', $cents, [
            'reference' => $reference,
            'description' => $note ?: 'Account recharge',
            'performed_by' => $adminId,
        ]);

        $this->logs->log('credit_recharge', "Recharged {$customer->username} with " . money($entry->amount), $customer, [
            'amount' => $entry->amount, 'reference' => $entry->reference, 'balance_after' => $entry->balance_after,
        ], $adminId);

        return $entry;
    }

    /** Admin correction, up or down. A reason is required because it bypasses the normal flows. */
    public function adjust(User $customer, mixed $signedAmount, string $reason, ?string $reference, ?int $adminId): CreditTransaction
    {
        $cents = to_cents($signedAmount);

        if ($cents === 0) {
            throw new WorkflowException('The adjustment amount cannot be zero.');
        }

        $entry = $this->move($customer->id, 'adjustment', $cents, [
            'reference' => $reference,
            'description' => $reason,
            'performed_by' => $adminId,
        ]);

        $this->logs->log('credit_adjustment', "Adjusted {$customer->username} by " . money($entry->amount), $customer, [
            'amount' => $entry->amount, 'reason' => $reason, 'balance_after' => $entry->balance_after,
        ], $adminId);

        return $entry;
    }

    /** Takes the price of a service request from the customer's wallet. */
    public function charge(ServiceRequest $request, string $serviceName): CreditTransaction
    {
        return $this->move($request->user_id, 'deduction', -to_cents($request->amount), [
            'reference' => $request->reference,
            'description' => "Service: {$serviceName}",
            'service_request_id' => $request->id,
        ]);
    }

    /**
     * Gives the price of a request back as its own ledger entry (the deduction is never edited).
     * The caller holds a lock on the request row and has checked it is not refunded yet.
     */
    public function refund(ServiceRequest $request, string $reason, ?int $adminId): CreditTransaction
    {
        return $this->move($request->user_id, 'refund', to_cents($request->amount), [
            'reference' => $request->reference,
            'description' => $reason,
            'service_request_id' => $request->id,
            'performed_by' => $adminId,
        ]);
    }

    private function move(int $userId, string $type, int $cents, array $meta): CreditTransaction
    {
        return DB::transaction(function () use ($userId, $type, $cents, $meta) {
            // Concurrent operations on the same wallet queue up here.
            $wallet = $this->credits->lockWallet($userId);

            $before = to_cents($wallet->balance);
            $after = $before + $cents;

            if ($after < 0) {
                throw new WorkflowException(__('Insufficient credit. Available: :available, required: :required.', [
                    'available' => money(from_cents($before)),
                    'required' => money(from_cents(abs($cents))),
                ]));
            }

            $this->credits->saveBalance($wallet, from_cents($after));

            return $this->credits->createEntry([
                'user_id' => $userId,
                'transaction_type' => $type,
                'amount' => from_cents($cents),
                'balance_before' => from_cents($before),
                'balance_after' => from_cents($after),
                'reference' => ($meta['reference'] ?? null) ?: 'TXN-' . strtoupper(Str::random(10)),
                'description' => $meta['description'] ?? null,
                'service_request_id' => $meta['service_request_id'] ?? null,
                'performed_by' => $meta['performed_by'] ?? null,
            ]);
        });
    }
}
