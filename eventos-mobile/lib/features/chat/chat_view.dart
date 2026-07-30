import 'package:flutter/material.dart';
import 'package:flutter_screenutil/flutter_screenutil.dart';
import 'package:get/get.dart';

import '../../../utils/extension/theme_ext.dart';
import '../../../widgets/custom_image.dart';
import '../../../widgets/loading_skeletons/chat_list_skeleton.dart';
import '../../../widgets/state_handler/api_state_handler.dart';
import 'chat_controller.dart';
import 'widgets/chat_rooms_list.dart';

class ChatView extends StatefulWidget {
  const ChatView({super.key});

  @override
  State<ChatView> createState() => ChatViewState();
}

class ChatViewState extends State<ChatView> {
  final controller = Get.find<ChatController>();
  final _searchCtrl = TextEditingController();

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      controller.fetchRooms();
    });
  }

  @override
  void dispose() {
    _searchCtrl.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        titleSpacing: 0,
        backgroundColor: context.primaryTheme,
        title: Text(
          "Messages",
          style: context.titleLarge?.copyWith(color: context.tertiaryText),
        ),
      ),
      body: Column(
        children: [
          // ── Search bar ──────────────────────────────────────────────────
          Padding(
            padding: EdgeInsets.fromLTRB(16.w, 12.h, 16.w, 8.h),
            child: Container(
              decoration: BoxDecoration(
                borderRadius: BorderRadius.circular(8.r),
                border: Border.all(color: context.stroke),
              ),
              child: Row(
                children: [
                  SizedBox(width: 12.w),
                  CustomImage(
                    "assets/svg/icons/search.svg",
                    height: 20.sp,
                    color: context.ghost,
                  ),
                  SizedBox(width: 10.w),
                  Expanded(
                    child: TextField(
                      controller: _searchCtrl,
                      onChanged: controller.onSearchChanged,
                      decoration: InputDecoration(
                        border: InputBorder.none,
                        isDense: true,
                        contentPadding: EdgeInsets.symmetric(vertical: 10.h),
                        hintText: "Search conversations...",
                        hintStyle: context.bodyRegular?.copyWith(
                          color: context.ghost,
                        ),
                      ),
                    ),
                  ),
                  // Clear button — visible only when there is text
                  Obx(() => controller.searchQuery.value.isNotEmpty
                      ? GestureDetector(
                          onTap: () {
                            _searchCtrl.clear();
                            controller.onSearchChanged('');
                          },
                          child: Padding(
                            padding: EdgeInsets.symmetric(horizontal: 8.w),
                            child: Icon(Icons.close, size: 18.sp, color: context.ghost),
                          ),
                        )
                      : const SizedBox.shrink()),
                ],
              ),
            ),
          ),

          // ── Room list ────────────────────────────────────────────────────
          Expanded(
            child: Obx(
              () => ApiStateHandler(
                state: controller.roomStatus.value,
                onRetry: () => controller.fetchRooms(),
                skeleton: const ChatListSkeleton(),
                loadedElement: const ChatRoomsList(),
              ),
            ),
          ),
        ],
      ),
    );
  }
}
