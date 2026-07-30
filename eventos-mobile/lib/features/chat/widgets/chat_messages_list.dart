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
        return const Center(child: Text("No messages yet. Say hello!"));
      }

      return ListView.builder(
        controller: scrollController,
        itemCount: controller.messages.length,
        itemBuilder: (context, index) {
          final message = controller.messages[index];
          final isMe = message.userId == (controller.currentUser?.id ?? 0);
          return MessageBubble(
            message: message,
            isMe: isMe,
          );
        },
      );
    });
  }
}
