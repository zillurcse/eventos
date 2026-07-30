import 'package:flutter/material.dart';
import 'package:flutter_screenutil/flutter_screenutil.dart';
import 'package:get/get.dart';
import '../../models/notification_model.dart';
import '../../utils/extension/theme_ext.dart';
import '../../widgets/state_handler/api_state_handler.dart';
import '../../widgets/loading_skeletons/delegate_list_skeleton.dart';
import 'notification_controller.dart';
import 'widgets/notification_card.dart';
import 'package:intl/intl.dart';

class NotificationsView extends StatefulWidget {
  const NotificationsView({super.key});

  @override
  State<NotificationsView> createState() => _NotificationsViewState();
}

class _NotificationsViewState extends State<NotificationsView> {
  final controller = Get.put(NotificationController());
  final ScrollController _scrollController = ScrollController();

  @override
  void initState() {
    super.initState();
    _scrollController.addListener(() {
      if (_scrollController.position.pixels >= _scrollController.position.maxScrollExtent - 100) {
        controller.fetchNotifications(loadMore: true);
      }
    });
  }

  @override
  void dispose() {
    _scrollController.dispose();
    super.dispose();
  }

  String _getGroupTitle(DateTime? date) {
    if (date == null) return "Earlier";
    final now = DateTime.now();
    final difference = now.difference(date).inDays;
    
    if (date.year == now.year && date.month == now.month && date.day == now.day) {
      return "Today";
    } else if (difference == 1 || (difference == 0 && now.day != date.day)) {
      return "Yesterday";
    } else if (difference > 1 && difference < 7) {
      return "$difference days ago";
    } else {
      return DateFormat('dd MMM yyyy').format(date);
    }
  }

  String _getTimeString(DateTime? date) {
    if (date == null) return "";
    final now = DateTime.now();
    final difference = now.difference(date);

    if (difference.inMinutes < 60) {
      final mins = difference.inMinutes == 0 ? 1 : difference.inMinutes;
      return "${mins}m ago";
    } else if (difference.inHours < 24 && now.day == date.day) {
      return "${difference.inHours}h ago";
    } else {
      return DateFormat('hh:mm a').format(date).toLowerCase();
    }
  }

  List<TextSpan> _buildMessageSpans(String contextString, NotificationUserModel? user) {
    final spans = <TextSpan>[];
    
    if (user != null && user.name.isNotEmpty && contextString.contains(user.name)) {
      final parts = contextString.split(user.name);
      for (int i = 0; i < parts.length; i++) {
        if (parts[i].isNotEmpty) {
          spans.add(TextSpan(text: parts[i]));
        }
        if (i < parts.length - 1) {
          spans.add(TextSpan(
            text: "${user.name} ",
            style: const TextStyle(fontWeight: FontWeight.bold),
          ));
        }
      }
    } else {
      spans.add(TextSpan(text: contextString));
    }
    return spans;
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: context.backgroundColor,
      appBar: AppBar(
        backgroundColor: context.primaryTheme,
        elevation: 0,
        iconTheme: const IconThemeData(color: Colors.white),
        title: Text(
          "Notifications",
          style: context.titleLarge?.copyWith(
            color: Colors.white,
            fontWeight: FontWeight.bold,
          ),
        ),
        actions: [
          Obx(() {
            if (controller.unreadCount.value == 0) return const SizedBox.shrink();
            return TextButton(
              onPressed: () => controller.markAllAsRead(),
              child: Text(
                "Mark all as read",
                style: context.bodyRegular?.copyWith(
                  color: Colors.white,
                  fontWeight: FontWeight.w500,
                ),
              ),
            );
          }),
        ],
      ),
      body: Obx(() {
        return ApiStateHandler(
          state: controller.dataStatus.value,
          onRetry: () => controller.fetchNotifications(),
          skeleton: const DelegateListSkeleton(),
          loadedElement: _buildNotificationsList(),
        );
      }),
    );
  }

  Widget _buildNotificationsList() {
    final notifications = controller.notifications;
    if (notifications.isEmpty) {
      return Center(
        child: Text(
          "No notifications yet.",
          style: context.bodyRegular?.copyWith(color: context.ghost),
        ),
      );
    }

    // Group by title
    final Map<String, List<NotificationItemModel>> grouped = {};
    for (var notif in notifications) {
      final title = _getGroupTitle(notif.createdAt);
      if (!grouped.containsKey(title)) {
        grouped[title] = [];
      }
      grouped[title]!.add(notif);
    }

    final children = <Widget>[];
    grouped.forEach((title, items) {
      children.add(
        Padding(
          padding: EdgeInsets.only(top: 16.h, bottom: 12.h),
          child: Text(
            title,
            style: context.titleLarge?.copyWith(
              fontWeight: FontWeight.bold,
              color: context.heading,
            ),
          ),
        ),
      );

      for (var item in items) {
        children.add(
          GestureDetector(
            onTap: () {
              if (!item.isRead) {
                controller.markAsRead(item.id);
              }
              if (item.url.isNotEmpty) {
                Get.toNamed(item.url); // Navigation fallback
              }
            },
            child: NotificationCard(
              isUnread: !item.isRead,
              iconType: NotificationIconType.avatar,
              avatarUrl: item.user?.profilePhotoUrl ?? '',
              messageSpans: _buildMessageSpans(item.context, item.user),
              time: _getTimeString(item.createdAt),
            ),
          ),
        );
      }
    });

    return ListView(
      controller: _scrollController,
      padding: EdgeInsets.symmetric(horizontal: 16.w, vertical: 8.h),
      children: children,
    );
  }
}
