import 'dart:io';

import 'package:dio/dio.dart';

import '../../utils/config/dio_config.dart';
import '../../utils/helpers/app_data_provider.dart';

class EventFeedService {
  final Dio _dio = DioConfig.obj.dio!;

  String get _eventUuid {
    final uuid = AppDataProvider.obj.eventUuid;
    if (uuid == null || uuid.isEmpty) {
      throw StateError('Event UUID is not set. Open Home first or re-login.');
    }
    return uuid;
  }

  String get _base => 'events/$_eventUuid';

  /// GET /events/{uuid}/feed — paginated posts (+ communication on additional).
  Future<Response> getEventFeed({
    int page = 1,
    String filter = 'all',
    String? q,
  }) async {
    final query = <String, dynamic>{'page': page};
    if (filter == 'mine') {
      query['mine'] = 1;
    } else if (filter.isNotEmpty && filter != 'all') {
      query['type'] = filter;
    }
    if (q != null && q.trim().isNotEmpty) {
      query['q'] = q.trim();
    }
    return await _dio.get('$_base/feed', queryParameters: query);
  }

  /// POST /events/{uuid}/feed — create a text / networking / poll / media post.
  Future<Response> createPost(Map<String, dynamic> body) async {
    return await _dio.post('$_base/feed', data: body);
  }

  /// POST /events/{uuid}/uploads — multipart feed media → public URL.
  Future<Response> uploadMedia(File file) async {
    final fileName = file.path.split(RegExp(r'[\\/]')).last;
    final formData = FormData.fromMap({
      'collection': 'feed',
      'file': await MultipartFile.fromFile(file.path, filename: fileName),
    });
    return await _dio.post(
      '$_base/uploads',
      data: formData,
      options: Options(contentType: 'multipart/form-data'),
    );
  }

  /// POST /events/{uuid}/feed/{post}/reactions — toggle like.
  Future<Response> toggleReaction(String postUuid) async {
    return await _dio.post(
      '$_base/feed/$postUuid/reactions',
      data: {'type': 'like'},
    );
  }

  /// GET /events/{uuid}/feed/{post}/comments
  Future<Response> getComments(String postUuid) async {
    return await _dio.get('$_base/feed/$postUuid/comments');
  }

  /// POST /events/{uuid}/feed/{post}/comments
  Future<Response> storeComment({
    required String postUuid,
    required String body,
  }) async {
    return await _dio.post(
      '$_base/feed/$postUuid/comments',
      data: {'body': body},
    );
  }

  /// POST /events/{uuid}/feed/{post}/poll/vote
  Future<Response> votePoll(String postUuid, String optionId) async {
    return await _dio.post(
      '$_base/feed/$postUuid/poll/vote',
      data: {'option_id': optionId},
    );
  }
}
