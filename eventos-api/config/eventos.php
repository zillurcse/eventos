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
    | Profile forms (Event Settings › Profile)
    |--------------------------------------------------------------------------
    | One form per audience, auto-provisioned as a draft the first time the
    | organizer opens the Profile settings page. Seeded fields carry
    | is_default = true — the builder lets organizers hide them (meta.visible)
    | but not delete them, so the projection keys the rest of the platform
    | relies on (first_name, email, company…) never disappear.
    |
    | meta.surfaces controls where a field is collected:
    |   registration — the event-site signup step
    |   onboarding   — the post-login "complete your profile" modal
    |   public       — the shareable / embeddable public form (/f/{uuid})
    */
    'profile_defaults' => [

        'attendee' => [
            ['key' => 'first_name', 'label' => 'First name', 'type' => 'text',   'is_required' => true,  'is_pii' => true],
            ['key' => 'last_name',  'label' => 'Last name',  'type' => 'text',   'is_required' => true,  'is_pii' => true],
            ['key' => 'email',      'label' => 'Email',      'type' => 'email',  'is_required' => true,  'is_unique' => true, 'is_pii' => true],
            ['key' => 'phone',      'label' => 'Phone',      'type' => 'phone',  'is_pii' => true, 'meta' => ['width' => 50]],
            ['key' => 'gender',     'label' => 'Gender',     'type' => 'select', 'meta' => ['width' => 50], 'options' => ['Male', 'Female', 'Non-binary', 'Prefer not to say']],
            ['key' => 'country',    'label' => 'Country',    'type' => 'select', 'meta' => ['width' => 50], 'options' => ['United States', 'United Kingdom', 'India', 'United Arab Emirates', 'Germany', 'France', 'Singapore', 'Australia', 'Canada', 'Other']],
            ['key' => 'city',       'label' => 'City',       'type' => 'text',   'meta' => ['width' => 50]],
            ['key' => 'company',    'label' => 'Company',    'type' => 'text',   'meta' => ['width' => 50, 'show_to_others' => true]],
            ['key' => 'job_title',  'label' => 'Designation', 'type' => 'text',  'meta' => ['width' => 50, 'show_to_others' => true]],
            ['key' => 'purpose_of_visit', 'label' => 'Purpose of visit', 'type' => 'select', 'meta' => ['surfaces' => ['registration' => false]], 'options' => ['Networking', 'Buying / sourcing', 'Learning & sessions', 'Exploring the industry', 'Other']],
        ],

        'speaker' => [
            ['key' => 'first_name', 'label' => 'First name', 'type' => 'text',     'is_required' => true, 'is_pii' => true, 'meta' => ['width' => 50]],
            ['key' => 'last_name',  'label' => 'Last name',  'type' => 'text',     'is_required' => true, 'is_pii' => true, 'meta' => ['width' => 50]],
            ['key' => 'email',      'label' => 'Email',      'type' => 'email',    'is_required' => true, 'is_unique' => true, 'is_pii' => true],
            ['key' => 'phone',      'label' => 'Phone',      'type' => 'phone',    'is_pii' => true, 'meta' => ['width' => 50]],
            ['key' => 'company',    'label' => 'Company / Organization', 'type' => 'text', 'meta' => ['width' => 50, 'show_to_others' => true]],
            ['key' => 'job_title',  'label' => 'Designation', 'type' => 'text',    'meta' => ['width' => 50, 'show_to_others' => true]],
            ['key' => 'headline',   'label' => 'Headline',   'type' => 'text',     'meta' => ['show_to_others' => true]],
            ['key' => 'bio',        'label' => 'Bio',        'type' => 'textarea', 'meta' => ['show_to_others' => true]],
            ['key' => 'talk_title', 'label' => 'Proposed talk title', 'type' => 'text'],
            ['key' => 'talk_abstract', 'label' => 'Talk abstract', 'type' => 'textarea'],
            ['key' => 'linkedin',   'label' => 'LinkedIn',   'type' => 'link',     'meta' => ['width' => 50, 'show_to_others' => true]],
            ['key' => 'headshot',   'label' => 'Headshot',   'type' => 'file'],
        ],

        'exhibitor' => [
            ['key' => 'company_name',  'label' => 'Company name',   'type' => 'text',     'is_required' => true],
            ['key' => 'contact_name',  'label' => 'Contact person', 'type' => 'text',     'is_required' => true, 'is_pii' => true, 'meta' => ['width' => 50]],
            ['key' => 'email',         'label' => 'Contact email',  'type' => 'email',    'is_required' => true, 'is_unique' => true, 'is_pii' => true, 'meta' => ['width' => 50]],
            ['key' => 'phone',         'label' => 'Phone',          'type' => 'phone',    'is_pii' => true, 'meta' => ['width' => 50]],
            ['key' => 'website',       'label' => 'Website',        'type' => 'link',     'meta' => ['width' => 50]],
            ['key' => 'description',   'label' => 'About the company', 'type' => 'textarea'],
            ['key' => 'booth_preference', 'label' => 'Booth preference', 'type' => 'select', 'options' => ['Standard booth', 'Corner booth', 'Island booth', 'No preference']],
        ],

        'sponsor' => [
            ['key' => 'company_name', 'label' => 'Company name',   'type' => 'text',     'is_required' => true],
            ['key' => 'contact_name', 'label' => 'Contact person', 'type' => 'text',     'is_required' => true, 'is_pii' => true, 'meta' => ['width' => 50]],
            ['key' => 'email',        'label' => 'Contact email',  'type' => 'email',    'is_required' => true, 'is_unique' => true, 'is_pii' => true, 'meta' => ['width' => 50]],
            ['key' => 'phone',        'label' => 'Phone',          'type' => 'phone',    'is_pii' => true, 'meta' => ['width' => 50]],
            ['key' => 'website',      'label' => 'Website',        'type' => 'link',     'meta' => ['width' => 50]],
            ['key' => 'sponsorship_tier', 'label' => 'Sponsorship tier of interest', 'type' => 'select', 'options' => ['Platinum', 'Gold', 'Silver', 'Community']],
            ['key' => 'message',      'label' => 'Message',        'type' => 'textarea'],
        ],

        'organizer' => [
            ['key' => 'first_name', 'label' => 'First name', 'type' => 'text',  'is_required' => true, 'is_pii' => true, 'meta' => ['width' => 50]],
            ['key' => 'last_name',  'label' => 'Last name',  'type' => 'text',  'is_required' => true, 'is_pii' => true, 'meta' => ['width' => 50]],
            ['key' => 'email',      'label' => 'Email',      'type' => 'email', 'is_required' => true, 'is_unique' => true, 'is_pii' => true],
            ['key' => 'phone',      'label' => 'Phone',      'type' => 'phone', 'is_pii' => true, 'meta' => ['width' => 50]],
            ['key' => 'job_title',  'label' => 'Role',       'type' => 'text',  'meta' => ['width' => 50]],
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
