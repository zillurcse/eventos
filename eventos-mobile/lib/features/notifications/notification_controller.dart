import 'package:get/get.dart';
import '../../models/notification_model.dart';
import '../../utils/enum/enums.dart';
import '../../utils/helpers/helper_functions.dart';
import 'notification_service.dart';

class NotificationController extends GetxController {
  final _service = NotificationService();

  final dataStatus = ApiState.initial.obs;
  final RxList<NotificationItemModel> notifications = <NotificationItemModel>[].obs;
  final unreadCount = 0.obs;

  int _currentPage = 1;
  int _lastPage = 1;
  bool _isLoadingMore = false;

  @override
  void onInit() {
    super.onInit();
    fetchNotifications();
    fetchUnreadCount();
  }

  Future<void> fetchNotifications({bool loadMore = false}) async {
    if (loadMore) {
      if (_currentPage >= _lastPage || _isLoadingMore) return;
      _isLoadingMore = true;
      _currentPage++;
    } else {
      _currentPage = 1;
      _isLoadingMore = false;
      notifications.clear();
    }

    await handleApiClient(
      onStateChanged: (state) {
        if (!loadMore) dataStatus(state);
      },
      handleApiCall: () async {
        final response = await _service.getNotifications(_currentPage);
        if (response.data is Map) {
          final data = Map<String, dynamic>.from(response.data as Map);
          final parsed = NotificationResponseModel.fromJson(data);
          
          _lastPage = parsed.lastPage;
          unreadCount.value = parsed.unreadCount;
          
          if (loadMore) {
            notifications.addAll(parsed.data);
          } else {
            notifications.assignAll(parsed.data);
          }
        }
      },
    );
    _isLoadingMore = false;
  }

  Future<void> fetchUnreadCount() async {
    try {
      final response = await _service.getUnreadCount();
      if (response.data != null) {
        if (response.data is int) {
          unreadCount.value = response.data;
        } else if (response.data is String) {
          unreadCount.value = int.tryParse(response.data.toString()) ?? 0;
        }
      }
    } catch (e) {
      // Ignore background errors for unread count
    }
  }

  Future<void> markAsRead(int id) async {
    try {
      final response = await _service.markAsRead(id);
      if (response.statusCode == 200) {
        // Update local state
        final index = notifications.indexWhere((element) => element.id == id);
        if (index != -1) {
          final old = notifications[index];
          // create a new instance with readAt filled
          notifications[index] = NotificationItemModel(
            id: old.id,
            title: old.title,
            context: old.context,
            type: old.type,
            url: old.url,
            readAt: DateTime.now().toIso8601String(),
            createdAt: old.createdAt,
            user: old.user,
          );
        }
        if (unreadCount.value > 0) unreadCount.value--;
      }
    } catch (e) {
      // handle error if needed
    }
  }

  Future<void> markAllAsRead() async {
    try {
      final response = await _service.markAllAsRead();
      if (response.statusCode == 200) {
        unreadCount.value = 0;
        fetchNotifications(); // Refresh list to reflect read status
      }
    } catch (e) {
      // handle error
    }
  }
}
