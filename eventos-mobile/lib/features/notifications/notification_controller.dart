import 'package:get/get.dart';
import '../../models/notification_model.dart';
import '../../utils/enum/enums.dart';
import '../../utils/helpers/helper_functions.dart';
import '../chat/chat_controller.dart';
import '../chat/pages/chat_detail_view.dart';
import 'notification_service.dart';

class NotificationController extends GetxController {
  final _service = NotificationService();

  final dataStatus = ApiState.initial.obs;
  final RxList<NotificationItemModel> notifications =
      <NotificationItemModel>[].obs;
  final unreadCount = 0.obs;

  @override
  void onInit() {
    super.onInit();
    fetchNotifications();
  }

  Future<void> fetchNotifications({bool refresh = false}) async {
    await handleApiClient(
      onStateChanged: (state) => dataStatus(state),
      handleApiCall: () async {
        final response = await _service.getNotifications();
        if (response.data is Map) {
          final parsed = NotificationResponseModel.fromJson(
            Map<String, dynamic>.from(response.data as Map),
          );
          unreadCount.value = parsed.unreadCount;
          notifications.assignAll(parsed.data);
        }
      },
    );
  }

  Future<void> fetchUnreadCount() async {
    try {
      final response = await _service.getNotifications();
      if (response.data is Map) {
        final unread = (response.data as Map)['unread'];
        unreadCount.value =
            unread is int ? unread : int.tryParse('$unread') ?? 0;
      }
    } catch (_) {}
  }

  Future<void> markAsRead(String id) async {
    try {
      final response = await _service.markAsRead(id);
      if (response.statusCode == 200) {
        final index = notifications.indexWhere((e) => e.id == id);
        if (index != -1) {
          final old = notifications[index];
          notifications[index] = NotificationItemModel(
            id: old.id,
            title: old.title,
            body: old.body,
            status: 'read',
            readAt: DateTime.now().toIso8601String(),
            createdAt: old.createdAt,
            templateKey: old.templateKey,
            data: old.data,
          );
        }
        if (unreadCount.value > 0) unreadCount.value--;
      }
    } catch (_) {}
  }

  Future<void> markAllAsRead() async {
    try {
      final response = await _service.markAllAsRead();
      if (response.statusCode == 200) {
        unreadCount.value = 0;
        await fetchNotifications();
      }
    } catch (_) {}
  }

  Future<void> openNotification(NotificationItemModel item) async {
    if (!item.isRead) {
      await markAsRead(item.id);
    }

    final conversationId = item.conversationId;
    if (conversationId != null &&
        conversationId.isNotEmpty &&
        (item.type == 'chat' ||
            item.templateKey == 'chat.message' ||
            item.data['type']?.toString() == 'chat')) {
      if (!Get.isRegistered<ChatController>()) {
        Get.put(ChatController());
      }
      final chat = Get.find<ChatController>();
      await chat.fetchRooms();
      final room =
          chat.chatRooms.firstWhereOrNull((r) => r.id == conversationId);
      Get.to(
        () => ChatDetailView(
          conversationId: conversationId,
          partnerId: room?.partner?.id ?? '',
          partnerName: room?.partner?.name ?? item.title,
          partnerImageUrl: room?.partner?.avatarUrl,
        ),
      );
    }
  }
}
