<?php

namespace App\Services;

use App\Mail\SiteMail;
use App\Models\ServiceRequest;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Throwable;

/**
 * The site's automatic emails:
 *  - admin: a new customer registered, a customer ordered a service;
 *  - customer: their order was completed, failed or cancelled.
 * Emails are sent after the page has been answered, so a slow mail server never slows the
 * customer down, and a mail error is written to laravel.log instead of breaking the action.
 */
class EmailNotifier
{
    /** The admin addresses from MAIL_ADMIN_ADDRESS (comma separated). */
    public function adminAddresses(): array
    {
        $list = array_map('trim', explode(',', (string) config('mail.admin_address', '')));

        return array_values(array_unique(array_filter($list, fn ($a) => filter_var($a, FILTER_VALIDATE_EMAIL))));
    }

    private function siteName(): string
    {
        return (string) (setting('site_name') ?: config('app.name'));
    }

    /** How long the email confirmation link works. */
    public const EMAIL_LINK_MINUTES = 5;

    /** Sends the new customer a link that confirms their email address. */
    public function confirmEmail(User $customer): void
    {
        if (!filter_var($customer->email, FILTER_VALIDATE_EMAIL)) {
            return;
        }

        $url = URL::temporarySignedRoute('verification.verify', now()->addMinutes(self::EMAIL_LINK_MINUTES), [
            'id' => $customer->id,
            'hash' => sha1($customer->email),
        ]);

        $this->send($customer->email, new SiteMail(
            mailSubject: 'Confirm your email for ' . $this->siteName(),
            heading: 'Confirm your email address',
            lines: [
                "Hello {$customer->first_name}, thank you for creating an account. Press the button below to confirm your email address.",
                'The link works for ' . self::EMAIL_LINK_MINUTES . ' minutes. If it expires, an administrator will activate your account instead.',
                'If you did not create this account, you can ignore this email.',
            ],
            details: ['Username' => $customer->username],
            buttonText: 'Confirm my email',
            buttonUrl: $url,
        ));
    }

    public function customerRegistered(User $customer): void
    {
        $autoActivate = setting('new_account_status') !== 'pending';

        $this->toAdmins(new SiteMail(
            mailSubject: "New customer: {$customer->username}" . ($autoActivate ? '' : ' (waiting for activation)'),
            heading: 'A new customer created an account',
            lines: [$autoActivate
                ? 'A confirmation link was emailed to the customer. When they open it (within ' . self::EMAIL_LINK_MINUTES . ' minutes) the account becomes active by itself. If they do not, open the customer and set the status to Active.'
                : 'The account is waiting for your activation. Open the customer and set the status to Active to let them sign in.'],
            details: array_filter([
                'Name' => trim("{$customer->first_name} {$customer->last_name}"),
                'Username' => $customer->username,
                'Email' => $customer->email,
                'Phone' => $customer->phone,
                'Country' => $customer->country,
                'Status' => ucfirst((string) $customer->status),
                'Came from' => $customer->signup_source,
                'Date' => $customer->created_at?->format('Y-m-d H:i'),
            ], fn ($v) => $v !== null && $v !== ''),
            buttonText: 'Open customer',
            buttonUrl: route('admin.customers.show', $customer->id),
        ));
    }

    public function requestSubmitted(ServiceRequest $request): void
    {
        $request->loadMissing('user', 'service');

        $details = [
            'Order' => $request->reference,
            'Service' => $request->service?->name,
            'Customer' => $request->user ? "{$request->user->name} ({$request->user->username})" : '—',
            'Credit' => amount($request->amount) . '$',
            'Status' => ucfirst($request->status),
        ];
        foreach ($request->request_data ?? [] as $field) {
            $details[$field['label'] ?? 'Field'] = $field['value'] ?? '';
        }
        $details['Date'] = $request->created_at?->format('Y-m-d H:i');

        $this->toAdmins(new SiteMail(
            mailSubject: "New order {$request->reference}: {$request->service?->name}",
            heading: 'A customer ordered a service',
            lines: [$request->api_provider_id
                ? 'This service is connected to a provider, so the order is sent to the provider automatically.'
                : 'Open the order to process it.'],
            details: array_filter($details, fn ($v) => $v !== null),
            buttonText: 'Open order',
            buttonUrl: route('admin.requests.show', $request->id),
        ));
    }

    /** Tells the customer their order is finished: completed (with the result), failed or cancelled. */
    public function requestUpdated(ServiceRequest $request): void
    {
        if (!in_array($request->status, ['completed', 'failed', 'cancelled'], true)) {
            return;
        }

        $request->loadMissing('user', 'service');
        $customer = $request->user;

        if (!$customer || !filter_var($customer->email, FILTER_VALIDATE_EMAIL)) {
            return;
        }

        $service = $request->service?->name ?? 'your service';
        $refunded = $request->refunded_at && in_array($request->status, ['failed', 'cancelled'], true);

        [$subject, $heading, $lines] = match ($request->status) {
            'completed' => [
                "Your order {$request->reference} is completed",
                'Your order is completed',
                ["Hello {$customer->first_name}, your order for {$service} is done. The result is below."],
            ],
            'failed' => [
                "Your order {$request->reference} could not be completed",
                'Your order could not be completed',
                array_filter([
                    "Hello {$customer->first_name}, we could not complete your order for {$service}.",
                    $request->error_message ? "Reason: {$request->error_message}" : null,
                    $refunded ? amount($request->amount) . '$ was returned to your credit.' : null,
                ]),
            ],
            'cancelled' => [
                "Your order {$request->reference} was cancelled",
                'Your order was cancelled',
                array_filter([
                    "Hello {$customer->first_name}, your order for {$service} was cancelled.",
                    $request->error_message ? "Reason: {$request->error_message}" : null,
                    $refunded ? amount($request->amount) . '$ was returned to your credit.' : null,
                ]),
            ],
        };

        $this->send($customer->email, new SiteMail(
            mailSubject: $subject,
            heading: $heading,
            lines: array_values($lines),
            details: [
                'Order' => $request->reference,
                'Service' => $service,
                'Credit' => amount($request->amount) . '$',
                'Status' => ucfirst($request->status),
            ],
            code: $request->status === 'completed' ? (string) ($request->response_data['result'] ?? '') : null,
            buttonText: 'View order',
            buttonUrl: route('customer.requests.show', $request->id),
        ));
    }

    /** Sends at once (not after the response) so the admin sees whether it worked. Throws on failure. */
    public function sendTest(string $to): void
    {
        Mail::to($to)->send(new SiteMail(
            mailSubject: 'Test email from ' . $this->siteName(),
            heading: 'Email is working',
            lines: ['This test was sent from Admin > Server Tools. New customer and new order notices will reach the admin address, and customers will get an email when their order is finished.'],
            details: [
                'Sent by' => config('mail.from.address'),
                'Mail server' => config('mail.default') === 'smtp' ? config('mail.mailers.smtp.host') . ':' . config('mail.mailers.smtp.port') : config('mail.default'),
                'Date' => now()->format('Y-m-d H:i'),
            ],
        ));
    }

    private function toAdmins(SiteMail $mail): void
    {
        if ($to = $this->adminAddresses()) {
            $this->send($to, $mail);
        }
    }

    private function send(string|array $to, SiteMail $mail): void
    {
        \Illuminate\Support\defer(function () use ($to, $mail) {
            try {
                Mail::to($to)->send($mail);
            } catch (Throwable $e) {
                report($e);
            }
        });
    }
}
