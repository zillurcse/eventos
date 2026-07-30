import 'package:expouse/utils/extension/theme_ext.dart';
import 'package:flutter/material.dart';
import 'package:flutter_screenutil/flutter_screenutil.dart';

class FailedWidget extends StatelessWidget {
  final Function() onRetry;
  const FailedWidget({super.key, required this.onRetry});

  @override
  Widget build(BuildContext context) {
    return Center(
      child: InkWell(
        onTap: (){
          onRetry.call();
        },
        child: Icon(Icons.restart_alt, size: 26.sp, color: context.redError),
      ),
    );
  }
}
