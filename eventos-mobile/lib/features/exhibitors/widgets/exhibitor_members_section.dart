import 'package:flutter/material.dart';
import 'package:flutter_screenutil/flutter_screenutil.dart';
import 'package:get/get.dart';
import '../../../models/exhibitor_model.dart';
import '../../../utils/extension/theme_ext.dart';
import '../../../widgets/custom_image.dart';

class ExhibitorMembersSection extends StatelessWidget {
  final ExhibitorModel exhibitor;

  const ExhibitorMembersSection({super.key, required this.exhibitor});

  @override
  Widget build(BuildContext context) {
    final List<dynamic> rawMembers = [];

    // Try reading actual lists from exhibitor object
    if (exhibitor.exhibitorMembers.isNotEmpty) {
      rawMembers.addAll(exhibitor.exhibitorMembers);
    } else if (exhibitor.exhibitorRepresentatives.isNotEmpty) {
      rawMembers.addAll(exhibitor.exhibitorRepresentatives);
    } else if (exhibitor.representative.isNotEmpty) {
      rawMembers.addAll(exhibitor.representative);
    }

    // Default mock list matching the UI mockup if none are provided
    if (rawMembers.isEmpty) {
      rawMembers.addAll([
        {
          'id': 1,
          'name': 'Leslie Alexander',
          'designation': 'Director of Sales',
          'company': 'Expouse',
          'image': 'https://images.unsplash.com/photo-1494790108377-be9c29b29330?q=80&w=200',
        },
        {
          'id': 2,
          'name': 'Ronald Richards',
          'designation': 'Director of Marketing',
          'company': 'Expouse',
          'image': 'https://images.unsplash.com/photo-1500648767791-00dcc994a43e?q=80&w=200',
        },
        {
          'id': 3,
          'name': 'Esther Howard',
          'designation': 'Lead Marketing',
          'company': 'Expouse',
          'image': 'https://images.unsplash.com/photo-1438761681033-6461ffad8d80?q=80&w=200',
        },
        {
          'id': 4,
          'name': 'Floyd Miles',
          'designation': 'Product Manager',
          'company': 'Expouse',
          'image': 'https://images.unsplash.com/photo-1472099645785-5658abf4ff4e?q=80&w=200',
        },
      ]);
    }

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          'Members (${rawMembers.length})',
          style: context.h2?.copyWith(color: context.heading),
        ),
        SizedBox(height: 16.h),
        GridView.builder(
          physics: const NeverScrollableScrollPhysics(),
          shrinkWrap: true,
          gridDelegate: SliverGridDelegateWithFixedCrossAxisCount(
            crossAxisCount: 2,
            crossAxisSpacing: 12.w,
            mainAxisSpacing: 12.h,
            childAspectRatio: 0.72,
          ),
          itemCount: rawMembers.length,
          itemBuilder: (context, idx) {
            return ExhibitorMemberCard(member: rawMembers[idx]);
          },
        ),
      ],
    );
  }
}

class ExhibitorMemberCard extends StatefulWidget {
  final dynamic member;
  const ExhibitorMemberCard({super.key, required this.member});

  @override
  State<ExhibitorMemberCard> createState() => _ExhibitorMemberCardState();
}

class _ExhibitorMemberCardState extends State<ExhibitorMemberCard> {
  bool _isBookmarked = false;

  @override
  Widget build(BuildContext context) {
    final m = widget.member;

    String name = '';
    String designation = '';
    String company = 'Expouse';
    String imageUrl = '';

    if (m is Map) {
      name = m['name']?.toString() ?? m['full_name']?.toString() ?? '';
      designation = m['designation']?.toString() ?? m['position']?.toString() ?? m['role']?.toString() ?? '';
      company = m['company']?.toString() ?? m['company_name']?.toString() ?? 'Expouse';
      final String rawImg = m['image']?.toString() ?? m['image_url']?.toString() ?? m['avatar']?.toString() ?? '';
      imageUrl = rawImg.isEmpty
          ? ''
          : (rawImg.startsWith('http') ? rawImg : 'https://admin.expouse.com/storage/$rawImg');
    }

    return Container(
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(12.r),
        border: Border.all(color: context.strokeLight),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withValues(alpha: 0.02),
            blurRadius: 8.r,
            offset: Offset(0, 4.h),
          ),
        ],
      ),
      child: ClipRRect(
        borderRadius: BorderRadius.circular(12.r),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Expanded(
              child: Stack(
                children: [
                  Positioned.fill(
                    child: CustomImage(
                      imageUrl,
                      fit: BoxFit.cover,
                      avatar: true,
                    ),
                  ),
                  Positioned(
                    top: 8.h,
                    right: 8.w,
                    child: GestureDetector(
                      onTap: () {
                        setState(() {
                          _isBookmarked = !_isBookmarked;
                        });
                        Get.rawSnackbar(
                          message: _isBookmarked ? "$name bookmarked" : "$name removed from bookmarks",
                        );
                      },
                      child: Container(
                        padding: EdgeInsets.symmetric(horizontal: 8.w, vertical: 6.h),
                        decoration: BoxDecoration(
                          color: Colors.white,
                          borderRadius: BorderRadius.circular(6.r),
                          boxShadow: [
                            BoxShadow(
                              color: Colors.black.withValues(alpha: 0.1),
                              blurRadius: 4.r,
                              offset: const Offset(0, 1),
                            ),
                          ],
                        ),
                        child: CustomImage(
                          _isBookmarked ? "assets/svg/icons/bookmark_fill.svg" : "assets/svg/icons/bookmark.svg",
                          height: 14.sp,
                          width: 10.sp,
                          color: _isBookmarked ? context.primaryTheme : context.primaryTheme.withValues(alpha: 0.5),
                        ),
                      ),
                    ),
                  ),
                ],
              ),
            ),
            Padding(
              padding: EdgeInsets.all(8.sp),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                mainAxisSize: MainAxisSize.min,
                children: [
                  Text(
                    name,
                    style: context.bodyRegular?.copyWith(
                      fontWeight: FontWeight.bold,
                      color: context.heading,
                    ),
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                  ),
                  SizedBox(height: 2.h),
                  Text(
                    designation.isNotEmpty ? designation : 'Representative',
                    style: context.specialCaption2?.copyWith(
                      color: context.caption,
                    ),
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                  ),
                  SizedBox(height: 2.h),
                  Text(
                    company,
                    style: context.specialCaption2?.copyWith(
                      color: context.caption.withValues(alpha: 0.7),
                    ),
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                  ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}
