import 'package:dio/dio.dart';

import '../../utils/config/dio_config.dart';
import '../../utils/helpers/app_data_provider.dart';

class LeaderboardService {
  final Dio _dio = DioConfig.obj.dio!;

  String get _eventUuid {
    final uuid = AppDataProvider.obj.eventUuid;
    if (uuid == null || uuid.isEmpty) {
      throw StateError('No event context - eventUuid missing');
    }
    return uuid;
  }

  /// GET /events/{uuid}/my/gamification - top 20 + caller's points.
  Future<Response> getLeaderboard() async {
    return await _dio.get('events/$_eventUuid/my/gamification');
  }
}
