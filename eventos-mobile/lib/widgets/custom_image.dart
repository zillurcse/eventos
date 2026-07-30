import 'package:cached_network_image/cached_network_image.dart';
import 'package:flutter/material.dart';
import 'package:flutter_svg/flutter_svg.dart';

import '../utils/enum/enums.dart';

class CustomImage extends StatelessWidget {
  final double? height;
  final double? width;
  final String src;
  final BoxFit fit;
  final Color? color;
  final double radius;
  final bool avatar;
  final bool isCircle;
  final Widget? placeholder;
  final Widget? errorWidget;
  final Map<String, String>? httpHeaders;
  final BorderRadius? borderRadius;

  const CustomImage(
    this.src, {
    super.key,
    this.height,
    this.width,
    this.fit = BoxFit.cover,
    this.color,
    this.radius = 0,
    this.avatar = false,
    this.isCircle = false,
    this.placeholder,
    this.errorWidget,
    this.httpHeaders,
    this.borderRadius,
  });

  ImageSource get _source {
    if (src.endsWith('.svg')) return ImageSource.svg;
    if (src.startsWith('http://') || src.startsWith('https://')) return ImageSource.network;
    return ImageSource.asset;
  }

  bool get _isNetwork => _source == ImageSource.network;

  ColorFilter? get _colorFilter =>
      color != null ? ColorFilter.mode(color!, BlendMode.srcIn) : null;

  Widget _buildSvg() {
    if (_isNetwork) {
      return SvgPicture.network(
        src,
        height: height,
        width: width,
        fit: fit,
        colorFilter: _colorFilter,
        placeholderBuilder: (_) => _buildPlaceholder(),
      );
    }
    return SvgPicture.asset(
      src,
      height: height,
      width: width,
      fit: fit,
      colorFilter: _colorFilter,
    );
  }

  Widget _buildAsset() => Image.asset(
    src,
    height: height,
    width: width,
    fit: fit,
    color: color,
    errorBuilder: (_, __, ___) => _buildError(),
  );

  Widget _buildNetwork() => CachedNetworkImage(
    imageUrl: src,
    height: height,
    width: width,
    fit: fit,
    httpHeaders: httpHeaders,
    color: color,
    colorBlendMode: color != null ? BlendMode.srcIn : null,
    progressIndicatorBuilder: (_, __, ___) => _buildPlaceholder(),
    errorWidget: (_, __, ___) => _buildError(),
  );

  Widget _buildPlaceholder() =>
      placeholder ??
      (avatar
          ? Image.asset(
              'assets/png/user_placeholder.png',
              height: height,
              width: width,
              fit: fit,
            )
          : SvgPicture.asset(
              "assets/svg/img/place_holder.svg",
              height: height,
              width: width,
              fit: fit,
              colorFilter: _colorFilter,
            ));

  Widget _buildError() =>
      errorWidget ??
      (avatar
          ? Image.asset(
              'assets/png/user_placeholder.png',
              height: height,
              width: width,
              fit: fit,
            )
          : SvgPicture.asset(
              "assets/svg/img/place_holder.svg",
              height: height,
              width: width,
              fit: fit,
              colorFilter: _colorFilter,
            ));

  Widget _applyClip(Widget child) {
    if (isCircle) {
      return ClipOval(child: child);
    }

    final effectiveBorderRadius =
        borderRadius ?? (radius > 0 ? BorderRadius.circular(radius) : null);

    if (effectiveBorderRadius != null) {
      return ClipRRect(borderRadius: effectiveBorderRadius, child: child);
    }

    return child;
  }

  @override
  Widget build(BuildContext context) {
    if (src.trim().isEmpty) return _applyClip(_buildError());

    final Widget image = switch (_source) {
      ImageSource.svg => _buildSvg(),
      ImageSource.asset => _buildAsset(),
      ImageSource.network => _buildNetwork(),
    };

    return _applyClip(image);
  }
}
