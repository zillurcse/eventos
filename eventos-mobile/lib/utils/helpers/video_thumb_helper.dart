import 'dart:io';
import 'dart:typed_data';

import 'package:image/image.dart' as img;
import 'package:video_thumbnail/video_thumbnail.dart';

/// Crop + JPEG-compress feed video thumbnails to match the video aspect.
class VideoThumbHelper {
  VideoThumbHelper._();

  static const int maxEdge = 1280;
  static const int maxInputBytes = 5 * 1024 * 1024; // 5 MB pick limit
  static const int targetBytes = 450 * 1024;
  static const double fallbackAspect = 16 / 9;

  /// Grab a JPEG frame from [videoPath] at [timeMs], then crop/compress.
  static Future<File> frameFromVideo({
    required String videoPath,
    required int timeMs,
    double aspect = fallbackAspect,
  }) async {
    final bytes = await VideoThumbnail.thumbnailData(
      video: videoPath,
      imageFormat: ImageFormat.JPEG,
      timeMs: timeMs < 0 ? 0 : timeMs,
      quality: 90,
      maxWidth: maxEdge,
    );
    if (bytes == null || bytes.isEmpty) {
      throw Exception('Couldn’t capture this frame.');
    }
    return compressBytes(bytes, aspect: aspect);
  }

  /// Center-crop a custom image to [aspect] and JPEG-compress it.
  static Future<File> compressFile(File file, {double aspect = fallbackAspect}) async {
    final bytes = await file.readAsBytes();
    return compressBytes(bytes, aspect: aspect);
  }

  static Future<File> compressBytes(
    Uint8List bytes, {
    double aspect = fallbackAspect,
  }) async {
    final decoded = img.decodeImage(bytes);
    if (decoded == null) throw Exception('Couldn’t read that image.');

    final cropped = _centerCrop(decoded, aspect);
    final scaled = _fitMaxEdge(cropped, maxEdge);

    var quality = 85;
    var encoded = Uint8List.fromList(img.encodeJpg(scaled, quality: quality));
    while (encoded.lengthInBytes > targetBytes && quality > 45) {
      quality -= 10;
      encoded = Uint8List.fromList(img.encodeJpg(scaled, quality: quality));
    }

    final out = File(
      '${Directory.systemTemp.path}/feed_thumb_${DateTime.now().millisecondsSinceEpoch}.jpg',
    );
    await out.writeAsBytes(encoded, flush: true);
    return out;
  }

  static img.Image _centerCrop(img.Image src, double aspect) {
    if (aspect <= 0) return src;
    var cropW = src.width;
    var cropH = src.height;
    if (src.width / src.height > aspect) {
      cropW = (src.height * aspect).round().clamp(1, src.width);
    } else {
      cropH = (src.width / aspect).round().clamp(1, src.height);
    }
    final x = ((src.width - cropW) / 2).floor().clamp(0, src.width - 1);
    final y = ((src.height - cropH) / 2).floor().clamp(0, src.height - 1);
    return img.copyCrop(src, x: x, y: y, width: cropW, height: cropH);
  }

  static img.Image _fitMaxEdge(img.Image src, int edge) {
    final longest = src.width > src.height ? src.width : src.height;
    if (longest <= edge) return src;
    if (src.width >= src.height) {
      return img.copyResize(src, width: edge);
    }
    return img.copyResize(src, height: edge);
  }
}
