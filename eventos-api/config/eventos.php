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

        // Partner (exhibitor/sponsor) company profile
        'partner' => [
            ['key' => 'company_name', 'label' => 'Company name', 'type' => 'text',     'is_required' => true],
            ['key' => 'website',      'label' => 'Website',      'type' => 'text',     'is_required' => false],
            ['key' => 'description',  'label' => 'Description',  'type' => 'textarea', 'is_required' => false],
            ['key' => 'contact_email','label' => 'Contact email','type' => 'email',    'is_required' => true,  'is_pii' => true],
        ],
    ],
];
