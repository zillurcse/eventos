<?php

namespace App\Services\Ticketing;

use App\Models\Contact;
use App\Models\DiscountCode;
use App\Models\Event;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Ticket;
use App\Models\TicketType;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Builds orders from ticket selections: prices lines, applies a discount code,
 * enforces capacity, issues one ticket (with a QR token) per seat, and bumps
 * sold/redeemed counters (architecture §6.4). Money is BIGINT minor units.
 */
class OrderService
{
    /**
     * @param  array<int,array{ticket_type_id:int,quantity:int}>  $lines
     * @return array{order: Order, tickets: Collection<int,Ticket>}
     */
    public function createOrder(Event $event, Contact $contact, array $lines, ?string $discountCode = null): array
    {
        return DB::transaction(function () use ($event, $contact, $lines, $discountCode) {
            $subtotal = 0;
            $resolved = [];

            foreach ($lines as $line) {
                $type = TicketType::where('event_id', $event->id)->findOrFail($line['ticket_type_id']);
                $qty = max(1, (int) $line['quantity']);

                if ($type->quantity !== null && ($type->sold + $qty) > $type->quantity) {
                    throw ValidationException::withMessages([
                        'ticket_type_id' => ["'{$type->name}' is sold out (only ".max(0, $type->quantity - $type->sold)." left)."],
                    ]);
                }

                $subtotal += $type->price_cents * $qty;
                $resolved[] = ['type' => $type, 'qty' => $qty];
            }

            $discount = $this->applyDiscount($event, $discountCode, $subtotal);
            $total = max(0, $subtotal - $discount);

            $order = new Order([
                'event_id' => $event->id,
                'number' => $this->orderNumber(),
                'buyer_user_id' => $contact->user_id,
                'buyer_email' => $contact->email,
            ]);
            // status + money are server-computed and not $fillable — set here.
            $order->forceFill([
                'status' => $total === 0 ? 'paid' : 'pending',
                'subtotal_cents' => $subtotal,
                'discount_cents' => $discount,
                'tax_cents' => 0,
                'total_cents' => $total,
            ])->save();

            $tickets = collect();

            foreach ($resolved as $row) {
                $item = OrderItem::create([
                    'order_id' => $order->id,
                    'ticket_type_id' => $row['type']->id,
                    'quantity' => $row['qty'],
                    'unit_price_cents' => $row['type']->price_cents,
                ]);

                for ($i = 0; $i < $row['qty']; $i++) {
                    $tickets->push(Ticket::create([
                        'order_item_id' => $item->id,
                        'event_id' => $event->id,
                        'status' => 'issued',
                        'qr_token' => $this->qrToken(),
                    ]));
                }

                $row['type']->increment('sold', $row['qty']);
            }

            return ['order' => $order, 'tickets' => $tickets];
        });
    }

    protected function applyDiscount(Event $event, ?string $code, int $subtotal): int
    {
        if (! $code) {
            return 0;
        }

        $discount = DiscountCode::where('event_id', $event->id)->where('code', $code)->first();

        $valid = $discount
            && (! $discount->max_uses || $discount->used < $discount->max_uses)
            && (! $discount->expires_at || $discount->expires_at->isFuture());

        if (! $valid) {
            return 0;
        }

        $discount->increment('used');

        return $discount->type === 'percent'
            ? (int) round($subtotal * $discount->value / 100)
            : min((int) $discount->value, $subtotal);
    }

    protected function orderNumber(): string
    {
        return 'ORD-'.now()->format('ymd').'-'.strtoupper(Str::random(6));
    }

    protected function qrToken(): string
    {
        return Str::random(48);
    }
}
