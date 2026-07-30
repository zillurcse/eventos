import 'package:flutter/material.dart';
import 'package:flutter_screenutil/flutter_screenutil.dart';
import '../shimmer_box.dart';
import '../../utils/extension/theme_ext.dart';
import '../../utils/extension/size_ext.dart';

class SessionDetailsSkeleton extends StatelessWidget {
  const SessionDetailsSkeleton({super.key});

  @override
  Widget build(BuildContext context) {
    return CustomScrollView(
      physics: const NeverScrollableScrollPhysics(),
      slivers: [
        SliverToBoxAdapter(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              // Cover Image Placeholder
              ShimmerBox(
                width: double.infinity,
                height: 180.h,
                topRadius: 0,
              ),

              Container(
                color: context.tertiaryText,
                padding: EdgeInsets.symmetric(horizontal: 16.w, vertical: 16.h),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    // Badges Row
                    Row(
                      children: [
                        ShimmerBox(width: 45.w, height: 20.h, topRadius: 4.r),
                        SizedBox(width: 8.w),
                        ShimmerBox(width: 75.w, height: 20.h, topRadius: 4.r),
                      ],
                    ),
                    SizedBox(height: 16.h),

                    // Title Lines
                    ShimmerBox(width: double.infinity, height: 22.h, topRadius: 4.r),
                    SizedBox(height: 6.h),
                    ShimmerBox(width: 220.w, height: 22.h, topRadius: 4.r),
                    SizedBox(height: 20.h),

                    // Date & Time Row
                    Row(
                      children: [
                        ShimmerBox(width: 16.sp, height: 16.sp, topRadius: 4.r),
                        SizedBox(width: 8.w),
                        ShimmerBox(width: 200.w, height: 14.h, topRadius: 4.r),
                      ],
                    ),
                    SizedBox(height: 12.h),

                    // Location Row
                    Row(
                      children: [
                        ShimmerBox(width: 16.sp, height: 16.sp, topRadius: 4.r),
                        SizedBox(width: 8.w),
                        ShimmerBox(width: 150.w, height: 14.h, topRadius: 4.r),
                      ],
                    ),
                    SizedBox(height: 24.h),

                    // Action Buttons Row
                    Row(
                      children: [
                        // Bookmark icon placeholder
                        ShimmerBox(width: 44.sp, height: 44.sp, topRadius: 8.r),
                        SizedBox(width: 12.w),
                        // Add Note button placeholder
                        Expanded(
                          child: ShimmerBox(width: double.infinity, height: 44.sp, topRadius: 8.r),
                        ),
                        SizedBox(width: 12.w),
                        // Add to Calendar button placeholder
                        Expanded(
                          child: ShimmerBox(width: double.infinity, height: 44.sp, topRadius: 8.r),
                        ),
                      ],
                    ),
                    SizedBox(height: 24.h),

                    // About section header
                    ShimmerBox(width: 60.w, height: 18.h, topRadius: 4.r),
                    SizedBox(height: 10.h),

                    // Track Tag
                    ShimmerBox(width: 100.w, height: 22.h, topRadius: 4.r),
                    SizedBox(height: 12.h),

                    // Description lines
                    ShimmerBox(width: double.infinity, height: 12.h, topRadius: 4.r),
                    SizedBox(height: 6.h),
                    ShimmerBox(width: double.infinity, height: 12.h, topRadius: 4.r),
                    SizedBox(height: 6.h),
                    ShimmerBox(width: 180.w, height: 12.h, topRadius: 4.r),
                    SizedBox(height: 16.h),

                    // Tags list mockup
                    Wrap(
                      spacing: 8.w,
                      runSpacing: 8.h,
                      children: List.generate(
                        3,
                        (_) => ShimmerBox(width: 60.w, height: 24.h, topRadius: 6.r),
                      ),
                    ),
                  ],
                ),
              ),

              // Speakers section
              Container(
                width: double.infinity,
                padding: EdgeInsets.all(16.sp),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    ShimmerBox(width: 120.w, height: 18.h, topRadius: 4.r),
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
                      itemCount: 2,
                      itemBuilder: (context, idx) {
                        return const _SpeakerMockCard();
                      },
                    ),
                  ],
                ),
              ),

              // Sponsors section
              Container(
                width: double.infinity,
                padding: EdgeInsets.fromLTRB(16.w, 0, 16.w, 16.h),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    ShimmerBox(width: 120.w, height: 18.h, topRadius: 4.r),
                    SizedBox(height: 12.h),
                    SizedBox(
                      height: (context.height * .27).sp,
                      child: ListView.builder(
                        scrollDirection: Axis.horizontal,
                        physics: const NeverScrollableScrollPhysics(),
                        itemCount: 2,
                        itemBuilder: (context, idx) {
                          return const _SponsorMockCard();
                        },
                      ),
                    ),
                  ],
                ),
              ),

              // Files and documents section
              Container(
                width: double.infinity,
                padding: EdgeInsets.symmetric(horizontal: 16.w, vertical: 16.h),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    ShimmerBox(width: 150.w, height: 18.h, topRadius: 4.r),
                    SizedBox(height: 12.h),
                    Container(
                      padding: EdgeInsets.all(12.sp),
                      decoration: BoxDecoration(
                        color: Colors.white,
                        borderRadius: BorderRadius.circular(12.r),
                        border: Border.all(color: context.strokeLight),
                      ),
                      child: Row(
                        children: [
                          ShimmerBox(width: 36.sp, height: 36.sp, topRadius: 6.r),
                          SizedBox(width: 12.w),
                          Expanded(
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                ShimmerBox(width: 140.w, height: 12.h, topRadius: 4.r),
                                SizedBox(height: 6.h),
                                ShimmerBox(width: 50.w, height: 10.h, topRadius: 4.r),
                              ],
                            ),
                          ),
                          ShimmerBox(width: 20.sp, height: 20.sp, topRadius: 4.r),
                          SizedBox(width: 8.w),
                          ShimmerBox(width: 20.sp, height: 20.sp, topRadius: 4.r),
                        ],
                      ),
                    ),
                  ],
                ),
              ),
              SizedBox(height: 24.h),
            ],
          ),
        ),
      ],
    );
  }
}

class _SpeakerMockCard extends StatelessWidget {
  const _SpeakerMockCard();

  @override
  Widget build(BuildContext context) {
    return Container(
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(12.r),
        border: Border.all(color: context.strokeLight),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Expanded(
            child: ClipRRect(
              borderRadius: BorderRadius.only(
                topLeft: Radius.circular(12.r),
                topRight: Radius.circular(12.r),
              ),
              child: ShimmerBox(
                width: double.infinity,
                height: double.infinity,
                topRadius: 12.r,
              ),
            ),
          ),
          Padding(
            padding: EdgeInsets.all(8.w),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                ShimmerBox(width: 80.w, height: 12.h, topRadius: 4.r),
                SizedBox(height: 4.h),
                ShimmerBox(width: 50.w, height: 10.h, topRadius: 4.r),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _SponsorMockCard extends StatelessWidget {
  const _SponsorMockCard();

  @override
  Widget build(BuildContext context) {
    final cardWidth = (context.width * .75).sp;
    return Padding(
      padding: EdgeInsets.only(right: 16.sp, bottom: 12.h),
      child: ClipRRect(
        borderRadius: BorderRadius.circular(12.r),
        child: Container(
          decoration: BoxDecoration(
            color: Colors.white,
            borderRadius: BorderRadius.circular(12.r),
            border: Border.all(color: context.strokeLight),
          ),
          child: Column(
            children: [
              ShimmerBox(
                width: cardWidth,
                height: (context.height * .15).sp,
                topRadius: 12.r,
              ),
              Container(
                color: context.tertiaryText,
                width: cardWidth,
                padding: EdgeInsets.symmetric(horizontal: 12.w, vertical: 12.h),
                child: Row(
                  children: [
                    ShimmerBox(
                      width: 48.sp,
                      height: 48.sp,
                      topRadius: 8.r,
                    ),
                    SizedBox(width: 12.w),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        mainAxisSize: MainAxisSize.min,
                        children: [
                          ShimmerBox(
                            width: 100.w,
                            height: 12.h,
                            topRadius: 4.r,
                          ),
                          SizedBox(height: 6.h),
                          ShimmerBox(
                            width: 60.w,
                            height: 10.h,
                            topRadius: 4.r,
                          ),
                        ],
                      ),
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
