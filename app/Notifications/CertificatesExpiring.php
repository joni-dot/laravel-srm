<?php

namespace App\Notifications;

use App\Models\Certificate;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Collection;

/**
 * One email per recipient listing every certificate that needs attention.
 */
class CertificatesExpiring extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @var list<array{supplier: string, type: string, expires_on: string, days_left: int, url: string}>
     */
    private readonly array $items;

    /**
     * @param  Collection<int, Certificate>  $certificates
     */
    public function __construct(Collection $certificates)
    {
        $this->afterCommit();

        $this->items = array_values($certificates
            ->sortBy('expires_on')
            ->map(fn (Certificate $certificate) => [
                'supplier' => $certificate->supplier->name,
                'type' => $certificate->type->name,
                'expires_on' => (string) $certificate->expires_on?->toDateString(),
                'days_left' => (int) today()->diffInDays($certificate->expires_on),
                'url' => route('suppliers.show', $certificate->supplier_id),
            ])
            ->all());
    }

    /**
     * @return list<string>
     */
    public function via(AnonymousNotifiable $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(AnonymousNotifiable $notifiable): MailMessage
    {
        $count = count($this->items);

        return (new MailMessage)
            ->subject(trans_choice(':count certificate is expiring soon|:count certificates are expiring soon', $count))
            ->markdown('mail.certificates-expiring', [
                'intro' => trans_choice('This certificate needs to be renewed soon:|These certificates need to be renewed soon:', $count),
                'items' => $this->items,
                'url' => route('suppliers.index'),
            ]);
    }

    /**
     * @return list<array{supplier: string, type: string, expires_on: string, days_left: int, url: string}>
     */
    public function items(): array
    {
        return $this->items;
    }
}
