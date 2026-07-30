import 'package:cached_network_image/cached_network_image.dart';
import 'package:expouse/widgets/state_handler/api_state_handler.dart';
import 'package:flutter/material.dart';
import 'package:get/get.dart';
import '../chat_controller.dart';
import '../widgets/chat_input_bar.dart';
import '../widgets/chat_messages_list.dart';
import '../../../utils/extension/theme_ext.dart';
import '../../../widgets/loading_skeletons/chat_detail_skeleton.dart';

class ChatDetailView extends StatefulWidget {
  final int roomId;
  final int partnerId;
  final String partnerName;
  final String? partnerImageUrl;

  const ChatDetailView({
    super.key,
    required this.roomId,
    required this.partnerId,
    required this.partnerName,
    this.partnerImageUrl,
  });

  @override
  State<ChatDetailView> createState() => ChatDetailViewState();
}

class ChatDetailViewState extends State<ChatDetailView> {
  final controller = Get.find<ChatController>();
  final ScrollController _scrollController = ScrollController();

  /// Worker returned by ever() — must be disposed to prevent listener
  /// stack-up when navigating in and out of the same room.
  Worker? _scrollWorker;

  @override
  void initState() {
    super.initState();
    controller.enterRoom(widget.roomId, widget.partnerId);
    _scrollWorker = ever(controller.messages, (_) {
      WidgetsBinding.instance.addPostFrameCallback((_) {
       Future.delayed(Duration(seconds: 1), (){
         if (_scrollController.hasClients) {
           _scrollController.animateTo(
             _scrollController.position.maxScrollExtent,
             duration: const Duration(milliseconds: 500),
             curve: Curves.easeOut,
           );
         }
       });
      });
    });
  }

  @override
  void dispose() {
    _scrollWorker?.dispose();
    controller.leaveRoom();
    _scrollController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        titleSpacing: 0,
        backgroundColor: context.primaryTheme,
        title: Row(
          children: [
            ClipRRect(
              borderRadius: BorderRadius.circular(8), // Adjust radius to your preference
              child: CachedNetworkImage(
                imageUrl: widget.partnerImageUrl ?? "",
                width: 32, // Equivalent to a CircleAvatar radius of 16
                height: 32,
                fit: BoxFit.cover,
                placeholder: (context, url) => const SizedBox(
                  width: 32,
                  height: 32,
                  child: Padding(
                    padding: EdgeInsets.all(8.0),
                    child: CircularProgressIndicator(strokeWidth: 2),
                  ),
                ),
                errorWidget: (context, url, error) => Container(
                  width: 32,
                  height: 32,
                  color: Colors.grey[300], // Background color for the error state
                  child: const Icon(Icons.person, size: 16),
                ),
              ),
            ),
            const SizedBox(width: 8),
            Expanded(
              child: Text(
                widget.partnerName,
                style: context.titleLarge?.copyWith(color: context.tertiaryText),
                overflow: TextOverflow.ellipsis,
              ),
            ),
          ],
        ),
      ),
      body: Column(
        children: [
          Expanded(
            child: Obx(() => ApiStateHandler(
              state: controller.messageStatus.value,
              onRetry: () => controller.fetchMessages(widget.partnerId),
              skeleton: const ChatDetailSkeleton(),
              loadedElement: ChatMessagesList(scrollController: _scrollController),
            ),),
          ),
          ChatInputBar(
            onSendMessage: (text) => controller.sendMessage(message: text),
            onAttachFile: () => controller.pickFile(),
          ),
        ],
      ),
    );
  }
}
