import 'package:flutter/material.dart';
import 'package:flutter_screenutil/flutter_screenutil.dart';
import 'package:get/get.dart';

import '../../../models/my_event.dart';
import '../../../utils/bindings/auth_binding.dart';
import '../../../utils/extension/theme_ext.dart';
import '../../../utils/helpers/app_data_provider.dart';
import '../../../utils/helpers/open_event_app.dart';
import '../../../widgets/custom_image.dart';
import '../../../widgets/headers/auth_header.dart';
import '../../../widgets/state_handler/api_state_handler.dart';
import '../auth_controller.dart';

/// Shown after login when the account belongs to more than one event,
/// or from the drawer to switch events without logging out.
class SelectEventView extends StatefulWidget {
  final List<MyEvent>? events;
  final bool isSwitching;

  const SelectEventView({
    super.key,
    this.events,
    this.isSwitching = false,
  });

  @override
  State<SelectEventView> createState() => _SelectEventViewState();
}

class _SelectEventViewState extends State<SelectEventView> {
  late final AuthController _controller;
  late final List<MyEvent> _seededEvents;

  String? get _currentUuid => AppDataProvider.obj.eventUuid;

  @override
  void initState() {
    super.initState();
    if (!Get.isRegistered<AuthController>()) {
      AuthBinding().dependencies();
    }
    _controller = Get.find<AuthController>();
    _seededEvents = widget.events ?? _controller.availableEvents.toList();

    if (_seededEvents.isEmpty) {
      WidgetsBinding.instance.addPostFrameCallback((_) {
        _controller.loadMyEvents();
      });
    }
  }

  void _onSelect(MyEvent event) {
    _controller.applySelectedEvent(event);
    openEventApp();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: context.tertiaryText,
      body: Column(
        children: [
          const AuthHeader(),
          Expanded(
            child: Padding(
              padding: EdgeInsets.fromLTRB(16.w, 20.h, 16.w, 16.h),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  if (widget.isSwitching)
                    IconButton(
                      padding: EdgeInsets.zero,
                      alignment: Alignment.centerLeft,
                      constraints: const BoxConstraints(),
                      icon: Icon(
                        Icons.arrow_back_ios_new,
                        size: 18.sp,
                        color: context.body,
                      ),
                      onPressed: () => Get.back(),
                    ),
                  if (widget.isSwitching) SizedBox(height: 12.h),
                  Text(
                    widget.isSwitching ? 'Switch event' : 'Select an event',
                    style: context.h5,
                  ),
                  SizedBox(height: 6.h),
                  Text(
                    widget.isSwitching
                        ? 'Choose another event associated with your account.'
                        : 'Your account is linked to more than one event. Pick which one to open.',
                    style: context.bodyRegular?.copyWith(color: context.caption),
                  ),
                  SizedBox(height: 20.h),
                  Expanded(
                    child: _seededEvents.isNotEmpty
                        ? _buildList(context, _seededEvents)
                        : Obx(() {
                            final events = _controller.availableEvents.toList();
                            return ApiStateHandler(
                              state: _controller.myEventsStatus.value,
                              onRetry: () => _controller.loadMyEvents(),
                              initElement: const SizedBox.shrink(),
                              loadedElement: _buildList(context, events),
                            );
                          }),
                  ),
                ],
              ),
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildList(BuildContext context, List<MyEvent> events) {
    if (events.isEmpty) {
      return Center(
        child: Text(
          'No events found for this account.',
          style: context.bodyRegular?.copyWith(color: context.caption),
          textAlign: TextAlign.center,
        ),
      );
    }

    return ListView.separated(
      padding: EdgeInsets.only(bottom: 24.h),
      itemCount: events.length,
      separatorBuilder: (_, __) => SizedBox(height: 10.h),
      itemBuilder: (context, index) {
        final event = events[index];
        final isCurrent =
            widget.isSwitching &&
            _currentUuid != null &&
            _currentUuid == event.uuid;
        return _EventTile(
          event: event,
          isCurrent: isCurrent,
          onTap: () => _onSelect(event),
        );
      },
    );
  }
}

class _EventTile extends StatelessWidget {
  final MyEvent event;
  final bool isCurrent;
  final VoidCallback onTap;

  const _EventTile({
    required this.event,
    required this.isCurrent,
    required this.onTap,
  });

  @override
  Widget build(BuildContext context) {
    final logo = event.logoUrl;
    final hasLogo = logo != null && logo.isNotEmpty;
    final org = event.organizationName?.trim();

    return Material(
      color: context.tertiaryText,
      borderRadius: BorderRadius.circular(12.r),
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(12.r),
        child: Container(
          padding: EdgeInsets.symmetric(horizontal: 14.w, vertical: 14.h),
          decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(12.r),
            border: Border.all(
              color: isCurrent ? context.primaryTheme : context.strokeLight,
              width: isCurrent ? 1.5 : 1,
            ),
          ),
          child: Row(
            children: [
              ClipRRect(
                borderRadius: BorderRadius.circular(10.r),
                child: Container(
                  height: 52.sp,
                  width: 52.sp,
                  color: context.primaryFocused,
                  alignment: Alignment.center,
                  child: hasLogo
                      ? CustomImage(
                          logo,
                          height: 52.sp,
                          width: 52.sp,
                          fit: BoxFit.cover,
                          radius: 10.r,
                        )
                      : Icon(
                          Icons.event_outlined,
                          size: 24.sp,
                          color: context.primaryTheme,
                        ),
                ),
              ),
              SizedBox(width: 12.w),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      event.name,
                      style: context.titleRegular?.copyWith(
                        fontWeight: FontWeight.w600,
                        color: context.heading,
                      ),
                      maxLines: 2,
                      overflow: TextOverflow.ellipsis,
                    ),
                    if (org != null && org.isNotEmpty) ...[
                      SizedBox(height: 4.h),
                      Text(
                        org,
                        style: context.bodyRegular?.copyWith(
                          color: context.caption,
                          fontSize: 12.sp,
                        ),
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                      ),
                    ],
                    if (isCurrent) ...[
                      SizedBox(height: 4.h),
                      Text(
                        'Current',
                        style: context.specialCaption2?.copyWith(
                          color: context.primaryTheme,
                          fontWeight: FontWeight.w600,
                        ),
                      ),
                    ],
                  ],
                ),
              ),
              Icon(
                Icons.chevron_right,
                color: context.caption,
                size: 22.sp,
              ),
            ],
          ),
        ),
      ),
    );
  }
}
