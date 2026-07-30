import 'dart:io';
import 'package:dio/dio.dart';
import '../../utils/config/dio_config.dart';

class EventFeedService {
  final Dio _dio = DioConfig.obj.dio!;

  /// Fetch paginated event feed posts.
  Future<Response> getEventFeed({int page = 1, String filter = 'all', String? s}) async {
    final Map<String, dynamic> data = {'page': page, 'filter': filter};
    if (s != null && s.isNotEmpty) {
      data['s'] = s;
    }
    return await _dio.post('mobile/event/feed', data: data);
  }

  /// Create a regular post (type: post, offering, looking-for).
  Future<Response> storePost({
    required String body,
    required String type, // 'post' | 'offering' | 'looking-for'
  }) async {
    return await _dio.post(
      'mobile/post/store',
      data: {'body': body, 'type': type, 'from': 'feed'},
    );
  }

  /// Create a post with a file attachment sent as multipart/form-data.
  ///
  /// [postType]   — `'post'` for images, `'video'` for videos, `'pdf'` for PDFs.
  /// [attachType] — `'image'`, `'video'`, or `'pdf'`.
  /// [file]       — the actual [File] to upload.
  Future<Response> storePostWithAttach({
    required String body,
    required String postType,   // 'post' | 'video' | 'pdf'
    required String attachType, // 'image' | 'video' | 'pdf'
    required File file,
  }) async {
    final fileName = file.path.split('/').last;
    final formData = FormData.fromMap({
      'body': body,
      'type': postType,
      'from': 'feed',
      'attach_type': attachType,
      'attach': await MultipartFile.fromFile(
        file.path,
        filename: fileName,
      ),
    });
    return await _dio.post(
      'mobile/post/store',
      data: formData,
      options: Options(
        contentType: 'multipart/form-data',
      ),
    );
  }

  /// Create a poll post.
  Future<Response> storePoll({
    required String question,
    required List<String> options, // will be joined as comma-separated string
    required int lengthDays,
    required int lengthHours,
    required int lengthMinutes,
  }) async {
    return await _dio.post(
      'mobile/post/store',
      data: {
        'question': question,
        'type': 'poll',
        'from': 'feed',
        'options': options.join(','),
        'length_days': lengthDays,
        'length_hours': lengthHours,
        'length_minutes': lengthMinutes,
      },
    );
  }

  /// Like a post by [postId].
  Future<Response> likePost(int postId) async {
    return await _dio.post('mobile/post/like/plus/$postId');
  }

  /// Remove like (dislike) from a post by [postId].
  Future<Response> dislikePost(int postId) async {
    return await _dio.post('mobile/post/like/minus/$postId');
  }

  /// Post a comment on a post.
  /// [postId] – target post, [body] – comment text.
  Future<Response> storeComment({
    required int postId,
    required String body,
  }) async {
    return await _dio.post(
      'mobile/comments/store',
      data: {
        'post': postId,
        'body': body,
      },
    );
  }

  /// Vote on a poll option.
  /// [postId] – the poll post, [optionId] – the selected option id.
  Future<Response> votePoll(int postId, int optionId) async {
    return await _dio.post('mobile/post/poll/vote/$postId/$optionId');
  }

  /// Fetch the latest poll data (votes, results) for a post.
  Future<Response> getPollData(int postId) async {
    return await _dio.get('mobile/post/poll/data/$postId');
  }
}
