import 'package:flutter/material.dart';
import 'package:get/get.dart';
import '../chat_controller.dart';
import 'message_bubble.dart';

class ChatMessagesList extends StatelessWidget {
  final ScrollController scrollController;

  const ChatMessagesList({super.key, required this.scrollController});

  @override
  Widget build(BuildContext context) {
    final controller = Get.find<ChatController>();
    return Obx(() {
      if (controller.messages.isEmpty) {
        return const Center(child: Text('No messages yet. Say hello!'));
      }

      final myAvatar = controller.myProfile?.avatarUrl ??
          controller.currentUser?.profilePhotoUrl;
      final myName = controller.myProfile?.name ??
          controller.currentUser?.name ??
          'You';
      final partnerAvatar = controller.currentPartnerAvatarUrl.value;
      final partnerName = controller.currentPartnerName.value;

      return ListView.builder(
        controller: scrollController,
        itemCount: controller.messages.length,
        itemBuilder: (context, index) {
          final message = controller.messages[index];
          final isMe = message.mine;
          return MessageBubble(
            message: message,
            isMe: isMe,
            avatarUrl: isMe ? myAvatar : partnerAvatar,
            senderName: isMe ? myName : partnerName,
          );
        },
      );
    });
  }
}
