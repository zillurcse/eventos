import 'package:dio/dio.dart';
import '../../utils/config/dio_config.dart';

class ProfileService {
  final Dio _dio = DioConfig.obj.dio!;

  /// Fetch the user profile
  Future<Response> getProfile() async {
    return await _dio.post('mobile/user/profile/show');
  }

  /// Update the user profile details
  Future<Response> updateProfile(Map<String, dynamic> data) async {
    return await _dio.post(
      'mobile/user/profile/update',
      data: data,
    );
  }

  /// Update the user profile photo
  Future<Response> updateProfilePhoto(String filePath) async {
    final formData = FormData.fromMap({
      'file': await MultipartFile.fromFile(filePath),
    });
    return await _dio.post(
      'mobile/user/profile/photo',
      data: formData,
    );
  }
}
