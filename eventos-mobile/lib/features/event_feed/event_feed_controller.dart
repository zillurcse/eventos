import 'package:flutter/material.dart';
import 'package:get/get.dart';
import 'package:get_storage/get_storage.dart';

import '../../models/event_feed_model.dart';
import '../../models/mappers/feed_mapper.dart';
import '../../models/user.dart';
import '../../utils/enum/enums.dart';
import '../../utils/helpers/helper_functions.dart';
import '../../utils/helpers/local_key.dart';
import '../../utils/helpers/toast_msg.dart';
import 'event_feed_service.dart';

/// Owns the event-feed list state.
///
/// Follows the same focused pattern as [SpeakerController] and
/// [DelegateController]: data status + list + pagination + mutations.
/// Create-post / file-attach logic lives in [CreatePostController].
class EventFeedController extends GetxController {
  final _service = EventFeedService();

  // ── Feed state ─────────────────────────────────────────────────────────────
  final feedStatus = ApiState.initial.obs;
  final RxList<FeedPostModel> posts = <FeedPostModel>[].obs;
  final RxString activeFilter = 'all'.obs;
  final RxString searchKey = ''.obs;
  final TextEditingController searchController = TextEditingController();
  final RxInt currentPage = 1.obs;
  final RxBool hasNextPage = false.obs;
  final RxBool isLoadingMore = false.obs;
  final Set<int> _commentsLoaded = {};
  /// Post UUIDs reported this session - hide Report after success.
  final reportedPostUuids = <String>{}.obs;

  @override
  void onInit() {
    super.onInit();
    debounce(searchKey, (_) {
      currentPage.value = 1;
      posts.clear();
      fetchFeed();
    }, time: const Duration(milliseconds: 500));
  }

  @override
  void onClose() {
    searchController.dispose();
    super.onClose();
  }

  // ── Tab / permission config from API ───────────────────────────────────────
  final RxList<FeedTabModel> tabs = <FeedTabModel>[].obs;
  final RxList<String> permissions = <String>[].obs;

  // ── Filter / tab selection ─────────────────────────────────────────────────
  void selectFilter(String key) {
    if (activeFilter.value == key) return;
    activeFilter.value = key;
    currentPage.value = 1;
    posts.clear();
    fetchFeed();
  }

  void setSearchKey(String val) {
    searchKey.value = val;
  }

  void clearSearch() {
    searchController.clear();
    searchKey.value = '';
  }

  // ── Permission helpers ─────────────────────────────────────────────────────
  bool hasPermission(String permission) => permissions.contains(permission);

  FeedPostModel? _findPost(int postId) {
    final index = posts.indexWhere((p) => p.id == postId);
    if (index == -1) return null;
    return posts[index];
  }

  String? _uuidFor(int postId) => _findPost(postId)?.uuid;

  // ── API: initial / refresh load ────────────────────────────────────────────
  Future<void> fetchFeed() async {
    await handleApiClient(
      onStateChanged: (state) => feedStatus(state),
      handleApiCall: () async {
        final response = await _service.getEventFeed(
          page: 1,
          filter: activeFilter.value,
          q: searchKey.value,
        );
        if (response.data is Map) {
          final model = FeedMapper.pageFromV1(
            Map<String, dynamic>.from(response.data as Map),
          );
          posts.value = model.posts;
          currentPage.value = model.currentPage;
          hasNextPage.value = model.nextPageUrl != null;
          _commentsLoaded.clear();

          if (model.functionalityConfig != null) {
            tabs.value = model.functionalityConfig!.tabs
                .where((t) => t.status)
                .toList()
              ..sort((a, b) => a.order.compareTo(b.order));
            permissions.value = model.functionalityConfig!.permissions;
          }
        }
      },
    );
  }

  // ── API: load next page ────────────────────────────────────────────────────
  Future<void> loadMore() async {
    if (!hasNextPage.value || isLoadingMore.value) return;
    isLoadingMore.value = true;
    try {
      final nextPage = currentPage.value + 1;
      final response = await _service.getEventFeed(
        page: nextPage,
        filter: activeFilter.value,
        q: searchKey.value,
      );
      if (response.data is Map) {
        final model = FeedMapper.pageFromV1(
          Map<String, dynamic>.from(response.data as Map),
        );
        posts.addAll(model.posts);
        currentPage.value = model.currentPage;
        hasNextPage.value = model.nextPageUrl != null;
      }
    } finally {
      isLoadingMore.value = false;
    }
  }

  // ── Like / Dislike ─────────────────────────────────────────────────────────
  /// Optimistically toggles like state and rolls back on failure.
  Future<void> toggleLike(int postId) async {
    final index = posts.indexWhere((p) => p.id == postId);
    if (index == -1) return;

    final original = posts[index];
    final uuid = original.uuid;
    if (uuid.isEmpty) return;

    final nowLiked = !original.isLiked;
    final newLikeCount = nowLiked ? original.like + 1 : original.like - 1;
    final t = original.type;
    final reactionType = (t == 'looking_for' || t == 'looking-for' || t == 'offering')
        ? 'interested'
        : 'like';

    posts[index] =
        _copyPostWith(original, isLiked: nowLiked, like: newLikeCount);
    posts.refresh();

    try {
      final response = await _service.toggleReaction(uuid, type: reactionType);
      final data = response.data;
      if (data is Map) {
        posts[index] = _copyPostWith(
          posts[index],
          isLiked: data['reacted'] as bool? ?? nowLiked,
          like: (data['reactions'] as num?)?.toInt() ?? newLikeCount,
        );
        posts.refresh();
      }
    } catch (err) {
      posts[index] = original;
      posts.refresh();
      ToastMsg.showApiErrorMessage(err);
    }
  }

  // ── Comments ───────────────────────────────────────────────────────────────
  /// Lazily loads comments for a post (index payload does not embed them).
  Future<void> ensureCommentsLoaded(int postId) async {
    if (_commentsLoaded.contains(postId)) return;
    final index = posts.indexWhere((p) => p.id == postId);
    if (index == -1) return;
    final uuid = posts[index].uuid;
    if (uuid.isEmpty) return;

    try {
      final response = await _service.getComments(uuid);
      final data = response.data;
      if (data is! Map) return;
      final list = data['data'];
      if (list is! List) return;
      final comments = list
          .whereType<Map>()
          .map((e) =>
              FeedMapper.commentFromV1(Map<String, dynamic>.from(e)))
          .toList();
      posts[index] = _copyPostWith(posts[index], comments: comments);
      posts.refresh();
      _commentsLoaded.add(postId);
    } catch (_) {
      // Best-effort - comments stay empty until retry.
    }
  }

  /// Opens/closes the comment composer for a post and loads comments on open.
  void toggleComments(int postId) {
    final index = posts.indexWhere((p) => p.id == postId);
    if (index == -1) return;
    final next = !posts[index].commentOpen;
    posts[index] = _copyPostWith(posts[index], commentOpen: next);
    posts.refresh();
    if (next) ensureCommentsLoaded(postId);
  }

  /// Submits a comment (or reply) and injects it into the post's comment list.
  /// Returns true on success so the widget can clear its input.
  Future<bool> storeComment({
    required int postId,
    required String body,
    int? parentId,
  }) async {
    if (body.trim().isEmpty) return false;
    final uuid = _uuidFor(postId);
    if (uuid == null || uuid.isEmpty) return false;

    try {
      final response = await _service.storeComment(
        postUuid: uuid,
        body: body.trim(),
        parentId: parentId,
      );

      final index = posts.indexWhere((p) => p.id == postId);
      if (index != -1) {
        final original = posts[index];
        FeedCommentModel newComment;

        final data = response.data;
        if (data is Map && data['data'] is Map) {
          newComment = FeedMapper.commentFromV1(
            Map<String, dynamic>.from(data['data'] as Map),
          );
        } else if (data is Map && data.containsKey('id')) {
          newComment = FeedMapper.commentFromV1(
            Map<String, dynamic>.from(data),
          );
        } else {
          final rawUser = GetStorage().read(LocalKeyHelper.userInfo);
          final currentUser = rawUser is Map
              ? User.fromJson(Map<String, dynamic>.from(rawUser))
              : null;
          newComment = FeedCommentModel(
            id: DateTime.now().millisecondsSinceEpoch,
            parentId: parentId,
            body: body,
            user: FeedUserModel(
              id: currentUser?.id ?? 0,
              name: currentUser?.name ?? 'You',
              profilePhotoUrl: currentUser?.profilePhotoUrl ?? '',
            ),
            diff: DateTime.now(),
          );
        }

        final updatedComments =
            List<FeedCommentModel>.from(original.comments)..add(newComment);
        posts[index] = _copyPostWith(
          original,
          comments: updatedComments,
          commentOpen: true,
          commentCount: original.commentCount + 1,
        );
        posts.refresh();
        _commentsLoaded.add(postId);
      }
      return true;
    } catch (err) {
      ToastMsg.showApiErrorMessage(err);
      return false;
    }
  }

  // ── Poll: vote ─────────────────────────────────────────────────────────────
  /// Optimistically registers a vote then calls the API.
  Future<void> votePoll(int postId, int optionId) async {
    final index = posts.indexWhere((p) => p.id == postId);
    if (index == -1) return;

    final original = posts[index];
    final uuid = original.uuid;
    if (uuid.isEmpty) return;

    final option = original.options.firstWhereOrNull((o) => o.id == optionId);
    final optionUuid = option?.uuid ?? '';
    if (optionUuid.isEmpty) return;

    final optimisticOptions = original.options.map((opt) {
      if (opt.id == optionId) {
        return FeedPollOptionModel(
          id: opt.id,
          uuid: opt.uuid,
          option: opt.option,
          votes: opt.votes + 1,
        );
      }
      return opt;
    }).toList();

    posts[index] = _copyPostWith(
      original,
      voteByThisUser: true,
      myVote: optionId,
      totalVotes: (original.totalVotes ?? 0) + 1,
      options: optimisticOptions,
    );
    posts.refresh();

    try {
      final response = await _service.votePoll(uuid, optionUuid);
      final data = response.data;
      if (data is Map && data['data'] is Map) {
        final updated = FeedMapper.postFromV1(
          Map<String, dynamic>.from(data['data'] as Map),
        );
        // Preserve any already-loaded comments.
        posts[index] = _copyPostWith(
          updated,
          comments: original.comments,
        );
        posts.refresh();
      }
    } catch (err) {
      posts[index] = original;
      posts.refresh();
      ToastMsg.showApiErrorMessage(err);
    }
  }

  // ── Report ─────────────────────────────────────────────────────────────────
  bool canReport(FeedPostModel post) =>
      !post.isMine &&
      post.status == 'published' &&
      post.uuid.isNotEmpty &&
      !post.reportedByMe &&
      !reportedPostUuids.contains(post.uuid);

  /// Flags a post for organizer review. [reason] is inappropriate|irrelevant|spam.
  Future<bool> reportPost(int postId, String reason) async {
    final index = posts.indexWhere((p) => p.id == postId);
    if (index == -1) return false;
    final original = posts[index];
    final uuid = original.uuid;
    if (uuid.isEmpty || original.reportedByMe) return false;
    try {
      await _service.reportPost(uuid, reason);
      reportedPostUuids.add(uuid);
      reportedPostUuids.refresh();
      posts[index] = _copyPostWith(original, reportedByMe: true);
      posts.refresh();
      ToastMsg.showSuccessMessage('Thanks - we received your report.');
      return true;
    } catch (err) {
      ToastMsg.showApiErrorMessage(err);
      return false;
    }
  }

  // ── Private helpers ────────────────────────────────────────────────────────
  FeedPostModel _copyPostWith(
    FeedPostModel post, {
    bool? isLiked,
    int? like,
    List<FeedCommentModel>? comments,
    bool? commentOpen,
    int? commentCount,
    bool? voteByThisUser,
    int? myVote,
    int? totalVotes,
    List<FeedPollOptionModel>? options,
    bool? reportedByMe,
  }) =>
      FeedPostModel(
        id: post.id,
        uuid: post.uuid,
        body: post.body,
        userId: post.userId,
        like: like ?? post.like,
        attach: post.attach,
        attachUrl: post.attachUrl,
        attachType: post.attachType,
        attachPoster: post.attachPoster,
        question: post.question,
        type: post.type,
        isLive: post.isLive,
        isResultPublished: post.isResultPublished,
        isScheduled: post.isScheduled,
        scheduleAt: post.scheduleAt,
        pollEndAt: post.pollEndAt,
        status: post.status,
        user: post.user,
        createdAtTime: post.createdAtTime,
        createdAtDate: post.createdAtDate,
        isLiked: isLiked ?? post.isLiked,
        comments: comments ?? post.comments,
        commentOpen: commentOpen ?? post.commentOpen,
        commentCount: commentCount ??
            (comments != null ? comments.length : post.commentCount),
        isMine: post.isMine,
        reportedByMe: reportedByMe ?? post.reportedByMe,
        totalVotes: totalVotes ?? post.totalVotes,
        voteByThisUser: voteByThisUser ?? post.voteByThisUser,
        myVote: myVote ?? post.myVote,
        options: options ?? post.options,
      );
}
