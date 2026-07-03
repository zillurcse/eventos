<?php

/**
 * EventOS platform configuration.
 *
 * default_fields: the seeded core fields per builder-driven entity
 * (architecture §3.4, §6.12). When an organization/event is provisioned, the
 * form-builder engine instantiates these as form_fields (is_default = true);
 * organizers then extend them at runtime.
 */
return [

    'default_fields' => [

        // Attendee registration (target_entity = contact/participation)
        'registration' => [
            ['key' => 'first_name', 'label' => 'First name', 'type' => 'text',  'is_required' => true,  'is_pii' => true],
            ['key' => 'last_name',  'label' => 'Last name',  'type' => 'text',  'is_required' => true,  'is_pii' => true],
            ['key' => 'email',      'label' => 'Email',      'type' => 'email', 'is_required' => true,  'is_unique' => true, 'is_pii' => true],
            ['key' => 'phone',      'label' => 'Phone',      'type' => 'phone', 'is_required' => false, 'is_pii' => true],
            ['key' => 'company',    'label' => 'Company',    'type' => 'text',  'is_required' => false],
            ['key' => 'job_title',  'label' => 'Job title',  'type' => 'text',  'is_required' => false],
        ],

        // Speaker profile (participation role = speaker)
        'speaker' => [
            ['key' => 'first_name', 'label' => 'First name', 'type' => 'text',     'is_required' => true,  'is_pii' => true],
            ['key' => 'last_name',  'label' => 'Last name',  'type' => 'text',     'is_required' => true,  'is_pii' => true],
            ['key' => 'email',      'label' => 'Email',      'type' => 'email',    'is_required' => true,  'is_unique' => true, 'is_pii' => true],
            ['key' => 'headline',   'label' => 'Headline',   'type' => 'text',     'is_required' => false],
            ['key' => 'bio',        'label' => 'Bio',        'type' => 'textarea', 'is_required' => false],
            ['key' => 'linkedin',   'label' => 'LinkedIn',   'type' => 'text',     'is_required' => false],
            ['key' => 'twitter',    'label' => 'X / Twitter','type' => 'text',     'is_required' => false],
        ],

        // Exhibitor (exhibitor/sponsor) company profile
        'exhibitor' => [
            ['key' => 'company_name', 'label' => 'Company name', 'type' => 'text',     'is_required' => true],
            ['key' => 'website',      'label' => 'Website',      'type' => 'text',     'is_required' => false],
            ['key' => 'description',  'label' => 'Description',  'type' => 'textarea', 'is_required' => false],
            ['key' => 'contact_email','label' => 'Contact email','type' => 'email',    'is_required' => true,  'is_pii' => true],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Domains
    |--------------------------------------------------------------------------
    | apex           — platform apex; every event gets <subdomain>.<apex> free
    |                  (served by a wildcard DNS + TLS cert on the edge).
    | cname_target   — where an organizer points a CUSTOM sub-domain (CNAME).
    | ip             — A-record target for a custom APEX domain (CNAME not allowed
    |                  on a bare apex).
    | challenge_prefix — TXT host prefix used to prove domain ownership.
    | reserved       — subdomains organizers may not claim.
    */
    'domain' => [
        'apex' => env('PLATFORM_APEX', 'eventos.app'),
        'cname_target' => env('PLATFORM_CNAME_TARGET', 'cname.eventos.app'),
        'ip' => env('PLATFORM_INGRESS_IP', '76.76.21.21'),
        'challenge_prefix' => '_eventos-challenge',
        'reserved' => [
            'www', 'app', 'api', 'admin', 'dashboard', 'mail', 'smtp', 'ftp',
            'ns', 'ns1', 'ns2', 'cname', 'cdn', 'assets', 'static', 'status',
            'support', 'help', 'docs', 'blog', 'go', 'link', 'my', 'account',
        ],
    ],
];
