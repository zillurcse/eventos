import 'package:flutter/material.dart';
import 'package:qr_flutter/qr_flutter.dart';

import '../../../utils/config/app_config.dart';
import '../../../widgets/custom_image.dart';
import '../badge_design.dart';

/// Draws a badge design with the attendee's data merged in.
///
/// Mirrors `eventos-event/app/components/badge/BadgeRender.vue`: the design is
/// authored on a fixed pixel canvas and scaled down to fit [maxWidth]/[maxHeight].
class BadgeRender extends StatelessWidget {
  final Map<String, dynamic> badgeJson;
  final Map<String, String>? data;
  final String side;
  final double maxWidth;
  final double maxHeight;

  const BadgeRender({
    super.key,
    required this.badgeJson,
    this.data,
    this.side = 'front',
    this.maxWidth = 300,
    this.maxHeight = 420,
  });

  @override
  Widget build(BuildContext context) {
    final page = badgePageSize(badgeJson);
    final scaleX = maxWidth / page.width;
    final scaleY = maxHeight / page.height;
    final scale = scaleX < scaleY ? scaleX : scaleY;

    final boxes = List<Map<String, dynamic>>.from(badgeBoxes(badgeJson, side))
      ..sort((a, b) => badgeBoxZIndex(a).compareTo(badgeBoxZIndex(b)));
    final background = badgeBackground(badgeJson, side);
    final punch = badgePunch(badgeJson);

    final faceW = page.width * scale;
    final faceH = page.height * scale;

    return SizedBox(
      width: faceW,
      height: faceH,
      child: FittedBox(
        fit: BoxFit.fill,
        child: SizedBox(
          width: page.width,
          height: page.height,
          child: Stack(
            clipBehavior: Clip.hardEdge,
            children: [
              Positioned.fill(
                child: DecoratedBox(
                  decoration: badgeBackgroundDecoration(background),
                ),
              ),
              ..._punchGuides(punch),
              ...boxes.map((box) => _BadgeBox(box: box, data: data)),
            ],
          ),
        ),
      ),
    );
  }

  List<Widget> _punchGuides(BadgePunch punch) {
    final widgets = <Widget>[];

    switch (punch.long) {
      case 'long-left-right':
        widgets.add(
          const Positioned(top: 20, left: 20, child: _PunchLong()),
        );
        widgets.add(
          const Positioned(top: 20, right: 20, child: _PunchLong()),
        );
        break;
      case 'long-center':
        widgets.add(
          const Positioned(
            top: 20,
            left: 0,
            right: 0,
            child: Align(
              alignment: Alignment.topCenter,
              child: _PunchLong(),
            ),
          ),
        );
        break;
    }

    switch (punch.circle) {
      case 'circle-left-right':
        widgets.add(
          const Positioned(top: 20, left: 20, child: _PunchCircle()),
        );
        widgets.add(
          const Positioned(top: 20, right: 20, child: _PunchCircle()),
        );
        break;
      case 'circle-center':
        widgets.add(
          const Positioned(
            top: 20,
            left: 0,
            right: 0,
            child: Align(
              alignment: Alignment.topCenter,
              child: _PunchCircle(),
            ),
          ),
        );
        break;
    }

    return widgets;
  }
}

class _PunchLong extends StatelessWidget {
  const _PunchLong();

  @override
  Widget build(BuildContext context) {
    return Container(
      width: 64,
      height: 16,
      decoration: BoxDecoration(
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: const Color(0xFFE5E7EB)),
      ),
    );
  }
}

class _PunchCircle extends StatelessWidget {
  const _PunchCircle();

  @override
  Widget build(BuildContext context) {
    return Container(
      width: 20,
      height: 20,
      decoration: BoxDecoration(
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: const Color(0xFFE5E7EB)),
      ),
    );
  }
}

class _BadgeBox extends StatelessWidget {
  final Map<String, dynamic> box;
  final Map<String, String>? data;

  const _BadgeBox({required this.box, this.data});

  @override
  Widget build(BuildContext context) {
    final rect = badgeBoxRect(box);
    final rotation = badgeBoxRotation(box);
    final fill = badgeBoxFill(box);
    final border = badgeBoxBorder(box);
    final type = box['type']?.toString() ?? '';

    Widget child;
    if (badgeTextTypes.contains(type)) {
      child = _TextBox(box: box, data: data);
    } else if (type == 'img' || type == 'background') {
      child = _ImageBox(box: box, data: data);
    } else if (type == 'avatar') {
      child = _AvatarBox(box: box, data: data);
    } else if (type == 'qrcode') {
      child = _QrBox(box: box, data: data);
    } else {
      child = const SizedBox.shrink();
    }

    return Positioned(
      left: rect.left,
      top: rect.top,
      width: rect.width,
      height: rect.height,
      child: Transform.rotate(
        angle: rotation,
        child: Container(
          decoration: BoxDecoration(
            color: fill,
            border: border,
          ),
          clipBehavior: Clip.hardEdge,
          child: child,
        ),
      ),
    );
  }
}

class _TextBox extends StatelessWidget {
  final Map<String, dynamic> box;
  final Map<String, String>? data;

  const _TextBox({required this.box, this.data});

  @override
  Widget build(BuildContext context) {
    final layout = badgeTextLayout(box);
    final text = applyTextTransform(badgeText(box, data), box);
    if (text.isEmpty) return const SizedBox.shrink();

    return Align(
      alignment: layout.alignment,
      child: Text(
        text,
        textAlign: layout.textAlign,
        maxLines: layout.maxLines,
        overflow: layout.overflow,
        style: TextStyle(
          fontSize: layout.fontSize,
          fontWeight: layout.fontWeight,
          fontStyle: layout.fontStyle,
          decoration: layout.decoration,
          color: layout.color,
          height: 1.25,
        ),
      ),
    );
  }
}

class _ImageBox extends StatelessWidget {
  final Map<String, dynamic> box;
  final Map<String, String>? data;

  const _ImageBox({required this.box, this.data});

  @override
  Widget build(BuildContext context) {
    final url = badgeImage(box, data);
    if (url.isEmpty) return const SizedBox.shrink();
    return CustomImage(
      AppConfig.resolveMediaUrl(url),
      fit: badgeImageFit(box),
      width: double.infinity,
      height: double.infinity,
    );
  }
}

class _AvatarBox extends StatelessWidget {
  final Map<String, dynamic> box;
  final Map<String, String>? data;

  const _AvatarBox({required this.box, this.data});

  @override
  Widget build(BuildContext context) {
    final frame = badgeAvatarFrame(box);
    final url = badgeImage(box, data);

    return Container(
      width: double.infinity,
      height: double.infinity,
      decoration: BoxDecoration(
        color: frame.backgroundColor,
        borderRadius: frame.borderRadius,
        border: frame.border,
        boxShadow: frame.boxShadow,
      ),
      clipBehavior: Clip.hardEdge,
      child: url.isEmpty
          ? null
          : CustomImage(
              AppConfig.resolveMediaUrl(url),
              fit: BoxFit.cover,
              width: double.infinity,
              height: double.infinity,
            ),
    );
  }
}

class _QrBox extends StatelessWidget {
  final Map<String, dynamic> box;
  final Map<String, String>? data;

  const _QrBox({required this.box, this.data});

  @override
  Widget build(BuildContext context) {
    final opts = badgeQr(box, data);
    return ColoredBox(
      color: opts.whiteColor == Colors.transparent
          ? Colors.white
          : opts.whiteColor,
      child: QrImageView(
        data: opts.value,
        version: QrVersions.auto,
        eyeStyle: QrEyeStyle(
          eyeShape: QrEyeShape.square,
          color: opts.blackColor,
        ),
        dataModuleStyle: QrDataModuleStyle(
          dataModuleShape: QrDataModuleShape.square,
          color: opts.blackColor,
        ),
        backgroundColor: Colors.transparent,
        padding: EdgeInsets.zero,
      ),
    );
  }
}
