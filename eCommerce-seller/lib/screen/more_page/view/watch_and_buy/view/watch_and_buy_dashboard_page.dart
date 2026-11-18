// ignore_for_file: deprecated_member_use

import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:hyper_commerce_seller/config/colors.dart';
import 'package:hyper_commerce_seller/config/hive_storage.dart';
import 'package:hyper_commerce_seller/widgets/custom/custom_alert_dialog.dart';
import 'package:hyper_commerce_seller/widgets/custom/custom_buttons.dart';
import 'package:hyper_commerce_seller/widgets/custom/custom_loading_indicator.dart';
import 'package:hyper_commerce_seller/widgets/custom/custom_network_image.dart';
import 'package:hyper_commerce_seller/widgets/custom/custom_scaffold.dart';
import 'package:hyper_commerce_seller/widgets/custom/custom_shimmer.dart';
import 'package:hyper_commerce_seller/widgets/custom/custom_snackbar.dart';
import 'package:hyper_commerce_seller/widgets/ui/empty_state_widget.dart';
import '../bloc/profile/watch_and_buy_profile_bloc.dart';
import '../bloc/reels/watch_and_buy_reels_bloc.dart';
import '../bloc/status/watch_and_buy_status_bloc.dart';
import '../model/watch_and_buy_profile_model.dart';
import '../model/watch_and_buy_reel_model.dart';
import '../model/seller_status_model.dart';
import '../repo/watch_and_buy_repo.dart';
import 'profile/watch_and_buy_profile_page.dart';
import 'reels/create_reel_page.dart';
import 'reels/reel_preview_page.dart';
import 'status/create_status_page.dart';
import 'status/status_preview_page.dart';

class WatchAndBuyDashboardPage extends StatefulWidget {
  const WatchAndBuyDashboardPage({super.key});

  @override
  State<WatchAndBuyDashboardPage> createState() => _WatchAndBuyDashboardPageState();
}

class _WatchAndBuyDashboardPageState extends State<WatchAndBuyDashboardPage>
  with SingleTickerProviderStateMixin {
  late final TabController _tabController;

  final WatchAndBuyRepo _repo = WatchAndBuyRepo();
  List<WatchAndBuyReelModel> _cachedReels = [];
  List<SellerStatusModel> _cachedStatuses = [];
  String? _selectedReelStatus;

  static const List<Map<String, dynamic>> _reelFilters = [
    {'label': 'All', 'value': null, 'color': null},
    {'label': 'Published', 'value': 'published', 'color': Color(0xFF2EAA68)},
    {'label': 'Unpublished', 'value': 'unpublished', 'color': Colors.orange},
    {'label': 'Blocked', 'value': 'blocked', 'color': Color(0xFFE53935)},
  ];

  bool get _statusesEnabled => HiveStorage.watchAndBuySettings.statusesEnabled;

  @override
  void initState() {
    super.initState();
    _tabController = TabController(length: _statusesEnabled ? 2 : 1, vsync: this);
  }

  @override
  void dispose() {
    _tabController.dispose();
    super.dispose();
  }

  bool _onReelsScrollNotification(ScrollNotification notification) {
    if (notification.metrics.axis == Axis.vertical &&
        notification.metrics.pixels >= notification.metrics.maxScrollExtent - 200) {
      context.read<WatchAndBuyReelsBloc>().add(const LoadMoreReelsEvent());
    }
    return false;
  }

  bool _onStatusScrollNotification(ScrollNotification notification) {
    if (notification.metrics.axis == Axis.vertical &&
        notification.metrics.pixels >= notification.metrics.maxScrollExtent - 200) {
      context.read<WatchAndBuyStatusBloc>().add(const LoadMoreStatusesEvent());
    }
    return false;
  }

  Future<void> _onRefresh() async {
    context.read<WatchAndBuyProfileBloc>().add(const FetchProfileEvent());
    if (_statusesEnabled && _tabController.index == 1) {
      context.read<WatchAndBuyStatusBloc>().add(const FetchStatusesEvent(isRefresh: true));
    } else {
      context.read<WatchAndBuyReelsBloc>().add(FetchReelsEvent(isRefresh: true, status: _selectedReelStatus));
    }
  }

  void _navigateToProfileSetup(WatchAndBuyProfileModel? profile) async {
    final updated = await Navigator.push<WatchAndBuyProfileModel?>(
      context,
      MaterialPageRoute(
        builder: (_) => BlocProvider(
          create: (_) => WatchAndBuyProfileBloc(repo: _repo),
          child: WatchAndBuyProfilePage(initialProfile: profile),
        ),
      ),
    );
    if (updated != null && mounted) {
      context.read<WatchAndBuyProfileBloc>().add(const FetchProfileEvent());
    }
  }

  void _navigateToCreateReel(WatchAndBuyProfileModel? profile, [int? reelId]) async {
    final result = await Navigator.push<bool?>(
      context,
      MaterialPageRoute(
        builder: (_) => CreateReelPage(
          reelId: reelId,
          profileId: profile?.id,
        ),
      ),
    );
    if (result == true && mounted) {
      context.read<WatchAndBuyReelsBloc>().add(FetchReelsEvent(isRefresh: true, status: _selectedReelStatus));
    }
  }

  void _navigateToCreateStatus(WatchAndBuyProfileModel? profile) async {
    final result = await Navigator.push<bool?>(
      context,
      MaterialPageRoute(
        builder: (_) => CreateStatusPage(profileId: profile?.id),
      ),
    );
    if (result == true && mounted) {
      context.read<WatchAndBuyStatusBloc>().add(const FetchStatusesEvent(isRefresh: true));
    }
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final isDark = theme.brightness == Brightness.dark;

    final reelsState = context.watch<WatchAndBuyReelsBloc>().state;
    final statusState = context.watch<WatchAndBuyStatusBloc>().state;

    final isDeletingReel = reelsState is ReelsActionInProgress;
    final isDeletingStatus = statusState is StatusActionInProgress;
    final isProcessing = isDeletingReel || isDeletingStatus;
    final processingMessage = isDeletingReel
        ? reelsState.message
        : (isDeletingStatus ? statusState.message : 'Please wait...');

    return MultiBlocListener(
      listeners: [
        BlocListener<WatchAndBuyReelsBloc, WatchAndBuyReelsState>(
          listener: (context, state) {
            if (state is ReelsActionSuccess) {
              showCustomSnackbar(context: context, message: state.message);
            } else if (state is ReelsError) {
              showCustomSnackbar(context: context, message: state.error, isError: true);
            }
          },
        ),
        BlocListener<WatchAndBuyStatusBloc, WatchAndBuyStatusState>(
          listener: (context, state) {
            if (state is StatusActionSuccess) {
              showCustomSnackbar(context: context, message: state.message);
            } else if (state is StatusError) {
              showCustomSnackbar(context: context, message: state.error, isError: true);
            }
          },
        ),
      ],
      child: CustomScaffold(
        title: HiveStorage.watchAndBuySettings.title,
        showAppbar: true,
        body: Stack(
          children: [
            RefreshIndicator(
              onRefresh: _onRefresh,
              child: NestedScrollView(
                physics: const AlwaysScrollableScrollPhysics(),
                headerSliverBuilder: (context, innerBoxIsScrolled) {
                  return [
                    SliverToBoxAdapter(
                      child: BlocBuilder<WatchAndBuyProfileBloc, WatchAndBuyProfileState>(
                        builder: (context, state) {
                          if (state is ProfileLoading) {
                            return _buildProfileShimmer(isDark);
                          }

                          WatchAndBuyProfileModel? profile;
                          bool hasProfile = false;

                          if (state is ProfileLoaded) {
                            profile = state.profile;
                            hasProfile = state.hasProfile;
                          }

                          return Padding(
                            padding: const EdgeInsets.fromLTRB(16, 12, 16, 4),
                            child: Column(
                              children: [
                                if (hasProfile && profile != null)
                                  _buildProfileCard(context, profile, isDark)
                                else
                                  _buildSetupProfileBanner(context, profile, isDark),

                                const SizedBox(height: 12),

                                _buildActionButtons(context, profile, isDark),

                                const SizedBox(height: 10),
                              ],
                            ),
                          );
                        },
                      ),
                    ),

                    if (_statusesEnabled)
                      SliverPersistentHeader(
                        pinned: true,
                        delegate: _SliverTabBarDelegate(
                          TabBar(
                            controller: _tabController,
                            indicatorColor: AppColors.primaryColor,
                            indicatorWeight: 2.5,
                            indicatorSize: TabBarIndicatorSize.tab,
                            labelColor: isDark ? Colors.white : const Color(0xFF1F2024),
                            unselectedLabelColor: isDark ? Colors.white54 : const Color(0xFF8E8E93),
                            labelStyle: const TextStyle(fontWeight: FontWeight.w600, fontSize: 13.5),
                            unselectedLabelStyle: const TextStyle(fontWeight: FontWeight.w500, fontSize: 13.5),
                            dividerColor: isDark ? Colors.white12 : const Color(0xFFEAEAEA),
                            tabs: const [
                              Tab(text: 'Reels / Videos'),
                              Tab(text: 'Seller Status'),
                            ],
                          ),
                          isDark: isDark,
                        ),
                      ),
                  ];
                },
                body: _statusesEnabled
                    ? TabBarView(
                        controller: _tabController,
                        children: [
                          _buildReelsTab(context, isDark),
                          _buildStatusTab(context, isDark),
                        ],
                      )
                    : _buildReelsTab(context, isDark),
              ),
            ),

            // Modal overlay progress indicator during deletion/actions
            if (isProcessing)
              Positioned.fill(
                child: AbsorbPointer(
                  absorbing: true,
                  child: Container(
                    color: Colors.black.withOpacity(0.38),
                    child: Center(
                      child: Container(
                        padding: const EdgeInsets.symmetric(horizontal: 24, vertical: 20),
                        decoration: BoxDecoration(
                          color: isDark ? const Color(0xFF242426) : Colors.white,
                          borderRadius: BorderRadius.circular(16),
                          boxShadow: [
                            BoxShadow(
                              color: Colors.black.withOpacity(0.18),
                              blurRadius: 16,
                              offset: const Offset(0, 4),
                            ),
                          ],
                        ),
                        child: Column(
                          mainAxisSize: MainAxisSize.min,
                          children: [
                            const CustomLoadingIndicator(size: 42),
                            const SizedBox(height: 14),
                            Text(
                              processingMessage.isNotEmpty ? processingMessage : 'Deleting...',
                              style: TextStyle(
                                fontSize: 13.5,
                                fontWeight: FontWeight.w600,
                                color: isDark ? Colors.white : const Color(0xFF1F2024),
                              ),
                            ),
                          ],
                        ),
                      ),
                    ),
                  ),
                ),
              ),
          ],
        ),
      ),
    );
  }

  Widget _buildProfileCard(BuildContext context, WatchAndBuyProfileModel profile, bool isDark) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
      decoration: BoxDecoration(
        color: isDark ? const Color(0xFF1E1E1E) : Colors.white,
        borderRadius: BorderRadius.circular(22),
        border: Border.all(color: isDark ? Colors.white12 : const Color(0xFFEBEBEB)),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withOpacity(0.02),
            blurRadius: 8,
            offset: const Offset(0, 2),
          ),
        ],
      ),
      child: Row(
        children: [
          Container(
            width: 48,
            height: 48,
            decoration: BoxDecoration(
              color: const Color(0xFFFFF0F0),
              shape: BoxShape.circle,
              border: Border.all(color: Colors.pink.shade100, width: 1.5),
            ),
            clipBehavior: Clip.antiAlias,
            child: profile.photoUrl != null && profile.photoUrl!.isNotEmpty
                ? CustomNetworkImage(
                    imageUrl: profile.photoUrl!,
                    fit: BoxFit.cover,
                  )
                : Center(
                    child: Icon(Icons.person, color: Colors.pink.shade300, size: 24),
                  ),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              mainAxisSize: MainAxisSize.min,
              children: [
                Text(
                  '@${profile.username ?? 'seller'}',
                  style: TextStyle(
                    fontWeight: FontWeight.w700,
                    fontSize: 14.5,
                    color: isDark ? Colors.white : const Color(0xFF1F2024),
                  ),
                ),
                const SizedBox(height: 2),
                Text(
                  profile.bio != null && profile.bio!.isNotEmpty ? profile.bio! : 'Seller bio',
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: TextStyle(
                    fontSize: 12,
                    color: isDark ? Colors.white60 : const Color(0xFF8E8E93),
                  ),
                ),
              ],
            ),
          ),
          IconButton(
            icon: Icon(
              Icons.edit_outlined,
              size: 19,
              color: isDark ? Colors.white70 : const Color(0xFF555555),
            ),
            tooltip: 'Edit Profile',
            onPressed: () => _navigateToProfileSetup(profile),
          ),
        ],
      ),
    );
  }

  Widget _buildSetupProfileBanner(BuildContext context, WatchAndBuyProfileModel? profile, bool isDark) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
      decoration: BoxDecoration(
        color: isDark ? const Color(0xFF1E1E1E) : Colors.white,
        borderRadius: BorderRadius.circular(22),
        border: Border.all(color: AppColors.primaryColor.withOpacity(0.35)),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withOpacity(0.02),
            blurRadius: 8,
            offset: const Offset(0, 2),
          ),
        ],
      ),
      child: Row(
        children: [
          Container(
            width: 44,
            height: 44,
            decoration: BoxDecoration(
              color: AppColors.primaryColor.withOpacity(0.12),
              shape: BoxShape.circle,
            ),
            child: const Icon(Icons.stars_rounded, color: AppColors.primaryColor, size: 26),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              mainAxisSize: MainAxisSize.min,
              children: [
                const Text(
                  'Set Up Profile',
                  style: TextStyle(fontWeight: FontWeight.bold, fontSize: 14),
                ),
                const SizedBox(height: 1),
                Text(
                  'Create handle & bio to publish reels',
                  style: TextStyle(fontSize: 11.5, color: isDark ? Colors.white70 : const Color(0xFF8E8E93)),
                ),
              ],
            ),
          ),
          const SizedBox(width: 8),
          PrimaryButton(
            text: 'Setup',
            width: 72,
            height: 32,
            padding: const EdgeInsets.symmetric(horizontal: 8),
            onPressed: () => _navigateToProfileSetup(profile),
          ),
        ],
      ),
    );
  }

  Widget _buildActionButtons(BuildContext context, WatchAndBuyProfileModel? profile, bool isDark) {
    return Row(
      children: [
        Expanded(
          child: Container(
            height: 46,
            decoration: BoxDecoration(
              color: AppColors.primaryColor,
              borderRadius: BorderRadius.circular(24),
            ),
            child: Material(
              color: Colors.transparent,
              child: InkWell(
                borderRadius: BorderRadius.circular(24),
                onTap: () => _navigateToCreateReel(profile),
                child: const Row(
                  mainAxisAlignment: MainAxisAlignment.center,
                  children: [
                    Icon(Icons.videocam_outlined, size: 20, color: Color(0xFF1F2024)),
                    SizedBox(width: 8),
                    Text(
                      'Create Reel',
                      style: TextStyle(
                        color: Color(0xFF1F2024),
                        fontWeight: FontWeight.w700,
                        fontSize: 13.5,
                      ),
                    ),
                  ],
                ),
              ),
            ),
          ),
        ),

        if (_statusesEnabled) ...[
          const SizedBox(width: 12),
          Expanded(
            child: Container(
              height: 46,
              decoration: BoxDecoration(
                color: isDark ? const Color(0xFF1E1E1E) : Colors.white,
                borderRadius: BorderRadius.circular(24),
                border: Border.all(color: isDark ? Colors.white24 : const Color(0xFFE2E2E2)),
              ),
              child: Material(
                color: Colors.transparent,
                child: InkWell(
                  borderRadius: BorderRadius.circular(24),
                  onTap: () => _navigateToCreateStatus(profile),
                  child: Row(
                    mainAxisAlignment: MainAxisAlignment.center,
                    children: [
                      Icon(Icons.add, size: 20, color: isDark ? Colors.white : const Color(0xFF1F2024)),
                      const SizedBox(width: 6),
                      Text(
                        'Add Status',
                        style: TextStyle(
                          color: isDark ? Colors.white : const Color(0xFF1F2024),
                          fontWeight: FontWeight.w700,
                          fontSize: 13.5,
                        ),
                      ),
                    ],
                  ),
                ),
              ),
            ),
          ),
        ],
      ],
    );
  }

  Widget _buildProfileShimmer(bool isDark) {
    return Padding(
      padding: const EdgeInsets.fromLTRB(16, 12, 16, 4),
      child: Column(
        children: [
          Container(
            padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
            decoration: BoxDecoration(
              color: isDark ? const Color(0xFF1E1E1E) : Colors.white,
              borderRadius: BorderRadius.circular(22),
              border: Border.all(color: isDark ? Colors.white12 : const Color(0xFFEBEBEB)),
            ),
            child: Row(
              children: [
                CustomShimmer(
                  width: 48,
                  height: 48,
                  borderRadius: BorderRadius.circular(24),
                ),
                const SizedBox(width: 12),
                const Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      CustomShimmer(width: 120, height: 14),
                      SizedBox(height: 6),
                      CustomShimmer(width: 160, height: 11),
                    ],
                  ),
                ),
                CustomShimmer(
                  width: 20,
                  height: 20,
                  borderRadius: BorderRadius.circular(4),
                ),
              ],
            ),
          ),

          const SizedBox(height: 12),

          Row(
            children: [
              Expanded(
                child: CustomShimmer(
                  width: double.infinity,
                  height: 46,
                  borderRadius: BorderRadius.circular(24),
                ),
              ),
              if (_statusesEnabled) ...[
                const SizedBox(width: 12),
                Expanded(
                  child: CustomShimmer(
                    width: double.infinity,
                    height: 46,
                    borderRadius: BorderRadius.circular(24),
                  ),
                ),
              ],
            ],
          ),

          const SizedBox(height: 10),
        ],
      ),
    );
  }

  Widget _buildReelsGridShimmer(bool isDark) {
    return GridView.builder(
      padding: const EdgeInsets.all(16),
      physics: const AlwaysScrollableScrollPhysics(),
      gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
        crossAxisCount: 2,
        crossAxisSpacing: 12,
        mainAxisSpacing: 14,
        childAspectRatio: 0.58,
      ),
      itemCount: 4,
      itemBuilder: (context, index) {
        return Container(
          decoration: BoxDecoration(
            color: isDark ? const Color(0xFF1E1E1E) : Colors.white,
            borderRadius: BorderRadius.circular(16),
            border: Border.all(color: isDark ? Colors.white12 : const Color(0xFFEAEAEA), width: 0.8),
          ),
          clipBehavior: Clip.antiAlias,
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              const Expanded(
                child: CustomShimmer(
                  width: double.infinity,
                  height: double.infinity,
                ),
              ),
              Padding(
                padding: const EdgeInsets.all(10),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    const CustomShimmer(width: double.infinity, height: 12),
                    const SizedBox(height: 6),
                    const CustomShimmer(width: 80, height: 10),
                    const SizedBox(height: 10),
                    Row(
                      mainAxisAlignment: MainAxisAlignment.spaceBetween,
                      children: const [
                        CustomShimmer(width: 32, height: 10),
                        CustomShimmer(width: 24, height: 10),
                      ],
                    ),
                  ],
                ),
              ),
            ],
          ),
        );
      },
    );
  }

  Widget _buildStatusListShimmer(bool isDark) {
    return ListView.separated(
      padding: const EdgeInsets.all(16),
      physics: const AlwaysScrollableScrollPhysics(),
      itemCount: 4,
      separatorBuilder: (_, __) => const SizedBox(height: 12),
      itemBuilder: (context, index) {
        return Container(
          padding: const EdgeInsets.all(12),
          decoration: BoxDecoration(
            color: isDark ? const Color(0xFF1E1E1E) : Colors.white,
            borderRadius: BorderRadius.circular(16),
            border: Border.all(color: isDark ? Colors.white12 : const Color(0xFFEAEAEA)),
          ),
          child: Row(
            children: [
              CustomShimmer(
                width: 60,
                height: 60,
                borderRadius: BorderRadius.circular(10),
              ),
              const SizedBox(width: 14),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: const [
                    CustomShimmer(width: 140, height: 14),
                    SizedBox(height: 8),
                    CustomShimmer(width: 90, height: 11),
                  ],
                ),
              ),
              CustomShimmer(
                width: 24,
                height: 24,
                borderRadius: BorderRadius.circular(12),
              ),
            ],
          ),
        );
      },
    );
  }

  Widget _buildReelsTab(BuildContext context, bool isDark) {
    return Column(
      children: [
        _buildReelsFilterBar(context, isDark),
        Expanded(
          child: BlocBuilder<WatchAndBuyReelsBloc, WatchAndBuyReelsState>(
            builder: (context, state) {
              if (state is ReelsLoading && _cachedReels.isEmpty) {
                return _buildReelsGridShimmer(isDark);
              }

              List<WatchAndBuyReelModel> reels = [];
              bool hasMore = false;

              if (state is ReelsLoaded) {
                reels = state.reels;
                hasMore = state.hasMore;
                _cachedReels = reels;
              } else if (state is ReelsLoadingMore) {
                reels = state.currentReels;
                hasMore = true;
                _cachedReels = reels;
              } else if (state is ReelsActionInProgress) {
                reels = state.currentReels.isNotEmpty ? state.currentReels : _cachedReels;
              } else if (state is ReelsActionSuccess) {
                reels = state.currentReels.isNotEmpty ? state.currentReels : _cachedReels;
              } else {
                reels = _cachedReels;
              }

              if (reels.isEmpty && state is ReelsLoading) {
                return _buildReelsGridShimmer(isDark);
              }

              if (reels.isEmpty && state is! ReelsLoading) {
                return ListView(
                  physics: const AlwaysScrollableScrollPhysics(),
                  children: [
                    const SizedBox(height: 60),
                    EmptyStateWidget(
                      title: _selectedReelStatus != null
                          ? 'No ${_selectedReelStatus![0].toUpperCase()}${_selectedReelStatus!.substring(1)} Reels Found'
                          : 'No Reels Found',
                      svgPath: '',
                      onAction: () {
                        final profileState = context.read<WatchAndBuyProfileBloc>().state;
                        WatchAndBuyProfileModel? p = profileState is ProfileLoaded ? profileState.profile : null;
                        _navigateToCreateReel(p);
                      },
                    ),
                  ],
                );
              }

              return NotificationListener<ScrollNotification>(
                onNotification: _onReelsScrollNotification,
                child: GridView.builder(
                  physics: const AlwaysScrollableScrollPhysics(),
                  padding: const EdgeInsets.all(16),
                  gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
                    crossAxisCount: 2,
                    crossAxisSpacing: 12,
                    mainAxisSpacing: 14,
                    childAspectRatio: 0.58,
                  ),
                  itemCount: reels.length + (hasMore ? 1 : 0),
                  itemBuilder: (context, index) {
                    if (index == reels.length) {
                      return const Center(child: CircularProgressIndicator(strokeWidth: 2));
                    }

                    final reel = reels[index];
                    return _buildReelCard(context, reel, isDark);
                  },
                ),
              );
            },
          ),
        ),
      ],
    );
  }

  Widget _buildReelsFilterBar(BuildContext context, bool isDark) {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.symmetric(vertical: 8),
      decoration: BoxDecoration(
        color: isDark ? const Color(0xFF1E1E1E) : Colors.white,
        border: Border(
          bottom: BorderSide(
            color: isDark ? Colors.white10 : const Color(0xFFEFEFEF),
            width: 0.8,
          ),
        ),
      ),
      child: SingleChildScrollView(
        scrollDirection: Axis.horizontal,
        padding: const EdgeInsets.symmetric(horizontal: 16),
        child: Row(
          children: _reelFilters.map((filter) {
            final String? value = filter['value'] as String?;
            final String label = filter['label'] as String;
            final Color? statusColor = filter['color'] as Color?;
            final bool isSelected = _selectedReelStatus == value;

            return Padding(
              padding: const EdgeInsets.only(right: 8),
              child: InkWell(
                onTap: () {
                  if (_selectedReelStatus == value) return;
                  setState(() {
                    _selectedReelStatus = value;
                    _cachedReels = [];
                  });
                  PrimaryScrollController.maybeOf(context)?.jumpTo(0);
                  context.read<WatchAndBuyReelsBloc>().add(
                        FetchReelsEvent(status: value),
                      );
                },
                borderRadius: BorderRadius.circular(20),
                child: AnimatedContainer(
                  duration: const Duration(milliseconds: 180),
                  padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 7),
                  decoration: BoxDecoration(
                    color: isSelected
                        ? AppColors.primaryColor
                        : (isDark ? const Color(0xFF2C2C2E) : const Color(0xFFF4F5F7)),
                    borderRadius: BorderRadius.circular(20),
                    border: Border.all(
                      color: isSelected
                          ? AppColors.primaryColor
                          : (isDark ? Colors.white12 : const Color(0xFFE2E4E8)),
                      width: 1,
                    ),
                  ),
                  child: Row(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      if (statusColor != null) ...[
                        Container(
                          width: 7,
                          height: 7,
                          decoration: BoxDecoration(
                            color: isSelected ? Colors.black : statusColor,
                            shape: BoxShape.circle,
                          ),
                        ),
                        const SizedBox(width: 6),
                      ],
                      Text(
                        label,
                        style: TextStyle(
                          fontSize: 12.5,
                          fontWeight: isSelected ? FontWeight.w700 : FontWeight.w500,
                          color: isSelected
                              ? Colors.black
                              : (isDark ? Colors.white70 : const Color(0xFF4A4A4A)),
                        ),
                      ),
                    ],
                  ),
                ),
              ),
            );
          }).toList(),
        ),
      ),
    );
  }

  Widget _buildReelCard(BuildContext context, WatchAndBuyReelModel reel, bool isDark) {
    final captionText = reel.caption != null && reel.caption!.isNotEmpty
        ? reel.caption!
        : (reel.products.isNotEmpty ? reel.products.first.productTitle : 'Watch & Buy Reel');

    final coverImageUrl = reel.coverUrl != null && reel.coverUrl!.isNotEmpty
        ? reel.coverUrl!
        : (reel.products.isNotEmpty && reel.products.first.productImage != null && reel.products.first.productImage!.isNotEmpty
            ? reel.products.first.productImage!
            : null);

    return GestureDetector(
      onTap: () {
        Navigator.push(
          context,
          MaterialPageRoute(builder: (_) => ReelPreviewPage(reel: reel)),
        );
      },
      child: Container(
        decoration: BoxDecoration(
          color: isDark ? const Color(0xFF1E1E1E) : Colors.white,
          borderRadius: BorderRadius.circular(16),
          border: Border.all(color: isDark ? Colors.white12 : const Color(0xFFEAEAEA), width: 0.8),
          boxShadow: [
            BoxShadow(
              color: Colors.black.withOpacity(0.03),
              blurRadius: 8,
              offset: const Offset(0, 2),
            ),
          ],
        ),
        clipBehavior: Clip.antiAlias,
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Expanded(
              child: Stack(
                fit: StackFit.expand,
                children: [
                  if (coverImageUrl != null)
                    CustomNetworkImage(
                      imageUrl: coverImageUrl,
                      fit: BoxFit.cover,
                    )
                  else
                    Container(
                      decoration: const BoxDecoration(
                        gradient: LinearGradient(
                          colors: [Color(0xFF2C3E50), Color(0xFF000000)],
                          begin: Alignment.topCenter,
                          end: Alignment.bottomCenter,
                        ),
                      ),
                      child: const Center(
                        child: Icon(Icons.movie_outlined, color: Colors.white38, size: 36),
                      ),
                    ),

                  Center(
                    child: Container(
                      width: 42,
                      height: 42,
                      decoration: BoxDecoration(
                        color: Colors.white.withOpacity(0.82),
                        shape: BoxShape.circle,
                        boxShadow: [
                          BoxShadow(
                            color: Colors.black.withOpacity(0.15),
                            blurRadius: 6,
                          ),
                        ],
                      ),
                      child: const Icon(
                        Icons.play_arrow_rounded,
                        color: Color(0xFF1F2024),
                        size: 26,
                      ),
                    ),
                  ),

                  Positioned(
                    top: 8,
                    left: 8,
                    child: Container(
                      padding: const EdgeInsets.symmetric(horizontal: 7, vertical: 3),
                      decoration: BoxDecoration(
                        color: reel.isPublished
                            ? const Color(0xFF2EAA68)
                            : (reel.isBlocked
                                ? const Color(0xFFE53935)
                                : Colors.orange),
                        borderRadius: BorderRadius.circular(10),
                      ),
                      child: Text(
                        reel.status?.toUpperCase() ?? 'PUBLISHED',
                        style: const TextStyle(
                          color: Colors.white,
                          fontSize: 8.5,
                          fontWeight: FontWeight.w800,
                          letterSpacing: 0.3,
                        ),
                      ),
                    ),
                  ),

                  Positioned(
                    top: 6,
                    right: 6,
                    child: PopupMenuButton<String>(
                      icon: Container(
                        width: 28,
                        height: 28,
                        decoration: BoxDecoration(
                          color: Colors.white.withOpacity(0.85),
                          shape: BoxShape.circle,
                          boxShadow: [
                            BoxShadow(
                              color: Colors.black.withOpacity(0.1),
                              blurRadius: 4,
                            ),
                          ],
                        ),
                        child: const Icon(Icons.more_vert, size: 16, color: Color(0xFF333333)),
                      ),
                      onSelected: (action) => _handleReelAction(reel, action),
                      itemBuilder: (context) => [
                        const PopupMenuItem(
                          value: 'preview',
                          child: Row(
                            children: [
                              Icon(Icons.play_arrow, size: 18),
                              SizedBox(width: 8),
                              Text('Preview'),
                            ],
                          ),
                        ),
                        const PopupMenuItem(
                          value: 'edit',
                          child: Row(
                            children: [
                              Icon(Icons.edit_outlined, size: 18),
                              SizedBox(width: 8),
                              Text('Edit Reel'),
                            ],
                          ),
                        ),
                        if (!reel.isBlocked)
                          PopupMenuItem(
                            value: 'publish',
                            child: Row(
                              children: [
                                Icon(
                                  reel.isPublished ? Icons.visibility_off_outlined : Icons.visibility_outlined,
                                  size: 18,
                                ),
                                const SizedBox(width: 8),
                                Text(reel.isPublished ? 'Unpublish' : 'Publish'),
                              ],
                            ),
                          ),
                        const PopupMenuItem(
                          value: 'delete',
                          child: Row(
                            children: [
                              Icon(Icons.delete_outline, color: Colors.red, size: 18),
                              SizedBox(width: 8),
                              Text('Delete', style: TextStyle(color: Colors.red)),
                            ],
                          ),
                        ),
                      ],
                    ),
                  ),
                ],
              ),
            ),

            Padding(
              padding: const EdgeInsets.fromLTRB(10, 10, 10, 10),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                mainAxisSize: MainAxisSize.min,
                children: [
                  Text(
                    captionText,
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: TextStyle(
                      fontWeight: FontWeight.w600,
                      fontSize: 12,
                      color: isDark ? Colors.white : const Color(0xFF2C2C2E),
                      height: 1.25,
                    ),
                  ),
                  const SizedBox(height: 8),
                  Row(
                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                    children: [
                      Row(
                        children: [
                          const Icon(Icons.favorite, size: 13, color: Color(0xFFEE4D56)),
                          const SizedBox(width: 4),
                          Text(
                            '${reel.likeCount}',
                            style: TextStyle(
                              fontSize: 11,
                              fontWeight: FontWeight.w500,
                              color: isDark ? Colors.white70 : const Color(0xFF8E8E93),
                            ),
                          ),
                        ],
                      ),
                      Text(
                        reel.formattedDuration,
                        style: TextStyle(
                          fontSize: 11,
                          fontWeight: FontWeight.w500,
                          color: isDark ? Colors.white70 : const Color(0xFF8E8E93),
                        ),
                      ),
                    ],
                  ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }

  void _handleReelAction(WatchAndBuyReelModel reel, String action) {
    if (action == 'preview') {
      Navigator.push(
        context,
        MaterialPageRoute(builder: (_) => ReelPreviewPage(reel: reel)),
      );
    } else if (action == 'edit') {
      final profileState = context.read<WatchAndBuyProfileBloc>().state;
      WatchAndBuyProfileModel? p = profileState is ProfileLoaded ? profileState.profile : null;
      _navigateToCreateReel(p, reel.id);
    } else if (action == 'publish') {
      context.read<WatchAndBuyReelsBloc>().add(
            TogglePublishReelEvent(
              reelId: reel.id,
              publish: !reel.isPublished,
            ),
          );
    } else if (action == 'delete') {
      showAppAlertDialog(
        context: context,
        title: 'Delete Reel',
        message: 'Are you sure you want to delete this reel? This action cannot be undone.',
        confirmText: 'Delete',
        cancelText: 'Cancel',
        isDestructive: true,
        onConfirm: () {
          context.read<WatchAndBuyReelsBloc>().add(DeleteReelEvent(reel.id));
        },
      );
    }
  }

  Widget _buildStatusTab(BuildContext context, bool isDark) {
    return BlocBuilder<WatchAndBuyStatusBloc, WatchAndBuyStatusState>(
      builder: (context, state) {
        if (state is StatusLoading && _cachedStatuses.isEmpty) {
          return _buildStatusListShimmer(isDark);
        }

        List<SellerStatusModel> statuses = [];
        bool hasMore = false;

        if (state is StatusLoaded) {
          statuses = state.statuses;
          hasMore = state.hasMore;
          _cachedStatuses = statuses;
        } else if (state is StatusLoadingMore) {
          statuses = state.currentStatuses;
          hasMore = true;
          _cachedStatuses = statuses;
        } else if (state is StatusActionInProgress) {
          statuses = state.currentStatuses.isNotEmpty ? state.currentStatuses : _cachedStatuses;
        } else if (state is StatusActionSuccess) {
          statuses = state.currentStatuses.isNotEmpty ? state.currentStatuses : _cachedStatuses;
        } else {
          statuses = _cachedStatuses;
        }

        if (statuses.isEmpty && state is StatusLoading) {
          return _buildStatusListShimmer(isDark);
        }

        if (statuses.isEmpty && state is! StatusLoading) {
          return ListView(
            physics: const AlwaysScrollableScrollPhysics(),
            children: [
              const SizedBox(height: 60),
              EmptyStateWidget(
                title: 'No Status Updates Found',
                svgPath: '',
                onAction: () {
                  final profileState = context.read<WatchAndBuyProfileBloc>().state;
                  WatchAndBuyProfileModel? p = profileState is ProfileLoaded ? profileState.profile : null;
                  _navigateToCreateStatus(p);
                },
              ),
            ],
          );
        }

        return NotificationListener<ScrollNotification>(
          onNotification: _onStatusScrollNotification,
          child: ListView.separated(
            physics: const AlwaysScrollableScrollPhysics(),
            padding: const EdgeInsets.all(16),
            itemCount: statuses.length + (hasMore ? 1 : 0),
            separatorBuilder: (_, __) => const SizedBox(height: 12),
            itemBuilder: (context, index) {
              if (index == statuses.length) {
                return const Center(child: CircularProgressIndicator(strokeWidth: 2));
              }

              final status = statuses[index];
              return _buildStatusItem(context, status, isDark);
            },
          ),
        );
      },
    );
  }

  Widget _buildStatusItem(BuildContext context, SellerStatusModel status, bool isDark) {
    return GestureDetector(
      onTap: () {
        Navigator.push(
          context,
          MaterialPageRoute(
            builder: (_) => StatusPreviewPage(
              status: status,
              onDelete: () {
                context.read<WatchAndBuyStatusBloc>().add(DeleteStatusEvent(status.id));
              },
            ),
          ),
        );
      },
      child: Container(
        padding: const EdgeInsets.all(12),
        decoration: BoxDecoration(
          color: isDark ? const Color(0xFF1E1E1E) : Colors.white,
          borderRadius: BorderRadius.circular(16),
          border: Border.all(color: isDark ? Colors.white12 : const Color(0xFFEAEAEA)),
          boxShadow: [
            BoxShadow(
              color: Colors.black.withOpacity(0.02),
              blurRadius: 6,
              offset: const Offset(0, 2),
            ),
          ],
        ),
        child: Row(
          children: [
            ClipRRect(
              borderRadius: BorderRadius.circular(10),
              child: status.isImage && status.mediaUrl != null
                  ? CustomNetworkImage(
                      imageUrl: status.mediaUrl!,
                      width: 60,
                      height: 60,
                      fit: BoxFit.cover,
                    )
                  : Container(
                      width: 60,
                      height: 60,
                      decoration: BoxDecoration(
                        gradient: LinearGradient(
                          colors: status.isVideo
                              ? [Colors.deepPurple, Colors.indigo]
                              : [const Color(0xFF833AB4), const Color(0xFFFD1D1D)],
                        ),
                      ),
                      child: Icon(
                        status.isVideo ? Icons.videocam : Icons.text_snippet_outlined,
                        color: Colors.white,
                        size: 26,
                      ),
                    ),
            ),
            const SizedBox(width: 14),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    status.text != null && status.text!.isNotEmpty
                        ? status.text!
                        : (status.isVideo ? 'Video Status' : 'Image Status'),
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: const TextStyle(fontWeight: FontWeight.w600, fontSize: 13.5),
                  ),
                  const SizedBox(height: 4),
                  Row(
                    children: [
                      const Icon(Icons.timer_outlined, size: 13, color: Colors.orange),
                      const SizedBox(width: 4),
                      Text(
                        status.timeRemainingFormatted,
                        style: const TextStyle(color: Colors.orange, fontSize: 11, fontWeight: FontWeight.w500),
                      ),
                      if (status.products.isNotEmpty) ...[
                        const SizedBox(width: 10),
                        Container(
                          padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
                          decoration: BoxDecoration(
                            color: AppColors.primaryColor.withOpacity(0.12),
                            borderRadius: BorderRadius.circular(4),
                          ),
                          child: Text(
                            '${status.products.length} tagged',
                            style: const TextStyle(fontSize: 10, color: AppColors.primaryColor, fontWeight: FontWeight.bold),
                          ),
                        ),
                      ],
                    ],
                  ),
                ],
              ),
            ),
            IconButton(
              icon: const Icon(Icons.delete_outline, color: Colors.red, size: 20),
              onPressed: () {
                showAppAlertDialog(
                  context: context,
                  title: 'Delete Status',
                  message: 'Are you sure you want to delete this status update?',
                  confirmText: 'Delete',
                  cancelText: 'Cancel',
                  isDestructive: true,
                  onConfirm: () {
                    context.read<WatchAndBuyStatusBloc>().add(DeleteStatusEvent(status.id));
                  },
                );
              },
            ),
          ],
        ),
      ),
    );
  }
}

class _SliverTabBarDelegate extends SliverPersistentHeaderDelegate {
  final TabBar _tabBar;
  final bool isDark;

  _SliverTabBarDelegate(this._tabBar, {required this.isDark});

  @override
  double get minExtent => _tabBar.preferredSize.height;
  @override
  double get maxExtent => _tabBar.preferredSize.height;

  @override
  Widget build(BuildContext context, double shrinkOffset, bool overlapsContent) {
    return Container(
      color: isDark ? const Color(0xFF1E1E1E) : Colors.white,
      child: _tabBar,
    );
  }

  @override
  bool shouldRebuild(_SliverTabBarDelegate oldDelegate) {
    return isDark != oldDelegate.isDark || _tabBar != oldDelegate._tabBar;
  }
}
