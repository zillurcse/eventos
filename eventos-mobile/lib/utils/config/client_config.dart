
import 'package:expouse/utils/config/dio_config.dart';

class ClientConfig {
  ClientConfig._();
  static ClientConfig obj = ClientConfig._();
  factory ClientConfig() => obj;

  static final client = DioConfig.obj.dio!;

  static Future<ResponseData?> get<ResponseData, RequestData>(
    String url, {
    RequestData? query,
  }) async {
    final response = await client.get(
      url,
      queryParameters: query != null ? (query as dynamic).toJson() : null,
    );

    if (ResponseData == Null) return null;
    return (ResponseData as dynamic).fromJson(response.data);
  }

  static Future<List<ResponseData>> getList<ResponseData, RequestData>(
    String url, {
    required String fieldName,
    RequestData? query,
  }) async {
    final response = await client.get(
      url,
      queryParameters: query != null ? (query as dynamic).toJson() : null,
    );

    return response.data[fieldName].map((item) {
      return (ResponseData as dynamic).fromJson(item);
    }).toList();
  }

  static Future<ResponseData?> post<ResponseData, RequestData>(
    String url, {
    RequestData? bodyData,
  }) async {
    final response = await client.post(
      url,
      data: bodyData != null ? (bodyData as dynamic).toJson() : null,
    );
    if (ResponseData == Null) return null;
    return (ResponseData as dynamic).fromJson(response.data);
  }

  static Future<ResponseData?> put<ResponseData, RequestData>(
    String url, {
    RequestData? bodyData,
  }) async {
    final response = await client.put(
      url,
      data: bodyData != null ? (bodyData as dynamic).toJson() : null,
    );
    if (ResponseData == Null) return null;
    return (ResponseData as dynamic).fromJson(response.data);
  }

  static Future<void> delete(String url, int id) async {
    await client.delete(url, data: {"id": id});
  }
}

