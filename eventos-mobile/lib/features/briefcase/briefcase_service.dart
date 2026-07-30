import 'package:dio/dio.dart';
import '../../utils/config/dio_config.dart';

class BriefcaseService {
  final Dio _dio = DioConfig.obj.dio!;

  /// Fetch all briefcase items
  Future<Response> getAllBriefcaseItems() async {
    return await _dio.post('mobile/briefcase/all');
  }
}
