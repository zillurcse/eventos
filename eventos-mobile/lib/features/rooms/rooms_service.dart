import 'package:dio/dio.dart';

import '../../utils/config/dio_config.dart';
import '../../utils/helpers/app_data_provider.dart';

class RoomsService {
  final Dio _dio = DioConfig.obj.dio!;

  String get _eventUuid {
    final uuid = AppDataProvider.obj.eventUuid;
    if (uuid == null || uuid.isEmpty) {
      throw StateError('No event context - eventUuid missing');
    }
    return uuid;
  }

  /// GET /public/rooms
  Future<Response> getRooms() {
    return _dio.get('public/rooms');
  }

  /// POST /events/{event}/breakout-rooms/{room}/token
  Future<Response> joinRoom({
    required int roomId,
    String? accessCode,
  }) {
    return _dio.post(
      'events/$_eventUuid/breakout-rooms/$roomId/token',
      data: {
        if (accessCode != null && accessCode.isNotEmpty)
          'access_code': accessCode,
      },
    );
  }
}
