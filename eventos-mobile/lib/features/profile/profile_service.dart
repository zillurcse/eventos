import 'package:dio/dio.dart';
import '../../utils/config/dio_config.dart';
import '../../utils/helpers/app_data_provider.dart';

class ProfileService {
  final Dio _dio = DioConfig.obj.dio!;

  String get _eventUuid {
    final uuid = AppDataProvider.obj.eventUuid;
    if (uuid == null || uuid.isEmpty) {
      throw StateError('No event context - eventUuid missing');
    }
    return uuid;
  }

  String get _base => 'events/$_eventUuid';

  /// GET /events/{event}/profile
  Future<Response> getProfile() async {
    return await _dio.get('$_base/profile');
  }

  /// PUT /events/{event}/profile
  Future<Response> updateProfile(Map<String, dynamic> data) async {
    return await _dio.put('$_base/profile', data: data);
  }

  /// PUT /events/{event}/profile with a remote avatar URL after upload.
  Future<Response> updateProfilePhoto(String filePath) async {
    final upload = await _dio.post(
      '$_base/uploads',
      data: FormData.fromMap({
        'file': await MultipartFile.fromFile(filePath),
        'collection': 'avatar',
      }),
    );
    final body = upload.data;
    final file = body is Map ? body['data'] ?? body : null;
    final avatarUrl = file is Map ? file['url']?.toString() : null;
    final avatarFileId = file is Map ? file['id'] : null;

    return await _dio.put(
      '$_base/profile',
      data: {
        if (avatarUrl != null && avatarUrl.isNotEmpty) 'avatar_url': avatarUrl,
        if (avatarFileId != null) 'avatar_file_id': avatarFileId,
      },
    );
  }
}
