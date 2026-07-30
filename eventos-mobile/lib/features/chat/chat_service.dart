import 'package:dio/dio.dart';
import '../../utils/config/dio_config.dart';

class ChatService {
  final dio = DioConfig.obj.dio!;

  /// Send a chat message, optionally with a base64-encoded file attachment.
  ///
  /// [fileBase64] — base64 string of the file bytes.
  /// [attachType] — one of `"image"`, `"video"`, or `"pdf"`.
  Future<Response> sendMessage({
    required int roomId,
    String? message,
    String? attachType,
    String? fileBase64,
  }) async {
    return await dio.post(
      "mobile/user/room/messageStore",
      data: {
        "room_id": roomId,
        "context": message ?? "",
        "type": "user_room",
        if (attachType != null) "attach_type": attachType,
        if (fileBase64 != null) "file": fileBase64,
      },
    );
  }

  Future<Response> getChatRooms({String? search}) async {
    return await dio.post(
      "mobile/user/rooms",
      data: search != null && search.isNotEmpty ? {"search": search} : null,
    );
  }

  Future<Response> getRoomDetails(int targetUserId) async {
    return await dio.post("mobile/user/roomWith/$targetUserId");
  }
}
