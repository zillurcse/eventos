import 'package:flutter/material.dart';
import 'package:get/get.dart';
import '../delegate_controller.dart';
import 'delegate_card.dart';

class DelegateList extends StatelessWidget {
  const DelegateList({super.key});

  @override
  Widget build(BuildContext context) {
    final controller = Get.find<DelegateController>();
    return Obx(() {
      final delegates = controller.delegates;

      return SliverList(
        delegate: SliverChildBuilderDelegate(
          (context, index) {
            if (index >= delegates.length) return null;
            return DelegateCard(delegate: delegates[index]);
          },
          childCount: delegates.length,
        ),
      );
    });
  }
}
