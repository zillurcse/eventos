import 'package:expouse/utils/extension/string_ext.dart';
import 'package:flutter/material.dart';
import 'package:flutter_screenutil/flutter_screenutil.dart';
import '../../../models/exhibitor_model.dart';
import '../../../utils/extension/theme_ext.dart';

class ExhibitorAboutSection extends StatelessWidget {
  final ExhibitorModel exhibitor;

  const ExhibitorAboutSection({super.key, required this.exhibitor});

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(exhibitor.aboutTitle?.isNotEmpty == true ? exhibitor.aboutTitle! : "About",
            style: context.h2?.copyWith(color: context.heading)),
        SizedBox(height: 16.h),
        Text(
          exhibitor.description?.isNotEmpty == true
              ? exhibitor.description!.htmlToPlainText()
              : "The repercussions of COVID-19 outbreak are being felt more strongly with every passing day, and despite the unprecedented steps and cumulative efforts undertaken by governments, businesses and individuals to stem its growth...",
          style: context.bodyRegular?.copyWith(
              color: context.caption, height: 1.5),
        ),
      ],
    );
  }
}
