import 'package:flutter/material.dart';
import 'package:get/get.dart';

import '../../../widgets/loading_skeletons/event_feed_skeleton.dart';
import '../../../widgets/state_handler/api_state_handler.dart';
import 'event_feed_controller.dart';
import 'widgets/create_post_widget.dart';
import 'widgets/feed_posts_list.dart';
import 'widgets/search_widget.dart';

class EventFeedView extends StatefulWidget {
  const EventFeedView({super.key});

  @override
  State<EventFeedView> createState() => _EventFeedViewState();
}

class _EventFeedViewState extends State<EventFeedView> {
  late final EventFeedController ctrl;

  @override
  void initState() {
    super.initState();
    ctrl = Get.find<EventFeedController>();
  }

  @override
  Widget build(BuildContext context) {
    return Obx(
      () => ApiStateHandler(
        state: ctrl.feedStatus.value,
        onRetry: ctrl.fetchFeed,
        skeleton: const EventFeedSkeleton(),
        loadedElement: _buildFeedList(context),
        initElement: const EventFeedSkeleton(),
      ),
    );
  }

  Widget _buildFeedList(BuildContext context) {
    return NotificationListener<ScrollNotification>(
      onNotification: (notification) {
        if (notification is ScrollEndNotification &&
            notification.metrics.pixels >=
                notification.metrics.maxScrollExtent - 200) {
          ctrl.loadMore();
        }
        return false;
      },
      child: RefreshIndicator(
        elevation: 0.5,
        color: Theme.of(context).colorScheme.primary,
        backgroundColor: Theme.of(context).colorScheme.primary.withOpacity(0.1),
        onRefresh: ctrl.fetchFeed,
        child: const CustomScrollView(
          slivers: [
            SearchWidget(),
            CreatePostWidget(),
            FeedPostsList(),
          ],
        ),
      ),
    );
  }
}
