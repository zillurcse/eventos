<?php

use App\Http\Controllers\Api\V1\Admin\AdminMembershipController;
use App\Http\Controllers\Api\V1\Admin\AdminMetricsController;
use App\Http\Controllers\Api\V1\Admin\AdminOrganizationController;
use App\Http\Controllers\Api\V1\Admin\AdminPartnerController;
use App\Http\Controllers\Api\V1\Admin\AdminPlanController;
use App\Http\Controllers\Api\V1\Admin\AdminUserController;
use App\Http\Controllers\Api\V1\AnalyticsController;
use App\Http\Controllers\Api\V1\AnnouncementController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\BoothController;
use App\Http\Controllers\Api\V1\CheckInController;
use App\Http\Controllers\Api\V1\ConnectionController;
use App\Http\Controllers\Api\V1\DeviceTokenController;
use App\Http\Controllers\Api\V1\CheckInStationController;
use App\Http\Controllers\Api\V1\DiscountCodeController;
use App\Http\Controllers\Api\V1\EmailTemplateController;
use App\Http\Controllers\Api\V1\EventController;
use App\Http\Controllers\Api\V1\FeedController;
use App\Http\Controllers\Api\V1\FileUploadController;
use App\Http\Controllers\Api\V1\FormController;
use App\Http\Controllers\Api\V1\MeetingController;
use App\Http\Controllers\Api\V1\MembershipController;
use App\Http\Controllers\Api\V1\NotificationController;
use App\Http\Controllers\Api\V1\NotificationPreferenceController;
use App\Http\Controllers\Api\V1\NotificationTemplateController;
use App\Http\Controllers\Api\V1\OrganizationController;
use App\Http\Controllers\Api\V1\PartnerController;
use App\Http\Controllers\Api\V1\PartnerMemberController;
use App\Http\Controllers\Api\V1\PartnerPackageController;
use App\Http\Controllers\Api\V1\PartnerProductController;
use App\Http\Controllers\Api\V1\PartnerSelfMemberController;
use App\Http\Controllers\Api\V1\PartnerSpaceController;
use App\Http\Controllers\Api\V1\PlanController;
use App\Http\Controllers\Api\V1\RegistrationController;
use App\Http\Controllers\Api\V1\RoomController;
use App\Http\Controllers\Api\V1\SessionController;
use App\Http\Controllers\Api\V1\SubscriptionController;
use App\Http\Controllers\Api\V1\TicketTypeController;
use App\Http\Controllers\Api\V1\TrackController;
use App\Http\Controllers\Api\V1\VenueController;
use App\Http\Controllers\HealthController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API v1   (apiPrefix "api" + "v1" group → /api/v1/...)
|--------------------------------------------------------------------------
*/
Route::prefix('v1')->group(function () {

    // ── Public ──────────────────────────────────────────────
    Route::get('/health', HealthController::class)->name('health');
    Route::get('/plans', [PlanController::class, 'index']);
    Route::post('/auth/register', [AuthController::class, 'register']);
    Route::post('/auth/login', [AuthController::class, 'login']);

    // Public form rendering + submission (the form uuid is the render token).
    Route::get('/forms/{uuid}', [FormController::class, 'render']);
    Route::post('/forms/{uuid}/submit', [FormController::class, 'submit']);

    // Public registration (event uuid + its published registration form).
    Route::post('/events/{uuid}/register', [RegistrationController::class, 'register']);

    // ── Authenticated (any signed-in user; no tenant required) ──
    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/auth/me', [AuthController::class, 'me']);
        Route::post('/auth/logout', [AuthController::class, 'logout']);

        // ── User notifications & preferences (cross-tenant identity) ──
        Route::get('/notifications', [NotificationController::class, 'index']);
        Route::post('/notifications/read-all', [NotificationController::class, 'readAll']);
        Route::patch('/notifications/{uuid}/read', [NotificationController::class, 'markRead']);
        Route::get('/notification-preferences', [NotificationPreferenceController::class, 'index']);
        Route::put('/notification-preferences', [NotificationPreferenceController::class, 'update']);
        Route::post('/device-tokens', [DeviceTokenController::class, 'store']);
        Route::delete('/device-tokens', [DeviceTokenController::class, 'destroy']);

        // ── Super-Admin control plane (platform staff only, cross-tenant §2.1) ──
        Route::middleware('platform')->prefix('admin')->group(function () {
            Route::get('/metrics', [AdminMetricsController::class, 'index']);
            Route::get('/organizations', [AdminOrganizationController::class, 'index']);
            Route::get('/organizations/{uuid}', [AdminOrganizationController::class, 'show']);
            Route::match(['put', 'patch'], '/organizations/{uuid}', [AdminOrganizationController::class, 'update']);
            Route::get('/plans', [AdminPlanController::class, 'index']);
            Route::post('/plans', [AdminPlanController::class, 'store']);
            Route::match(['put', 'patch'], '/plans/{uuid}', [AdminPlanController::class, 'update']);

            // ── Account & access management (all login types, cross-tenant §2.1) ──
            // Platform staff + global user accounts.
            Route::get('/users', [AdminUserController::class, 'index']);
            Route::post('/users', [AdminUserController::class, 'store']);
            Route::get('/users/{uuid}', [AdminUserController::class, 'show']);
            Route::match(['put', 'patch'], '/users/{uuid}', [AdminUserController::class, 'update']);
            Route::post('/users/{uuid}/password', [AdminUserController::class, 'password']);
            Route::post('/users/{uuid}/status', [AdminUserController::class, 'setStatus']);
            Route::delete('/users/{uuid}', [AdminUserController::class, 'destroy']);

            // Organizer memberships within a tenant + the assignable role list.
            Route::get('/roles', [AdminMembershipController::class, 'roles']);
            Route::get('/organizations/{uuid}/members', [AdminMembershipController::class, 'index']);
            Route::post('/organizations/{uuid}/members', [AdminMembershipController::class, 'store']);
            Route::match(['put', 'patch'], '/organizations/{uuid}/members/{membership}', [AdminMembershipController::class, 'update']);
            Route::delete('/organizations/{uuid}/members/{membership}', [AdminMembershipController::class, 'destroy']);

            // Exhibitors & sponsors (partner accounts) across all tenants.
            Route::get('/partners', [AdminPartnerController::class, 'index']);
            Route::get('/partners/{uuid}', [AdminPartnerController::class, 'show']);
            Route::post('/partners/{uuid}/admin', [AdminPartnerController::class, 'setAdmin']);
            Route::match(['put', 'patch'], '/partners/{uuid}', [AdminPartnerController::class, 'update']);
        });

        // ── Partner self-service: admin + staff (resolves the partner → its org) ──
        Route::middleware('partner.admin')->prefix('partner')->group(function () {
            Route::get('/space', [PartnerSpaceController::class, 'show']);
            Route::match(['put', 'patch'], '/space', [PartnerSpaceController::class, 'update']);
            Route::post('/uploads', [FileUploadController::class, 'store']);
            Route::post('/products', [PartnerSpaceController::class, 'storeProduct']);

            // Exhibitor booth details (code/type/resources).
            Route::get('/booth', [PartnerSpaceController::class, 'showBooth']);
            Route::match(['put', 'patch'], '/booth', [PartnerSpaceController::class, 'updateBooth']);

            // Team management (invite teammates, give them logins).
            Route::get('/members', [PartnerSelfMemberController::class, 'index']);
            Route::post('/members', [PartnerSelfMemberController::class, 'store']);
            Route::delete('/members/{member}', [PartnerSelfMemberController::class, 'destroy']);
            Route::post('/members/{member}/password', [PartnerSelfMemberController::class, 'password']);
        });

        // ── Attendee context: networking & feed (§6.5, §6.6) ──
        Route::middleware('participant')->prefix('events/{event}')->group(function () {
            Route::get('/feed', [FeedController::class, 'index']);
            Route::post('/feed', [FeedController::class, 'store']);
            Route::post('/feed/{post}/comments', [FeedController::class, 'comment']);
            Route::post('/feed/{post}/reactions', [FeedController::class, 'react']);
            Route::get('/connections', [ConnectionController::class, 'index']);
            Route::post('/connections', [ConnectionController::class, 'store']);
            Route::patch('/connections/{connection}', [ConnectionController::class, 'respond']);
            Route::get('/meetings', [MeetingController::class, 'index']);
            Route::post('/meetings', [MeetingController::class, 'store']);
            Route::post('/track', [AnalyticsController::class, 'track']); // analytics fact
        });

        // ── Tenant-scoped (resolves org → sets RLS GUC → rate limit) ──
        Route::middleware(['tenant', 'throttle:api'])->group(function () {
            Route::get('/organization', [OrganizationController::class, 'current']);

            // Image uploads → MinIO (event covers, etc.) under the tenant GUC.
            Route::post('/uploads', [FileUploadController::class, 'store']);

            // ── Organizer team self-service (§6.1) ──
            Route::get('/assignable-roles', [MembershipController::class, 'roles'])->middleware('perm:members.manage');
            Route::get('/members', [MembershipController::class, 'index'])->middleware('perm:members.manage');
            Route::post('/members', [MembershipController::class, 'store'])->middleware('perm:members.manage');
            Route::match(['put', 'patch'], '/members/{membership}', [MembershipController::class, 'update'])->middleware('perm:members.manage');
            Route::delete('/members/{membership}', [MembershipController::class, 'destroy'])->middleware('perm:members.manage');

            Route::get('/subscription', [SubscriptionController::class, 'current']);
            Route::post('/subscription/change', [SubscriptionController::class, 'change'])
                ->middleware('perm:settings.manage');

            // ── Events module (§6.3) ──
            Route::get('/events', [EventController::class, 'index'])->middleware('perm:events.view');
            Route::get('/events/{uuid}', [EventController::class, 'show'])->middleware('perm:events.view');
            Route::get('/events/{uuid}/agenda', [EventController::class, 'agenda'])->middleware('perm:events.view');
            Route::get('/events/{uuid}/overview', [EventController::class, 'overview'])->middleware('perm:events.view');
            Route::get('/events/{uuid}/settings', [EventController::class, 'showSettings'])->middleware('perm:events.view');
            Route::match(['put', 'patch'], '/events/{uuid}/settings', [EventController::class, 'updateSettings'])->middleware('perm:events.manage');
            Route::post('/events', [EventController::class, 'store'])->middleware('perm:events.manage');
            Route::match(['put', 'patch'], '/events/{uuid}', [EventController::class, 'update'])->middleware('perm:events.manage');
            Route::post('/events/{uuid}/publish', [EventController::class, 'publish'])->middleware('perm:events.manage');
            Route::delete('/events/{uuid}', [EventController::class, 'destroy'])->middleware('perm:events.manage');

            // Venues & rooms
            Route::get('/venues', [VenueController::class, 'index'])->middleware('perm:events.view');
            Route::get('/venues/{id}', [VenueController::class, 'show'])->middleware('perm:events.view');
            Route::post('/venues', [VenueController::class, 'store'])->middleware('perm:venues.manage');
            Route::post('/rooms', [RoomController::class, 'store'])->middleware('perm:venues.manage');

            // Tracks
            Route::get('/tracks', [TrackController::class, 'index'])->middleware('perm:events.view');
            Route::post('/tracks', [TrackController::class, 'store'])->middleware('perm:sessions.manage');

            // Sessions & speakers
            Route::get('/sessions', [SessionController::class, 'index'])->middleware('perm:events.view');
            Route::get('/sessions/{uuid}', [SessionController::class, 'show'])->middleware('perm:events.view');
            Route::post('/sessions', [SessionController::class, 'store'])->middleware('perm:sessions.manage');
            Route::match(['put', 'patch'], '/sessions/{uuid}', [SessionController::class, 'update'])->middleware('perm:sessions.manage');
            Route::post('/sessions/{uuid}/speakers', [SessionController::class, 'addSpeaker'])->middleware('perm:speakers.manage');
            Route::delete('/sessions/{uuid}/speakers/{participation}', [SessionController::class, 'removeSpeaker'])->middleware('perm:speakers.manage');

            // ── Ticketing & check-in (§6.4) ──
            Route::get('/ticket-types', [TicketTypeController::class, 'index'])->middleware('perm:events.view');
            Route::post('/ticket-types', [TicketTypeController::class, 'store'])->middleware('perm:ticketing.manage');
            Route::post('/discount-codes', [DiscountCodeController::class, 'store'])->middleware('perm:ticketing.manage');
            Route::get('/check-in-stations', [CheckInStationController::class, 'index'])->middleware('perm:checkin.manage');
            Route::post('/check-in-stations', [CheckInStationController::class, 'store'])->middleware('perm:checkin.manage');
            Route::post('/check-in/scan', [CheckInController::class, 'scan'])->middleware('perm:checkin.manage');

            // ── Partners: exhibitors & sponsors (§6.3) ──
            Route::middleware('perm:partners.manage')->group(function () {
                Route::get('/partner-packages', [PartnerPackageController::class, 'index']);
                Route::post('/partner-packages', [PartnerPackageController::class, 'store']);
                Route::get('/partners', [PartnerController::class, 'index']);
                Route::post('/partners', [PartnerController::class, 'store']);
                Route::get('/partners/{uuid}', [PartnerController::class, 'show']);
                Route::match(['put', 'patch'], '/partners/{uuid}', [PartnerController::class, 'update']);
                Route::post('/partners/{uuid}/members', [PartnerMemberController::class, 'store']);
                Route::delete('/partners/{uuid}/members/{member}', [PartnerMemberController::class, 'destroy']);
                Route::get('/partners/{uuid}/products', [PartnerProductController::class, 'index']);
                Route::post('/partners/{uuid}/products', [PartnerProductController::class, 'store']);
                Route::post('/partners/{uuid}/booths', [BoothController::class, 'store']);
            });

            // ── Announcements (§6.6) ──
            Route::get('/announcements', [AnnouncementController::class, 'index'])->middleware('perm:announcements.manage');
            Route::post('/announcements', [AnnouncementController::class, 'store'])->middleware('perm:announcements.manage');

            // ── Notification templates + analytics (§6.7, §6.11) ──
            Route::get('/notification-templates', [NotificationTemplateController::class, 'index'])->middleware('perm:notifications.manage');
            Route::post('/notification-templates', [NotificationTemplateController::class, 'store'])->middleware('perm:notifications.manage');
            Route::get('/events/{uuid}/analytics', [AnalyticsController::class, 'summary'])->middleware('perm:analytics.view');
            Route::post('/events/{uuid}/analytics/rollup', [AnalyticsController::class, 'rollup'])->middleware('perm:analytics.view');

            // Form builder
            Route::middleware('perm:forms.manage')->group(function () {
                Route::get('/forms', [FormController::class, 'index']);
                Route::post('/forms', [FormController::class, 'store']);
                Route::get('/forms/{uuid}/edit', [FormController::class, 'show']);
                Route::match(['put', 'patch'], '/forms/{uuid}', [FormController::class, 'update']);
                Route::post('/forms/{uuid}/publish', [FormController::class, 'publish']);
            });

            // Email builder
            Route::middleware('perm:email.manage')->group(function () {
                Route::get('/email-templates', [EmailTemplateController::class, 'index']);
                Route::post('/email-templates', [EmailTemplateController::class, 'store']);
                Route::get('/email-templates/{uuid}', [EmailTemplateController::class, 'show']);
                Route::post('/email-templates/{uuid}/preview', [EmailTemplateController::class, 'preview']);
                Route::post('/email-templates/{uuid}/send-test', [EmailTemplateController::class, 'sendTest']);
            });
        });
    });
});
