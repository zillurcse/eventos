<?php

namespace App\Services\Registration;

use App\Models\Contact;
use App\Models\Event;
use App\Models\Form;
use App\Models\Participation;
use App\Models\User;
use App\Services\Forms\FormSubmissionService;
use App\Services\Forms\FormValidatorBuilder;
use App\Services\Ticketing\OrderService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Registration ties the form builder (§6.12) to an event (§6.3) and ticketing
 * (§6.4): a submission upserts the global contact, records the form submission,
 * creates the per-event participation, projects the dynamic fields, and — for
 * paid events — issues an order + tickets.
 */
class RegistrationService
{
    public function __construct(
        protected FormValidatorBuilder $validator,
        protected FormSubmissionService $forms,
        protected OrderService $orders,
    ) {}

    /**
     * @param  array<int,array{ticket_type_id:int,quantity:int}>  $lines
     * @param  array<int,string>|null  $only  field keys the signup surface renders
     *                                        (profile forms collect per surface)
     * @return array{contact: Contact, participation: Participation, order: ?\App\Models\Order, tickets: Collection}
     */
    public function register(Event $event, Form $form, array $input, array $lines = [], ?string $discount = null, ?string $password = null, ?array $only = null): array
    {
        return DB::transaction(function () use ($event, $form, $input, $lines, $discount, $password, $only) {
            $validated = $this->validator->validate($form, $input, $only);

            $email = $validated['email'] ?? null;
            if (! $email) {
                throw ValidationException::withMessages(['email' => ['An email field is required to register.']]);
            }

            $contact = Contact::firstOrCreate(
                ['email' => $email],
                ['first_name' => $validated['first_name'] ?? null, 'last_name' => $validated['last_name'] ?? null],
            );

            // Optional attendee login so they can use the event app (feed, networking).
            if ($password && ! $contact->user_id) {
                $user = User::firstOrCreate(
                    ['email' => $email],
                    [
                        'name' => $contact->fullName() ?: $email,
                        'password' => $password,
                        'email_verified_at' => now(),
                    ],
                );
                $contact->update(['user_id' => $user->id]);
            }

            // Recorded as an already-approved submission: signing up IS the
            // approval, so it shows in Profile › Submissions as history rather
            // than as something waiting on the organizer.
            $result = $this->forms->submit($form, $input, 'contact', $contact->id, [
                'source' => 'registration',
                'review_status' => 'approved',
                'submitted_by_contact_id' => $contact->id,
                'meta' => array_filter([
                    'submitter_name' => $contact->fullName() ?: null,
                    'submitter_email' => $email,
                ]),
            ], $only);
            $projection = $result['projection'];

            $contact->projectDynamic($projection)->save();

            // role is privileged (not $fillable); match on it but set it via
            // forceFill on first creation so mass-assignment can't drop it.
            $participation = Participation::firstOrNew(
                ['event_id' => $event->id, 'contact_id' => $contact->id, 'role' => 'attendee'],
            );
            if (! $participation->exists) {
                $participation->forceFill([
                    'role' => 'attendee',
                    'status' => 'registered',
                    'registration_submission_id' => $result['submission']->id,
                ])->save();
            }
            $participation->projectDynamic($projection)->save();

            $order = null;
            $tickets = collect();

            if (! empty($lines)) {
                ['order' => $order, 'tickets' => $tickets] = $this->orders->createOrder($event, $contact, $lines, $discount);

                if ($first = $tickets->first()) {
                    $first->update(['participation_id' => $participation->id]);
                    $participation->ticket_id = $first->id;
                }

                $participation->status = $order->status === 'paid' ? 'confirmed' : 'registered';
                $participation->save();
            }

            return compact('contact', 'participation', 'order', 'tickets');
        });
    }
}
