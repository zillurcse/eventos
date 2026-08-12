import 'dart:io';

import 'package:dio/dio.dart';

import '../../utils/config/dio_config.dart';
import '../../utils/helpers/app_data_provider.dart';

class ContestsService {
  final Dio _dio = DioConfig.obj.dio!;

  String get _eventUuid {
    final uuid = AppDataProvider.obj.eventUuid;
    if (uuid == null || uuid.isEmpty) {
      throw StateError('No event context - eventUuid missing');
    }
    return uuid;
  }

  String get _base => 'events/$_eventUuid';

  /// GET /events/{uuid}/my/contests
  Future<Response> getContests() {
    return _dio.get('$_base/my/contests');
  }

  /// GET /events/{uuid}/my/contests/{contest}
  Future<Response> getContest(String contestId) {
    return _dio.get('$_base/my/contests/$contestId');
  }

  /// GET /events/{uuid}/my/contests/{contest}/entries
  Future<Response> getEntries(
    String contestId, {
    String sort = 'recent',
    bool mineOnly = false,
  }) {
    return _dio.get(
      '$_base/my/contests/$contestId/entries',
      queryParameters: {
        'sort': sort,
        'mine': mineOnly ? 1 : 0,
      },
    );
  }

  /// POST /events/{uuid}/my/contests/{contest}/entries
  Future<Response> submitEntry(
    String contestId, {
    String? body,
    List<Map<String, dynamic>>? attachments,
  }) {
    return _dio.post(
      '$_base/my/contests/$contestId/entries',
      data: {
        if (body != null && body.isNotEmpty) 'body': body,
        if (attachments != null && attachments.isNotEmpty)
          'attachments': attachments,
      },
    );
  }

  /// DELETE /events/{uuid}/contest-entries/{entry}
  Future<Response> removeEntry(String entryId) {
    return _dio.delete('$_base/contest-entries/$entryId');
  }

  /// POST /events/{uuid}/contest-entries/{entry}/like
  Future<Response> toggleLike(String entryId) {
    return _dio.post('$_base/contest-entries/$entryId/like');
  }

  /// GET /events/{uuid}/contest-entries/{entry}/comments
  Future<Response> getComments(String entryId) {
    return _dio.get('$_base/contest-entries/$entryId/comments');
  }

  /// POST /events/{uuid}/contest-entries/{entry}/comments
  Future<Response> addComment(String entryId, String body) {
    return _dio.post(
      '$_base/contest-entries/$entryId/comments',
      data: {'body': body},
    );
  }

  /// POST /events/{uuid}/uploads - contest entry media.
  Future<Response> uploadMedia(File file) async {
    final fileName = file.path.split(RegExp(r'[\\/]')).last;
    final formData = FormData.fromMap({
      'collection': 'contest_entry',
      'file': await MultipartFile.fromFile(file.path, filename: fileName),
    });
    // Never set Content-Type to a bare `multipart/form-data` - Dio must add the boundary.
    return _dio.post(
      '$_base/uploads',
      data: formData,
      options: Options(
        sendTimeout: const Duration(minutes: 5),
        receiveTimeout: const Duration(minutes: 5),
      ),
    );
  }

  /// GET /public/ads?page=contests
  Future<Response> getAds() {
    return _dio.get('public/ads', queryParameters: {'page': 'contests'});
  }
}
