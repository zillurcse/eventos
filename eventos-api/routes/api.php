<?php

use App\Http\Controllers\Api\V1\Admin\AdminExhibitorController;
use App\Http\Controllers\Api\V1\Admin\AdminMembershipController;
use App\Http\Controllers\Api\V1\Admin\AdminMetricsController;
use App\Http\Controllers\Api\V1\Admin\AdminOrganizationController;
use App\Http\Controllers\Api\V1\Admin\AdminPlanChangeRequestController;
use App\Http\Controllers\Api\V1\Admin\AdminPlanController;
use App\Http\Controllers\Api\V1\Admin\AdminUserController;
use App\Http\Controllers\Api\V1\AnalyticsController;
use App\Http\Controllers\Api\V1\AnnouncementController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\BadgeDesignController;
use App\Http\Controllers\Api\V1\BlogPostController;
use App\Http\Controllers\Api\V1\BookmarkController;
use App\Http\Controllers\Api\V1\BoothController;
use App\Http\Controllers\Api\V1\BreakoutRoomController;
use App\Http\Controllers\Api\V1\BriefcaseController;
use App\Http\Controllers\Api\V1\ChatController;
use App\Http\Controllers\Api\V1\CheckInController;
use App\Http\Controllers\Api\V1\CheckInStationController;
use App\Http\Controllers\Api\V1\ConnectionController;
use App\Http\Controllers\Api\V1\ContestController;
use App\Http\Controllers\Api\V1\CtaController;
use App\Http\Controllers\Api\V1\DelegateController;
use App\Http\Controllers\Api\V1\DeviceTokenController;
use App\Http\Controllers\Api\V1\DiscountCodeController;
use App\Http\Controllers\Api\V1\DomainController;
use App\Http\Controllers\Api\V1\EmailTemplateController;
use App\Http\Controllers\Api\V1\EventAdController;
use App\Http\Controllers\Api\V1\EventAdminController;
use App\Http\Controllers\Api\V1\EventController;
use App\Http\Controllers\Api\V1\ExhibitorContactController;
use App\Http\Controllers\Api\V1\ExhibitorController;
use App\Http\Controllers\Api\V1\ExhibitorDocumentController;
use App\Http\Controllers\Api\V1\ExhibitorImportController;
use App\Http\Controllers\Api\V1\ExhibitorInboxController;
use App\Http\Controllers\Api\V1\ExhibitorMemberController;
use App\Http\Controllers\Api\V1\ExhibitorPackageController;
use App\Http\Controllers\Api\V1\ExhibitorProductController;
use App\Http\Controllers\Api\V1\ExhibitorProjectController;
use App\Http\Controllers\Api\V1\ExhibitorScanningController;
use App\Http\Controllers\Api\V1\ExhibitorSelfCatalogController;
use App\Http\Controllers\Api\V1\ExhibitorSelfLeadAnalyticsController;
use App\Http\Controllers\Api\V1\ExhibitorSelfLeadController;
use App\Http\Controllers\Api\V1\ExhibitorSelfLeadExportController;
use App\Http\Controllers\Api\V1\ExhibitorSelfLeadQualificationController;
use App\Http\Controllers\Api\V1\ExhibitorSelfMemberController;
use App\Http\Controllers\Api\V1\ExhibitorSelfRecommendationController;
use App\Http\Controllers\Api\V1\ExhibitorSelfServiceController;
use App\Http\Controllers\Api\V1\ExhibitorSpaceController;
use App\Http\Controllers\Api\V1\FeedController;
use App\Http\Controllers\Api\V1\FeedModerationController;
use App\Http\Controllers\Api\V1\FileUploadController;
use App\Http\Controllers\Api\V1\FloorController;
use App\Http\Controllers\Api\V1\FormController;
use App\Http\Controllers\Api\V1\GalleryImageController;
use App\Http\Controllers\Api\V1\GamificationController;
use App\Http\Controllers\Api\V1\GateScanningController;
use App\Http\Controllers\Api\V1\GuestBadgeController;
use App\Http\Controllers\Api\V1\IconController;
use App\Http\Controllers\Api\V1\LeadGenerationController;
use App\Http\Controllers\Api\V1\LoungeController;
use App\Http\Controllers\Api\V1\MeetingController;
use App\Http\Controllers\Api\V1\MembershipController;
use App\Http\Controllers\Api\V1\NoteController;
use App\Http\Controllers\Api\V1\NotificationController;
use App\Http\Controllers\Api\V1\NotificationPreferenceController;
use App\Http\Controllers\Api\V1\NotificationTemplateController;
use App\Http\Controllers\Api\V1\OrganizationController;
use App\Http\Controllers\Api\V1\OtpAuthController;
use App\Http\Controllers\Api\V1\ParticipantBadgeController;
use App\Http\Controllers\Api\V1\ParticipantController;
use App\Http\Controllers\Api\V1\ParticipantContestController;
use App\Http\Controllers\Api\V1\ParticipantProfileController;
use App\Http\Controllers\Api\V1\ProfileFormController;
use App\Http\Controllers\Api\V1\ParticipantSurveyController;
use App\Http\Controllers\Api\V1\PlanController;
use App\Http\Controllers\Api\V1\PresenceController;
use App\Http\Controllers\Api\V1\PublicSiteController;
use App\Http\Controllers\Api\V1\RegistrationController;
use App\Http\Controllers\Api\V1\RoomController;
use App\Http\Controllers\Api\V1\ServiceCategoryController;
use App\Http\Controllers\Api\V1\ServiceController;
use App\Http\Controllers\Api\V1\ServiceOrderController;
use App\Http\Controllers\Api\V1\SessionController;
use App\Http\Controllers\Api\V1\SessionEngagementController;
use App\Http\Controllers\Api\V1\SessionPollController;
use App\Http\Controllers\Api\V1\SocialAuthController;
use App\Http\Controllers\Api\V1\SpeakerController;
use App\Http\Controllers\Api\V1\SpeakerImportController;
use App\Http\Controllers\Api\V1\SubscriptionController;
use App\Http\Controllers\Api\V1\SurveyController;
use App\Http\Controllers\Api\V1\TicketTypeController;
use App\Http\Controllers\Api\V1\TrackController;
use App\Http\Controllers\Api\V1\VenueController;
use App\Http\Controllers\Api\V1\VideoSettingsController;
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
    Route::post('/auth/register', [AuthController::class, 'register'])->middleware('throttle:10,1');
    Route::post('/auth/login', [AuthController::class, 'login'])->middleware('throttle:login');

    // ── Attendee sign-in channels (Settings › Access authentication) ──
    // A one-time code emailed to the attendee. Rate-limited inside the
    // controller (per email, per IP, per code) — it mails strangers on demand.
    Route::post('/public/auth/otp', [OtpAuthController::class, 'request']);
    Route::post('/public/auth/otp/verify', [OtpAuthController::class, 'verify']);
    // Social sign-in. One callback for every event: the event rides through the
    // OAuth `state`, and the token comes home in the URL fragment.
    Route::get('/auth/social/{provider}/redirect', [SocialAuthController::class, 'redirect']);
    Route::get('/auth/social/{provider}/callback', [SocialAuthController::class, 'callback']);

    // Public per-event microsite bootstrap (resolve subdomain → published event
    // branding/config) + email-first login/signup branching. No auth, no tenant.
    Route::get('/public/site', [PublicSiteController::class, 'show']);
    Route::get('/public/reception', [PublicSiteController::class, 'reception']);
    Route::get('/public/sessions', [PublicSiteController::class, 'sessions']);
    Route::get('/public/speakers', [PublicSiteController::class, 'speakers']);
    Route::get('/public/exhibitors', [PublicSiteController::class, 'exhibitors']);
    Route::get('/public/exhibitors/{uuid}', [PublicSiteController::class, 'exhibitor']);
    Route::get('/public/ads', [PublicSiteController::class, 'ads']);
    Route::get('/public/rooms', [PublicSiteController::class, 'rooms']);
    Route::get('/public/sessions/{uuid}/zoom-signature', [PublicSiteController::class, 'zoomSignature']);
    // Unauthenticated write endpoints — per-IP throttle. 20/min/IP tolerates a
    // real person checking a couple of addresses or resubmitting a multi-step
    // form, while capping automated abuse: check-email is an account-existence
    // oracle, and form/register submissions are spammable. (throttle:N,M keys
    // guest requests by client IP.)
    Route::middleware('throttle:20,1')->group(function () {
        Route::post('/public/check-email', [PublicSiteController::class, 'checkEmail']);

        // Public form submission (the form uuid is the render token).
        Route::post('/forms/{uuid}/submit', [FormController::class, 'submit']);

        // Public registration (event uuid + its published registration form).
        Route::post('/events/{uuid}/register', [RegistrationController::class, 'register']);
    });

    // Public form rendering (read-only; the form uuid is the render token).
    Route::get('/forms/{uuid}', [FormController::class, 'render']);

    // ── Authenticated (any signed-in user; no tenant required) ──
    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/auth/me', [AuthController::class, 'me']);
        Route::post('/auth/logout', [AuthController::class, 'logout']);
        Route::post('/auth/change-password', [AuthController::class, 'changePassword']);
        Route::get('/auth/sessions', [AuthController::class, 'sessions']);
        Route::delete('/auth/sessions/{id}', [AuthController::class, 'revokeSession'])->whereNumber('id');
        Route::post('/auth/sessions/logout-others', [AuthController::class, 'logoutOtherSessions']);

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

            // Organizer plan-change requests: review + approve/reject.
            Route::get('/plan-change-requests', [AdminPlanChangeRequestController::class, 'index']);
            Route::post('/plan-change-requests/{uuid}/approve', [AdminPlanChangeRequestController::class, 'approve']);
            Route::post('/plan-change-requests/{uuid}/reject', [AdminPlanChangeRequestController::class, 'reject']);

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

            // Team management (invite teammates, give them logins) + ACL.
            Route::get('/members', [ExhibitorSelfMemberController::class, 'index']);
            Route::post('/members', [ExhibitorSelfMemberController::class, 'store']);
            Route::match(['put', 'patch'], '/members/{member}', [ExhibitorSelfMemberController::class, 'update']);
            Route::delete('/members/{member}', [ExhibitorSelfMemberController::class, 'destroy']);
            Route::post('/members/{member}/password', [ExhibitorSelfMemberController::class, 'password']);

            // Self-service catalog: documents, projects, products.
            Route::get('/documents', [ExhibitorSelfCatalogController::class, 'documents']);
            Route::post('/documents', [ExhibitorSelfCatalogController::class, 'storeDocument']);
            Route::delete('/documents/{document}', [ExhibitorSelfCatalogController::class, 'destroyDocument']);
            Route::get('/projects', [ExhibitorSelfCatalogController::class, 'projects']);
            Route::post('/projects', [ExhibitorSelfCatalogController::class, 'storeProject']);
            Route::match(['put', 'patch'], '/projects/{project}', [ExhibitorSelfCatalogController::class, 'updateProject']);
            Route::delete('/projects/{project}', [ExhibitorSelfCatalogController::class, 'destroyProject']);
            Route::get('/products', [ExhibitorSelfCatalogController::class, 'products']);
            Route::post('/products', [ExhibitorSelfCatalogController::class, 'storeProduct']);
            Route::match(['put', 'patch'], '/products/{product}', [ExhibitorSelfCatalogController::class, 'updateProduct']);
            Route::delete('/products/{product}', [ExhibitorSelfCatalogController::class, 'destroyProduct']);

            // Contact inbox: attendee messages + meeting requests, assign a member.
            Route::get('/inbox/conversations', [ExhibitorInboxController::class, 'conversations']);
            Route::get('/inbox/conversations/{conversation}/messages', [ExhibitorInboxController::class, 'messages']);
            Route::post('/inbox/conversations/{conversation}/messages', [ExhibitorInboxController::class, 'reply']);
            Route::get('/inbox/meeting-requests', [ExhibitorInboxController::class, 'meetingRequests']);
            Route::patch('/inbox/meeting-requests/{request}', [ExhibitorInboxController::class, 'respondMeeting']);

            // Lead capture CRM: list/filter, edit rating/status/notes, export.
            Route::get('/leads', [ExhibitorSelfLeadController::class, 'index']);
            // Per-teammate roll-up of every connection the booth has made.
            Route::get('/leads/team', [ExhibitorSelfLeadController::class, 'team']);
            // Recommended Leads: attendees scored on their interest in this
            // booth, with routing + outreach. Declared before /leads/{lead} so
            // "recommended" is never read as a lead uuid.
            Route::get('/leads/recommended', [ExhibitorSelfRecommendationController::class, 'index']);
            Route::get('/leads/recommended/{participation}', [ExhibitorSelfRecommendationController::class, 'show']);
            Route::post('/leads/recommended/{participation}/assign', [ExhibitorSelfRecommendationController::class, 'assign']);
            Route::post('/leads/recommended/{participation}/connect', [ExhibitorSelfRecommendationController::class, 'connect']);
            Route::post('/leads/recommended/{participation}/dismiss', [ExhibitorSelfRecommendationController::class, 'dismiss']);
            Route::delete('/leads/recommended/{participation}/dismiss', [ExhibitorSelfRecommendationController::class, 'restore']);
            // Qualification board (BANT + pipeline stages) and its one write.
            Route::get('/leads/pipeline', [ExhibitorSelfLeadQualificationController::class, 'index']);
            // Lead quality, funnel, sources, busiest hours, rep leaderboard.
            Route::get('/leads/analytics', [ExhibitorSelfLeadAnalyticsController::class, 'index']);
            // Deliberate export: pick columns + filters, see the count, download.
            Route::get('/leads/export/summary', [ExhibitorSelfLeadExportController::class, 'summary']);
            Route::post('/leads/export/download', [ExhibitorSelfLeadExportController::class, 'download']);
            Route::post('/leads', [ExhibitorSelfLeadController::class, 'store']);
            Route::post('/leads/export', [ExhibitorSelfLeadController::class, 'export']);
            Route::patch('/leads/{lead}/qualification', [ExhibitorSelfLeadQualificationController::class, 'update']);
            Route::match(['put', 'patch'], '/leads/{lead}', [ExhibitorSelfLeadController::class, 'update']);
            Route::delete('/leads/{lead}', [ExhibitorSelfLeadController::class, 'destroy']);

            // Request Service: browse the event catalogue + manage the booth's orders.
            Route::get('/services/catalog', [ExhibitorSelfServiceController::class, 'catalog']);
            Route::get('/services/categories', [ExhibitorSelfServiceController::class, 'categories']);
            Route::get('/services/requests', [ExhibitorSelfServiceController::class, 'index']);
            Route::post('/services/requests', [ExhibitorSelfServiceController::class, 'store']);
            Route::match(['put', 'patch'], '/services/requests/{serviceRequest}', [ExhibitorSelfServiceController::class, 'update']);
            Route::delete('/services/requests/{serviceRequest}', [ExhibitorSelfServiceController::class, 'destroy']);
        });

        // ── Attendee context: networking & feed (§6.5, §6.6) ──
        Route::middleware('participant')->prefix('events/{event}')->group(function () {
            // The attendee's own profile, and the onboarding step that fills it in.
            Route::get('/profile', [ParticipantProfileController::class, 'show']);
            Route::match(['put', 'patch'], '/profile', [ParticipantProfileController::class, 'update']);
            Route::get('/feed', [FeedController::class, 'index']);
            Route::post('/feed', [FeedController::class, 'store']);
            Route::get('/feed/{post}/comments', [FeedController::class, 'comments']);
            Route::post('/feed/{post}/comments', [FeedController::class, 'comment']);
            Route::post('/feed/{post}/reactions', [FeedController::class, 'react']);
            Route::post('/feed/{post}/poll/vote', [FeedController::class, 'votePoll']);
            // Contests ("Contests" tab): browse, enter, like and comment.
            Route::get('/contests', [ParticipantContestController::class, 'index']);
            Route::get('/contests/{contest}', [ParticipantContestController::class, 'show']);
            Route::get('/contests/{contest}/entries', [ParticipantContestController::class, 'entries']);
            Route::post('/contests/{contest}/entries', [ParticipantContestController::class, 'store']);
            Route::delete('/contest-entries/{entry}', [ParticipantContestController::class, 'destroy']);
            Route::post('/contest-entries/{entry}/like', [ParticipantContestController::class, 'like']);
            Route::get('/contest-entries/{entry}/comments', [ParticipantContestController::class, 'comments']);
            Route::post('/contest-entries/{entry}/comments', [ParticipantContestController::class, 'comment']);
            // Surveys ("Surveys" tab): answer the organizer's questionnaires.
            // Under `my/` because the organizer's own survey list lives at
            // /events/{uuid}/surveys — identical URIs would shadow each other,
            // and Laravel would hand both audiences to whichever is declared first.
            // The attendee's own badge(s) ("My Badges" tab). One per
            // participation, so a speaker who also staffs a booth gets both.
            Route::get('/my/badges', [ParticipantBadgeController::class, 'index']);
            Route::get('/my/surveys', [ParticipantSurveyController::class, 'index']);
            Route::get('/my/surveys/{survey}', [ParticipantSurveyController::class, 'show']);
            Route::post('/my/surveys/{survey}/responses', [ParticipantSurveyController::class, 'store']);
            // Attendee media uploads (feed images/video/PDF) → MinIO, under the
            // event's org GUC set by the participant middleware.
            Route::post('/uploads', [FileUploadController::class, 'store']);
            Route::get('/delegates', [DelegateController::class, 'index']);
            // "People like you" strip above the directory (same designation/company).
            Route::get('/delegates/similar', [DelegateController::class, 'similar']);
            // Live-session engagement panel (attendee watch page): group chat,
            // Q&A, polls and the attendees list, all scoped to one session.
            Route::get('/sessions/{sessionUuid}/chat', [SessionEngagementController::class, 'chatIndex']);
            Route::post('/sessions/{sessionUuid}/chat', [SessionEngagementController::class, 'chatSend']);
            Route::get('/sessions/{sessionUuid}/questions', [SessionEngagementController::class, 'questionIndex']);
            Route::post('/sessions/{sessionUuid}/questions', [SessionEngagementController::class, 'questionAsk']);
            Route::post('/sessions/{sessionUuid}/questions/{message}/upvote', [SessionEngagementController::class, 'questionUpvote']);
            // Reply to a question. Gated by the session's qa_answer_policy —
            // organizers only, organizers + speakers, or the whole room.
            Route::post('/sessions/{sessionUuid}/questions/{message}/replies', [SessionEngagementController::class, 'questionReply']);
            Route::get('/sessions/{sessionUuid}/polls', [SessionEngagementController::class, 'pollIndex']);
            Route::post('/sessions/{sessionUuid}/polls/{poll}/vote', [SessionEngagementController::class, 'pollVote']);
            Route::get('/sessions/{sessionUuid}/attendees', [SessionEngagementController::class, 'attendees']);
            // Embedded Jitsi join details — the host is issued a moderator JWT,
            // so the room starts without anyone logging in to meet.jit.si.
            Route::get('/sessions/{sessionUuid}/jitsi-token', [SessionEngagementController::class, 'jitsiToken']);
            // Embedded Agora broadcast — the host's token carries publish
            // privileges, an attendee's is join-only.
            Route::get('/sessions/{sessionUuid}/agora-token', [SessionEngagementController::class, 'agoraToken']);
            // Host moderation, live from the watch page. Every one of these
            // re-checks Session::isModeratedBy server-side — the client's
            // can_moderate flag only decides what UI to draw.
            Route::patch('/sessions/{sessionUuid}/messages/{message}', [SessionEngagementController::class, 'messageModerate']);
            Route::delete('/sessions/{sessionUuid}/messages/{message}', [SessionEngagementController::class, 'messageDestroy']);
            Route::post('/sessions/{sessionUuid}/polls', [SessionEngagementController::class, 'pollStore']);
            Route::patch('/sessions/{sessionUuid}/polls/{poll}', [SessionEngagementController::class, 'pollUpdate']);
            Route::delete('/sessions/{sessionUuid}/polls/{poll}', [SessionEngagementController::class, 'pollDestroy']);
            Route::post('/sessions/{sessionUuid}/mutes', [SessionEngagementController::class, 'muteStore']);
            Route::delete('/sessions/{sessionUuid}/mutes/{participation}', [SessionEngagementController::class, 'muteDestroy']);
            // One-to-one participant chat (attendee ↔ attendee/speaker/exhibitor).
            Route::get('/chat', [ChatController::class, 'index']);
            Route::get('/chat/partners', [ChatController::class, 'partners']);
            Route::post('/chat', [ChatController::class, 'open']);
            Route::get('/chat/{conversation}/messages', [ChatController::class, 'messages']);
            Route::post('/chat/{conversation}/messages', [ChatController::class, 'send']);
            Route::patch('/chat/{conversation}/read', [ChatController::class, 'read']);
            // Online-presence heartbeat (Redis TTL key; see PresenceController).
            Route::post('/presence', [PresenceController::class, 'ping']);
            // Cross-tab "save" bookmarks (speakers/sessions/delegates/exhibitors).
            Route::get('/bookmarks', [BookmarkController::class, 'index']);
            Route::post('/bookmarks', [BookmarkController::class, 'toggle']);
            // Personal notes jotted against a speaker/session/delegate.
            Route::get('/notes', [NoteController::class, 'index']);
            Route::post('/notes', [NoteController::class, 'save']);
            Route::delete('/notes/{type}/{targetId}', [NoteController::class, 'destroy']);
            // Personal "Briefcase" of saved files (exhibitor brochures, docs…).
            Route::get('/briefcase', [BriefcaseController::class, 'index']);
            Route::post('/briefcase', [BriefcaseController::class, 'store']);
            Route::delete('/briefcase/{item}', [BriefcaseController::class, 'destroy']);
            Route::get('/connections', [ConnectionController::class, 'index']);
            Route::post('/connections', [ConnectionController::class, 'store']);
            Route::patch('/connections/{connection}', [ConnectionController::class, 'respond']);
            Route::get('/meetings', [MeetingController::class, 'index']);
            Route::get('/lounge', [MeetingController::class, 'lounge']);
            Route::post('/meetings', [MeetingController::class, 'store']);
            Route::patch('/meetings/{meeting}', [MeetingController::class, 'respond']);
            // Join the live video room once a one-to-one meeting is confirmed and running.
            Route::post('/meetings/{meeting}/join', [MeetingController::class, 'join']);
            // Attendee → exhibitor "Contact" (Chat + Meet). Recipient is a booth;
            // the exhibitor admin later assigns a member (handled in the admin).
            Route::get('/exhibitor-conversations', [ExhibitorContactController::class, 'conversations']);
            Route::get('/exhibitors/{exhibitor}/thread', [ExhibitorContactController::class, 'thread']);
            Route::post('/exhibitors/{exhibitor}/messages', [ExhibitorContactController::class, 'sendMessage']);
            Route::get('/exhibitors/{exhibitor}/meeting-requests', [ExhibitorContactController::class, 'meetingRequests']);
            Route::post('/exhibitors/{exhibitor}/meeting-requests', [ExhibitorContactController::class, 'requestMeeting']);
            // Networking-lounge tables (live video tables + join).
            Route::get('/lounge/tables', [LoungeController::class, 'tables']);
            Route::post('/lounge/tables/{table}/join', [LoungeController::class, 'join']);
            // Attendee join: mint a media token for a published breakout room in
            // this event (role derived server-side; attendees are subscribe-only).
            Route::post('/breakout-rooms/{room}/token', [BreakoutRoomController::class, 'token']);
            Route::post('/track', [AnalyticsController::class, 'track']); // analytics fact
        });

        // ── Tenant-scoped (resolves org → sets RLS GUC → rate limit) ──
        Route::middleware(['tenant', 'throttle:api'])->group(function () {
            Route::get('/organization', [OrganizationController::class, 'current'])->middleware('perm:events.view');

            // Global icon catalog for icon-picker fields (e.g. Participate Profile).
            Route::get('/icons', [IconController::class, 'index']);

            // Image uploads → MinIO (event covers, etc.) under the tenant GUC.
            Route::post('/uploads', [FileUploadController::class, 'store'])->middleware('perm:events.view');

            // ── Organizer team self-service (§6.1) ──
            Route::get('/assignable-roles', [MembershipController::class, 'roles'])->middleware('perm:members.manage');
            Route::get('/members', [MembershipController::class, 'index'])->middleware('perm:members.manage');
            Route::post('/members', [MembershipController::class, 'store'])->middleware('perm:members.manage');
            Route::match(['put', 'patch'], '/members/{membership}', [MembershipController::class, 'update'])->middleware('perm:members.manage');
            Route::delete('/members/{membership}', [MembershipController::class, 'destroy'])->middleware('perm:members.manage');

            Route::get('/subscription', [SubscriptionController::class, 'current'])->middleware('perm:events.view');
            Route::get('/subscription/change-request', [SubscriptionController::class, 'changeRequest'])
                ->middleware('perm:events.view');
            Route::post('/subscription/change-request', [SubscriptionController::class, 'requestChange'])
                ->middleware('perm:settings.manage');
            Route::delete('/subscription/change-request', [SubscriptionController::class, 'cancelChangeRequest'])
                ->middleware('perm:settings.manage');

            // ── Events module (§6.3) ──
            Route::get('/events', [EventController::class, 'index'])->middleware('perm:events.view');
            Route::get('/events/{uuid}', [EventController::class, 'show'])->middleware('perm:events.view');
            Route::get('/events/{uuid}/agenda', [EventController::class, 'agenda'])->middleware('perm:events.view');
            Route::get('/events/{uuid}/overview', [EventController::class, 'overview'])->middleware('perm:events.view');
            Route::get('/events/{uuid}/settings', [EventController::class, 'showSettings'])->middleware('perm:events.view');
            Route::match(['put', 'patch'], '/events/{uuid}/settings', [EventController::class, 'updateSettings'])->middleware('perm:events.manage');

            // ── Domain settings (subdomain + custom domain DNS verification) ──
            Route::get('/events/{uuid}/domain', [DomainController::class, 'show'])->middleware('perm:events.view');
            Route::match(['put', 'patch'], '/events/{uuid}/domain', [DomainController::class, 'update'])->middleware('perm:events.manage');
            Route::post('/events/{uuid}/domain/verify', [DomainController::class, 'verify'])->middleware('perm:events.manage');
            // ── Event admins (Settings › Add event admin) — web-app access +
            //    session moderation, i.e. a participation with role=staff. ──
            Route::get('/events/{uuid}/admins', [EventAdminController::class, 'index'])->middleware('perm:events.view');
            Route::post('/events/{uuid}/admins', [EventAdminController::class, 'store'])->middleware('perm:events.manage');
            Route::delete('/events/{uuid}/admins/{participation}', [EventAdminController::class, 'destroy'])->middleware('perm:events.manage');
            // ── Video settings (the event's own Jitsi/JaaS signing credentials) ──
            Route::get('/events/{uuid}/video', [VideoSettingsController::class, 'show'])->middleware('perm:events.view');
            Route::match(['put', 'patch'], '/events/{uuid}/video', [VideoSettingsController::class, 'update'])->middleware('perm:events.manage');
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
                // Live poll authoring for a session (attendees vote from the watch page).
                Route::post('/sessions/{uuid}/polls', [SessionPollController::class, 'store']);
                Route::match(['put', 'patch'], '/session-polls/{poll}', [SessionPollController::class, 'update']);
                Route::delete('/session-polls/{poll}', [SessionPollController::class, 'destroy']);
                // Organizer-side moderation of what attendees posted in a session
                // (the host does the same live, from the watch page).
                Route::match(['put', 'patch'], '/session-messages/{message}', [SessionPollController::class, 'messageUpdate']);
                Route::delete('/session-messages/{message}', [SessionPollController::class, 'messageDestroy']);
                // Answer a question from the console — no need to join the room.
                Route::post('/session-messages/{message}/replies', [SessionPollController::class, 'messageReply']);
            });
            Route::get('/sessions/{uuid}/polls', [SessionPollController::class, 'index'])->middleware('perm:events.view');
            Route::get('/sessions/{uuid}/messages', [SessionPollController::class, 'messages'])->middleware('perm:events.view');
            Route::post('/sessions/{uuid}/speakers', [SessionController::class, 'addSpeaker'])->middleware('perm:speakers.manage');
            Route::delete('/sessions/{uuid}/speakers/{participation}', [SessionController::class, 'removeSpeaker'])->middleware('perm:speakers.manage');

            // Showcase speakers (event-level, not tied to a session)
            Route::middleware('perm:speakers.manage')->group(function () {
                Route::get('/events/{uuid}/speaker-categories', [SpeakerController::class, 'categories']);
                Route::post('/events/{uuid}/speaker-categories', [SpeakerController::class, 'storeCategory']);
                Route::match(['put', 'patch'], '/events/{uuid}/speaker-categories/{category}', [SpeakerController::class, 'updateCategory']);
                Route::delete('/events/{uuid}/speaker-categories/{category}', [SpeakerController::class, 'destroyCategory']);

                Route::get('/events/{uuid}/speakers', [SpeakerController::class, 'index']);
                Route::post('/events/{uuid}/speakers', [SpeakerController::class, 'store']);
                // "Previous speakers": re-seat someone who has spoken at one of
                // this organizer's earlier events. Declared ahead of the
                // /speakers/{participation} routes, which would swallow the path.
                Route::get('/events/{uuid}/speakers/importable', [SpeakerImportController::class, 'candidates']);
                Route::post('/events/{uuid}/speakers/import', [SpeakerImportController::class, 'store']);
                Route::match(['put', 'patch'], '/events/{uuid}/speakers/{participation}', [SpeakerController::class, 'update']);
                Route::delete('/events/{uuid}/speakers/{participation}', [SpeakerController::class, 'destroy']);
                // Give a speaker a login so they can sign in to the event site
                // and take the stage on their own session.
                Route::post('/events/{uuid}/speakers/{participation}/reset-password', [SpeakerController::class, 'resetPassword']);
            });

            // ── Lead Generation (Onsite): event-wide view over booth leads ──
            Route::get('/events/{uuid}/leads', [LeadGenerationController::class, 'index'])->middleware('perm:events.view');
            Route::post('/events/{uuid}/leads/export', [LeadGenerationController::class, 'export'])->middleware('perm:events.view');

            // ── Gates Scanning (Onsite): venue-gate analytics + gate CRUD ──
            Route::get('/events/{uuid}/gates', [GateScanningController::class, 'index'])->middleware('perm:events.view');
            Route::get('/events/{uuid}/gates/no-shows', [GateScanningController::class, 'noShows'])->middleware('perm:events.view');
            Route::post('/events/{uuid}/gates/no-shows/export', [GateScanningController::class, 'exportNoShows'])->middleware('perm:events.view');
            Route::post('/events/{uuid}/gates', [GateScanningController::class, 'store'])->middleware('perm:events.manage');
            Route::match(['put', 'patch'], '/events/{uuid}/gates/{station}', [GateScanningController::class, 'update'])->middleware('perm:events.manage');
            Route::delete('/events/{uuid}/gates/{station}', [GateScanningController::class, 'destroy'])->middleware('perm:events.manage');

            // ── Exhibitors Scanning (Onsite): booth footfall analytics + booth CRUD ──
            Route::get('/events/{uuid}/booths', [ExhibitorScanningController::class, 'index'])->middleware('perm:events.view');
            Route::post('/events/{uuid}/booths', [ExhibitorScanningController::class, 'store'])->middleware('perm:events.manage');
            Route::match(['put', 'patch'], '/events/{uuid}/booths/{station}', [ExhibitorScanningController::class, 'update'])->middleware('perm:events.manage');
            Route::delete('/events/{uuid}/booths/{station}', [ExhibitorScanningController::class, 'destroy'])->middleware('perm:events.manage');

            // ── Event advertisements (AD Managements) ──
            Route::get('/events/{uuid}/ads', [EventAdController::class, 'index'])->middleware('perm:events.view');
            Route::get('/events/{uuid}/ads/insights', [EventAdController::class, 'insights'])->middleware('perm:events.view');
            Route::post('/events/{uuid}/ads', [EventAdController::class, 'store'])->middleware('perm:events.manage');
            Route::post('/ads/{ad}/track', [EventAdController::class, 'track'])->middleware('perm:events.view');
            Route::get('/ads/{ad}', [EventAdController::class, 'show'])->middleware('perm:events.view');
            Route::match(['put', 'patch'], '/ads/{ad}', [EventAdController::class, 'update'])->middleware('perm:events.manage');
            Route::delete('/ads/{ad}', [EventAdController::class, 'destroy'])->middleware('perm:events.manage');

            // ── Breakout Rooms (Event Engagement) ──
            Route::get('/events/{uuid}/breakout-rooms', [BreakoutRoomController::class, 'index'])->middleware('perm:events.view');
            Route::post('/events/{uuid}/breakout-rooms', [BreakoutRoomController::class, 'store'])->middleware('perm:events.manage');
            Route::get('/breakout-rooms/{room}', [BreakoutRoomController::class, 'show'])->middleware('perm:events.view');
            Route::post('/breakout-rooms/{room}/token', [BreakoutRoomController::class, 'token'])->middleware('perm:events.view');
            Route::match(['put', 'patch'], '/breakout-rooms/{room}', [BreakoutRoomController::class, 'update'])->middleware('perm:events.manage');
            Route::post('/breakout-rooms/{room}/duplicate', [BreakoutRoomController::class, 'duplicate'])->middleware('perm:events.manage');
            Route::patch('/breakout-rooms/{room}/status', [BreakoutRoomController::class, 'setStatus'])->middleware('perm:events.manage');
            Route::delete('/breakout-rooms/{room}', [BreakoutRoomController::class, 'destroy'])->middleware('perm:events.manage');

            // ── Contests (Event Engagement) ──
            Route::get('/events/{uuid}/contests', [ContestController::class, 'index'])->middleware('perm:events.view');
            Route::post('/events/{uuid}/contests', [ContestController::class, 'store'])->middleware('perm:events.manage');
            Route::get('/contests/{contest}', [ContestController::class, 'show'])->middleware('perm:events.view');
            Route::match(['put', 'patch'], '/contests/{contest}', [ContestController::class, 'update'])->middleware('perm:events.manage');
            Route::delete('/contests/{contest}', [ContestController::class, 'destroy'])->middleware('perm:events.manage');
            // Attendee entries, for moderation and for picking the winners of
            // an `admin`-judged contest.
            Route::get('/contests/{contest}/entries', [ContestController::class, 'entries'])->middleware('perm:events.view');
            Route::patch('/contests/{contest}/entries/{entry}', [ContestController::class, 'updateEntry'])->middleware('perm:events.manage');
            Route::delete('/contests/{contest}/entries/{entry}', [ContestController::class, 'destroyEntry'])->middleware('perm:events.manage');

            // ── Surveys (Event Engagement) ──
            Route::get('/events/{uuid}/surveys', [SurveyController::class, 'index'])->middleware('perm:events.view');
            Route::post('/events/{uuid}/surveys', [SurveyController::class, 'store'])->middleware('perm:events.manage');
            Route::get('/surveys/{survey}', [SurveyController::class, 'show'])->middleware('perm:events.view');
            // Results: per-question roll-up + the individual attendee responses.
            Route::get('/surveys/{survey}/responses', [SurveyController::class, 'responses'])->middleware('perm:events.view');
            Route::match(['put', 'patch'], '/surveys/{survey}', [SurveyController::class, 'update'])->middleware('perm:events.manage');
            Route::delete('/surveys/{survey}', [SurveyController::class, 'destroy'])->middleware('perm:events.manage');

            // ── Activity Feed moderation (Event Engagement) ──
            Route::get('/events/{uuid}/feed-moderation', [FeedModerationController::class, 'index'])->middleware('perm:events.view');
            Route::patch('/events/{uuid}/feed-moderation/settings', [FeedModerationController::class, 'settings'])->middleware('perm:events.manage');
            Route::patch('/events/{uuid}/feed-moderation/{post}', [FeedModerationController::class, 'decide'])->middleware('perm:events.manage');

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
                // "Previous exhibitors": carry a company the organizer has run
                // before into the event they are building now. Declared ahead of
                // /exhibitors/{uuid}, which would otherwise swallow the path.
                Route::get('/exhibitors/importable', [ExhibitorImportController::class, 'candidates']);
                Route::post('/exhibitors/import', [ExhibitorImportController::class, 'store']);
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

            // ── Requested services: exhibitor orders the organizer reviews ──
            Route::get('/events/{uuid}/service-orders', [ServiceOrderController::class, 'index'])->middleware('perm:events.view');
            Route::get('/service-orders/{order}', [ServiceOrderController::class, 'show'])->middleware('perm:events.view');
            Route::get('/service-orders/{order}/pdf', [ServiceOrderController::class, 'pdf'])->middleware('perm:events.view');
            Route::patch('/service-orders/{order}', [ServiceOrderController::class, 'update'])->middleware('perm:events.manage');
            Route::patch('/service-requests/{line}', [ServiceOrderController::class, 'updateLine'])->middleware('perm:events.manage');

            // ── Floor plans (floor.expouse canvas editor) ──
            Route::get('/events/{uuid}/floors', [FloorController::class, 'index'])->middleware('perm:events.view');
            Route::post('/events/{uuid}/floors', [FloorController::class, 'store'])->middleware('perm:events.manage');
            Route::get('/floors/{floor}', [FloorController::class, 'show'])->middleware('perm:events.view');
            Route::match(['put', 'patch'], '/floors/{floor}', [FloorController::class, 'update'])->middleware('perm:events.manage');
            Route::delete('/floors/{floor}', [FloorController::class, 'destroy'])->middleware('perm:events.manage');

            // ── Badge designs (badge.expouse canvas editor) ──
            Route::get('/events/{uuid}/badge-designs/element-library', [BadgeDesignController::class, 'elementLibrary'])->middleware('perm:events.view');
            // Placeholder values for previewing a design with nobody behind it.
            Route::get('/events/{uuid}/badge-designs/sample-data', [BadgeDesignController::class, 'sampleData'])->middleware('perm:events.view');
            // One starter design per audience, for an event with none.
            Route::post('/events/{uuid}/badge-designs/seed-defaults', [BadgeDesignController::class, 'seedDefaults'])->middleware('perm:events.manage');
            Route::get('/events/{uuid}/badge-designs', [BadgeDesignController::class, 'index'])->middleware('perm:events.view');
            Route::post('/events/{uuid}/badge-designs', [BadgeDesignController::class, 'store'])->middleware('perm:events.manage');
            Route::get('/badge-designs/{badgeDesign}', [BadgeDesignController::class, 'show'])->middleware('perm:events.view');
            Route::match(['put', 'patch'], '/badge-designs/{badgeDesign}', [BadgeDesignController::class, 'update'])->middleware('perm:events.manage');
            Route::delete('/badge-designs/{badgeDesign}', [BadgeDesignController::class, 'destroy'])->middleware('perm:events.manage');

            // ── Guest badges (press / VVIP passes for people never registered) ──
            // A batch is a participation group; its guests are participations
            // with role=guest, so their QR works at the gates like anyone's.
            Route::get('/events/{uuid}/guest-badges', [GuestBadgeController::class, 'index'])->middleware('perm:events.view');
            Route::post('/events/{uuid}/guest-badges', [GuestBadgeController::class, 'store'])->middleware('perm:events.manage');
            Route::get('/guest-badges/{batch}', [GuestBadgeController::class, 'show'])->middleware('perm:events.view');
            Route::post('/guest-badges/{batch}/guests', [GuestBadgeController::class, 'importGuests'])->middleware('perm:events.manage');
            Route::post('/guest-badges/{batch}/deliver', [GuestBadgeController::class, 'deliver'])->middleware('perm:events.manage');
            Route::delete('/guest-badges/{batch}', [GuestBadgeController::class, 'destroy'])->middleware('perm:events.manage');

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

            // ── Event Settings › Profile: per-audience profile forms ──
            Route::middleware('perm:forms.manage')->group(function () {
                Route::get('/events/{uuid}/profile-forms', [ProfileFormController::class, 'index']);
                Route::get('/events/{uuid}/profile-forms/{audience}', [ProfileFormController::class, 'show']);
                Route::match(['put', 'patch'], '/events/{uuid}/profile-forms/{audience}', [ProfileFormController::class, 'update']);
                Route::post('/events/{uuid}/profile-forms/{audience}/publish', [ProfileFormController::class, 'publish']);
                Route::delete('/events/{uuid}/profile-forms/{audience}', [ProfileFormController::class, 'destroy']);
                Route::get('/events/{uuid}/profile-forms/{audience}/submissions', [ProfileFormController::class, 'submissions']);
                Route::post('/events/{uuid}/profile-forms/{audience}/submissions/export', [ProfileFormController::class, 'submissionsExport']);
                Route::get('/profile-submissions/{uuid}', [ProfileFormController::class, 'submissionShow']);
                Route::patch('/profile-submissions/{uuid}', [ProfileFormController::class, 'submissionReview']);
                Route::delete('/profile-submissions/{uuid}', [ProfileFormController::class, 'submissionDestroy']);
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
