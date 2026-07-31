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

  String _getGroupTitle(DateTime? date) {
    if (date == null) return 'Earlier';
    final now = DateTime.now();
    final difference = now.difference(date).inDays;

    if (date.year == now.year &&
        date.month == now.month &&
        date.day == now.day) {
      return 'Today';
    } else if (difference == 1 ||
        (difference == 0 && now.day != date.day)) {
      return 'Yesterday';
    } else if (difference > 1 && difference < 7) {
      return '$difference days ago';
    } else {
      return DateFormat('dd MMM yyyy').format(date);
    }
  }

  String _getTimeString(DateTime? date) {
    if (date == null) return '';
    final now = DateTime.now();
    final difference = now.difference(date);

    if (difference.inMinutes < 60) {
      final mins = difference.inMinutes == 0 ? 1 : difference.inMinutes;
      return '${mins}m ago';
    } else if (difference.inHours < 24 && now.day == date.day) {
      return '${difference.inHours}h ago';
    } else {
      return DateFormat('hh:mm a').format(date).toLowerCase();
    }
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
          'Notifications',
          style: context.titleLarge?.copyWith(
            color: Colors.white,
            fontWeight: FontWeight.bold,
          ),
        ),
        actions: [
          Obx(() {
            if (controller.unreadCount.value == 0) {
              return const SizedBox.shrink();
            }
            return TextButton(
              onPressed: () => controller.markAllAsRead(),
              child: Text(
                'Mark all as read',
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
          'No notifications yet.',
          style: context.bodyRegular?.copyWith(color: context.ghost),
        ),
      );
    }

    final Map<String, List<NotificationItemModel>> grouped = {};
    for (final notif in notifications) {
      final title = _getGroupTitle(notif.createdAt);
      grouped.putIfAbsent(title, () => []).add(notif);
    }

    // Flatten to (header | item) rows for ListView.builder.
    final rows = <Object>[];
    grouped.forEach((title, items) {
      rows.add(title);
      rows.addAll(items);
    });

    return RefreshIndicator(
      onRefresh: () => controller.fetchNotifications(refresh: true),
      child: ListView.builder(
        padding: EdgeInsets.symmetric(horizontal: 16.w, vertical: 8.h),
        itemCount: rows.length,
        itemBuilder: (context, index) {
          final row = rows[index];
          if (row is String) {
            return Padding(
              padding: EdgeInsets.only(top: 16.h, bottom: 12.h),
              child: Text(
                row,
                style: context.titleLarge?.copyWith(
                  fontWeight: FontWeight.bold,
                  color: context.heading,
                ),
              ),
            );
          }

          final item = row as NotificationItemModel;
          final display = item.body.isNotEmpty ? item.body : item.title;
          return GestureDetector(
            onTap: () => controller.openNotification(item),
            child: NotificationCard(
              isUnread: !item.isRead,
              iconType: NotificationIconType.avatar,
              avatarUrl: '',
              messageSpans: [
                if (item.title.isNotEmpty && item.body.isNotEmpty) ...[
                  TextSpan(
                    text: '${item.title} ',
                    style: const TextStyle(fontWeight: FontWeight.bold),
                  ),
                  TextSpan(text: item.body),
                ] else
                  TextSpan(text: display),
              ],
              time: _getTimeString(item.createdAt),
            ),
          );
        },
      ),
    );
  }
}
