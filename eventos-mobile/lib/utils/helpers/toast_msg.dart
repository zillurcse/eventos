import 'package:dio/dio.dart';
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
      showErrorMessage(response.toString());
    }
  }

  static void _handleException(DioException exception) {
    switch (exception.type) {
      case DioExceptionType.connectionTimeout:
        showErrorMessage("Connection Timeout!");
        break;
      case DioExceptionType.receiveTimeout:
        showErrorMessage("Receive Timeout!");
        break;
      case DioExceptionType.sendTimeout:
        showErrorMessage("Send Timeout!");
        break;
      case DioExceptionType.cancel:
        showErrorMessage("Request Cancelled!");
        break;
      case DioExceptionType.connectionError:
        showErrorMessage("Check your internet connection!");
        break;
      case DioExceptionType.badResponse:
        _handleBadResponse(exception);
        break;
      default:
        showErrorMessage(
          exception.message ?? "Something went wrong, unknown exception!",
        );
        break;
    }
  }

  static void _handleBadResponse(DioException exception) {
    final statusCode = exception.response?.statusCode;
    switch (statusCode) {
      case 301:
        String newUrl = exception.response?.headers['location']?[0] ?? "";
        showErrorMessage("(301) Moved Permanently: $newUrl");
        break;
      case 400:
        showErrorMessage(exception.response?.data["message"]);
        break;
      case 401:
        /// Todo logout feature
        showErrorMessage("Token Expired!, Please login again!");
        break;
      case 403:
        showErrorMessage("(403) Forbidden! you don't have permission!");
        break;
      case 404:
        showErrorMessage("(404) Not Found!");
        break;
      case 406:
        showErrorMessage("(406) Not Acceptable!, Please login again!");
        break;
      case 500:
        showErrorMessage("(500) Internal Server Error!");
        break;
      case 503:
        showErrorMessage("(500) Service Unavailable");
        break;
      default:
        final statusCode = exception.response?.statusCode;
        final msg = exception.response?.data;
        showErrorMessage("($statusCode) Unkndgown Error! $msg");
        break;
    }
  }
}
