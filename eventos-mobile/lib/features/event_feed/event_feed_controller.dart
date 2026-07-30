import 'package:flutter/material.dart';
import 'package:get/get.dart';
import 'package:get_storage/get_storage.dart';

import '../../models/event_feed_model.dart';
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

  // ── API: initial / refresh load ────────────────────────────────────────────
  Future<void> fetchFeed() async {
    await handleApiClient(
      onStateChanged: (state) => feedStatus(state),
      handleApiCall: () async {
        final response = await _service.getEventFeed(
          page: 1,
          filter: activeFilter.value,
          s: searchKey.value,
        );
        if (response.data is Map) {
          final model = EventFeedModel.fromJson(
            Map<String, dynamic>.from(response.data as Map),
          );
          posts.value = model.posts;
          currentPage.value = model.currentPage;
          hasNextPage.value = model.nextPageUrl != null;

          // Persist tab & permission config on first load
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
        s: searchKey.value,
      );
      if (response.data is Map) {
        final model = EventFeedModel.fromJson(
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
    final nowLiked = !original.isLiked;
    final newLikeCount = nowLiked ? original.like + 1 : original.like - 1;

    posts[index] = _copyPostWith(original, isLiked: nowLiked, like: newLikeCount);
    posts.refresh();

    try {
      if (nowLiked) {
        await _service.likePost(postId);
      } else {
        await _service.dislikePost(postId);
      }
    } catch (err) {
      posts[index] = original;
      posts.refresh();
      ToastMsg.showApiErrorMessage(err);
    }
  }

  // ── Comment ────────────────────────────────────────────────────────────────
  /// Submits a comment and injects it into the post's comment list.
  /// Returns true on success so the widget can clear its input.
  Future<bool> storeComment({
    required int postId,
    required String body,
  }) async {
    if (body.trim().isEmpty) return false;

    try {
      final response = await _service.storeComment(postId: postId, body: body);

      final index = posts.indexWhere((p) => p.id == postId);
      if (index != -1) {
        final original = posts[index];
        FeedCommentModel newComment;

        if (response.data is Map &&
            (response.data as Map).containsKey('id')) {
          newComment = FeedCommentModel.fromJson(
            Map<String, dynamic>.from(response.data as Map),
          );
        } else {
          final rawUser = GetStorage().read(LocalKeyHelper.userInfo);
          final currentUser = rawUser is Map
              ? User.fromJson(Map<String, dynamic>.from(rawUser))
              : null;
          newComment = FeedCommentModel(
            id: DateTime.now().millisecondsSinceEpoch,
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
        posts[index] = _copyPostWith(original, comments: updatedComments);
        posts.refresh();
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

    final optimisticOptions = original.options.map((opt) {
      if (opt.id == optionId) {
        return FeedPollOptionModel(
          id: opt.id,
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
      await _service.votePoll(postId, optionId);
      await refreshPollData(postId);
    } catch (err) {
      posts[index] = original;
      posts.refresh();
      ToastMsg.showApiErrorMessage(err);
    }
  }

  // ── Poll: refresh data ─────────────────────────────────────────────────────
  Future<void> refreshPollData(int postId) async {
    try {
      final response = await _service.getPollData(postId);
      if (response.data == null) return;

      final index = posts.indexWhere((p) => p.id == postId);
      if (index == -1) return;

      final Map<String, dynamic> raw = response.data is Map
          ? Map<String, dynamic>.from(response.data as Map)
          : <String, dynamic>{};

      List<FeedPollOptionModel>? updatedOptions;
      if (raw['options'] is List) {
        updatedOptions = (raw['options'] as List)
            .map((e) => FeedPollOptionModel.fromJson(
                Map<String, dynamic>.from(e as Map)))
            .toList();
      }

      posts[index] = _copyPostWith(
        posts[index],
        totalVotes: raw['total_votes'] as int? ?? posts[index].totalVotes,
        voteByThisUser:
            raw['vote_by_this_user'] as bool? ?? posts[index].voteByThisUser,
        myVote: raw['my_vote'] as int? ?? posts[index].myVote,
        options: updatedOptions ?? posts[index].options,
      );
      posts.refresh();
    } catch (_) {
      // Best-effort — silently ignore.
    }
  }

  // ── Private helpers ────────────────────────────────────────────────────────
  FeedPostModel _copyPostWith(
    FeedPostModel post, {
    bool? isLiked,
    int? like,
    List<FeedCommentModel>? comments,
    bool? voteByThisUser,
    int? myVote,
    int? totalVotes,
    List<FeedPollOptionModel>? options,
  }) =>
      FeedPostModel(
        id: post.id,
        body: post.body,
        userId: post.userId,
        like: like ?? post.like,
        attach: post.attach,
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
        commentOpen: post.commentOpen,
        totalVotes: totalVotes ?? post.totalVotes,
        voteByThisUser: voteByThisUser ?? post.voteByThisUser,
        myVote: myVote ?? post.myVote,
        options: options ?? post.options,
      );
}
