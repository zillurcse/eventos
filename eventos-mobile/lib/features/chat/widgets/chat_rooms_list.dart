import 'package:flutter/material.dart';
import 'package:get/get.dart';
import '../chat_controller.dart';
import 'chat_list_tile.dart';
import '../pages/chat_detail_view.dart';
import '../../../utils/extension/theme_ext.dart';

class ChatRoomsList extends StatelessWidget {
  const ChatRoomsList({super.key});

  @override
  Widget build(BuildContext context) {
    final controller = Get.find<ChatController>();
    return Obx(() {
      if (controller.chatRooms.isEmpty) {
        return Center(
          child: Text(
            controller.searchQuery.value.isNotEmpty
                ? "No results for \"${controller.searchQuery.value}\""
                : "No conversations yet",
            style: context.bodyRegular?.copyWith(color: context.ghost),
          ),
        );
      }

      return RefreshIndicator(
        onRefresh: () => controller.fetchRooms(),
        child: ListView.builder(
          itemCount: controller.chatRooms.length,
          itemBuilder: (context, index) {
            final room = controller.chatRooms[index];
            return ChatListTile(
              room: room,
              isUnread: controller.isRoomTrulyUnread(room.id),
              onTap: () {
                if (room.partner != null) {
                  Get.to(
                    () => ChatDetailView(
                      roomId: room.id,
                      partnerId: room.partner!.id,
                      partnerName: room.partner!.name,
                      partnerImageUrl: room.partner!.profilePhotoUrl,
                    ),
                  );
                }
              },
            );
          },
        ),
      );
    });
  }
}
