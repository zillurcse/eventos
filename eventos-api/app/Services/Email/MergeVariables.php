<?php

namespace App\Services\Email;

/**
 * The catalogue of dynamic merge variables an organizer can drop into an email
 * template ({{ contact.first_name }} etc.). Grouped for the builder's variable
 * picker, with sample values used to render a realistic preview when no real
 * recipient data is supplied (architecture §6.13).
 */
class MergeVariables
{
    /**
     * Grouped variable catalogue surfaced to the builder UI.
     *
     * @return array<int,array{group:string,label:string,variables:array<int,array{token:string,label:string,sample:string}>}>
     */
    public function catalogue(): array
    {
        return [
            [
                'group' => 'contact',
                'label' => 'Recipient',
                'variables' => [
                    ['token' => 'contact.first_name', 'label' => 'First name', 'sample' => 'Alex'],
                    ['token' => 'contact.last_name', 'label' => 'Last name', 'sample' => 'Morgan'],
                    ['token' => 'contact.full_name', 'label' => 'Full name', 'sample' => 'Alex Morgan'],
                    ['token' => 'contact.email', 'label' => 'Email', 'sample' => 'alex.morgan@example.com'],
                    ['token' => 'contact.company', 'label' => 'Company', 'sample' => 'Northwind Labs'],
                    ['token' => 'contact.job_title', 'label' => 'Job title', 'sample' => 'Head of Product'],
                    ['token' => 'contact.phone', 'label' => 'Phone', 'sample' => '+1 555 0142'],
                ],
            ],
            [
                'group' => 'event',
                'label' => 'Event',
                'variables' => [
                    ['token' => 'event.name', 'label' => 'Event name', 'sample' => 'TechSummit 2026'],
                    ['token' => 'event.starts_at', 'label' => 'Start date', 'sample' => 'Sep 14, 2026'],
                    ['token' => 'event.ends_at', 'label' => 'End date', 'sample' => 'Sep 16, 2026'],
                    ['token' => 'event.location', 'label' => 'Location', 'sample' => 'Moscone Center, San Francisco'],
                    ['token' => 'event.url', 'label' => 'Event URL', 'sample' => 'https://techsummit.events'],
                    ['token' => 'event.timezone', 'label' => 'Timezone', 'sample' => 'America/Los_Angeles'],
                ],
            ],
            [
                'group' => 'ticket',
                'label' => 'Ticket & Registration',
                'variables' => [
                    ['token' => 'ticket.type', 'label' => 'Ticket type', 'sample' => 'Full Conference Pass'],
                    ['token' => 'ticket.code', 'label' => 'Ticket code', 'sample' => 'TS26-AX91KD'],
                    ['token' => 'ticket.qr_url', 'label' => 'QR / check-in URL', 'sample' => 'https://techsummit.events/t/AX91KD'],
                    ['token' => 'registration.status', 'label' => 'Registration status', 'sample' => 'Confirmed'],
                ],
            ],
            [
                'group' => 'organization',
                'label' => 'Organizer',
                'variables' => [
                    ['token' => 'organization.name', 'label' => 'Organization name', 'sample' => 'Northwind Events'],
                    ['token' => 'organization.support_email', 'label' => 'Support email', 'sample' => 'help@northwind.events'],
                    ['token' => 'organization.website', 'label' => 'Website', 'sample' => 'https://northwind.events'],
                ],
            ],
            [
                'group' => 'system',
                'label' => 'System',
                'variables' => [
                    ['token' => 'system.year', 'label' => 'Current year', 'sample' => (string) now()->year],
                    ['token' => 'system.date', 'label' => 'Current date', 'sample' => now()->format('M j, Y')],
                    ['token' => 'unsubscribe_url', 'label' => 'Unsubscribe URL', 'sample' => 'https://techsummit.events/unsubscribe'],
                ],
            ],
        ];
    }

    /**
     * Flat token => sample map used to render a realistic preview when the
     * caller doesn't pass real recipient data. Nested by dotted token so
     * data_get() resolves {{ contact.first_name }} against it.
     */
    public function sampleData(): array
    {
        $sample = [];
        foreach ($this->catalogue() as $group) {
            foreach ($group['variables'] as $variable) {
                data_set($sample, $variable['token'], $variable['sample']);
            }
        }

        return $sample;
    }
}
