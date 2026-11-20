<?php

namespace App\Services;

use App\Exceptions\WorkflowException;
use App\Models\User;
use App\Repositories\ActivityLogRepository;
use App\Repositories\CreditRepository;
use App\Repositories\CustomerRepository;
use App\Repositories\ServiceRequestRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class CustomerService
{
    private const PROFILE_FIELDS = [
        // The address columns (address1, address2, country, state, city, zip_code) are no longer asked for anywhere.
        'first_name', 'last_name', 'username', 'email', 'phone',
    ];

    public function __construct(
        private CustomerRepository $customers,
        private CreditRepository $credits,
        private ServiceRequestRepository $requests,
        private ActivityLogRepository $logs,
        private EmailNotifier $mail,
    ) {
    }

    public function paginate(array $filters): LengthAwarePaginator
    {
        return $this->customers->paginate($filters);
    }

    public function statusCounts(): array
    {
        return $this->customers->statusCounts();
    }

    public function find(int $id): User
    {
        return $this->customers->findOrFail($id);
    }

    public function allForSelect()
    {
        return $this->customers->allForSelect();
    }

    /**
     * Public sign-up. The account starts as pending and a confirmation link is emailed to the customer.
     * Opening the link (within EMAIL_LINK_MINUTES) activates the account by itself when Settings say
     * "Active"; otherwise, or if the link is never opened, an administrator activates it.
     */
    public function register(array $data, ?string $source = null): User
    {
        if (setting('allow_registration') !== '1') {
            throw new WorkflowException('Registration is currently closed.');
        }

        // Sign-up does not ask for a name: the first name starts as the username, editable later in My profile.
        $data['first_name'] = filled($data['first_name'] ?? null) ? $data['first_name'] : $data['username'];

        $customer = $this->store($data, 'pending');

        // Where they came from (Facebook, WhatsApp...), as detected on their first page of this visit.
        $customer->forceFill(['signup_source' => $source ? mb_substr($source, 0, 30) : 'direct'])->save();

        $this->logs->log('customer_registered', "New customer registered: {$customer->username}", $customer, [], $customer->id);
        $this->mail->confirmEmail($customer);
        $this->mail->customerRegistered($customer);

        return $customer;
    }

    /** The customer opened their confirmation link. Only a pending account is activated; a suspended one stays suspended. */
    public function confirmEmail(User $customer): User
    {
        if (!$customer->hasConfirmedEmail()) {
            $customer->forceFill(['email_verified_at' => now()])->save();
        }

        if ($customer->status === 'pending' && setting('new_account_status') !== 'pending') {
            $this->customers->setStatus($customer, 'active');
        }

        $this->logs->log('customer_email_confirmed', "{$customer->username} confirmed their email", $customer, [], $customer->id);

        return $customer;
    }

    public function createByAdmin(array $data, int $adminId): User
    {
        $customer = $this->store($data, $data['status'] ?? 'active');
        $this->applyTag($customer, $data);

        $this->logs->log('customer_created', "Created customer {$customer->username}", $customer, [], $adminId);

        return $customer;
    }

    /** Sets the admin's label for a customer (e.g. "VIP"); empty removes it. Not mass-assignable on purpose. */
    private function applyTag(User $customer, array $data): void
    {
        if (!array_key_exists('customer_tag', $data)) {
            return;
        }

        $tag = trim((string) $data['customer_tag']);
        $wasVipTag = User::isVipTag($customer->customer_tag);
        $customer->forceFill(['customer_tag' => $tag !== '' ? mb_substr($tag, 0, 30) : null]);

        // The "VIP" tag and VIP prices go together: adding the tag gives VIP prices, removing it takes them away.
        if (User::isVipTag($tag)) {
            $customer->forceFill(['is_vip' => true]);
        } elseif ($wasVipTag) {
            $customer->forceFill(['is_vip' => false]);
        }

        $customer->save();
    }

    /** Creates the user and their empty wallet together. */
    private function store(array $data, string $status): User
    {
        $payload = Arr::only($data, self::PROFILE_FIELDS);
        $payload['name'] = $this->fullName($data);
        $payload['password'] = $data['password'];

        return DB::transaction(fn () => $this->customers->create($payload, $status));
    }

    public function update(User $customer, array $data, ?int $adminId = null): User
    {
        $payload = Arr::only($data, self::PROFILE_FIELDS);
        $payload['name'] = $this->fullName(array_merge($customer->only(['first_name', 'last_name']), $data));

        if (!empty($data['password'])) {
            $payload['password'] = $data['password'];
        }

        $this->customers->update($customer, $payload);

        // Only an administrator can change the tag; a customer editing their own profile never sends it.
        if ($adminId !== null) {
            $this->applyTag($customer, $data);
        }

        $this->logs->log('customer_updated', "Updated profile of {$customer->username}", $customer, [
            'password_changed' => !empty($data['password']),
        ], $adminId ?? $customer->id);

        return $customer;
    }

    public function changeStatus(User $customer, string $status, int $adminId): User
    {
        if (!in_array($status, User::STATUSES, true)) {
            throw new WorkflowException('Unknown account status.');
        }

        $old = $customer->status;
        $this->customers->setStatus($customer, $status);

        $this->logs->log('customer_status', "Changed {$customer->username} from {$old} to {$status}", $customer, [
            'from' => $old, 'to' => $status,
        ], $adminId);

        return $customer;
    }

    /** Stops (or allows again) every service order for this customer. The account itself keeps working. */
    public function suspendAllServices(User $customer, bool $suspended, int $adminId): void
    {
        $customer->forceFill(['services_suspended' => $suspended])->save();

        $this->logs->log('customer_services', ($suspended ? 'Suspended' : 'Restored') . " all services for {$customer->username}", $customer, [], $adminId);
    }

    /** VIP customers pay the VIP prices (Services > VIP prices). Set only by an administrator. */
    public function setVip(User $customer, bool $vip, int $adminId): void
    {
        $customer->forceFill(['is_vip' => $vip]);

        // A "VIP" tag would say VIP while they pay normal prices: removing VIP removes that tag too.
        if (!$vip && User::isVipTag($customer->customer_tag)) {
            $customer->forceFill(['customer_tag' => null]);
        }

        $customer->save();

        $this->logs->log('customer_vip', ($vip ? 'Enabled' : 'Disabled') . " VIP prices for {$customer->username}", $customer, [], $adminId);
    }

    /** Suspends the customer from one service, or lifts that suspension. */
    public function blockService(User $customer, int $serviceId, bool $blocked, int $adminId): void
    {
        $service = \App\Models\Service::findOrFail($serviceId);

        if ($blocked) {
            $customer->blockedServices()->syncWithoutDetaching([$service->id]);
        } else {
            $customer->blockedServices()->detach($service->id);
        }

        $this->logs->log('customer_services', ($blocked ? 'Suspended' : 'Restored') . " {$customer->username} for service {$service->name}", $customer, ['service_id' => $service->id], $adminId);
    }

    /** Everything the admin profile page shows for one customer. */
    public function profile(User $customer): array
    {
        return [
            'customer' => $customer,
            'balance' => $this->credits->balance($customer->id),
            'totals' => $this->credits->totals(['user_id' => $customer->id]),
            'requestCounts' => $this->requests->statusCounts($customer->id),
            'transactions' => $this->credits->paginate(['user_id' => $customer->id], 10),
            'requests' => $this->requests->recent(10, $customer->id),
            'blockedServices' => $customer->blockedServices()->orderBy('name')->get(),
            'allServices' => \App\Models\Service::orderBy('name')->get(['id', 'name', 'status']),
        ];
    }

    private function fullName(array $data): string
    {
        return trim(($data['first_name'] ?? '') . ' ' . ($data['last_name'] ?? ''));
    }

    public function exportRows(array $filters): \Generator
    {
        yield ['ID', 'Name', 'Username', 'Email', 'Phone', 'Status', 'Balance', 'Requests', 'Registered', 'Last login'];

        foreach ($this->customers->cursor($filters) as $c) {
            yield [
                $c->id, $c->name, $c->username, $c->email, $c->phone, $c->status,
                $c->wallet->balance ?? '0.00', $c->service_requests_count,
                $c->created_at->format('Y-m-d'), $c->last_login_at?->format('Y-m-d H:i'),
            ];
        }
    }
}
