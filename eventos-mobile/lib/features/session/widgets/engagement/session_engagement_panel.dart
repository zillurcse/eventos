import 'package:flutter/material.dart';
import 'package:flutter_screenutil/flutter_screenutil.dart';
import 'package:get/get.dart';

import '../../../../models/session_detail_response_model.dart';
import '../../../../models/session_engagement_models.dart';
import '../../../../utils/extension/theme_ext.dart';
import '../../../../widgets/custom_image.dart';
import '../../session_engagement_controller.dart';
import '../../session_phase.dart';

class SessionEngagementPanel extends StatefulWidget {
  final SessionDetailModel detail;

  const SessionEngagementPanel({super.key, required this.detail});

  @override
  State<SessionEngagementPanel> createState() => _SessionEngagementPanelState();
}

class _SessionEngagementPanelState extends State<SessionEngagementPanel> {
  SessionEngagementController? _ctrl;
  String? _boundTag;

  List<SessionEngagementTab> _enabledTabs(SessionDetailModel d) {
    final phase = SessionPhaseHelper.resolve(
      status: d.status,
      startsAt: d.startsAt,
      endsAt: d.endsAt,
    );
    // Match web: engagement is useful during live (and still readable after).
    if (phase == SessionPhase.upcoming) return const [];

    final tabs = <SessionEngagementTab>[];
    if (d.canLiveChat) tabs.add(SessionEngagementTab.chat);
    if (d.canQa) tabs.add(SessionEngagementTab.qa);
    if (d.canLivePolls) tabs.add(SessionEngagementTab.polls);
    if (d.canAttendeeList) tabs.add(SessionEngagementTab.attendees);
    return tabs;
  }

  @override
  void initState() {
    super.initState();
    _bind();
  }

  @override
  void didUpdateWidget(covariant SessionEngagementPanel oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (oldWidget.detail.uuid != widget.detail.uuid) {
      _unbind();
      _bind();
    }
  }

  void _bind() {
    final tabs = _enabledTabs(widget.detail);
    if (tabs.isEmpty || widget.detail.uuid.isEmpty) return;
    final tag = 'session_engage_${widget.detail.uuid}';
    _boundTag = tag;
    if (Get.isRegistered<SessionEngagementController>(tag: tag)) {
      Get.delete<SessionEngagementController>(tag: tag);
    }
    _ctrl = Get.put(
      SessionEngagementController(
        sessionUuid: widget.detail.uuid,
        enabledTabs: tabs,
      ),
      tag: tag,
    );
  }

  void _unbind() {
    final tag = _boundTag;
    if (tag != null &&
        Get.isRegistered<SessionEngagementController>(tag: tag)) {
      Get.delete<SessionEngagementController>(tag: tag);
    }
    _ctrl = null;
    _boundTag = null;
  }

  @override
  void dispose() {
    _unbind();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final tabs = _enabledTabs(widget.detail);
    final ctrl = _ctrl;
    if (tabs.isEmpty || ctrl == null) return const SizedBox.shrink();

    return Container(
      width: double.infinity,
      margin: EdgeInsets.only(bottom: 24.h),
      padding: EdgeInsets.symmetric(horizontal: 16.w),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            'Live Engagement',
            style: context.h2?.copyWith(
              color: context.heading,
              fontWeight: FontWeight.bold,
            ),
          ),
          SizedBox(height: 12.h),
          Obx(() {
            final active = ctrl.activeTab.value;
            return SingleChildScrollView(
              scrollDirection: Axis.horizontal,
              child: Row(
                children: tabs.map((tab) {
                  final selected = active == tab;
                  return Padding(
                    padding: EdgeInsets.only(right: 8.w),
                    child: ChoiceChip(
                      label: Text(_tabLabel(tab)),
                      selected: selected,
                      onSelected: (_) => ctrl.selectTab(tab),
                      color: WidgetStateProperty.all(
                        context.primaryTheme,
                      ),
                      checkmarkColor: context.tertiaryText,
                      labelStyle: context.bodyRegular?.copyWith(
                        color: context.tertiaryText,
                        fontWeight:
                            selected ? FontWeight.w600 : FontWeight.w400,
                      ),
                      side: const BorderSide(color: Colors.transparent),
                    ),
                  );
                }).toList(),
              ),
            );
          }),
          SizedBox(height: 12.h),
          Container(
            constraints: BoxConstraints(minHeight: 220.h, maxHeight: 360.h),
            decoration: BoxDecoration(
              color: Colors.white,
              borderRadius: BorderRadius.circular(12.r),
              border: Border.all(color: context.strokeLight),
            ),
            // Only rebuild tab shell on activeTab / bootstrap loading -
            // list updates are handled inside each tab's own Obx.
            child: Obx(() {
              final tab = ctrl.activeTab.value;
              if (ctrl.loading.value && !ctrl.hasLoadedOnce.value) {
                return const Center(child: CircularProgressIndicator());
              }
              switch (tab) {
                case SessionEngagementTab.chat:
                  return _ChatTab(ctrl: ctrl);
                case SessionEngagementTab.qa:
                  return _QaTab(ctrl: ctrl);
                case SessionEngagementTab.polls:
                  return _PollsTab(ctrl: ctrl);
                case SessionEngagementTab.attendees:
                  return _AttendeesTab(ctrl: ctrl);
              }
            }),
          ),
        ],
      ),
    );
  }

  String _tabLabel(SessionEngagementTab tab) {
    switch (tab) {
      case SessionEngagementTab.chat:
        return 'Chat';
      case SessionEngagementTab.qa:
        return 'Q&A';
      case SessionEngagementTab.polls:
        return 'Polls';
      case SessionEngagementTab.attendees:
        return 'Attendees';
    }
  }
}

class _ChatTab extends StatelessWidget {
  final SessionEngagementController ctrl;
  const _ChatTab({required this.ctrl});

  @override
  Widget build(BuildContext context) {
    return Column(
      children: [
        Expanded(
          child: Obx(() {
            if (ctrl.chat.isEmpty) {
              return Center(
                child: Text(
                  'No chats yet.\nType something to start.',
                  textAlign: TextAlign.center,
                  style: context.bodyRegular?.copyWith(color: context.caption),
                ),
              );
            }
            return ListView.builder(
              padding: EdgeInsets.all(12.sp),
              itemCount: ctrl.chat.length,
              itemBuilder: (_, i) {
                final m = ctrl.chat[i];
                if (m.isHidden && !m.isMine) return const SizedBox.shrink();
                return _MessageBubble(message: m);
              },
            );
          }),
        ),
        Obx(() {
          if (ctrl.isMuted.value) {
            return _MutedBar(
              text:
                  'The host has muted you for this session. You can still watch and vote.',
            );
          }
          if (!ctrl.canChat.value) {
            return const _MutedBar(text: 'Chat posting is disabled for your role.');
          }
          return _Composer(
            controller: ctrl.chatInput,
            hint: 'Say something…',
            sending: ctrl.sending.value,
            onSend: ctrl.sendChat,
          );
        }),
      ],
    );
  }
}

class _QaTab extends StatelessWidget {
  final SessionEngagementController ctrl;
  const _QaTab({required this.ctrl});

  @override
  Widget build(BuildContext context) {
    return Column(
      children: [
        Expanded(
          child: Obx(() {
            final published = ctrl.questions
                .where((q) => q.status == 'published' || q.isMine)
                .toList();
            if (published.isEmpty) {
              return Center(
                child: Text(
                  'No questions yet.\nAsk the first one.',
                  textAlign: TextAlign.center,
                  style: context.bodyRegular?.copyWith(color: context.caption),
                ),
              );
            }
            return ListView.builder(
              padding: EdgeInsets.all(12.sp),
              itemCount: published.length,
              itemBuilder: (_, i) {
                final q = published[i];
                return Container(
                  margin: EdgeInsets.only(bottom: 10.h),
                  padding: EdgeInsets.all(10.sp),
                  decoration: BoxDecoration(
                    color: context.backgroundColor,
                    borderRadius: BorderRadius.circular(8.r),
                  ),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Row(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Expanded(
                            child: Text(
                              q.body,
                              style: context.bodyRegular?.copyWith(
                                color: context.heading,
                                fontWeight: FontWeight.w500,
                              ),
                            ),
                          ),
                          GestureDetector(
                            onTap: () => ctrl.upvoteQuestion(q.id),
                            child: Row(
                              children: [
                                Icon(
                                  q.voted
                                      ? Icons.thumb_up
                                      : Icons.thumb_up_alt_outlined,
                                  size: 16.sp,
                                  color: context.primaryTheme,
                                ),
                                SizedBox(width: 4.w),
                                Text(
                                  '${q.upvotes}',
                                  style: context.specialCaption2?.copyWith(
                                    color: context.primaryTheme,
                                  ),
                                ),
                              ],
                            ),
                          ),
                        ],
                      ),
                      SizedBox(height: 6.h),
                      Text(
                        q.isMine ? 'You' : q.author,
                        style: context.specialCaption2
                            ?.copyWith(color: context.caption),
                      ),
                      if (q.replies.isNotEmpty) ...[
                        SizedBox(height: 8.h),
                        ...q.replies.map(
                          (r) => Padding(
                            padding: EdgeInsets.only(left: 8.w, top: 4.h),
                            child: Text(
                              '${r.isOfficial ? 'Host' : r.author}: ${r.body}',
                              style: context.bodyRegular?.copyWith(
                                color: context.caption,
                                fontSize: 12.sp,
                              ),
                            ),
                          ),
                        ),
                      ],
                    ],
                  ),
                );
              },
            );
          }),
        ),
        Obx(() {
          if (!ctrl.canAsk.value) {
            return const _MutedBar(
                text: 'Asking questions is disabled for your role.');
          }
          return _Composer(
            controller: ctrl.qaInput,
            hint: 'Ask a question…',
            sending: ctrl.sending.value,
            onSend: ctrl.askQuestion,
          );
        }),
      ],
    );
  }
}

class _PollsTab extends StatelessWidget {
  final SessionEngagementController ctrl;
  const _PollsTab({required this.ctrl});

  @override
  Widget build(BuildContext context) {
    return Obx(() {
      final livePolls = ctrl.polls
          .where((p) => p.status == 'live' || p.status == 'closed')
          .toList();
      if (livePolls.isEmpty) {
        return Center(
          child: Text(
            'No polls yet.',
            style: context.bodyRegular?.copyWith(color: context.caption),
          ),
        );
      }
      return ListView.builder(
        padding: EdgeInsets.all(12.sp),
        itemCount: livePolls.length,
        itemBuilder: (_, i) {
          final poll = livePolls[i];
          final canVote =
              ctrl.canVotePoll.value && poll.status == 'live' && poll.myVote == null;
          return Container(
            margin: EdgeInsets.only(bottom: 12.h),
            padding: EdgeInsets.all(12.sp),
            decoration: BoxDecoration(
              color: context.backgroundColor,
              borderRadius: BorderRadius.circular(8.r),
            ),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  poll.question,
                  style: context.bodyRegular?.copyWith(
                    color: context.heading,
                    fontWeight: FontWeight.w600,
                  ),
                ),
                SizedBox(height: 8.h),
                ...poll.options.map((opt) {
                  final selected = poll.myVote == opt.id;
                  final showResults =
                      poll.resultsVisible || poll.myVote != null || poll.status == 'closed';
                  final pct = poll.totalVotes > 0
                      ? (opt.votes / poll.totalVotes * 100).round()
                      : 0;
                  return Padding(
                    padding: EdgeInsets.only(bottom: 6.h),
                    child: InkWell(
                      onTap: canVote
                          ? () => ctrl.votePoll(poll.id, opt.id)
                          : null,
                      borderRadius: BorderRadius.circular(8.r),
                      child: Container(
                        width: double.infinity,
                        padding: EdgeInsets.symmetric(
                          horizontal: 10.w,
                          vertical: 10.h,
                        ),
                        decoration: BoxDecoration(
                          color: selected
                              ? context.primaryTheme.withValues(alpha: 0.12)
                              : Colors.white,
                          borderRadius: BorderRadius.circular(8.r),
                          border: Border.all(
                            color: selected
                                ? context.primaryTheme
                                : context.strokeLight,
                          ),
                        ),
                        child: Row(
                          children: [
                            Expanded(
                              child: Text(
                                opt.text,
                                style: context.bodyRegular
                                    ?.copyWith(color: context.heading),
                              ),
                            ),
                            if (showResults)
                              Text(
                                '$pct%',
                                style: context.specialCaption2?.copyWith(
                                  color: context.caption,
                                ),
                              ),
                          ],
                        ),
                      ),
                    ),
                  );
                }),
                Text(
                  '${poll.totalVotes} vote${poll.totalVotes == 1 ? '' : 's'}'
                  '${poll.status == 'closed' ? ' · Closed' : ''}',
                  style: context.specialCaption2
                      ?.copyWith(color: context.caption),
                ),
              ],
            ),
          );
        },
      );
    });
  }
}

class _AttendeesTab extends StatelessWidget {
  final SessionEngagementController ctrl;
  const _AttendeesTab({required this.ctrl});

  @override
  Widget build(BuildContext context) {
    return Column(
      children: [
        Padding(
          padding: EdgeInsets.fromLTRB(12.w, 10.h, 12.w, 0),
          child: TextField(
            onChanged: (v) => ctrl.attendeeSearch.value = v,
            decoration: InputDecoration(
              hintText: 'Search attendees…',
              isDense: true,
              prefixIcon: const Icon(Icons.search, size: 18),
              border: OutlineInputBorder(
                borderRadius: BorderRadius.circular(8.r),
              ),
            ),
          ),
        ),
        Obx(() {
          return Padding(
            padding: EdgeInsets.symmetric(horizontal: 12.w, vertical: 6.h),
            child: Align(
              alignment: Alignment.centerLeft,
              child: Text(
                '${ctrl.attendeeOnline.value} online · ${ctrl.attendeeTotal.value} total',
                style: context.specialCaption2
                    ?.copyWith(color: context.caption),
              ),
            ),
          );
        }),
        Expanded(
          child: Obx(() {
            final list = ctrl.filteredAttendees;
            if (list.isEmpty) {
              return Center(
                child: Text(
                  ctrl.attendeeSearch.value.isEmpty
                      ? 'No attendees to show.'
                      : 'No one matches that search.',
                  style: context.bodyRegular?.copyWith(color: context.caption),
                ),
              );
            }
            return ListView.builder(
              padding: EdgeInsets.fromLTRB(12.w, 0, 12.w, 12.h),
              itemCount: list.length,
              itemBuilder: (_, i) {
                final a = list[i];
                return ListTile(
                  contentPadding: EdgeInsets.zero,
                  leading: CustomImage(
                    a.imageUrl ?? '',
                    width: 36.sp,
                    height: 36.sp,
                    isCircle: true,
                    avatar: true,
                  ),
                  title: Text(
                    a.name,
                    style: context.bodyRegular?.copyWith(
                      color: context.heading,
                      fontWeight: FontWeight.w600,
                    ),
                  ),
                  subtitle: Text(
                    [
                      if (a.isSpeaker) 'Speaker',
                      if (a.headline != null && a.headline!.isNotEmpty)
                        a.headline!,
                      if (a.online) 'Online',
                    ].join(' · '),
                    style: context.specialCaption2
                        ?.copyWith(color: context.caption),
                  ),
                );
              },
            );
          }),
        ),
      ],
    );
  }
}

class _MessageBubble extends StatelessWidget {
  final SessionPanelMessage message;
  const _MessageBubble({required this.message});

  @override
  Widget build(BuildContext context) {
    final mine = message.isMine;
    return Align(
      alignment: mine ? Alignment.centerRight : Alignment.centerLeft,
      child: Container(
        margin: EdgeInsets.only(bottom: 8.h),
        padding: EdgeInsets.symmetric(horizontal: 10.w, vertical: 8.h),
        constraints: BoxConstraints(maxWidth: 0.75.sw),
        decoration: BoxDecoration(
          color: mine
              ? context.primaryTheme.withValues(alpha: 0.12)
              : context.backgroundColor,
          borderRadius: BorderRadius.circular(10.r),
        ),
        child: Column(
          crossAxisAlignment:
              mine ? CrossAxisAlignment.end : CrossAxisAlignment.start,
          children: [
            Text(
              mine ? 'You' : message.author,
              style: context.specialCaption2?.copyWith(
                color: context.primaryTheme,
                fontWeight: FontWeight.w600,
              ),
            ),
            SizedBox(height: 2.h),
            Text(
              message.body,
              style: context.bodyRegular?.copyWith(color: context.heading),
            ),
          ],
        ),
      ),
    );
  }
}

class _Composer extends StatelessWidget {
  final TextEditingController controller;
  final String hint;
  final bool sending;
  final VoidCallback onSend;

  const _Composer({
    required this.controller,
    required this.hint,
    required this.sending,
    required this.onSend,
  });

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: EdgeInsets.fromLTRB(8.w, 6.h, 8.w, 8.h),
      decoration: BoxDecoration(
        border: Border(top: BorderSide(color: context.strokeLight)),
      ),
      child: Row(
        children: [
          Expanded(
            child: TextField(
              controller: controller,
              minLines: 1,
              maxLines: 3,
              decoration: InputDecoration(
                hintText: hint,
                isDense: true,
                border: OutlineInputBorder(
                  borderRadius: BorderRadius.circular(8.r),
                ),
              ),
              onSubmitted: (_) => onSend(),
            ),
          ),
          SizedBox(width: 8.w),
          IconButton(
            onPressed: sending ? null : onSend,
            icon: sending
                ? SizedBox(
                    width: 18.sp,
                    height: 18.sp,
                    child: const CircularProgressIndicator(strokeWidth: 2),
                  )
                : Icon(Icons.send, color: context.primaryTheme),
          ),
        ],
      ),
    );
  }
}

class _MutedBar extends StatelessWidget {
  final String text;
  const _MutedBar({required this.text});

  @override
  Widget build(BuildContext context) {
    return Container(
      width: double.infinity,
      padding: EdgeInsets.all(10.sp),
      decoration: BoxDecoration(
        color: context.backgroundColor,
        border: Border(top: BorderSide(color: context.strokeLight)),
      ),
      child: Text(
        text,
        style: context.specialCaption2?.copyWith(color: context.caption),
      ),
    );
  }
}
