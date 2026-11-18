<?php

namespace App\Services\Support;

use App\Models\SupportThread;
use App\Models\SupportTicket;
use App\Models\SupportTicketMessage;
use App\Services\SettingService;
use Illuminate\Support\Str;

class SupportPresenter
{
    public static function thread(SupportThread $thread, bool $admin = false, ?int $messageLimit = null): array
    {
        $relations = [
            'user', 'activeTicket', 'tickets.ticketType', 'tickets.order.items', 'tickets.order.sellerOrders.shipments',
            'tickets.order.returns', 'tickets.order.refunds', 'tickets.assignee.media',
            'tickets.resolver', 'tickets.calls', 'tickets.rating.agent',
        ];
        if ($messageLimit === null) {
            $relations = [...$relations, 'tickets.messages.user.media', 'tickets.messages.media'];
        }
        $thread->loadMissing($relations);

        $pagination = null;
        if ($messageLimit !== null) {
            $page = self::messageModels($thread, null, $messageLimit);
            $messagesByTicket = $page['messages']->groupBy('ticket_id');
            foreach ($thread->tickets as $ticket) {
                $ticket->setRelation('messages', $messagesByTicket->get($ticket->id, collect()));
            }
            $pagination = $page['pagination'];
        }

        return [
            'id' => $thread->id,
            'uuid' => $thread->uuid,
            'active_ticket_id' => $thread->active_ticket_id,
            'last_message_at' => $thread->last_message_at?->toISOString(),
            'customer' => [
                'id' => $thread->user->id,
                'name' => $thread->user->name,
                'email' => $thread->user->email,
                'phone' => trim(($thread->user->country_code ?? '').' '.($thread->user->mobile ?? '')),
                'avatar' => $thread->user->profile_image,
            ],
            'sessions' => $thread->tickets->map(fn (SupportTicket $ticket) => self::ticket($ticket, $admin))->values(),
            'message_pagination' => $pagination,
        ];
    }

    public static function messagePage(SupportThread $thread, bool $admin, ?int $beforeMessageId, int $limit = 50): array
    {
        $page = self::messageModels($thread, $beforeMessageId, $limit);

        return [
            'messages' => $page['messages']->map(fn (SupportTicketMessage $message) => self::message($message, $admin))->values(),
            ...$page['pagination'],
        ];
    }

    public static function ticket(SupportTicket $ticket, bool $admin = false): array
    {
        $ticket->loadMissing([
            'ticketType', 'order.items', 'order.sellerOrders.shipments', 'order.returns', 'order.refunds',
            'assignee.media', 'resolver', 'messages.user.media', 'messages.media', 'calls', 'rating.agent',
        ]);

        return [
            'id' => $ticket->id,
            'slug' => $ticket->slug,
            'parent_ticket_id' => $ticket->parent_ticket_id,
            'subject' => $ticket->subject,
            'status' => $ticket->status,
            'topic' => $ticket->ticketType ? [
                'id' => $ticket->ticketType->id,
                'slug' => $ticket->ticketType->slug,
                'title' => $ticket->ticketType->title,
                'quick_replies' => $admin
                    ? ($ticket->ticketType->agent_quick_replies ?? [])
                    : ($ticket->ticketType->customer_quick_replies ?? []),
            ] : null,
            'order' => $ticket->order ? [
                'id' => $ticket->order->id,
                'slug' => $ticket->order->slug,
                'number' => '#'.$ticket->order->id,
                'status' => $ticket->order->status,
                'fulfillment_status' => $ticket->order->fulfillment_status,
                'payment_status' => $ticket->order->payment_status,
                'currency_code' => $ticket->order->currency_code,
                'total' => $ticket->order->final_total,
                'items' => $ticket->order->items->map(fn ($item) => [
                    'id' => $item->id,
                    'title' => $item->title,
                    'status' => $item->status,
                    'fulfillment_status' => $item->fulfillment_status,
                    'quantity' => $item->quantity,
                ])->values(),
                'shipments' => $ticket->order->sellerOrders->flatMap->shipments->map(fn ($shipment) => [
                    'id' => $shipment->id,
                    'status' => self::scalar($shipment->status),
                    'courier' => $shipment->courier_name ?: $shipment->manual_carrier_name,
                    'tracking_number' => $shipment->tracking_number ?: $shipment->awb_number,
                    'is_return' => $shipment->is_return,
                ])->values(),
                'returns' => $ticket->order->returns->map(fn ($return) => [
                    'id' => $return->id,
                    'status' => self::scalar($return->return_status),
                    'reason' => $return->reason,
                    'refund_amount' => $return->refund_amount,
                ])->values(),
                'refunds' => $ticket->order->refunds->map(fn ($refund) => [
                    'id' => $refund->id,
                    'status' => self::scalar($refund->status),
                    'method' => self::scalar($refund->method),
                    'amount' => $refund->amount,
                    'currency_code' => $refund->currency_code,
                ])->values(),
            ] : null,
            'assignee' => $ticket->assignee ? [
                'id' => $ticket->assignee->id,
                'name' => $ticket->assignee->name,
                'avatar' => $ticket->assignee->profile_image,
            ] : null,
            'resolution_summary' => $ticket->resolution_summary,
            'resolved_by' => $ticket->resolver?->name,
            'resolved_at' => $ticket->resolved_at?->toISOString(),
            'closed_at' => $ticket->closed_at?->toISOString(),
            'created_at' => $ticket->created_at?->toISOString(),
            'messages' => $ticket->messages->sortBy('id')->map(fn (SupportTicketMessage $message) => self::message($message, $admin))->values(),
            'calls' => $ticket->calls->sortBy('id')->map(fn ($call) => [
                'id' => $call->id,
                'status' => $call->status,
                'phone' => $call->phone,
                'scheduled_at' => $call->scheduled_at?->toISOString(),
                'attempted_at' => $call->attempted_at?->toISOString(),
                'outcome' => $call->outcome,
                'private_note' => $admin ? $call->private_note : null,
                'created_at' => $call->created_at?->toISOString(),
            ])->values(),
            'deeplink' => $admin ? self::deeplink($ticket) : null,
            'rating' => $ticket->rating ? [
                'score' => $ticket->rating->score,
                'feedback' => $ticket->rating->feedback,
                'agent' => $ticket->rating->agent ? [
                    'id' => $ticket->rating->agent->id,
                    'name' => $ticket->rating->agent->name,
                ] : null,
            ] : null,
        ];
    }

    public static function message(SupportTicketMessage $message, bool $admin = false): array
    {
        $message->loadMissing(['user', 'media']);

        return [
            'id' => $message->id,
            'session_id' => $message->ticket_id,
            'client_message_id' => $message->client_message_id,
            'sender_role' => $message->send_by,
            'sender_name' => $message->type === 'auto_reply' ? __('labels.customer_support') : $message->user?->name,
            'sender_avatar' => $message->type === 'auto_reply' ? null : $message->user?->profile_image,
            'type' => $message->type,
            'text' => self::messageText($message, $admin),
            'metadata' => $message->metadata,
            'attachments' => $message->media->map(fn ($media) => [
                'id' => $media->id,
                'name' => $media->file_name,
                'mime_type' => $media->mime_type,
                'size' => $media->size,
                'download_url' => $admin
                    ? route('admin.support.attachments.show', $media)
                    : route('user.support.attachments.show', $media),
            ])->values(),
            'created_at' => $message->created_at?->toISOString(),
        ];
    }

    private static function scalar(mixed $value): mixed
    {
        return $value instanceof \BackedEnum ? $value->value : $value;
    }

    private static function messageModels(SupportThread $thread, ?int $beforeMessageId, int $limit): array
    {
        $ticketIds = $thread->tickets->pluck('id');
        $messages = SupportTicketMessage::query()
            ->with(['user.media', 'media'])
            ->whereIn('ticket_id', $ticketIds)
            ->when($beforeMessageId, fn ($query) => $query->where('id', '<', $beforeMessageId))
            ->orderByDesc('id')
            ->limit(max(1, min($limit, 100)))
            ->get()
            ->sortBy('id')
            ->values();
        $oldestId = $messages->min('id');
        $hasMore = $oldestId !== null && SupportTicketMessage::query()
            ->whereIn('ticket_id', $ticketIds)
            ->where('id', '<', $oldestId)
            ->exists();

        return [
            'messages' => $messages,
            'pagination' => [
                'has_more' => $hasMore,
                'oldest_message_id' => $oldestId,
            ],
        ];
    }

    public static function messageText(SupportTicketMessage $message, bool $admin = false): string
    {
        if ($message->send_by !== 'system' || $message->type === 'auto_reply') {
            return $message->message;
        }

        $metadata = $message->metadata ?? [];
        if (! isset($metadata['actor_name'])) {
            $message->loadMissing('user');
        }
        if (! isset($metadata['actor_role'])) {
            $message->loadMissing('ticket:id,user_id');
        }
        $actorRole = $metadata['actor_role'] ?? ($message->ticket?->user_id === $message->user_id ? 'customer' : 'admin');
        $actorName = $metadata['actor_name'] ?? $message->user?->name;
        if (! $actorName) {
            return $message->message;
        }

        $actor = ! $admin && $actorRole === 'customer' ? 'you' : $actorName;
        $text = $message->type === 'callback' && ($metadata['status'] ?? null) === 'requested'
            ? 'Call requested'
            : rtrim($message->message, '. ');

        return "{$text} by {$actor}";
    }

    private static function deeplink(SupportTicket $ticket): ?array
    {
        $content = collect([
            $ticket->subject,
            $ticket->description,
            $ticket->ticketType?->title,
            $ticket->ticketType?->slug,
            ...$ticket->messages->pluck('message')->all(),
        ])->filter()->implode(' ');
        $normalized = Str::lower($content);
        preg_match_all('/\b(?:https?:\/\/|[a-z][a-z0-9+.-]*:\/\/)[^\s<>"\']+/i', $content, $matches);
        $reportedLinks = collect($matches[0] ?? [])->map(fn (string $link) => rtrim($link, '.,;:!?)]}'))->unique()->values();
        $isRelated = Str::contains($normalized, [
            'deeplink', 'deep link', 'app link', 'open in app', 'redirect link', 'app scheme', 'universal link',
        ]) || $reportedLinks->contains(fn (string $link) => ! Str::startsWith(Str::lower($link), ['http://', 'https://']));

        if (! $isRelated) {
            return null;
        }

        $settings = app(SettingService::class)->getSettingValues('app');

        return [
            'reported_links' => $reportedLinks->all(),
            'customer' => [
                'scheme' => $settings['customerAppScheme'] ?? null,
                'play_store_url' => $settings['customerPlaystoreLink'] ?? null,
                'app_store_url' => $settings['customerAppstoreLink'] ?? null,
            ],
            'seller' => [
                'scheme' => $settings['sellerAppScheme'] ?? null,
                'play_store_url' => $settings['sellerPlaystoreLink'] ?? null,
                'app_store_url' => $settings['sellerAppstoreLink'] ?? null,
            ],
            'settings_url' => route('admin.settings.show', ['setting' => 'app']),
        ];
    }
}
