<?php

namespace App\Notifications;

use App\Models\Contract;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Collection;

/**
 * One email per recipient listing every contract that needs a decision. Prices are left out on purpose.
 */
class ContractsNeedAction extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @var list<array{supplier: string, title: string, action: string, action_by: string, days_left: int, url: string}>
     */
    private readonly array $items;

    /**
     * @param  Collection<int, Contract>  $contracts
     */
    public function __construct(Collection $contracts)
    {
        $this->afterCommit();

        $this->items = array_values($contracts
            ->sortBy('action_by')
            ->map(fn (Contract $contract) => [
                'supplier' => $contract->supplier->name,
                'title' => $contract->title,
                'action' => $contract->auto_renews ? 'Last day to cancel' : 'Ends',
                'action_by' => (string) $contract->action_by?->toDateString(),
                'days_left' => (int) today()->diffInDays($contract->action_by),
                'url' => route('suppliers.contracts.show', [$contract->supplier_id, $contract->id]),
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
            ->subject(trans_choice(':count contract needs your attention|:count contracts need your attention', $count))
            ->markdown('mail.contracts-need-action', [
                'intro' => trans_choice('This contract needs a decision soon:|These contracts need a decision soon:', $count),
                'items' => $this->items,
                'url' => route('suppliers.index'),
            ]);
    }

    /**
     * @return list<array{supplier: string, title: string, action: string, action_by: string, days_left: int, url: string}>
     */
    public function items(): array
    {
        return $this->items;
    }
}
