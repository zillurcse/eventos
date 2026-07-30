import 'package:flutter/material.dart';
import 'package:flutter_screenutil/flutter_screenutil.dart';
import 'package:get/get.dart';

import '../../models/speaker_model.dart';
import '../../models/session_model.dart';
import '../../models/exhibitor_model.dart';
import '../../models/delegate_model.dart';
import '../../utils/extension/theme_ext.dart';
import '../../widgets/custom_image.dart';
import '../../widgets/state_handler/api_state_handler.dart';
import '../../widgets/loading_skeletons/delegate_list_skeleton.dart';
import 'bookmark_controller.dart';

class BookmarksView extends StatefulWidget {
  const BookmarksView({super.key});

  @override
  State<BookmarksView> createState() => _BookmarksViewState();
}

class _BookmarksViewState extends State<BookmarksView> with SingleTickerProviderStateMixin {
  late final TabController _tabController;
  final bookmarkCtrl = Get.put(BookmarkController());

  @override
  void initState() {
    super.initState();
    _tabController = TabController(length: 4, vsync: this);
  }

  @override
  void dispose() {
    _tabController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: context.backgroundColor,
      appBar: AppBar(
        titleSpacing: 0,
        backgroundColor: context.primaryTheme,
        elevation: 0,
        iconTheme: const IconThemeData(color: Colors.white),
        title: Text(
          "My Bookmarks",
          style: context.titleLarge?.copyWith(
            color: Colors.white,
            fontWeight: FontWeight.bold,
          ),
        ),
        bottom: TabBar(
          controller: _tabController,
          indicatorColor: Colors.white,
          indicatorWeight: 3.h,
          labelColor: Colors.white,
          unselectedLabelColor: Colors.white.withValues(alpha: 0.7),
          labelStyle: context.bodyRegular?.copyWith(fontWeight: FontWeight.bold),
          unselectedLabelStyle: context.bodyRegular,
          tabs: const [
            Tab(text: "Speakers"),
            Tab(text: "Sessions"),
            Tab(text: "Exhibitors"),
            Tab(text: "Delegates"),
          ],
        ),
      ),
      body: Obx(() => ApiStateHandler(
        state: bookmarkCtrl.dataStatus.value,
        onRetry: bookmarkCtrl.fetchBookmarks,
        skeleton: const DelegateListSkeleton(),
        loadedElement: TabBarView(
          controller: _tabController,
          children: [
            _buildSpeakersTab(),
            _buildSessionsTab(),
            _buildExhibitorsTab(),
            _buildDelegatesTab(),
          ],
        ),
      )),
    );
  }

  Widget _buildSpeakersTab() {
    return Obx(() {
      final speakers = bookmarkCtrl.bookmarkedSpeakers;
      if (speakers.isEmpty) {
        return _buildEmptyState("No bookmarked speakers yet");
      }

      return GridView.builder(
        padding: EdgeInsets.all(16.w),
        gridDelegate: SliverGridDelegateWithFixedCrossAxisCount(
          crossAxisCount: 2,
          crossAxisSpacing: 12.w,
          mainAxisSpacing: 12.h,
          childAspectRatio: 0.70,
        ),
        itemCount: speakers.length,
        itemBuilder: (context, index) {
          final speaker = speakers[index];
          return _buildSpeakerCard(speaker);
        },
      );
    });
  }

  Widget _buildSpeakerCard(SpeakerItemModel speaker) {
    return Container(
      decoration: BoxDecoration(
        color: context.tertiaryText,
        borderRadius: BorderRadius.circular(12.r),
        border: Border.all(color: context.strokeLight, width: 1.sp),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withValues(alpha: 0.02),
            blurRadius: 6,
            offset: const Offset(0, 2),
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
                      speaker.image ?? "",
                      fit: BoxFit.cover,
                    ),
                  ),
                  Positioned(
                    top: 8.h,
                    right: 8.w,
                    child: GestureDetector(
                      onTap: () {
                        bookmarkCtrl.toggleSpeakerBookmark(speaker);
                      },
                      child: Container(
                        padding: EdgeInsets.symmetric(horizontal: 8.w, vertical: 8.h),
                        decoration: BoxDecoration(
                          color: context.primaryFocused,
                          borderRadius: BorderRadius.circular(6.r),
                          boxShadow: [
                            BoxShadow(
                              color: Colors.black.withValues(alpha: 0.08),
                              blurRadius: 4,
                              offset: const Offset(0, 2),
                            ),
                          ],
                        ),
                        child: CustomImage(
                          "assets/svg/icons/bookmark_fill.svg",
                          height: 22.sp,
                          width: 12.sp,
                          color: context.primaryTheme,
                        ),
                      ),
                    ),
                  ),
                ],
              ),
            ),
            Padding(
              padding: EdgeInsets.all(12.w),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    speaker.name,
                    style: context.titleLarge?.copyWith(
                      color: context.heading,
                      fontWeight: FontWeight.bold,
                    ),
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                  ),
                  SizedBox(height: 4.h),
                  Text(
                    speaker.designation,
                    style: context.bodyRegular?.copyWith(
                      color: context.caption,
                      fontSize: 12.sp,
                    ),
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                  ),
                  SizedBox(height: 2.h),
                  Text(
                    speaker.category ?? "Expouse",
                    style: context.bodyRegular?.copyWith(
                      color: context.caption,
                      fontSize: 12.sp,
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
    );
  }

  Widget _buildSessionsTab() {
    return Obx(() {
      final sessions = bookmarkCtrl.bookmarkedSessions;
      if (sessions.isEmpty) {
        return _buildEmptyState("No bookmarked sessions yet");
      }

      return ListView.builder(
        padding: EdgeInsets.symmetric(vertical: 12.h),
        itemCount: sessions.length,
        itemBuilder: (context, index) {
          final session = sessions[index];
          return _buildSessionCard(session);
        },
      );
    });
  }

  Widget _buildSessionCard(SessionModel session) {
    return Container(
      margin: EdgeInsets.symmetric(horizontal: 16.w, vertical: 8.h),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(16.r),
        border: Border.all(color: context.strokeLight, width: 1.sp),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withValues(alpha: 0.03),
            blurRadius: 8,
            offset: const Offset(0, 3),
          ),
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          // Top row: Date & Time, Action Icons
          Padding(
            padding: EdgeInsets.fromLTRB(16.w, 16.h, 16.w, 12.h),
            child: Row(
              children: [
                Expanded(
                  child: Text(
                    "28th Oct, 2026  |  10:00 AM - 12:30 PM",
                    style: context.bodyRegular?.copyWith(
                      color: context.caption,
                      fontWeight: FontWeight.w500,
                      fontSize: 12.sp,
                    ),
                  ),
                ),
                GestureDetector(
                  onTap: () {
                    bookmarkCtrl.toggleSessionBookmark(session);
                  },
                  child: CustomImage(
                    "assets/svg/icons/bookmark_fill.svg",
                    height: 20.sp,
                    width: 20.sp,
                    color: context.primaryTheme,
                  ),
                ),
                SizedBox(width: 14.w),
                CustomImage(
                  "assets/svg/icons/calender_add.svg",
                  height: 20.sp,
                  width: 20.sp,
                  color: context.primaryTheme,
                ),
              ],
            ),
          ),
          Padding(
            padding: EdgeInsets.symmetric(horizontal: 16.w),
            child: Divider(color: context.strokeLight, height: 1.h, thickness: 1.h),
          ),
          // Cover Image, Title, Location
          Padding(
            padding: EdgeInsets.all(16.w),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                ClipRRect(
                  borderRadius: BorderRadius.circular(8.r),
                  child: CustomImage(
                    "https://cdn.pixabay.com/photo/2016/11/21/06/53/beautiful-natural-image-1844362_640.jpg",
                    width: double.infinity,
                    height: 120.h,
                    fit: BoxFit.cover,
                  ),
                ),
                SizedBox(height: 16.h),
                Text(
                  session.title,
                  style: context.h2?.copyWith(
                    color: context.heading,
                    fontWeight: FontWeight.bold,
                  ),
                  maxLines: 2,
                  overflow: TextOverflow.ellipsis,
                ),
                SizedBox(height: 12.h),
                Row(
                  crossAxisAlignment: CrossAxisAlignment.center,
                  children: [
                    Container(
                      padding: EdgeInsets.all(8.sp),
                      decoration: BoxDecoration(
                        color: context.backgroundColor,
                        borderRadius: BorderRadius.circular(8.r),
                      ),
                      child: Icon(
                        Icons.location_on_outlined,
                        color: context.caption,
                        size: 18.sp,
                      ),
                    ),
                    SizedBox(width: 12.w),
                    Expanded(
                      child: Text(
                        session.sessionPlace,
                        style: context.bodyRegular?.copyWith(
                          color: context.caption,
                          fontSize: 12.sp,
                        ),
                        maxLines: 2,
                        overflow: TextOverflow.ellipsis,
                      ),
                    ),
                  ],
                ),
              ],
            ),
          ),
          Padding(
            padding: EdgeInsets.symmetric(horizontal: 16.w),
            child: Divider(color: context.strokeLight, height: 1.h, thickness: 1.h),
          ),
          // Speakers section
          Padding(
            padding: EdgeInsets.fromLTRB(16.w, 12.h, 16.w, 16.h),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  "Speakers (${session.speakers.length + 4})",
                  style: context.bodyRegular?.copyWith(
                    color: context.caption,
                    fontSize: 12.sp,
                  ),
                ),
                SizedBox(height: 8.h),
                Row(
                  children: [
                    Row(
                      mainAxisSize: MainAxisSize.min,
                      children: [
                        for (int i = 0; i < session.speakers.length; i++)
                          Align(
                            widthFactor: 0.7,
                            child: Container(
                              decoration: BoxDecoration(
                                shape: BoxShape.circle,
                                border: Border.all(color: Colors.white, width: 2.sp),
                              ),
                              child: CustomImage(
                                session.speakers[i].imageUrl,
                                width: 32.sp,
                                height: 32.sp,
                                isCircle: true,
                                avatar: true,
                              ),
                            ),
                          ),
                      ],
                    ),
                    SizedBox(width: 12.w),
                    Text(
                      "+4 More",
                      style: context.bodyRegular?.copyWith(
                        color: context.primaryTheme,
                        fontWeight: FontWeight.bold,
                      ),
                    ),
                  ],
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildExhibitorsTab() {
    return Obx(() {
      final exhibitors = bookmarkCtrl.bookmarkedExhibitors;
      if (exhibitors.isEmpty) {
        return _buildEmptyState("No bookmarked exhibitors yet");
      }

      return ListView.builder(
        padding: EdgeInsets.symmetric(vertical: 12.h),
        itemCount: exhibitors.length,
        itemBuilder: (context, index) {
          final exhibitor = exhibitors[index];
          return _buildExhibitorCard(exhibitor);
        },
      );
    });
  }

  Widget _buildExhibitorCard(ExhibitorModel exhibitor) {
    return Container(
      margin: EdgeInsets.fromLTRB(16.w, 8.h, 16.w, 0),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(16.r),
        border: Border.all(color: context.strokeLight, width: 1.sp),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withValues(alpha: 0.03),
            blurRadius: 8,
            offset: const Offset(0, 3),
          ),
        ],
      ),
      child: ClipRRect(
        borderRadius: BorderRadius.circular(16.r),
        child: Column(
          children: [
            Stack(
              children: [
                CustomImage(
                  exhibitor.spotlightBannerUrl,
                  height: 120.h,
                  width: double.infinity,
                  fit: BoxFit.cover,
                ),
                Positioned(
                  top: 8.h,
                  right: 8.w,
                  child: GestureDetector(
                    onTap: () {
                      bookmarkCtrl.toggleExhibitorBookmark(exhibitor);
                    },
                    child: Container(
                      padding: EdgeInsets.symmetric(horizontal: 8.w, vertical: 8.h),
                      decoration: BoxDecoration(
                        color: context.primaryFocused,
                        borderRadius: BorderRadius.circular(6.r),
                        boxShadow: [
                          BoxShadow(
                            color: Colors.black.withValues(alpha: 0.08),
                            blurRadius: 4,
                            offset: const Offset(0, 2),
                          ),
                        ],
                      ),
                      child: CustomImage(
                        "assets/svg/icons/bookmark_fill.svg",
                        height: 22.sp,
                        width: 12.sp,
                        color: context.primaryTheme,
                      ),
                    ),
                  ),
                ),
              ],
            ),
            Container(
              padding: EdgeInsets.all(16.w),
              child: Row(
                children: [
                  CustomImage(
                    exhibitor.logoUrl,
                    height: 48.sp,
                    width: 48.sp,
                    radius: 8.r,
                  ),
                  SizedBox(width: 16.w),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          exhibitor.name,
                          style: context.h2?.copyWith(
                            color: context.heading,
                            fontWeight: FontWeight.bold,
                          ),
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                        ),
                        SizedBox(height: 6.h),
                        Text(
                          "${exhibitor.stallNo}  |  ${exhibitor.exhibitorType}",
                          style: context.bodyRegular?.copyWith(
                            color: context.caption,
                            fontSize: 12.sp,
                          ),
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
    );
  }

  Widget _buildDelegatesTab() {
    return Obx(() {
      final delegates = bookmarkCtrl.bookmarkedDelegates;
      if (delegates.isEmpty) {
        return _buildEmptyState("No bookmarked delegates yet");
      }

      return ListView.builder(
        padding: EdgeInsets.symmetric(vertical: 12.h),
        itemCount: delegates.length,
        itemBuilder: (context, index) {
          final delegate = delegates[index];
          return _buildDelegateCard(delegate);
        },
      );
    });
  }

  Widget _buildDelegateCard(DelegateItemModel delegate) {
    final hasImage = delegate.image.isNotEmpty;

    return Container(
      margin: EdgeInsets.fromLTRB(16.w, 8.h, 16.w, 0),
      padding: EdgeInsets.all(12.sp),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(16.r),
        border: Border.all(color: context.strokeLight, width: 1.sp),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withValues(alpha: 0.03),
            blurRadius: 8,
            offset: const Offset(0, 3),
          ),
        ],
      ),
      child: Row(
        children: [
          hasImage
              ? CustomImage(
                  delegate.image,
                  height: 48.sp,
                  width: 48.sp,
                  radius: 8.r,
                )
              : Container(
                  height: 48.sp,
                  width: 48.sp,
                  decoration: BoxDecoration(
                    color: context.primaryFocused,
                    borderRadius: BorderRadius.circular(8.r),
                  ),
                  child: Center(
                    child: Text(
                      delegate.name.isNotEmpty
                          ? delegate.name[0].toUpperCase()
                          : '?',
                      style: context.titleLarge?.copyWith(
                        color: context.primaryTheme,
                      ),
                    ),
                  ),
                ),
          SizedBox(width: 16.w),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  delegate.name,
                  style: context.h2?.copyWith(
                    color: context.heading,
                    fontWeight: FontWeight.bold,
                  ),
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                ),
                if (delegate.designation.isNotEmpty) ...[
                  SizedBox(height: 4.h),
                  Text(
                    delegate.designation,
                    style: context.bodyRegular?.copyWith(
                      color: context.caption,
                      fontSize: 12.sp,
                    ),
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                  ),
                ],
                if (delegate.company.isNotEmpty) ...[
                  SizedBox(height: 2.h),
                  Text(
                    delegate.company,
                    style: context.bodyRegular?.copyWith(
                      color: context.primaryTheme,
                      fontSize: 12.sp,
                    ),
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                  ),
                ],
              ],
            ),
          ),
          SizedBox(width: 8.w),
          GestureDetector(
            onTap: () {
              bookmarkCtrl.toggleDelegateBookmark(delegate);
            },
            child: Container(
              padding: EdgeInsets.symmetric(horizontal: 8.w, vertical: 8.h),
              decoration: BoxDecoration(
                color: context.primaryFocused,
                borderRadius: BorderRadius.circular(6.r),
              ),
              child: CustomImage(
                "assets/svg/icons/bookmark_fill.svg",
                height: 22.sp,
                width: 12.sp,
                color: context.primaryTheme,
              ),
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildEmptyState(String message) {
    return Center(
      child: Column(
        mainAxisAlignment: MainAxisAlignment.center,
        children: [
          Icon(Icons.bookmark_outline, size: 64.sp, color: context.caption),
          SizedBox(height: 16.h),
          Text(
            message,
            style: context.titleLarge?.copyWith(color: context.caption),
          ),
        ],
      ),
    );
  }
}
