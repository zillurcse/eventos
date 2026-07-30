import 'package:flutter/material.dart';
import 'package:flutter_screenutil/flutter_screenutil.dart';
import '../../../../utils/extension/theme_ext.dart';

class PostPollOptionField extends StatefulWidget {
  final TextEditingController controller;
  final int index;
  final bool canRemove;
  final bool autoFocus;
  final VoidCallback onRemove;

  const PostPollOptionField({
    super.key,
    required this.controller,
    required this.index,
    required this.canRemove,
    required this.autoFocus,
    required this.onRemove,
  });

  @override
  State<PostPollOptionField> createState() => _PostPollOptionFieldState();
}

class _PostPollOptionFieldState extends State<PostPollOptionField> {
  late final FocusNode _focus;
  bool _hasFocus = false;

  @override
  void initState() {
    super.initState();
    _focus = FocusNode();
    _focus.addListener(_onFocusChange);
    if (widget.autoFocus) {
      WidgetsBinding.instance.addPostFrameCallback((_) {
        if (mounted) _focus.requestFocus();
      });
    }
  }

  void _onFocusChange() {
    if (mounted) setState(() => _hasFocus = _focus.hasFocus);
  }

  @override
  void dispose() {
    _focus.removeListener(_onFocusChange);
    _focus.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return AnimatedContainer(
      duration: const Duration(milliseconds: 200),
      decoration: BoxDecoration(
        borderRadius: BorderRadius.circular(8.r),
        border: Border.all(
          color: _hasFocus ? context.primaryTheme : context.stroke,
          width: _hasFocus ? 1.5 : 1.0,
        ),
        color: context.tertiaryText,
      ),
      child: Row(
        children: [
          SizedBox(width: 14.w),
          Expanded(
            child: TextField(
              controller: widget.controller,
              focusNode: _focus,
              maxLength: 100,
              buildCounter: (_, {required currentLength, required isFocused, maxLength}) =>
                  const SizedBox.shrink(),
              decoration: InputDecoration(
                border: InputBorder.none,
                isDense: true,
                contentPadding: EdgeInsets.symmetric(vertical: 12.h),
                hintText: 'Option ${widget.index + 1}',
                hintStyle: context.specialCaption1?.copyWith(color: context.ghost),
              ),
              style: context.specialCaption1?.copyWith(color: context.heading),
            ),
          ),
          if (widget.canRemove)
            GestureDetector(
              onTap: widget.onRemove,
              child: Padding(
                padding: EdgeInsets.symmetric(horizontal: 12.w),
                child: Icon(Icons.close, size: 18.sp, color: context.ghost),
              ),
            )
          else
            SizedBox(width: 14.w),
        ],
      ),
    );
  }
}
