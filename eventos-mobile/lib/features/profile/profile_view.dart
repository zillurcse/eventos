import 'package:flutter/material.dart';
import 'package:flutter_screenutil/flutter_screenutil.dart';
import 'package:get/get.dart';
import '../../utils/extension/theme_ext.dart';
import 'widgets/personal_details_tab.dart';
import 'widgets/interest_tab.dart';
import 'widgets/looking_offering_tab.dart';
import 'profile_controller.dart';
import '../../utils/enum/enums.dart';

class ProfileView extends StatefulWidget {
  const ProfileView({super.key});

  @override
  State<ProfileView> createState() => _ProfileViewState();
}

class _ProfileViewState extends State<ProfileView> with SingleTickerProviderStateMixin {
  late TabController _tabController;
  final ProfileController _controller = Get.put(ProfileController());

  @override
  void initState() {
    super.initState();
    _tabController = TabController(length: 3, vsync: this);
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
          "Edit Profile",
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
          isScrollable: true,
          tabAlignment: TabAlignment.start,
          unselectedLabelColor: Colors.white.withValues(alpha: 0.7),
          labelStyle: context.bodyRegular?.copyWith(
            fontWeight: FontWeight.bold,
          ),
          unselectedLabelStyle: context.bodyRegular,
          tabs: const [
            Tab(text: "Personal Details"),
            Tab(text: "Interest"),
            Tab(text: "Looking & Offering"),
          ],
        ),
      ),
      body: Column(
        children: [
          Expanded(
            child: Obx(() {
              if (_controller.dataStatus.value == ApiState.loading) {
                return Center(
                  child: CircularProgressIndicator(color: context.primaryTheme),
                );
              }
              return TabBarView(
                controller: _tabController,
                children: const [
                  PersonalDetailsTab(),
                  InterestTab(),
                  LookingOfferingTab(),
                ],
              );
            }),
          ),
          // Sticky Bottom Button
          Container(
            padding: EdgeInsets.all(16.w),
            decoration: BoxDecoration(
              color: Colors.white,
              border: Border(
                top: BorderSide(color: context.strokeLight, width: 1),
              ),
              boxShadow: [
                BoxShadow(
                  color: Colors.black.withValues(alpha: 0.05),
                  blurRadius: 10,
                  offset: const Offset(0, -5),
                ),
              ],
            ),
            child: SafeArea(
              top: false,
              child: SizedBox(
                width: double.infinity,
                child: ElevatedButton(
                  onPressed: () {
                    _controller.updateProfileData();
                  },
                  style: ElevatedButton.styleFrom(
                    backgroundColor: context.primaryTheme,
                    padding: EdgeInsets.symmetric(vertical: 14.h),
                    shape: RoundedRectangleBorder(
                      borderRadius: BorderRadius.circular(8.r),
                    ),
                    elevation: 0,
                  ),
                  child: Text(
                    "Save Changes",
                    style: context.titleRegular?.copyWith(
                      color: Colors.white,
                      fontWeight: FontWeight.bold,
                    ),
                  ),
                ),
              ),
            ),
          ),
        ],
      ),
    );
  }
}
