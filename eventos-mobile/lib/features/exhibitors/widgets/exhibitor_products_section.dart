import 'package:flutter/material.dart';
import 'package:flutter_screenutil/flutter_screenutil.dart';
import '../../../models/exhibitor_model.dart';
import '../../../utils/extension/theme_ext.dart';
import '../../../widgets/custom_image.dart';

class ExhibitorProductsSection extends StatelessWidget {
  final ExhibitorModel exhibitor;

  const ExhibitorProductsSection({super.key, required this.exhibitor});

  @override
  Widget build(BuildContext context) {
    final List<dynamic> productList = List.from(exhibitor.products);

    // Mock product fallback matching the mockup UI
    if (productList.isEmpty) {
      productList.add({
        'name': 'defigo',
        'image': '',
        'bg_color': const Color(0xFF2264F6),
      });
    }

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          'Products (${productList.length})',
          style: context.h2?.copyWith(color: context.heading),
        ),
        SizedBox(height: 16.h),
        SizedBox(
          height: 130.h,
          child: ListView.separated(
            scrollDirection: Axis.horizontal,
            itemCount: productList.length,
            separatorBuilder: (context, index) => SizedBox(width: 12.w),
            itemBuilder: (context, index) {
              final item = productList[index];
              return _buildProductCard(context, item);
            },
          ),
        ),
      ],
    );
  }

  Widget _buildProductCard(BuildContext context, dynamic item) {
    String name = '';
    String imageUrl = '';
    Color? bgColor;

    if (item is Map) {
      name = item['name']?.toString() ?? item['title']?.toString() ?? '';
      imageUrl = item['image']?.toString() ?? item['image_url']?.toString() ?? '';
      if (item['bg_color'] is Color) {
        bgColor = item['bg_color'] as Color;
      }
    } else if (item is String) {
      name = item;
    }

    final hasImage = imageUrl.isNotEmpty;

    return Container(
      width: 130.w,
      height: 130.h,
      decoration: BoxDecoration(
        color: bgColor ?? const Color(0xFF2264F6),
        borderRadius: BorderRadius.circular(12.r),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withValues(alpha: 0.05),
            blurRadius: 6.r,
            offset: Offset(0, 2.h),
          ),
        ],
      ),
      child: ClipRRect(
        borderRadius: BorderRadius.circular(12.r),
        child: hasImage
            ? Stack(
                fit: StackFit.expand,
                children: [
                  CustomImage(
                    imageUrl,
                    fit: BoxFit.cover,
                  ),
                  Container(
                    color: Colors.black.withValues(alpha: 0.1),
                  ),
                  Positioned(
                    bottom: 12.h,
                    left: 12.w,
                    right: 12.w,
                    child: Text(
                      name,
                      style: context.bodyRegular?.copyWith(
                        color: Colors.white,
                        fontWeight: FontWeight.bold,
                      ),
                      maxLines: 2,
                      overflow: TextOverflow.ellipsis,
                    ),
                  ),
                ],
              )
            : Center(
                child: Padding(
                  padding: EdgeInsets.symmetric(horizontal: 8.w),
                  child: Text(
                    name.toLowerCase(),
                    textAlign: TextAlign.center,
                    style: TextStyle(
                      fontFamily: 'Outfit',
                      fontSize: 22.sp,
                      fontWeight: FontWeight.bold,
                      color: Colors.white,
                    ),
                  ),
                ),
              ),
      ),
    );
  }
}
