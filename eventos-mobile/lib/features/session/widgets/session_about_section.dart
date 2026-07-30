import 'package:expouse/utils/extension/string_ext.dart';
import 'package:flutter/material.dart';
import 'package:flutter_screenutil/flutter_screenutil.dart';
import '../../../models/session_detail_response_model.dart';
import '../../../utils/extension/theme_ext.dart';

class SessionAboutSection extends StatelessWidget {
  final SessionDetailModel detail;

  const SessionAboutSection({super.key, required this.detail});

  @override
  Widget build(BuildContext context) {
    final plainTextDesc = detail.description.htmlToPlainText();
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          'About',
          style: context.h2?.copyWith(
            color: context.heading,
            fontWeight: FontWeight.bold,
          ),
        ),
        if (detail.track.isNotEmpty) ...[
          SizedBox(height: 8.h),
          Container(
            padding: EdgeInsets.symmetric(horizontal: 10.w, vertical: 4.h),
            decoration: BoxDecoration(
              color: context.primaryTheme.withValues(alpha: 0.1),
              borderRadius: BorderRadius.circular(4.r),
            ),
            child: Text(
              detail.track,
              style: context.specialCaption2?.copyWith(
                color: context.primaryTheme,
                fontWeight: FontWeight.bold,
              ),
            ),
          ),
        ],
        SizedBox(height: 12.h),
        if (plainTextDesc.isNotEmpty) ...[
          ExpandableDescription(text: plainTextDesc),
          SizedBox(height: 16.h),
        ],

        // Tags Row
        if (detail.tags.isNotEmpty)
          Wrap(
            spacing: 8.w,
            runSpacing: 8.h,
            children: detail.tags.map((tag) {
              return Container(
                padding: EdgeInsets.symmetric(horizontal: 10.w, vertical: 6.h),
                decoration: BoxDecoration(
                  color: context.backgroundColor,
                  borderRadius: BorderRadius.circular(6.r),
                  border: Border.all(color: context.strokeLight),
                ),
                child: Text(
                  tag,
                  style: context.specialCaption1?.copyWith(color: context.caption),
                ),
              );
            }).toList(),
          ),
      ],
    );
  }
}

// Expandable Description Component
class ExpandableDescription extends StatefulWidget {
  final String text;
  const ExpandableDescription({super.key, required this.text});

  @override
  State<ExpandableDescription> createState() => _ExpandableDescriptionState();
}

class _ExpandableDescriptionState extends State<ExpandableDescription> {
  bool _isExpanded = false;

  @override
  Widget build(BuildContext context) {
    final text = widget.text;
    const maxLength = 200;

    if (text.length <= maxLength) {
      return Text(
        text,
        style: context.bodyRegular?.copyWith(color: context.caption, height: 1.5),
      );
    }

    final displayText = _isExpanded ? text : '${text.substring(0, maxLength)}...';

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          displayText,
          style: context.bodyRegular?.copyWith(color: context.caption, height: 1.5),
        ),
        SizedBox(height: 4.h),
        GestureDetector(
          onTap: () {
            setState(() {
              _isExpanded = !_isExpanded;
            });
          },
          child: Text(
            _isExpanded ? "Read Less" : "Read More",
            style: context.bodyRegular?.copyWith(
              color: context.primaryTheme,
              fontWeight: FontWeight.bold,
            ),
          ),
        ),
      ],
    );
  }
}
