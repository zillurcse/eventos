import 'package:flutter/material.dart';
import 'package:flutter_screenutil/flutter_screenutil.dart';
import 'package:get/get.dart';
import '../../../models/session_detail_response_model.dart';
import '../../../models/exhibitor_model.dart';
import '../../../widgets/cards/exhibitor_card.dart';
import '../../../utils/extension/theme_ext.dart';
import '../../exhibitors/pages/exhibitor_details.dart';

class SessionSponsorsSection extends StatelessWidget {
  final SessionDetailModel detail;

  const SessionSponsorsSection({super.key, required this.detail});

  @override
  Widget build(BuildContext context) {
    if (detail.sponsors.isEmpty) return const SizedBox.shrink();

    return Container(
      width: double.infinity,
      padding: EdgeInsets.fromLTRB(16.w, 0, 16.w, 16.h),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            'Sponsors (${detail.sponsors.length})',
            style: context.h2?.copyWith(
              color: context.heading,
              fontWeight: FontWeight.bold,
            ),
          ),
          SizedBox(height: 12.h),
          SizedBox(
            height: (context.height * .27).sp,
            child: ListView.builder(
              scrollDirection: Axis.horizontal,
              itemCount: detail.sponsors.length,
              itemBuilder: (context, idx) {
                final ExhibitorModel spon = detail.sponsors[idx];
                return GestureDetector(
                  onTap: () {
                    Get.to(() => ExhibitorDetails(exhibitor: spon));
                  },
                  child: ExhibitorCard(
                    name: spon.name,
                    stallNo: spon.stallNo,
                    exhibitorType: spon.exhibitorType,
                    bannerUrl: spon.spotlightBannerUrl,
                    logoUrl: spon.logoUrl,
                  ),
                );
              },
            ),
          ),
        ],
      ),
    );
  }
}
