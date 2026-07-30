import 'package:flutter/material.dart';
import 'package:flutter_screenutil/flutter_screenutil.dart';
import 'package:get/get.dart';
import '../../../models/session_detail_response_model.dart';
import '../../../models/reception_speaker_model.dart';
import '../../../models/speaker_model.dart';
import '../../../widgets/custom_image.dart';
import '../../../utils/extension/theme_ext.dart';
import '../../speaker/pages/speaker_details.dart';

class SessionSpeakersSection extends StatelessWidget {
  final SessionDetailModel detail;

  const SessionSpeakersSection({super.key, required this.detail});

  @override
  Widget build(BuildContext context) {
    if (detail.speakers.isEmpty) return const SizedBox.shrink();

    return Container(
      width: double.infinity,
      padding: EdgeInsets.all(16.sp),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            'Speakers (${detail.speakers.length})',
            style: context.h2?.copyWith(
              color: context.heading,
              fontWeight: FontWeight.bold,
            ),
          ),
          SizedBox(height: 16.h),
          GridView.builder(
            physics: const NeverScrollableScrollPhysics(),
            shrinkWrap: true,
            gridDelegate: SliverGridDelegateWithFixedCrossAxisCount(
              crossAxisCount: 2,
              crossAxisSpacing: 12.w,
              mainAxisSpacing: 12.h,
              childAspectRatio: 0.75,
            ),
            itemCount: detail.speakers.length,
            itemBuilder: (context, idx) {
              return SessionSpeakerGridCard(speaker: detail.speakers[idx]);
            },
          ),
        ],
      ),
    );
  }
}

class SessionSpeakerGridCard extends StatefulWidget {
  final ReceptionSpeakerModel speaker;
  const SessionSpeakerGridCard({super.key, required this.speaker});

  @override
  State<SessionSpeakerGridCard> createState() => _SessionSpeakerGridCardState();
}

class _SessionSpeakerGridCardState extends State<SessionSpeakerGridCard> {
  bool _isBookmarked = false;

  @override
  Widget build(BuildContext context) {
    final speaker = widget.speaker;
    return GestureDetector(
      onTap: () {
        final speakerItem = SpeakerItemModel(
          id: speaker.id,
          name: speaker.name,
          image: speaker.imageUrl,
          designation: speaker.designation,
        );
        Get.to(() => SpeakerDetails(speaker: speakerItem));
      },
      child: Container(
        decoration: BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.circular(12.r),
          border: Border.all(color: context.strokeLight),
          boxShadow: [
            BoxShadow(
              color: Colors.black.withValues(alpha: 0.02),
              blurRadius: 8.r,
              offset: Offset(0, 4.h),
            ),
          ],
        ),
        child: ClipRRect(
          borderRadius: BorderRadius.circular(12.r),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Expanded(
                child: Stack(
                  children: [
                    Positioned.fill(
                      child: CustomImage(
                        speaker.imageUrl,
                        fit: BoxFit.cover,
                        avatar: true,
                      ),
                    ),
                    Positioned(
                      top: 8.h,
                      right: 8.w,
                      child: GestureDetector(
                        onTap: () {
                          setState(() {
                            _isBookmarked = !_isBookmarked;
                          });
                        },
                        child: Container(
                          padding: EdgeInsets.all(6.sp),
                          decoration: BoxDecoration(
                            color: Colors.white.withValues(alpha: 0.8),
                            shape: BoxShape.circle,
                          ),
                          child: Icon(
                            _isBookmarked ? Icons.bookmark : Icons.bookmark_border,
                            color: context.primaryTheme,
                            size: 16.sp,
                          ),
                        ),
                      ),
                    ),
                  ],
                ),
              ),
              Padding(
                padding: EdgeInsets.all(8.sp),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    Text(
                      speaker.name,
                      style: context.bodyRegular?.copyWith(
                        fontWeight: FontWeight.bold,
                        color: context.heading,
                      ),
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                    ),
                    SizedBox(height: 2.h),
                    Text(
                      speaker.designation,
                      style: context.specialCaption2?.copyWith(
                        color: context.caption,
                      ),
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                    ),
                    SizedBox(height: 2.h),
                    Text(
                      speaker.company.isNotEmpty ? speaker.company : 'Expouse',
                      style: context.specialCaption2?.copyWith(
                        color: context.caption,
                      ),
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                    ),
                  ],
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
