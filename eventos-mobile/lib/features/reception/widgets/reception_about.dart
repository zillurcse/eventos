import 'package:flutter/material.dart';

import '../../../core/theme/app_theme.dart';
import '../models/reception_models.dart';
import '../utils/reception_format.dart';

class ReceptionAboutSection extends StatefulWidget {
  const ReceptionAboutSection({super.key, required this.about});

  final ReceptionAbout about;

  @override
  State<ReceptionAboutSection> createState() => _ReceptionAboutSectionState();
}

class _ReceptionAboutSectionState extends State<ReceptionAboutSection> {
  bool _expanded = false;

  static const _socialIcons = {
    'website': Icons.language,
    'facebook': Icons.facebook,
    'instagram': Icons.camera_alt_outlined,
    'linkedin': Icons.business_center_outlined,
    'twitter': Icons.alternate_email,
    'whatsapp': Icons.chat,
  };

  @override
  Widget build(BuildContext context) {
    final about = widget.about;
    final date = formatDateRange(about.startsAt, about.endsAt);
    final time = formatTimeRange(about.startsAt, about.endsAt);
    final location = about.locationText;
    final description = stripHtml(about.description);
    final socials = about.social.entries.toList();

    return Padding(
      padding: const EdgeInsets.fromLTRB(20, 16, 20, 8),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            about.name.isNotEmpty ? about.name : 'Event',
            style: const TextStyle(
              fontSize: 22,
              fontWeight: FontWeight.w800,
              color: AppColors.headline,
              height: 1.25,
            ),
          ),
          if (date.isNotEmpty || time.isNotEmpty) ...[
            const SizedBox(height: 10),
            _MetaRow(
              icon: Icons.calendar_today_outlined,
              text: [
                if (date.isNotEmpty) date,
                if (time.isNotEmpty) time,
              ].join('  ·  '),
            ),
          ],
          if (location.isNotEmpty) ...[
            const SizedBox(height: 8),
            _MetaRow(icon: Icons.location_on_outlined, text: location),
          ],
          if (socials.isNotEmpty) ...[
            const SizedBox(height: 14),
            Wrap(
              spacing: 10,
              runSpacing: 10,
              children: [
                for (final entry in socials)
                  Container(
                    width: 40,
                    height: 40,
                    decoration: BoxDecoration(
                      color: Colors.white,
                      shape: BoxShape.circle,
                      border: Border.all(color: AppColors.inputBorder),
                    ),
                    child: Icon(
                      _socialIcons[entry.key] ?? Icons.link,
                      size: 18,
                      color: AppColors.label,
                    ),
                  ),
              ],
            ),
          ],
          if (description.isNotEmpty) ...[
            const SizedBox(height: 14),
            Text(
              description,
              maxLines: _expanded ? null : 3,
              overflow: _expanded ? TextOverflow.visible : TextOverflow.ellipsis,
              style: const TextStyle(
                fontSize: 14,
                height: 1.5,
                color: AppColors.body,
              ),
            ),
            const SizedBox(height: 4),
            TextButton(
              onPressed: () => setState(() => _expanded = !_expanded),
              style: TextButton.styleFrom(
                foregroundColor: AppColors.brandPurple,
                padding: EdgeInsets.zero,
                minimumSize: Size.zero,
                tapTargetSize: MaterialTapTargetSize.shrinkWrap,
              ),
              child: Text(
                _expanded ? 'Show less' : 'View Details',
                style: const TextStyle(fontWeight: FontWeight.w600),
              ),
            ),
          ],
        ],
      ),
    );
  }
}

class _MetaRow extends StatelessWidget {
  const _MetaRow({required this.icon, required this.text});

  final IconData icon;
  final String text;

  @override
  Widget build(BuildContext context) {
    return Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Icon(icon, size: 18, color: AppColors.label),
        const SizedBox(width: 8),
        Expanded(
          child: Text(
            text,
            style: const TextStyle(
              fontSize: 13,
              color: AppColors.label,
              height: 1.35,
            ),
          ),
        ),
      ],
    );
  }
}
