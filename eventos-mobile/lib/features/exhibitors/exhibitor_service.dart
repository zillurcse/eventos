import 'package:dio/dio.dart';

import '../../utils/config/dio_config.dart';

class ExhibitorService {
  final Dio _dio = DioConfig.obj.dio!;

  /// GET /public/exhibitors
  Future<Response> getExhibitors() async {
    return await _dio.get('public/exhibitors');
  }

  /// GET /public/exhibitors/{uuid}
  Future<Response> getExhibitorDetails(String uuid) async {
    return await _dio.get('public/exhibitors/$uuid');
  }

  Future<Response> toggleExhibitorBookmark(int id) async {
    return Response(
      requestOptions: RequestOptions(path: 'bookmarks/exhibitor/$id'),
      statusCode: 501,
      data: {'status': 'error', 'message': 'Bookmarks not available yet'},
    );
  }
}
