<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\BlogPostResource;
use App\Models\BlogPost;
use App\Models\Event;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Per-event blog / news articles (Content Hub → Blog). Organizer-side CRUD.
 */
class BlogPostController extends Controller
{
    public function index(string $uuid): AnonymousResourceCollection
    {
        $event = Event::where('uuid', $uuid)->firstOrFail();

        $posts = BlogPost::with('coverFile')
            ->where('event_id', $event->id)
            ->latest('id')
            ->get();

        return BlogPostResource::collection($posts);
    }

    public function store(string $uuid, Request $request): JsonResponse
    {
        $event = Event::where('uuid', $uuid)->firstOrFail();
        $data = $this->validatePost($request, creating: true);

        $post = BlogPost::create([
            'event_id' => $event->id,
            'title' => $data['title'],
            'slug' => $this->uniqueSlug($data['title'], $event->id),
            'excerpt' => $data['excerpt'] ?? null,
            'body' => $data['body'] ?? null,
            'cover_file_id' => $data['cover_file_id'] ?? null,
            'status' => $data['status'] ?? 'draft',
            'published_at' => $this->resolvePublishedAt($data, null),
            'created_by' => $request->user()->id,
        ]);

        return response()->json(['data' => new BlogPostResource($post->load('coverFile'))], 201);
    }

    public function update(string $uuid, string $post, Request $request): JsonResponse
    {
        $event = Event::where('uuid', $uuid)->firstOrFail();
        $model = BlogPost::where('event_id', $event->id)->where('uuid', $post)->firstOrFail();
        $data = $this->validatePost($request, creating: false);

        if (array_key_exists('title', $data) && $data['title'] !== $model->title) {
            $data['slug'] = $this->uniqueSlug($data['title'], $event->id, $model->id);
        }

        $data['published_at'] = $this->resolvePublishedAt($data, $model);

        $model->update($data + ['updated_by' => $request->user()->id]);

        return response()->json(['data' => new BlogPostResource($model->fresh('coverFile'))]);
    }

    public function destroy(string $uuid, string $post): JsonResponse
    {
        $event = Event::where('uuid', $uuid)->firstOrFail();
        BlogPost::where('event_id', $event->id)->where('uuid', $post)->firstOrFail()->delete();

        return response()->json(['message' => 'Blog post deleted.']);
    }

    protected function validatePost(Request $request, bool $creating): array
    {
        $req = $creating ? 'required' : 'sometimes';

        return $request->validate([
            'title' => [$req, 'string', 'max:255'],
            'excerpt' => ['nullable', 'string', 'max:500'],
            'body' => ['nullable', 'string'],
            // RLS scopes `files` to the active org, so exists() also enforces ownership.
            'cover_file_id' => ['nullable', 'integer', 'exists:files,id'],
            'status' => ['sometimes', 'in:draft,published'],
            'published_at' => ['nullable', 'date'],
        ]);
    }

    /**
     * Stamp published_at when a post first goes live (and no explicit date was
     * given); clear it when it returns to draft.
     */
    protected function resolvePublishedAt(array $data, ?BlogPost $existing): ?Carbon
    {
        if (array_key_exists('published_at', $data) && $data['published_at']) {
            return Carbon::parse($data['published_at']);
        }

        $status = $data['status'] ?? $existing?->status ?? 'draft';

        if ($status === 'published') {
            return $existing?->published_at ?? now();
        }

        return null;
    }

    protected function uniqueSlug(string $title, int $eventId, ?int $ignoreId = null): string
    {
        $base = Str::slug($title) ?: 'post';
        $slug = $base;
        $i = 1;
        while (
            BlogPost::where('event_id', $eventId)
                ->where('slug', $slug)
                ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
                ->withTrashed()
                ->exists()
        ) {
            $slug = $base.'-'.(++$i);
        }

        return $slug;
    }
}
