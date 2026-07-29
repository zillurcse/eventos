import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../onboarding_provider.dart';

const _pageAssets = [
  'assets/onboarding/onboarding_1.png',
  'assets/onboarding/onboarding_2.png',
  'assets/onboarding/onboarding_3.png',
];

/// Onboarding carousel using the provided splash/onboarding designs.
class OnboardingScreen extends ConsumerStatefulWidget {
  const OnboardingScreen({super.key});

  @override
  ConsumerState<OnboardingScreen> createState() => _OnboardingScreenState();
}

class _OnboardingScreenState extends ConsumerState<OnboardingScreen> {
  final _controller = PageController();
  int _index = 0;

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  Future<void> _finish() async {
    await ref.read(onboardingProvider.notifier).complete();
    if (mounted) context.go('/event');
  }

  void _next() {
    if (_index >= _pageAssets.length - 1) {
      _finish();
      return;
    }
    _controller.nextPage(
      duration: const Duration(milliseconds: 320),
      curve: Curves.easeOutCubic,
    );
  }

  void _previous() {
    if (_index == 0) return;
    _controller.previousPage(
      duration: const Duration(milliseconds: 320),
      curve: Curves.easeOutCubic,
    );
  }

  @override
  Widget build(BuildContext context) {
    return AnnotatedRegion<SystemUiOverlayStyle>(
      value: SystemUiOverlayStyle.dark.copyWith(
        statusBarColor: Colors.transparent,
      ),
      child: Scaffold(
        backgroundColor: Colors.white,
        body: PageView.builder(
          controller: _controller,
          itemCount: _pageAssets.length,
          onPageChanged: (value) => setState(() => _index = value),
          itemBuilder: (context, index) {
            return _OnboardingFrame(
              asset: _pageAssets[index],
              isFirst: index == 0,
              onSkip: _finish,
              onPrevious: _previous,
              onNext: _next,
            );
          },
        ),
      ),
    );
  }
}

class _OnboardingFrame extends StatelessWidget {
  const _OnboardingFrame({
    required this.asset,
    required this.isFirst,
    required this.onSkip,
    required this.onPrevious,
    required this.onNext,
  });

  final String asset;
  final bool isFirst;
  final VoidCallback onSkip;
  final VoidCallback onPrevious;
  final VoidCallback onNext;

  @override
  Widget build(BuildContext context) {
    return Stack(
      fit: StackFit.expand,
      children: [
        Image.asset(
          asset,
          fit: BoxFit.cover,
          alignment: Alignment.topCenter,
        ),
        // Transparent hit targets over the design's Skip / nav buttons.
        SafeArea(
          child: Align(
            alignment: Alignment.topRight,
            child: GestureDetector(
              behavior: HitTestBehavior.opaque,
              onTap: onSkip,
              child: const SizedBox(
                width: 96,
                height: 48,
              ),
            ),
          ),
        ),
        Align(
          alignment: Alignment.bottomCenter,
          child: SafeArea(
            child: Padding(
              padding: const EdgeInsets.fromLTRB(24, 0, 24, 18),
              child: SizedBox(
                height: 56,
                child: isFirst
                    ? GestureDetector(
                        behavior: HitTestBehavior.opaque,
                        onTap: onNext,
                        child: const SizedBox.expand(),
                      )
                    : Row(
                        children: [
                          Expanded(
                            child: GestureDetector(
                              behavior: HitTestBehavior.opaque,
                              onTap: onPrevious,
                              child: const SizedBox.expand(),
                            ),
                          ),
                          const SizedBox(width: 12),
                          Expanded(
                            child: GestureDetector(
                              behavior: HitTestBehavior.opaque,
                              onTap: onNext,
                              child: const SizedBox.expand(),
                            ),
                          ),
                        ],
                      ),
              ),
            ),
          ),
        ),
      ],
    );
  }
}
