import 'package:flutter/material.dart';

/// Session strip ad slot. Reception `ads.strip` is stored on
/// [AdsModel.strip] for a future placement - not shown yet.
class AdBanner extends StatelessWidget {
  const AdBanner({super.key});

  @override
  Widget build(BuildContext context) {
    return const SliverToBoxAdapter(child: SizedBox.shrink());
  }
}
