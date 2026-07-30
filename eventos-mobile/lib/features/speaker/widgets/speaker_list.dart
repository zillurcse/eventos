import 'package:flutter/material.dart';
import 'package:get/get.dart';
import '../speaker_controller.dart';
import 'speaker_card.dart';

class SpeakerList extends StatelessWidget {
  const SpeakerList({super.key});

  @override
  Widget build(BuildContext context) {
    final controller = Get.find<SpeakerController>();
    return Obx(() {
      final speakers = controller.speakers;

      return SliverList(
        delegate: SliverChildBuilderDelegate(
          (context, index) {
            if (index >= speakers.length) return null;
            return SpeakerCard(speaker: speakers[index]);
          },
          childCount: speakers.length,
        ),
      );
    });
  }
}
