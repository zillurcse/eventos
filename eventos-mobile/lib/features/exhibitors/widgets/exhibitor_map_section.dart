import 'package:flutter/material.dart';
import 'package:flutter_screenutil/flutter_screenutil.dart';
import 'package:url_launcher/url_launcher.dart';
import '../../../models/exhibitor_model.dart';
import '../../../utils/extension/theme_ext.dart';
import '../../../widgets/custom_image.dart';

class ExhibitorMapSection extends StatelessWidget {
  final ExhibitorModel exhibitor;

  const ExhibitorMapSection({super.key, required this.exhibitor});

  Future<void> _openMap(String locationOrAddress) async {
    if (locationOrAddress.isEmpty) return;
    
    // Check coordinates pattern (e.g. 34.148, -118.796)
    final coordsRegExp = RegExp(r'^-?\d+(\.\d+)?,\s*-?\d+(\.\d+)?$');
    final isCoordinates = coordsRegExp.hasMatch(locationOrAddress.trim());

    final String query = Uri.encodeComponent(locationOrAddress);
    final googleUrl = isCoordinates
        ? 'https://www.google.com/maps/search/?api=1&query=$query'
        : 'https://www.google.com/maps/search/?api=1&query=$query';
    final appleUrl = 'https://maps.apple.com/?q=$query';

    final uriGoogle = Uri.tryParse(googleUrl);
    final uriApple = Uri.tryParse(appleUrl);

    if (uriGoogle != null && await canLaunchUrl(uriGoogle)) {
      await launchUrl(uriGoogle, mode: LaunchMode.externalApplication);
    } else if (uriApple != null && await canLaunchUrl(uriApple)) {
      await launchUrl(uriApple, mode: LaunchMode.externalApplication);
    }
  }

  @override
  Widget build(BuildContext context) {
    // Determine target location to show/search
    final String location = (exhibitor.location?.isNotEmpty == true
            ? exhibitor.location
            : exhibitor.address?.isNotEmpty == true
                ? exhibitor.address
                : 'Agoura Hills, CA') ??
        'Agoura Hills, CA';

    // Static map URL using Yandex Maps API (centered around Agoura Hills/Thousand Oaks)
    // -118.7616 (lng), 34.1533 (lat)
    const String mapImgUrl =
        'https://static-maps.yandex.ru/1.x/?ll=-118.7616,34.1533&z=12&size=600,250&l=map&pt=-118.7616,34.1533,pm2blm';

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          'Map',
          style: context.h2?.copyWith(color: context.heading),
        ),
        SizedBox(height: 16.h),
        GestureDetector(
          onTap: () => _openMap(location),
          child: Container(
            height: 150.h,
            width: double.infinity,
            decoration: BoxDecoration(
              borderRadius: BorderRadius.circular(12.r),
              border: Border.all(color: context.strokeLight),
              boxShadow: [
                BoxShadow(
                  color: Colors.black.withValues(alpha: 0.03),
                  blurRadius: 8.r,
                  offset: Offset(0, 4.h),
                ),
              ],
            ),
            child: ClipRRect(
              borderRadius: BorderRadius.circular(12.r),
              child: Stack(
                fit: StackFit.expand,
                children: [
                  CustomImage(
                    mapImgUrl,
                    fit: BoxFit.cover,
                  ),
                  // Compass/Nav micro-button overlay
                  Positioned(
                    bottom: 12.h,
                    right: 12.w,
                    child: Container(
                      padding: EdgeInsets.all(8.sp),
                      decoration: const BoxDecoration(
                        color: Colors.white,
                        shape: BoxShape.circle,
                      ),
                      child: Icon(
                        Icons.navigation_outlined,
                        color: context.primaryTheme,
                        size: 20.sp,
                      ),
                    ),
                  ),
                ],
              ),
            ),
          ),
        ),
      ],
    );
  }
}
