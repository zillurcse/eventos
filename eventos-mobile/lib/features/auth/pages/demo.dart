import 'dart:convert';
import 'package:flutter/material.dart';
import 'package:flutter_screenutil/flutter_screenutil.dart';
import '../../../utils/extension/theme_ext.dart';

class Demo extends StatelessWidget {
  final List<Map<String, dynamic>> json;
  const Demo({super.key, required this.json});

  @override
  Widget build(BuildContext context) {
    const encoder = JsonEncoder.withIndent('  ');
    String beautifiedJson = encoder.convert(json);

    return Scaffold(
      appBar: AppBar(
        title: const Text("Form Data (Beautified)"),
        backgroundColor: context.primaryTheme,
        foregroundColor: Colors.white,
      ),
      body: SingleChildScrollView(
        padding: EdgeInsets.all(16.sp),
        child: Container(
          width: double.infinity,
          padding: EdgeInsets.all(12.sp),
          decoration: BoxDecoration(
            color: Colors.grey[900],
            borderRadius: BorderRadius.circular(8.r),
          ),
          child: SelectableText(
            beautifiedJson,
            style: TextStyle(
              color: Colors.greenAccent,
              fontFamily: 'monospace',
              fontSize: 14.sp,
            ),
          ),
        ),
      ),
    );
  }
}
