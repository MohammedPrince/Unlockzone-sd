<?php

namespace App\Services;

use App\Exceptions\WorkflowException;
use App\Models\Service;
use App\Models\ServiceRequest;
use App\Models\User;
use App\Repositories\ActivityLogRepository;
use App\Repositories\ServiceRepository;
use App\Repositories\ServiceRequestRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class ServiceRequestService
{
    public function __construct(
        private ServiceRequestRepository $requests,
        private ServiceRepository $services,
        private CreditService $credits,
        private ActivityLogRepository $logs,
    ) {
    }

    public function paginate(array $filters, int $perPage = 20): LengthAwarePaginator
    {
        return $this->requests->paginate($filters, $perPage);
    }

    public function statusCounts(?int $userId = null): array
    {
        return $this->requests->statusCounts($userId);
    }

    public function find(int $id): ServiceRequest
    {
        return $this->requests->findOrFail($id);
    }

    public function findOwned(User $customer, int $id): ServiceRequest
    {
        return $this->requests->findOwnedOrFail($customer->id, $id);
    }

    /** The service a customer is about to order, or a WorkflowException if it is not on sale. */
    public function orderableService(int $serviceId): Service
    {
        $service = $this->services->findAvailable($serviceId);

        if (!$service) {
            throw new WorkflowException('This service is not available right now.');
        }

        return $service;
    }

    /**
     * Customer buys a service.
     * Price comes from the database, never from the browser. The request row and the ledger
     * deduction are written in one transaction: either both exist or neither does.
     * $idempotencyKey is generated when the order form is shown, so a double submit is charged once.
     */
    public function submit(User $customer, int $serviceId, array $inputs, string $idempotencyKey): ServiceRequest
    {
        if ($existing = $this->requests->findByIdempotencyKey($customer->id, $idempotencyKey)) {
            return $existing;
        }

        // Read the account again: a suspension made a moment ago must count.
        $current = $customer->fresh();

        if (!$current?->isActive()) {
            throw new WorkflowException('Your account is not active.');
        }

        $service = $this->orderableService($serviceId);

        if (!$current->canOrder($service->id)) {
            throw new WorkflowException('This service is not available for your account. Please contact support.');
        }

        $requestData = $this->validateInputs($service, $inputs);

        try {
            $request = DB::transaction(function () use ($customer, $service, $requestData, $idempotencyKey) {
                $request = $this->requests->create([
                    'user_id' => $customer->id,
                    'service_id' => $service->id,
                    'reference' => $this->requests->newReference(),
                    'idempotency_key' => $idempotencyKey,
                    'amount' => $service->price,
                    // Orders go straight to processing: work starts at once, so the customer cannot cancel for a refund.
                    // Credit still comes back automatically if the order fails or an administrator cancels it.
                    'status' => 'processing',
                    'request_data' => $requestData,
                    'api_provider_id' => $service->usesProvider() ? $service->api_provider_id : null,
                ]);

                // Throws when credit is short, which rolls the request row back too.
                $this->credits->charge($request, $service->name);

                return $request;
            });
        } catch (UniqueConstraintViolationException) {
            // Two identical submits raced; the other one won and already charged.
            return $this->requests->findByIdempotencyKey($customer->id, $idempotencyKey)
                ?? throw new WorkflowException('This request could not be submitted. Please try again.');
        }

        $this->logs->log('request_submitted', "Ordered {$service->name} ({$request->reference})", $request, [
            'amount' => $request->amount,
        ], $customer->id);

        return $request;
    }

    /**
     * Checks the customer's answers against the fields the service requires.
     * Returns the data stored on the request: [{label, type, value}, ...].
     */
    private function validateInputs(Service $service, array $inputs): array
    {
        $fields = $service->inputFields();
        $rules = [];
        $names = [];

        foreach ($fields as $field) {
            $rules["fields.{$field->id}"] = $field->rules();
            $names["fields.{$field->id}"] = $field->label;
        }

        $clean = Validator::make(['fields' => $inputs], $rules, [], $names)->validate();

        return $fields->map(fn ($field) => [
            'label' => $field->label,
            'type' => $field->input_type,
            'value' => trim((string) ($clean['fields'][$field->id] ?? '')),
        ])->all();
    }

    /**
     * Moves a request along: processing, completed (with a result), failed or cancelled.
     * Failed and cancelled refund the customer automatically, once.
     * $admin is null when a connected provider's answer closes the request.
     */
    public function updateStatus(int $requestId, string $status, ?string $result, ?string $error, ?User $admin): ServiceRequest
    {
        $request = DB::transaction(function () use ($requestId, $status, $result, $error, $admin) {
            $request = $this->requests->lock($requestId);

            if (!in_array($status, $request->nextStatuses(), true)) {
                throw new WorkflowException(__('A :from request cannot be changed to :to.', ['from' => __($request->status), 'to' => __($status)]));
            }

            $request->status = $status;
            $request->processed_by = $admin?->id ?? $request->processed_by;

            if ($status === 'completed') {
                $request->response_data = ['result' => (string) $result];
                $request->error_message = null;
                $request->completed_at = now();
            }

            if (in_array($status, ['failed', 'cancelled'], true)) {
                $request->error_message = $error ?: ($status === 'failed' ? 'The service could not be completed.' : 'Cancelled by administrator.');
                $this->refundLocked($request, ucfirst($status) . ' request refund', $admin?->id);
            }

            $request->save();

            return $request;
        });

        $this->logs->log('request_' . $status, "Marked {$request->reference} as {$status}", $request, $admin ? [] : ['by' => 'provider'], $admin?->id);

        return $request;
    }

    /** Refund guard: refunded_at is set under the row lock, so a request is refunded at most once. */
    private function refundLocked(ServiceRequest $request, string $reason, ?int $adminId): void
    {
        if ($request->refunded_at !== null || to_cents($request->amount) <= 0) {
            return;
        }

        $this->credits->refund($request, $reason, $adminId);
        $request->refunded_at = now();
    }

    public function lockedAmount(int $userId): string
    {
        return $this->requests->lockedAmount($userId);
    }

    /** A customer's own orders as CSV rows: what they entered and the code they received. */
    public function customerExportRows(int $userId, array $filters): \Generator
    {
        yield [__('ID'), __('Date'), __('Service'), __('Credit'), __('Status'), __('Code'), __('Fields')];

        foreach ($this->requests->cursor(['user_id' => $userId] + $filters) as $r) {
            yield [
                $r->id, $r->created_at->format('Y-m-d H:i'), $r->service?->name, $r->amount, __(ucfirst($r->status)),
                $r->response_data['result'] ?? $r->error_message ?? '',
                collect($r->request_data ?? [])->map(fn ($i) => ($i['label'] ?? '') . ': ' . ($i['value'] ?? ''))->implode(' | '),
            ];
        }
    }

    public function exportRows(array $filters): \Generator
    {
        yield ['Reference', 'Date', 'Customer', 'Username', 'Service', 'Amount', 'Status', 'Refunded'];

        foreach ($this->requests->cursor($filters) as $r) {
            yield [
                $r->reference, $r->created_at->format('Y-m-d H:i'), $r->user?->name, $r->user?->username,
                $r->service?->name, $r->amount, $r->status, $r->refunded_at ? 'yes' : 'no',
            ];
        }
    }
}
