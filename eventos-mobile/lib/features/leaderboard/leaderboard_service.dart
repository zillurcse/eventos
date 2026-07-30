import 'package:dio/dio.dart';
import '../../utils/config/dio_config.dart';

class LeaderboardService {
  final Dio _dio = DioConfig.obj.dio!;

  /// Fetch the leaderboard list for the current event.
  /// Token is automatically injected by DioConfig's interceptor.
  Future<Response> getLeaderboard() async {
    return await _dio.post('mobile/event/leaderboard');
  }
}
