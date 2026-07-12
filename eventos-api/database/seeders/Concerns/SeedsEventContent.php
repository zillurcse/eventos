<?php

namespace Database\Seeders\Concerns;

use App\Models\BreakoutRoom;
use App\Models\Contact;
use App\Models\Event;
use App\Models\EventAd;
use App\Models\EventSetting;
use App\Models\Exhibitor;
use App\Models\ExhibitorDocument;
use App\Models\ExhibitorProduct;
use App\Models\Meeting;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\Participation;
use App\Models\Role;
use App\Models\Session;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Shared test-content builders for the microsite demo seeders (reception + rooms).
 *
 * Every method assumes the caller has already switched the default connection to
 * `pgsql_admin` (BYPASSRLS) and passes an explicit $org, because CLI seeders have
 * no ambient tenant. All writes are idempotent (updateOrCreate). $emailDomain and
 * $picPrefix keep logins/images distinct when seeding more than one event.
 */
trait SeedsEventContent
{
    private function pic(string $seed, int $w, int $h): string
    {
        return "https://picsum.photos/seed/{$seed}/{$w}/{$h}";
    }

    private function avatar(int $i): string
    {
        return "https://i.pravatar.cc/240?img={$i}";
    }

    /**
     * Seed the standard test dataset onto an existing event: 5 logins (4
     * attendees + 1 host, all password="password"), speakers, featured sessions,
     * exhibitors, sponsors, reception ads, breakout rooms and two meetings.
     *
     * @return string[] login summary lines for the console
     */
    protected function seedStandardContent(Organization $org, Event $event, Carbon $now, string $emailDomain, string $picPrefix): array
    {
        $tz = $event->timezone ?: 'UTC';

        // --- Logins (password = "password") -----------------------------------
        // The four logins deliberately overlap on designation and company, so
        // whichever one you sign in as, the Delegates tab's "Similar profiles"
        // strip has something to show: Alex and Sam share a title, Alex and
        // Jordan share a company.
        $alex = $this->attendee($org, $event, 'Alex Rivera', "alex@{$emailDomain}", $tz, 'Head of Product', 'Acme Robotics', 15);
        $sam = $this->attendee($org, $event, 'Sam Chen', "sam@{$emailDomain}", $tz, 'Head of Product', 'Northwind Software', 16);
        $this->attendee($org, $event, 'Jordan Blake', "jordan@{$emailDomain}", $tz, 'Data Scientist', 'Acme Robotics', 17);
        $this->attendee($org, $event, 'Taylor Reed', "taylor@{$emailDomain}", $tz, 'Data Scientist', 'Globex AI', 18);
        $this->host($org, $event, 'Nadia Osei', "host@{$emailDomain}", $tz);

        // --- Delegate directory ----------------------------------------------
        $this->seedDelegates($org, $event, $tz, $emailDomain);

        // --- Speakers ---------------------------------------------------------
        $speakerSpecs = [
            ['Dr. Amina Hassan', 'Chief AI Scientist', 'DeepScale Labs', 11],
            ['Marcus Webb', 'VP of Product', 'Northwind Software', 12],
            ['Yuki Tanaka', 'Head of Design', 'Studio Kaizen', 13],
            ['Priya Nair', 'Founder & CEO', 'LaunchPad Ventures', 14],
        ];
        $speakers = [];
        foreach ($speakerSpecs as $i => [$name, $role, $company, $img]) {
            $speakers[] = $this->speaker($org, $event, $name, $role, $company, $this->avatar($img), $i, $emailDomain);
        }

        // --- Featured sessions + speaker line-ups -----------------------------
        $sessionSpecs = [
            ['Opening Keynote: The Future of Hybrid Events', $now->copy()->addHours(2), 90, [0, 3]],
            ['Workshop: Designing Delightful Attendee Journeys', $now->copy()->addHours(5), 60, [2]],
            ['Panel: Scaling Community Beyond the Event', $now->copy()->addDay()->setTime(11, 0), 75, [0, 1, 3]],
        ];
        foreach ($sessionSpecs as [$title, $start, $mins, $speakerIdx]) {
            $session = Session::updateOrCreate(
                ['event_id' => $event->id, 'title' => $title],
                [
                    'organization_id' => $org->id,
                    'starts_at' => $start,
                    'ends_at' => $start->copy()->addMinutes($mins),
                    'timezone' => $tz,
                    'status' => 'scheduled',
                    'meta' => ['is_featured' => true],
                ],
            );
            $pivot = [];
            foreach ($speakerIdx as $order => $idx) {
                $pivot[$speakers[$idx]->id] = ['role' => 'speaker', 'sort_order' => $order];
            }
            $session->speakers()->syncWithoutDetaching($pivot);
        }

        // --- Exhibitors & sponsors -------------------------------------------
        $exhibitors = ['Okum', 'Altus Group', 'BrightCloud', 'Nova Robotics', 'Vertex Systems', 'PixelForge'];
        foreach ($exhibitors as $i => $name) {
            $this->partner($org, $event, $name, 'exhibitor', 10000 + $i * 137, $this->pic($picPrefix.'-exh'.$i, 240, 120), $i);
        }
        $sponsors = ['Muscat Steel', 'Oman Opportunities', 'DarGlobal', 'Expouse DSA'];
        foreach ($sponsors as $i => $name) {
            $this->partner($org, $event, $name, 'sponsor', 15000 + $i * 211, $this->pic($picPrefix.'-spo'.$i, 240, 120), $i);
        }

        // --- Networking Lounge (LOUNGE tab: tables + bookable meeting slots) ---
        $this->seedLounge($event, $tz, $picPrefix);

        // --- Reception ads (strip + sidebar) ---------------------------------
        EventAd::updateOrCreate(
            ['event_id' => $event->id, 'title' => 'Headline Sponsor — Cityscape'],
            [
                'organization_id' => $org->id, 'placement' => 'main', 'is_active' => true,
                'targeted_pages' => ['reception'],
                'images' => [['image_url' => $this->pic($picPrefix.'-ad-strip', 1200, 150), 'redirect_url' => 'https://example.com/cityscape', 'is_active' => true]],
            ],
        );
        EventAd::updateOrCreate(
            ['event_id' => $event->id, 'title' => 'Reception Sidebar Ads'],
            [
                'organization_id' => $org->id, 'placement' => 'content', 'is_active' => true,
                'targeted_pages' => ['reception'],
                'images' => [
                    ['image_url' => $this->pic($picPrefix.'-ad-side-1', 360, 300), 'redirect_url' => 'https://example.com/a', 'is_active' => true],
                    ['image_url' => $this->pic($picPrefix.'-ad-side-2', 360, 220), 'redirect_url' => 'https://example.com/b', 'is_active' => true],
                ],
            ],
        );

        // --- Meeting locations (venue/hybrid events meet somewhere physical) ---
        $places = $this->seedMeetingLocations($event);

        // --- Meetings (Today + Upcoming for the widget) -----------------------
        $this->meeting($org, $event, $alex, $sam, 'Intro chat with Sam', $now->copy()->addHours(2), $places[0] ?? null);
        $this->meeting($org, $event, $alex, $sam, 'Partnership sync', $now->copy()->addDays(2)->setTime(14, 0), $places[1] ?? null);

        // --- Breakout rooms (ROOMS tab — LiveKit/webrtc) ----------------------
        $roomSpecs = [
            ['AI Founders Networking Lounge', 'networking', 'anyone', null, 50, 'net'],
            ['Hands-on: Building with LiveKit', 'workshop', 'anyone', null, 30, 'work'],
            ['VIP Speaker Round Table', 'vip', 'coded', 'VIP2026', 12, 'vip'],
            ['Sponsor Demo: DeepScale', 'sponsor_demo', 'anyone', null, 100, 'demo'],
        ];
        foreach ($roomSpecs as $i => [$name, $type, $access, $code, $cap, $seed]) {
            $this->room($org, $event, $name, $type, $access, $code, $cap, $this->pic($picPrefix.'-room-'.$seed, 480, 270), $i);
        }

        return [
            'All logins use password = "password":',
            "  Attendees (viewer in rooms): alex@{$emailDomain}, sam@{$emailDomain}, jordan@{$emailDomain}, taylor@{$emailDomain}",
            "  Host (can publish video in rooms): host@{$emailDomain}",
            '  Coded room "VIP Speaker Round Table" access code: VIP2026',
        ];
    }

    /**
     * Configure the networking lounge (Communication → Lounge): a few named
     * attendee tables, exhibitor + sponsor branded tables (the partners seeded
     * above), and bookable meeting slots across every event day — so both the
     * LOUNGE tab (live video tables) and the Meetings slot picker have real data.
     */
    private function seedLounge(Event $event, string $tz, string $picPrefix): void
    {
        $times = ['10:00-10:30', '10:30-11:00', '11:00-11:30', '14:00-14:30', '14:30-15:00', '15:00-15:30'];
        $slots = [];
        $day = $event->starts_at->copy()->setTimezone($tz)->startOfDay();
        $last = ($event->ends_at ?? $event->starts_at)->copy()->setTimezone($tz)->startOfDay();
        $guard = 0;
        while ($day->lte($last) && $guard++ < 14) {
            $slots[$day->format('Y-m-d')] = $times;
            $day->addDay();
        }

        $attendeeTables = [
            ['id' => 't1', 'name' => 'Expouse Table', 'capacity' => 4, 'image_file_id' => null, 'image_url' => $this->pic($picPrefix.'-lt1', 200, 200)],
            ['id' => 't2', 'name' => 'Founders Corner', 'capacity' => 6, 'image_file_id' => null, 'image_url' => $this->pic($picPrefix.'-lt2', 200, 200)],
            ['id' => 't3', 'name' => 'Designers Lounge', 'capacity' => 4, 'image_file_id' => null, 'image_url' => $this->pic($picPrefix.'-lt3', 200, 200)],
            ['id' => 't4', 'name' => 'AI & Data Table', 'capacity' => 8, 'image_file_id' => null, 'image_url' => null],
        ];

        $existing = EventSetting::where('event_id', $event->id)->first();
        $lounge = is_array($existing?->lounge) ? $existing->lounge : [];

        EventSetting::updateOrCreate(
            ['event_id' => $event->id],
            [
                'organization_id' => $event->organization_id,
                'lounge' => array_merge($lounge, [
                    'enabled' => true,
                    'slots_open_all' => false,
                    'slots' => $slots,
                    'attendee_tables_enabled' => true,
                    'attendee_tables' => $attendeeTables,
                    'exhibitor_tables_enabled' => true,
                    'exhibitor_default_meetings' => 3,
                    'sponsor_tables_enabled' => true,
                    'sponsor_default_meetings' => 10,
                ]),
            ],
        );
    }

    /** A published LiveKit (webrtc) breakout room. */
    private function room(Organization $org, Event $event, string $name, string $type, string $access, ?string $code, int $cap, string $poster, int $i): void
    {
        BreakoutRoom::updateOrCreate(
            ['event_id' => $event->id, 'name' => $name],
            [
                'organization_id' => $org->id,
                'description' => "A live {$type} space — join over video and audio.",
                'purpose' => 'single', 'type' => $type, 'access_type' => $access,
                'access_code' => $access === 'coded' ? $code : null,
                'capacity' => $cap, 'poster_url' => $poster, 'provider' => 'webrtc',
                'recording_enabled' => false, 'status' => 'published', 'published_at' => now(),
            ],
        );
    }

    /**
     * A user + contact + attendee participation. Login password = "password".
     * The job title / company feed the delegate directory and its "Similar
     * profiles" matching; the avatar index picks a pravatar portrait.
     */
    private function attendee(
        Organization $org,
        Event $event,
        string $name,
        string $email,
        string $tz = 'UTC',
        ?string $jobTitle = null,
        ?string $company = null,
        ?int $avatar = null,
    ): Participation {
        $user = User::updateOrCreate(
            ['email' => $email],
            ['name' => $name, 'password' => 'password', 'email_verified_at' => now(), 'timezone' => $tz],
        );

        [$first, $last] = $this->splitName($name);
        $contact = Contact::updateOrCreate(
            ['organization_id' => $org->id, 'email' => $email],
            [
                'user_id' => $user->id,
                'first_name' => $first,
                'last_name' => $last,
                'job_title' => $jobTitle,
                'company' => $company,
            ],
        );

        return Participation::updateOrCreate(
            ['event_id' => $event->id, 'contact_id' => $contact->id, 'role' => 'attendee'],
            [
                'organization_id' => $org->id,
                'status' => 'confirmed',
                'networking_opt_in' => true,
                'meta' => $avatar ? ['avatar_url' => $this->avatar($avatar)] : null,
            ],
        );
    }

    /**
     * A directory of attendees to browse and connect with. Titles and companies
     * repeat on purpose — "Similar profiles" is only interesting when several
     * people share a designation ("Data Scientist") or an employer ("Acme
     * Robotics"), and the strip ranks an exact title+company match above either
     * one alone.
     */
    private function seedDelegates(Organization $org, Event $event, string $tz, string $emailDomain): void
    {
        $roster = [
            ['Maya Fernandez', 'Head of Product', 'Globex AI', 20],
            ['Idris Khan', 'Data Scientist', 'Acme Robotics', 21],
            ['Lena Vogel', 'Data Scientist', 'Northwind Software', 22],
            ['Omar Haddad', 'Machine Learning Engineer', 'Acme Robotics', 23],
            ['Grace Liu', 'Machine Learning Engineer', 'DeepScale Labs', 24],
            ['Tom Whitfield', 'Head of Product', 'Acme Robotics', 25],
            ['Ana Costa', 'CTO', 'LaunchPad Ventures', 26],
            ['Ravi Menon', 'Data Scientist', 'Globex AI', 27],
            ['Sofia Bergman', 'UX Researcher', 'Studio Kaizen', 28],
            ['Daniel Okoye', 'Machine Learning Engineer', 'Northwind Software', 29],
            ['Hana Sato', 'CTO', 'Nova Robotics', 30],
            ['Peter Novak', 'UX Researcher', 'Globex AI', 31],
        ];

        foreach ($roster as [$name, $jobTitle, $company, $avatar]) {
            $handle = str_replace(' ', '.', mb_strtolower($name));
            $this->attendee($org, $event, $name, "{$handle}@{$emailDomain}", $tz, $jobTitle, $company, $avatar);
        }
    }

    /**
     * A host: an attendee participation (so ResolveParticipant admits them to the
     * event's routes) PLUS an org membership with the 'manager' role, which grants
     * events.manage → the breakout-room token endpoint mints a HOST (publish) grant.
     */
    private function host(Organization $org, Event $event, string $name, string $email, string $tz = 'UTC'): void
    {
        $this->attendee($org, $event, $name, $email, $tz);

        $user = User::where('email', $email)->first();
        $membership = Membership::updateOrCreate(
            ['organization_id' => $org->id, 'user_id' => $user->id],
            ['status' => 'active', 'joined_at' => now()],
        );

        $manager = Role::where('name', 'manager')->where('scope', 'tenant')->first();
        if ($manager) {
            $membership->roles()->syncWithoutDetaching([$manager->id]);
        }
    }

    /** A speaker: contact (no login) + participation(role=speaker) with profile_data. */
    private function speaker(Organization $org, Event $event, string $name, string $role, string $company, string $img, int $i, string $emailDomain): Participation
    {
        [$first, $last] = $this->splitName($name);
        $email = Str::slug($name).'@speakers.'.$emailDomain;

        $contact = Contact::updateOrCreate(
            ['organization_id' => $org->id, 'email' => $email],
            ['first_name' => $first, 'last_name' => $last, 'company' => $company, 'job_title' => $role],
        );

        return Participation::updateOrCreate(
            ['event_id' => $event->id, 'contact_id' => $contact->id, 'role' => 'speaker'],
            [
                'organization_id' => $org->id, 'status' => 'confirmed',
                'profile_data' => [
                    'designation' => $role, 'company' => $company, 'category' => 'Keynote',
                    'image_url' => $img, 'bio' => "{$name} is {$role} at {$company}.",
                    'is_featured' => true, 'is_public' => true, 'sort_order' => $i,
                ],
            ],
        );
    }

    /** An exhibitor or sponsor (unified Exhibitor model, type discriminator). */
    private function partner(Organization $org, Event $event, string $name, string $type, int $booth, string $logo, int $i): void
    {
        $categories = ['Technology', 'Robotics', 'Cloud & Data', 'Manufacturing', 'Media'];
        $category = $type === 'sponsor' ? 'Partner' : $categories[$i % count($categories)];
        $slug = Str::slug($name);

        $exhibitor = Exhibitor::updateOrCreate(
            ['event_id' => $event->id, 'slug' => $slug],
            [
                'organization_id' => $org->id, 'type' => $type, 'name' => $name, 'status' => 'active',
                'description' => "{$name} is a leading {$category} company showcasing its latest work at the event. "
                    ."Stop by booth {$booth} for live demos, meet the team, and discover how {$name} can help your business.",
                'website' => 'https://example.com/'.$slug,
                'tier_rank' => 100 - $i,
                'profile_data' => [
                    'logo_url' => $logo,
                    'booth' => (string) $booth,
                    'category' => $category,
                    'linkedin' => 'https://linkedin.com/company/'.$slug,
                ],
            ],
        );

        // Give exhibitors a couple of products + a public brochure so the
        // directory's detail view has real content to show.
        if ($type === 'exhibitor') {
            foreach (['Flagship Platform', 'Starter Kit'] as $j => $product) {
                ExhibitorProduct::updateOrCreate(
                    ['exhibitor_id' => $exhibitor->id, 'name' => $product],
                    [
                        'description' => "{$product} by {$name} — built to help teams move faster and scale with confidence.",
                        'price_cents' => ($j + 1) * 49900,
                        'meta' => ['image_url' => $this->pic($slug.'-p'.$j, 400, 260)],
                    ],
                );
            }

            ExhibitorDocument::updateOrCreate(
                ['exhibitor_id' => $exhibitor->id, 'title' => 'Company Brochure'],
                ['url' => 'https://example.com/'.$slug.'/brochure.pdf', 'visibility' => 'all'],
            );
        }
    }

    /**
     * The places one-to-one meetings can be held in (Admin → Communication →
     * Meetings). Only venue/hybrid events have a floor to meet on; an online
     * event gets none and its meetings stay location-less.
     *
     * @return string[] the seeded places, empty for an online event
     */
    private function seedMeetingLocations(Event $event): array
    {
        if (! in_array($event->format, ['venue', 'hybrid'], true)) {
            return [];
        }

        $places = ['Hall 4', 'Meeting Room 2', 'Networking Lounge', 'Cafe Terrace'];

        $setting = EventSetting::firstOrNew(['event_id' => $event->id]);
        $setting->organization_id = $event->organization_id;
        $setting->meeting = array_merge((array) $setting->meeting, ['locations' => $places]);
        $setting->save();

        return $places;
    }

    /** A confirmed one-on-one meeting between two attendees. */
    private function meeting(Organization $org, Event $event, Participation $host, Participation $guest, string $title, \DateTimeInterface $start, ?string $location = null): void
    {
        $meeting = Meeting::updateOrCreate(
            ['event_id' => $event->id, 'title' => $title],
            [
                'organization_id' => $org->id,
                'organizer_participation_id' => $host->id,
                'type' => 'one_on_one',
                'location' => $location,
                'starts_at' => $start,
                'ends_at' => (clone $start)->modify('+30 minutes'),
                'status' => 'confirmed',
            ],
        );

        $meeting->participants()->syncWithoutDetaching([
            $host->id => ['role' => 'host', 'rsvp' => 'accepted'],
            $guest->id => ['role' => 'guest', 'rsvp' => 'accepted'],
        ]);
    }

    /** @return array{0:string,1:string} */
    private function splitName(string $name): array
    {
        $parts = preg_split('/\s+/', trim($name), 2);

        return [$parts[0] ?? $name, $parts[1] ?? ''];
    }
}
