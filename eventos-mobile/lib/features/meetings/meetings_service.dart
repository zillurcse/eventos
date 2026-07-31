import 'package:dio/dio.dart';

import '../../utils/config/dio_config.dart';
import '../../utils/helpers/app_data_provider.dart';

class MeetingsService {
  final Dio _dio = DioConfig.obj.dio!;

  String get _eventUuid {
    final uuid = AppDataProvider.obj.eventUuid;
    if (uuid == null || uuid.isEmpty) {
      throw StateError('No event context — eventUuid missing');
    }
    return uuid;
  }

  String get _base => 'events/$_eventUuid';

  /// GET /events/{uuid}/meetings
  Future<Response> getMeetings() {
    return _dio.get('$_base/meetings');
  }

  /// GET /events/{uuid}/meetings/capabilities
  Future<Response> getCapabilities() {
    return _dio.get('$_base/meetings/capabilities');
  }

  /// GET /events/{uuid}/meetings/partners
  Future<Response> getPartners({String? q, String? role}) {
    return _dio.get(
      '$_base/meetings/partners',
      queryParameters: {
        if (q != null && q.trim().isNotEmpty) 'q': q.trim(),
        if (role != null && role.isNotEmpty) 'role': role,
      },
    );
  }

  /// GET /events/{uuid}/lounge?with={counterpart}
  Future<Response> getLoungeAvailability({String? withId}) {
    return _dio.get(
      '$_base/lounge',
      queryParameters: {
        if (withId != null && withId.isNotEmpty) 'with': withId,
      },
    );
  }

  /// POST /events/{uuid}/meetings
  Future<Response> createMeeting(Map<String, dynamic> body) {
    return _dio.post('$_base/meetings', data: body);
  }

  /// PATCH /events/{uuid}/meetings/{id}
  Future<Response> respond(String meetingId, String action) {
    return _dio.patch(
      '$_base/meetings/$meetingId',
      data: {'action': action},
    );
  }

  /// POST /events/{uuid}/meetings/{id}/join
  Future<Response> join(String meetingId) {
    return _dio.post('$_base/meetings/$meetingId/join');
  }

  /// GET /public/ads?page=meetings
  Future<Response> getAds() {
    return _dio.get('public/ads', queryParameters: {'page': 'meetings'});
  }
}
