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
    return Stack(
      fit: StackFit.expand,
      children: [
        Positioned(
          top: 8.h,
          right: 8.w,
          child: Material(
            color: Colors.black45,
            shape: const CircleBorder(),
            child: InkWell(
              customBorder: const CircleBorder(),
              onTap: () => Navigator.pop(context),
              child: Padding(
                padding: EdgeInsets.all(8.sp),
                child: Icon(Icons.close, color: Colors.white, size: 22.sp),
              ),
            ),
          ),
        ),
        Center(
          child: YoutubePlayer(
            controller: _ytCtrl,
            showVideoProgressIndicator: true,
            progressIndicatorColor: Colors.white,
            progressColors: const ProgressBarColors(
              playedColor: Colors.white,
              handleColor: Colors.white,
              bufferedColor: Colors.white38,
              backgroundColor: Colors.white24,
            ),
          ),
        ),
      ],
    );
  }
}
