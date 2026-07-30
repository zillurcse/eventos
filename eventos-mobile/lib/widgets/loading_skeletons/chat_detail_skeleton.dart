import 'package:flutter/material.dart';
import 'package:flutter_screenutil/flutter_screenutil.dart';
import '../shimmer_box.dart';
import '../../utils/extension/theme_ext.dart';

class ChatDetailSkeleton extends StatelessWidget {
  const ChatDetailSkeleton({super.key});

  @override
  Widget build(BuildContext context) {
    // Alternating sender (isMe: true) and receiver (isMe: false) bubbles
    final List<bool> bubbleIsMe = [false, true, false, false, true, false, true];
    final List<double> bubbleWidths = [180.w, 120.w, 240.w, 150.w, 90.w, 210.w, 130.w];

    return ListView.builder(
      physics: const NeverScrollableScrollPhysics(),
      itemCount: bubbleIsMe.length,
      padding: const EdgeInsets.symmetric(vertical: 8.0, horizontal: 8.0),
      itemBuilder: (context, index) {
        final isMe = bubbleIsMe[index];
        final width = bubbleWidths[index];

        return Padding(
          padding: const EdgeInsets.symmetric(vertical: 6.0),
          child: Column(
            crossAxisAlignment: isMe ? CrossAxisAlignment.end : CrossAxisAlignment.start,
            children: [
              Container(
                padding: EdgeInsets.all(12.sp),
                decoration: BoxDecoration(
                  color: isMe ? context.primaryFocused : context.tertiaryText,
                  borderRadius: BorderRadius.only(
                    topLeft: const Radius.circular(12).r,
                    topRight: const Radius.circular(12).r,
                    bottomLeft: Radius.circular(isMe ? 12 : 0).r,
                    bottomRight: Radius.circular(isMe ? 0 : 12).r,
                  ),
                ),
                child: ShimmerBox(
                  width: width,
                  height: 14.h,
                  topRadius: 4.r,
                ),
              ),
              Padding(
                padding: const EdgeInsets.only(top: 4.0),
                child: ShimmerBox(
                  width: 50.w,
                  height: 10.h,
                  topRadius: 2.r,
                ),
              ),
            ],
          ),
        );
      },
    );
  }
}
