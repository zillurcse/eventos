import 'package:expouse/utils/extension/theme_ext.dart';
import 'package:flutter/material.dart';
import 'package:flutter_screenutil/flutter_screenutil.dart';
import 'package:youtube_player_flutter/youtube_player_flutter.dart';

class YoutubeVideoCard extends StatefulWidget {
  final String videoId;

  const YoutubeVideoCard({super.key, required this.videoId});

  @override
  State<YoutubeVideoCard> createState() => YoutubeVideoCardState();
}

class YoutubeVideoCardState extends State<YoutubeVideoCard> {
  late YoutubePlayerController _ytCtrl;

  @override
  void initState() {
    super.initState();
    _ytCtrl = YoutubePlayerController(
      initialVideoId: widget.videoId,
      flags: const YoutubePlayerFlags(
        autoPlay: true,
        mute: false,
        enableCaption: false,
      ),
    );
  }

  @override
  void dispose() {
    _ytCtrl.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return SafeArea(
      top: false,
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          Align(
            alignment: Alignment.topRight,
            child: GestureDetector(
              onTap: () => Navigator.pop(context),
              child: Padding(
                padding: EdgeInsets.only(right: 16.w),
                child: Icon(Icons.close, color: context.tertiaryText),
              ),
            ),
          ),
          // Drag handle — tap to close
          GestureDetector(
            onTap: () => Navigator.pop(context),
            child: Container(
              margin: EdgeInsets.symmetric(vertical: 12.h),
              height: 4.h,
              width: 40.w,
              decoration: BoxDecoration(
                color: context.ghost,
                borderRadius: BorderRadius.circular(2.r),
              ),
            ),
          ),
          // Player — full width minus 32 px gutter
          Padding(
            padding: EdgeInsets.symmetric(horizontal: 16.w),
            child: ClipRRect(
              borderRadius: BorderRadius.circular(10.r),
              child: YoutubePlayer(
                controller: _ytCtrl,
                showVideoProgressIndicator: true,
                progressColors: ProgressBarColors(
                  playedColor: context.primaryTheme,
                  handleColor: context.primaryTheme,
                  bufferedColor: context.primaryTheme.withValues(alpha: 0.4),
                  backgroundColor: context.ghost,
                ),
              ),
            ),
          ),
          SizedBox(height: 24.h),
        ],
      ),
    );
  }
}
