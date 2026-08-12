import '../event_feed_model.dart';
import '../../utils/helpers/type_helper.dart';

/// Maps EventOS `GET/POST /events/{uuid}/feed*` into existing feed UI models.
class FeedMapper {
  FeedMapper._();

  /// Laravel paginated feed response → [EventFeedModel].
  static EventFeedModel pageFromV1(Map<String, dynamic> body) {
    final postsRaw = body['data'];
    final posts = (postsRaw is List ? postsRaw : const [])
        .whereType<Map>()
        .map((e) => postFromV1(Map<String, dynamic>.from(e)))
        .toList();

    final meta = body['meta'] is Map
        ? Map<String, dynamic>.from(body['meta'] as Map)
        : <String, dynamic>{};
    final currentPage = TypeHelper.toInt(meta['current_page'] ?? 1);
    final lastPage = TypeHelper.toInt(meta['last_page'] ?? currentPage);
    final hasMore = currentPage < lastPage;

    return EventFeedModel(
      posts: posts,
      currentPage: currentPage > 0 ? currentPage : 1,
      nextPageUrl: hasMore ? 'page=${currentPage + 1}' : null,
      functionalityConfig: communicationFromV1(
        body['communication'] is Map
            ? Map<String, dynamic>.from(body['communication'] as Map)
            : null,
      ),
    );
  }

  static FeedPostModel postFromV1(Map<String, dynamic> json) {
    final uuid = (json['id'] ?? '').toString();
    final apiType = (json['type'] ?? 'text').toString();
    final attachments = (json['attachments'] as List? ?? [])
        .whereType<Map>()
        .map((e) => Map<String, dynamic>.from(e))
        .toList();
    final firstAttach = attachments.isNotEmpty ? attachments.first : null;
    final attachKind = firstAttach?['kind']?.toString();
    final attachUrl = firstAttach?['url']?.toString();
    final attachPoster = firstAttach?['poster']?.toString();

    final poll = json['poll'] is Map
        ? Map<String, dynamic>.from(json['poll'] as Map)
        : null;
    final options = <FeedPollOptionModel>[];
    int? myVote;
    if (poll != null) {
      for (final o in (poll['options'] as List? ?? [])) {
        if (o is! Map) continue;
        final m = Map<String, dynamic>.from(o);
        final optUuid = (m['id'] ?? '').toString();
        options.add(
          FeedPollOptionModel(
            id: TypeHelper.toInt(optUuid),
            uuid: optUuid,
            option: (m['text'] ?? m['option'] ?? '').toString(),
            votes: TypeHelper.toInt(m['votes']),
          ),
        );
      }
      final votes = poll['my_vote'];
      if (votes is List && votes.isNotEmpty) {
        myVote = TypeHelper.toInt(votes.first);
      }
    }

    final createdAt = (DateTime.tryParse(json['created_at']?.toString() ?? '') ??
            DateTime.now())
        .toLocal();

    // Keep legacy UI type keys used by feed_post.dart / chips.
    final uiType = switch (apiType) {
      'text' || 'image' => 'post',
      'looking_for' => 'looking-for',
      _ => apiType,
    };

    return FeedPostModel(
      id: TypeHelper.toInt(uuid),
      uuid: uuid,
      body: json['body']?.toString(),
      userId: 0,
      like: TypeHelper.toInt(json['reaction_count']),
      attach: attachUrl,
      attachUrl: attachUrl,
      attachType: attachKind,
      attachPoster: attachPoster,
      question: apiType == 'poll' ? json['body']?.toString() : null,
      type: uiType,
      isLive: false,
      isResultPublished: true,
      isScheduled: false,
      status: (json['status'] ?? '').toString(),
      user: FeedUserModel(
        id: 0,
        name: (json['author'] ?? '').toString(),
        profilePhotoUrl: (json['author_avatar'] ?? '').toString(),
      ),
      createdAtTime: createdAt,
      createdAtDate: createdAt,
      isLiked: TypeHelper.toBool(json['reacted']),
      comments: const [],
      commentOpen: false,
      commentCount: TypeHelper.toInt(json['comment_count']),
      isMine: TypeHelper.toBool(json['is_mine']),
      reportedByMe: TypeHelper.toBool(json['reported_by_me']),
      totalVotes: poll != null ? TypeHelper.toInt(poll['total_votes']) : null,
      voteByThisUser: myVote != null,
      myVote: myVote,
      options: options,
    );
  }

  static FeedCommentModel commentFromV1(Map<String, dynamic> json) {
    final created =
        DateTime.tryParse(json['created_at']?.toString() ?? '') ??
            DateTime.now();
    final parentRaw = json['parent_id'];
    return FeedCommentModel(
      id: TypeHelper.toInt(json['id']),
      parentId: parentRaw == null ? null : TypeHelper.toInt(parentRaw),
      body: (json['body'] ?? '').toString(),
      user: FeedUserModel(
        id: 0,
        name: (json['author'] ?? '').toString(),
        profilePhotoUrl: (json['author_avatar'] ?? '').toString(),
      ),
      diff: created.toLocal(),
    );
  }

  /// Map `communication.operations` + `feed_tabs` → legacy functionality config.
  static FeedFunctionalityConfig? communicationFromV1(
    Map<String, dynamic>? communication,
  ) {
    if (communication == null) return null;

    final ops = communication['operations'] is Map
        ? Map<String, dynamic>.from(communication['operations'] as Map)
        : <String, dynamic>{};
    final permissions = ops.entries
        .where((e) => TypeHelper.toBool(e.value))
        .map((e) => e.key)
        .toList();

    final tabsRaw = communication['feed_tabs'];
    final tabs = <FeedTabModel>[];
    if (tabsRaw is List) {
      var order = 0;
      for (final t in tabsRaw) {
        if (t is! Map) continue;
        final m = Map<String, dynamic>.from(t);
        final key = (m['key'] ?? '').toString();
        if (key.isEmpty) continue;
        final label = (m['label'] ?? key).toString();
        tabs.add(
          FeedTabModel(
            name: label,
            key: key,
            icon: '',
            changedName: label,
            order: order++,
            status: true,
          ),
        );
      }
    }

    // Default filter tabs when organizer left feed_tabs empty.
    if (tabs.isEmpty) {
      const defaults = [
        ('all', 'All'),
        ('image', 'Photos'),
        ('video', 'Video'),
        ('pdf', 'PDF'),
        ('poll', 'Polls'),
        ('offering', 'Offering'),
        ('looking_for', 'Looking For'),
        ('mine', 'My Posts'),
      ];
      for (var i = 0; i < defaults.length; i++) {
        tabs.add(
          FeedTabModel(
            name: defaults[i].$2,
            key: defaults[i].$1,
            icon: '',
            changedName: defaults[i].$2,
            order: i,
            status: true,
          ),
        );
      }
    }

    return FeedFunctionalityConfig(permissions: permissions, tabs: tabs);
  }
}
