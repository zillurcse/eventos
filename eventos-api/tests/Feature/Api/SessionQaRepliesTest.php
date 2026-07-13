<?php

namespace Tests\Feature\Api;

use App\Models\Contact;
use App\Models\Participation;
use App\Models\Session;
use App\Models\SessionMessage;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Q&A replies: who is allowed to answer a question, and what an answer does.
 *
 * The organizer sets the policy per session (qa_answer_policy). The interesting
 * cases are the boundaries — a plain attendee under the default policy must be
 * refused, and the same attendee must be allowed once the organizer opens it up.
 *
 * No DatabaseTransactions here, unlike the rest of the suite: the attendee
 * endpoints run behind ResolveParticipant, which resolves the event and the
 * caller's participation on the *admin* connection — it cannot see rows still
 * uncommitted on the tenant connection, and every participant route would 404.
 * So the fixtures are committed and torn down explicitly; deleting the event
 * cascades the session, its messages and the participations.
 */
class SessionQaRepliesTest extends TestCase
{
    private string $eventUuid;

    private string $sessionUuid;

    private Session $session;

    /** @var list<int> users to remove in tearDown (they outlive the event). */
    private array $userIds = [];

    /** @var list<int> */
    private array $contactIds = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAsOrganizer();
        $this->eventUuid = $this->createEvent()['id'];

        $this->sessionUuid = $this->postJson('/api/v1/sessions', [
            'event' => $this->eventUuid,
            'title' => 'Q&A Test Session',
            'starts_at' => now()->addWeek()->toIso8601String(),
            'ends_at' => now()->addWeek()->addHour()->toIso8601String(),
        ])->assertCreated()->json('data.id');

        $this->session = Session::where('uuid', $this->sessionUuid)->firstOrFail();
    }

    protected function tearDown(): void
    {
        $admin = DB::connection(self::ADMIN_CONN);
        $admin->table('events')->where('uuid', $this->eventUuid)->delete();
        if ($this->contactIds) {
            $admin->table('contacts')->whereIn('id', $this->contactIds)->delete();
        }
        if ($this->userIds) {
            $admin->table('users')->whereIn('id', $this->userIds)->delete();
        }

        parent::tearDown();
    }

    public function test_organizer_answers_a_question_from_the_console(): void
    {
        $question = $this->question($this->attendee()[1]);

        $this->postJson("/api/v1/session-messages/{$question->id}/replies", [
            'body' => 'We ship it next quarter.',
        ])
            ->assertCreated()
            ->assertJsonPath('data.kind', 'answer')
            ->assertJsonPath('data.author_role', 'organizer')
            ->assertJsonPath('data.is_official', true);

        // An answer from the event closes the question out.
        $this->assertTrue($question->fresh()->is_answered);

        // …and reads back threaded under it, not as a sibling row.
        $this->getJson("/api/v1/sessions/{$this->sessionUuid}/messages?kind=question")
            ->assertOk()
            ->assertJsonPath('data.0.id', $question->id)
            ->assertJsonPath('data.0.replies.0.body', 'We ship it next quarter.')
            ->assertJsonPath('data.0.replies.0.author_role', 'organizer');
    }

    public function test_speaker_answers_from_the_watch_page_under_the_default_policy(): void
    {
        [$speakerUser, $speaker] = $this->attendee();
        $this->session->speakers()->attach($speaker->id, ['role' => 'speaker']);

        $question = $this->question($this->attendee()[1]);

        Sanctum::actingAs($speakerUser);

        $this->postJson(
            "/api/v1/events/{$this->eventUuid}/sessions/{$this->sessionUuid}/questions/{$question->id}/replies",
            ['body' => 'Great question — right after this slide.'],
        )
            ->assertCreated()
            ->assertJsonPath('data.author_role', 'speaker')
            ->assertJsonPath('data.is_official', true);

        // The person on stage answering *is* the answer.
        $this->assertTrue($question->fresh()->is_answered);
    }

    public function test_attendee_cannot_reply_under_the_default_policy(): void
    {
        [$user, $participation] = $this->attendee();
        $question = $this->question($participation);

        Sanctum::actingAs($user);

        $this->postJson(
            "/api/v1/events/{$this->eventUuid}/sessions/{$this->sessionUuid}/questions/{$question->id}/replies",
            ['body' => 'I think I know this one.'],
        )->assertForbidden();

        $this->assertSame(0, SessionMessage::where('parent_id', $question->id)->count());
    }

    public function test_attendee_can_reply_once_the_organizer_opens_it_up(): void
    {
        [$user, $participation] = $this->attendee();
        $question = $this->question($participation);

        $this->setPolicy(Session::QA_ANSWER_EVERYONE);

        Sanctum::actingAs($user);

        $this->postJson(
            "/api/v1/events/{$this->eventUuid}/sessions/{$this->sessionUuid}/questions/{$question->id}/replies",
            ['body' => 'I think I know this one.'],
        )
            ->assertCreated()
            ->assertJsonPath('data.author_role', 'attendee')
            // One attendee helping another is not the event's answer.
            ->assertJsonPath('data.is_official', false);

        $this->assertFalse($question->fresh()->is_answered);

        // The asker sees it in the thread on the watch page.
        $this->getJson("/api/v1/events/{$this->eventUuid}/sessions/{$this->sessionUuid}/questions")
            ->assertOk()
            ->assertJsonPath('data.0.replies.0.body', 'I think I know this one.')
            ->assertJsonPath('meta.can_answer', true)
            ->assertJsonPath('meta.qa_answer_policy', 'everyone');
    }

    public function test_attendee_reply_waits_for_approval_when_qa_is_pre_moderated(): void
    {
        [$user, $participation] = $this->attendee();
        $question = $this->question($participation);

        $this->setPolicy(Session::QA_ANSWER_EVERYONE, moderated: true);

        Sanctum::actingAs($user);

        $this->postJson(
            "/api/v1/events/{$this->eventUuid}/sessions/{$this->sessionUuid}/questions/{$question->id}/replies",
            ['body' => 'Pretty sure it is 42.'],
        )
            ->assertCreated()
            ->assertJsonPath('data.status', 'pending');
    }

    public function test_deleting_a_question_takes_its_replies_with_it(): void
    {
        $question = $this->question($this->attendee()[1]);

        $this->postJson("/api/v1/session-messages/{$question->id}/replies", ['body' => 'Answered.'])
            ->assertCreated();

        $reply = SessionMessage::where('parent_id', $question->id)->firstOrFail();

        $this->deleteJson("/api/v1/session-messages/{$question->id}")->assertOk();

        // Soft delete: the FK cascade doesn't fire, so the reply must be taken
        // down explicitly or it outlives the question it answers.
        $this->assertNull(SessionMessage::find($reply->id));
    }

    // ── Fixtures ────────────────────────────────────────────────────────────
    /** The organizer's session setting, through the API they actually use. */
    private function setPolicy(string $policy, bool $moderated = false): void
    {
        $this->actingAsOrganizer();

        $this->patchJson("/api/v1/sessions/{$this->sessionUuid}/stream", [
            'can_qa' => true,
            'qa_answer_policy' => $policy,
            'qa_moderation' => $moderated,
        ])
            ->assertOk()
            ->assertJsonPath('data.qa_answer_policy', $policy);

        $this->session->refresh();
    }

    /** A signed-in attendee participating in the event. */
    private function attendee(): array
    {
        $user = (new User)->setConnection(self::ADMIN_CONN);
        $user->forceFill([
            'name' => 'Ada Attendee',
            'email' => 'attendee-'.uniqid().'@example.test',
            'password' => 'password',
            'email_verified_at' => now(),
        ])->save();

        $contact = Contact::create([
            'user_id' => $user->id,
            'email' => $user->email,
            'first_name' => 'Ada',
            'last_name' => 'Attendee',
        ]);

        $participation = Participation::create([
            'event_id' => $this->session->event_id,
            'contact_id' => $contact->id,
            'role' => 'attendee',
            'status' => 'confirmed',
        ]);

        $this->userIds[] = $user->id;
        $this->contactIds[] = $contact->id;

        return [$user, $participation];
    }

    private function question(Participation $asker): SessionMessage
    {
        return SessionMessage::create([
            'event_id' => $this->session->event_id,
            'session_id' => $this->session->id,
            'participation_id' => $asker->id,
            'kind' => SessionMessage::KIND_QUESTION,
            'author_role' => SessionMessage::ROLE_ATTENDEE,
            'status' => SessionMessage::STATUS_PUBLISHED,
            'body' => 'When does the API ship?',
        ]);
    }
}
