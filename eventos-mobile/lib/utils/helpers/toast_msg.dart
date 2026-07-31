import 'package:dio/dio.dart';
import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:flutter_screenutil/flutter_screenutil.dart';
import 'package:fluttertoast/fluttertoast.dart';

class ToastMsg {
  static void showSuccessMessage(String msg) {
    Fluttertoast.showToast(
      msg: msg,
      toastLength: Toast.LENGTH_LONG,
      gravity: ToastGravity.BOTTOM,
      backgroundColor: Colors.green,
      textColor: Colors.white,
      fontSize: 16.sp,
    );
  }

  static void showErrorMessage(String msg) {
    Fluttertoast.showToast(
      msg: msg,
      toastLength: Toast.LENGTH_LONG,
      gravity: ToastGravity.BOTTOM,
      backgroundColor: Colors.red,
      textColor: Colors.white,
      fontSize: 16.sp,
    );
  }

  static void showApiErrorMessage(dynamic response) {
    if (response is DioException) {
      _handleException(response);
    } else {
      // Never surface raw exception objects (may contain URLs/tokens).
      if (kDebugMode) {
        debugPrint('API error: $response');
      }
      showErrorMessage('Something went wrong. Please try again.');
    }
  }

  static void _handleException(DioException exception) {
    switch (exception.type) {
      case DioExceptionType.connectionTimeout:
        showErrorMessage('Connection timeout. Please try again.');
        break;
      case DioExceptionType.receiveTimeout:
        showErrorMessage('Server took too long to respond.');
        break;
      case DioExceptionType.sendTimeout:
        showErrorMessage('Upload timed out. Please try again.');
        break;
      case DioExceptionType.cancel:
        showErrorMessage('Request cancelled.');
        break;
      case DioExceptionType.connectionError:
        showErrorMessage('Check your internet connection.');
        break;
      case DioExceptionType.badResponse:
        _handleBadResponse(exception);
        break;
      default:
        if (kDebugMode) {
          debugPrint('Dio error: ${exception.message}');
        }
        showErrorMessage('Something went wrong. Please try again.');
        break;
    }
  }

  static void _handleBadResponse(DioException exception) {
    final statusCode = exception.response?.statusCode;
    switch (statusCode) {
      case 400:
        showErrorMessage(_safeServerMessage(exception) ?? 'Invalid request.');
        break;
      case 401:
        // SessionManager already clears auth via Dio interceptor.
        showErrorMessage('Session expired. Please sign in again.');
        break;
      case 403:
        showErrorMessage("You don't have permission for this action.");
        break;
      case 404:
        showErrorMessage('The requested resource was not found.');
        break;
      case 422:
        showErrorMessage(_safeServerMessage(exception) ?? 'Please check your input.');
        break;
      case 429:
        showErrorMessage('Too many requests. Please wait and try again.');
        break;
      case 500:
      case 502:
      case 503:
        showErrorMessage('Server error. Please try again later.');
        break;
      default:
        if (kDebugMode) {
          debugPrint('HTTP $statusCode: ${exception.response?.data}');
        }
        showErrorMessage('Something went wrong. Please try again.');
        break;
    }
  }

  /// Extracts a short user-facing message without dumping raw payloads.
  static String? _safeServerMessage(DioException exception) {
    final data = exception.response?.data;
    if (data is Map) {
      final msg = data['message'];
      if (msg is String && msg.isNotEmpty && msg.length <= 200) {
        return msg;
      }
    }
    return null;
  }
}
