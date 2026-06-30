<?php

use App\Http\Controllers\Api\V1\Admin\AdminMembershipController;
use App\Http\Controllers\Api\V1\Admin\AdminMetricsController;
use App\Http\Controllers\Api\V1\Admin\AdminOrganizationController;
use App\Http\Controllers\Api\V1\Admin\AdminExhibitorController;
use App\Http\Controllers\Api\V1\Admin\AdminPlanController;
use App\Http\Controllers\Api\V1\Admin\AdminUserController;
use App\Http\Controllers\Api\V1\AnalyticsController;
use App\Http\Controllers\Api\V1\AnnouncementController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\BlogPostController;
use App\Http\Controllers\Api\V1\BoothController;
use App\Http\Controllers\Api\V1\CheckInController;
use App\Http\Controllers\Api\V1\ConnectionController;
use App\Http\Controllers\Api\V1\CtaController;
use App\Http\Controllers\Api\V1\DeviceTokenController;
use App\Http\Controllers\Api\V1\CheckInStationController;
use App\Http\Controllers\Api\V1\DiscountCodeController;
use App\Http\Controllers\Api\V1\EmailTemplateController;
use App\Http\Controllers\Api\V1\EventController;
use App\Http\Controllers\Api\V1\FeedController;
use App\Http\Controllers\Api\V1\FileUploadController;
use App\Http\Controllers\Api\V1\FloorController;
use App\Http\Controllers\Api\V1\BadgeDesignController;
use App\Http\Controllers\Api\V1\ParticipantController;
use App\Http\Controllers\Api\V1\EventAdController;
use App\Http\Controllers\Api\V1\FormController;
use App\Http\Controllers\Api\V1\GamificationController;
use App\Http\Controllers\Api\V1\GalleryImageController;
use App\Http\Controllers\Api\V1\MeetingController;
use App\Http\Controllers\Api\V1\MembershipController;
use App\Http\Controllers\Api\V1\NotificationController;
use App\Http\Controllers\Api\V1\NotificationPreferenceController;
use App\Http\Controllers\Api\V1\NotificationTemplateController;
use App\Http\Controllers\Api\V1\OrganizationController;
use App\Http\Controllers\Api\V1\ExhibitorController;
use App\Http\Controllers\Api\V1\ExhibitorDocumentController;
use App\Http\Controllers\Api\V1\ExhibitorMemberController;
use App\Http\Controllers\Api\V1\ExhibitorPackageController;
use App\Http\Controllers\Api\V1\ExhibitorProductController;
use App\Http\Controllers\Api\V1\ExhibitorProjectController;
use App\Http\Controllers\Api\V1\ExhibitorSelfMemberController;
use App\Http\Controllers\Api\V1\ExhibitorSpaceController;
use App\Http\Controllers\Api\V1\PlanController;
use App\Http\Controllers\Api\V1\RegistrationController;
use App\Http\Controllers\Api\V1\RoomController;
use App\Http\Controllers\Api\V1\ServiceCategoryController;
use App\Http\Controllers\Api\V1\ServiceController;
use App\Http\Controllers\Api\V1\SessionController;
use App\Http\Controllers\Api\V1\SpeakerController;
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

            // Exhibitors & sponsors across all tenants.
            Route::get('/exhibitors', [AdminExhibitorController::class, 'index']);
            Route::get('/exhibitors/{uuid}', [AdminExhibitorController::class, 'show']);
            Route::post('/exhibitors/{uuid}/admin', [AdminExhibitorController::class, 'setAdmin']);
            Route::match(['put', 'patch'], '/exhibitors/{uuid}', [AdminExhibitorController::class, 'update']);
        });

        // ── Exhibitor self-service: admin + staff (resolves the exhibitor → its org) ──
        Route::middleware('exhibitor.admin')->prefix('exhibitor')->group(function () {
            Route::get('/space', [ExhibitorSpaceController::class, 'show']);
            Route::match(['put', 'patch'], '/space', [ExhibitorSpaceController::class, 'update']);
            Route::post('/uploads', [FileUploadController::class, 'store']);
            Route::post('/products', [ExhibitorSpaceController::class, 'storeProduct']);

            // Exhibitor booth details (code/type/resources).
            Route::get('/booth', [ExhibitorSpaceController::class, 'showBooth']);
            Route::match(['put', 'patch'], '/booth', [ExhibitorSpaceController::class, 'updateBooth']);

            // Team management (invite teammates, give them logins).
            Route::get('/members', [ExhibitorSelfMemberController::class, 'index']);
            Route::post('/members', [ExhibitorSelfMemberController::class, 'store']);
            Route::delete('/members/{member}', [ExhibitorSelfMemberController::class, 'destroy']);
            Route::post('/members/{member}/password', [ExhibitorSelfMemberController::class, 'password']);
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
            Route::patch('/events/{uuid}/credentials', [EventController::class, 'updateCredentials'])->middleware('perm:events.manage');
            Route::delete('/events/{uuid}', [EventController::class, 'destroy'])->middleware('perm:events.manage');

            // Venues & rooms
            Route::get('/venues', [VenueController::class, 'index'])->middleware('perm:events.view');
            Route::get('/venues/{id}', [VenueController::class, 'show'])->middleware('perm:events.view');
            Route::post('/venues', [VenueController::class, 'store'])->middleware('perm:venues.manage');
            Route::post('/rooms', [RoomController::class, 'store'])->middleware('perm:venues.manage');

            // Tracks
            Route::get('/tracks', [TrackController::class, 'index'])->middleware('perm:events.view');
            Route::middleware('perm:sessions.manage')->group(function () {
                Route::post('/tracks', [TrackController::class, 'store']);
                Route::match(['put', 'patch'], '/tracks/{id}', [TrackController::class, 'update']);
                Route::delete('/tracks/{id}', [TrackController::class, 'destroy']);
            });

            // Sessions & speakers
            Route::get('/sessions', [SessionController::class, 'index'])->middleware('perm:events.view');
            Route::get('/sessions/{uuid}', [SessionController::class, 'show'])->middleware('perm:events.view');
            Route::middleware('perm:sessions.manage')->group(function () {
                Route::post('/sessions', [SessionController::class, 'store']);
                Route::match(['put', 'patch'], '/sessions/{uuid}', [SessionController::class, 'update']);
                Route::match(['put', 'patch'], '/sessions/{uuid}/stream', [SessionController::class, 'updateStream']);
                Route::delete('/sessions/{uuid}', [SessionController::class, 'destroy']);
            });
            Route::post('/sessions/{uuid}/speakers', [SessionController::class, 'addSpeaker'])->middleware('perm:speakers.manage');
            Route::delete('/sessions/{uuid}/speakers/{participation}', [SessionController::class, 'removeSpeaker'])->middleware('perm:speakers.manage');

            // Showcase speakers (event-level, not tied to a session)
            Route::middleware('perm:speakers.manage')->group(function () {
                Route::get('/events/{uuid}/speakers', [SpeakerController::class, 'index']);
                Route::post('/events/{uuid}/speakers', [SpeakerController::class, 'store']);
                Route::match(['put', 'patch'], '/events/{uuid}/speakers/{participation}', [SpeakerController::class, 'update']);
                Route::delete('/events/{uuid}/speakers/{participation}', [SpeakerController::class, 'destroy']);
            });

            // ── Event advertisements (AD Managements) ──
            Route::get('/events/{uuid}/ads', [EventAdController::class, 'index'])->middleware('perm:events.view');
            Route::get('/events/{uuid}/ads/insights', [EventAdController::class, 'insights'])->middleware('perm:events.view');
            Route::post('/events/{uuid}/ads', [EventAdController::class, 'store'])->middleware('perm:events.manage');
            Route::post('/ads/{ad}/track', [EventAdController::class, 'track'])->middleware('perm:events.view');
            Route::get('/ads/{ad}', [EventAdController::class, 'show'])->middleware('perm:events.view');
            Route::match(['put', 'patch'], '/ads/{ad}', [EventAdController::class, 'update'])->middleware('perm:events.manage');
            Route::delete('/ads/{ad}', [EventAdController::class, 'destroy'])->middleware('perm:events.manage');

            // ── Event people directory ("Users" section) ──
            Route::get('/events/{uuid}/participants', [ParticipantController::class, 'index'])->middleware('perm:events.view');
            Route::post('/events/{uuid}/participants/{participation}/block', [ParticipantController::class, 'setBlocked'])->middleware('perm:events.manage');
            Route::delete('/events/{uuid}/participants/{participation}', [ParticipantController::class, 'destroy'])->middleware('perm:events.manage');

            // ── Ticketing & check-in (§6.4) ──
            Route::get('/ticket-types', [TicketTypeController::class, 'index'])->middleware('perm:events.view');
            Route::post('/ticket-types', [TicketTypeController::class, 'store'])->middleware('perm:ticketing.manage');
            Route::post('/discount-codes', [DiscountCodeController::class, 'store'])->middleware('perm:ticketing.manage');
            Route::get('/check-in-stations', [CheckInStationController::class, 'index'])->middleware('perm:checkin.manage');
            Route::post('/check-in-stations', [CheckInStationController::class, 'store'])->middleware('perm:checkin.manage');
            Route::post('/check-in/scan', [CheckInController::class, 'scan'])->middleware('perm:checkin.manage');

            // ── Exhibitors & sponsors (§6.3) ──
            Route::middleware('perm:exhibitors.manage')->group(function () {
                Route::get('/exhibitor-packages', [ExhibitorPackageController::class, 'index']);
                Route::post('/exhibitor-packages', [ExhibitorPackageController::class, 'store']);
                Route::match(['put', 'patch'], '/exhibitor-packages/{exhibitorPackage}', [ExhibitorPackageController::class, 'update']);
                Route::delete('/exhibitor-packages/{exhibitorPackage}', [ExhibitorPackageController::class, 'destroy']);
                Route::get('/exhibitors', [ExhibitorController::class, 'index']);
                Route::post('/exhibitors', [ExhibitorController::class, 'store']);
                Route::get('/exhibitors/{uuid}', [ExhibitorController::class, 'show']);
                Route::match(['put', 'patch'], '/exhibitors/{uuid}', [ExhibitorController::class, 'update']);
                Route::post('/exhibitors/{uuid}/reset-password', [ExhibitorController::class, 'resetPassword']);
                Route::post('/exhibitors/{uuid}/members', [ExhibitorMemberController::class, 'store']);
                Route::delete('/exhibitors/{uuid}/members/{member}', [ExhibitorMemberController::class, 'destroy']);
                Route::get('/exhibitors/{uuid}/products', [ExhibitorProductController::class, 'index']);
                Route::post('/exhibitors/{uuid}/products', [ExhibitorProductController::class, 'store']);
                Route::delete('/exhibitors/{uuid}/products/{product}', [ExhibitorProductController::class, 'destroy']);
                Route::get('/exhibitors/{uuid}/documents', [ExhibitorDocumentController::class, 'index']);
                Route::post('/exhibitors/{uuid}/documents', [ExhibitorDocumentController::class, 'store']);
                Route::delete('/exhibitors/{uuid}/documents/{document}', [ExhibitorDocumentController::class, 'destroy']);
                Route::get('/exhibitors/{uuid}/projects', [ExhibitorProjectController::class, 'index']);
                Route::post('/exhibitors/{uuid}/projects', [ExhibitorProjectController::class, 'store']);
                Route::delete('/exhibitors/{uuid}/projects/{project}', [ExhibitorProjectController::class, 'destroy']);
                Route::post('/exhibitors/{uuid}/booths', [BoothController::class, 'store']);
            });

            // ── Content Hub: blog / news articles ──
            Route::get('/events/{uuid}/blog-posts', [BlogPostController::class, 'index'])->middleware('perm:events.view');
            Route::post('/events/{uuid}/blog-posts', [BlogPostController::class, 'store'])->middleware('perm:events.manage');
            Route::match(['put', 'patch'], '/events/{uuid}/blog-posts/{post}', [BlogPostController::class, 'update'])->middleware('perm:events.manage');
            Route::delete('/events/{uuid}/blog-posts/{post}', [BlogPostController::class, 'destroy'])->middleware('perm:events.manage');

            // ── Communication: sponsor CTAs (image / video / text) ──
            Route::get('/events/{uuid}/ctas', [CtaController::class, 'index'])->middleware('perm:events.view');
            Route::post('/events/{uuid}/ctas', [CtaController::class, 'store'])->middleware('perm:events.manage');
            Route::match(['put', 'patch'], '/events/{uuid}/ctas/{cta}', [CtaController::class, 'update'])->middleware('perm:events.manage');
            Route::delete('/events/{uuid}/ctas/{cta}', [CtaController::class, 'destroy'])->middleware('perm:events.manage');

            // ── Communication: gamification (points + award) ──
            Route::get('/events/{uuid}/gamification', [GamificationController::class, 'show'])->middleware('perm:events.view');
            Route::match(['put', 'patch'], '/events/{uuid}/gamification', [GamificationController::class, 'update'])->middleware('perm:events.manage');

            // ── Content Hub: image gallery ──
            Route::get('/events/{uuid}/gallery', [GalleryImageController::class, 'index'])->middleware('perm:events.view');
            Route::post('/events/{uuid}/gallery', [GalleryImageController::class, 'store'])->middleware('perm:events.manage');
            Route::post('/events/{uuid}/gallery/reorder', [GalleryImageController::class, 'reorder'])->middleware('perm:events.manage');
            Route::match(['put', 'patch'], '/events/{uuid}/gallery/{image}', [GalleryImageController::class, 'update'])->middleware('perm:events.manage');
            Route::delete('/events/{uuid}/gallery/{image}', [GalleryImageController::class, 'destroy'])->middleware('perm:events.manage');

            // ── Event services catalogue ──
            Route::get('/events/{uuid}/service-categories', [ServiceCategoryController::class, 'index'])->middleware('perm:events.view');
            Route::post('/events/{uuid}/service-categories', [ServiceCategoryController::class, 'store'])->middleware('perm:events.manage');
            Route::match(['put', 'patch'], '/service-categories/{category}', [ServiceCategoryController::class, 'update'])->middleware('perm:events.manage');
            Route::delete('/service-categories/{category}', [ServiceCategoryController::class, 'destroy'])->middleware('perm:events.manage');

            Route::get('/events/{uuid}/services', [ServiceController::class, 'index'])->middleware('perm:events.view');
            Route::post('/events/{uuid}/services', [ServiceController::class, 'store'])->middleware('perm:events.manage');
            Route::match(['put', 'patch'], '/services/{group}', [ServiceController::class, 'update'])->middleware('perm:events.manage');
            Route::delete('/services/{group}', [ServiceController::class, 'destroy'])->middleware('perm:events.manage');

            // ── Floor plans (floor.expouse canvas editor) ──
            Route::get('/events/{uuid}/floors', [FloorController::class, 'index'])->middleware('perm:events.view');
            Route::post('/events/{uuid}/floors', [FloorController::class, 'store'])->middleware('perm:events.manage');
            Route::get('/floors/{floor}', [FloorController::class, 'show'])->middleware('perm:events.view');
            Route::match(['put', 'patch'], '/floors/{floor}', [FloorController::class, 'update'])->middleware('perm:events.manage');
            Route::delete('/floors/{floor}', [FloorController::class, 'destroy'])->middleware('perm:events.manage');

            // ── Badge designs (badge.expouse canvas editor) ──
            Route::get('/events/{uuid}/badge-designs/element-library', [BadgeDesignController::class, 'elementLibrary'])->middleware('perm:events.view');
            Route::get('/events/{uuid}/badge-designs', [BadgeDesignController::class, 'index'])->middleware('perm:events.view');
            Route::post('/events/{uuid}/badge-designs', [BadgeDesignController::class, 'store'])->middleware('perm:events.manage');
            Route::get('/badge-designs/{badgeDesign}', [BadgeDesignController::class, 'show'])->middleware('perm:events.view');
            Route::match(['put', 'patch'], '/badge-designs/{badgeDesign}', [BadgeDesignController::class, 'update'])->middleware('perm:events.manage');
            Route::delete('/badge-designs/{badgeDesign}', [BadgeDesignController::class, 'destroy'])->middleware('perm:events.manage');

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
                Route::get('/email-variables', [EmailTemplateController::class, 'variables']);
                Route::post('/email-templates', [EmailTemplateController::class, 'store']);
                Route::post('/email-templates/preview-draft', [EmailTemplateController::class, 'previewDraft']);
                Route::post('/email-templates/seed', [EmailTemplateController::class, 'seed']);
                Route::get('/email-templates/{uuid}', [EmailTemplateController::class, 'show']);
                Route::match(['put', 'patch'], '/email-templates/{uuid}', [EmailTemplateController::class, 'update']);
                Route::delete('/email-templates/{uuid}', [EmailTemplateController::class, 'destroy']);
                Route::post('/email-templates/{uuid}/duplicate', [EmailTemplateController::class, 'duplicate']);
                Route::post('/email-templates/{uuid}/preview', [EmailTemplateController::class, 'preview']);
                Route::post('/email-templates/{uuid}/send-test', [EmailTemplateController::class, 'sendTest']);
            });
        });
    });
});
