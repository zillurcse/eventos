import 'package:expouse/utils/extension/theme_ext.dart';
import 'package:expouse/widgets/custom_image.dart';
import 'package:flutter/material.dart';
import 'package:flutter_screenutil/flutter_screenutil.dart';

class ChatInputBar extends StatefulWidget {
  final Function(String) onSendMessage;
  final VoidCallback onAttachFile;

  const ChatInputBar({
    super.key,
    required this.onSendMessage,
    required this.onAttachFile,
  });

  @override
  State<ChatInputBar> createState() => ChatInputBarState();
}

class ChatInputBarState extends State<ChatInputBar> {
  final TextEditingController _controller = TextEditingController();

  void _handleSend() {
    if (_controller.text.trim().isNotEmpty) {
      widget.onSendMessage(_controller.text.trim());
      _controller.clear();
    }
  }

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: EdgeInsets.all(16.sp),
      decoration: BoxDecoration(
        color: context.tertiaryText,
        boxShadow: [
          BoxShadow(
            color: context.stroke,
            spreadRadius: 1,
            blurRadius: 3,
            offset: const Offset(0, -1),
          ),
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Container(
            padding: EdgeInsets.only(left: 12.w),
            decoration: BoxDecoration(
              borderRadius: BorderRadius.circular(12.r),
              border: Border.all(color: context.stroke),
            ),
            child: Row(
              children: [
                GestureDetector(
                    onTap: widget.onAttachFile,
                    child: CustomImage("assets/svg/icons/attach.svg"),),
                SizedBox(width: 12.w),
                Expanded(
                  child: TextField(
                    controller: _controller,
                    decoration: InputDecoration(
                      hint: Text("Message", style: context.bodyRegular,),
                      border: InputBorder.none,
                    ),
                    onSubmitted: (_) => _handleSend(),
                  ),
                ),
                GestureDetector(
                  onTap: _handleSend,
                  child: Container(
                    padding: EdgeInsets.symmetric(horizontal: 14.w, vertical: 8.h,),
                    color: Colors.transparent,
                      child: CustomImage("assets/svg/icons/send.svg",),),
                ),
              ],
            ),
          ),
          SizedBox(height: 4.h),
          Text(
            "Type @ to mention Users, Rooms, Lounges & Booths",
            style: context.specialCaption1?.copyWith(color: context.caption),
          ),
        ],
      ),
    );
  }

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }
}
