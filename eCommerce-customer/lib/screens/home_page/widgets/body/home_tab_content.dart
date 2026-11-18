import 'dart:developer';

import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:flutter_screenutil/flutter_screenutil.dart';
import 'package:hyper_commerce/screens/home_page/bloc/home_layout/home_layout_bloc.dart';
import 'package:hyper_commerce/screens/home_page/bloc/home_layout/home_layout_state.dart';
import 'package:hyper_commerce/utils/widgets/custom_circular_progress_indicator.dart';
import 'package:hyper_commerce/utils/widgets/custom_refresh_indicator.dart';
import 'package:hyper_commerce/utils/widgets/empty_states_page.dart';

import 'home_layout_body.dart';

class HomeTabContent extends StatefulWidget {
  final int tabIndex;
  final Map<String, dynamic>? layoutQuery;
  final Future<void> Function() onRefresh;
  final VoidCallback onRetry;

  const HomeTabContent({
    super.key,
    this.tabIndex = 0,
    this.layoutQuery,
    required this.onRefresh,
    required this.onRetry,
  });

  @override
  State<HomeTabContent> createState() => _HomeTabContentState();
}

class _HomeTabContentState extends State<HomeTabContent>
    with AutomaticKeepAliveClientMixin {
  @override
  bool get wantKeepAlive => true;

  bool _isQueryMatching(
      Map<String, dynamic>? stateQuery,
      Map<String, dynamic>? tabQuery,
      ) {
    if (widget.tabIndex == 0) {
      return stateQuery == null || stateQuery.isEmpty;
    }

    if (stateQuery == null && tabQuery == null) return true;
    if (stateQuery == null || tabQuery == null) return false;
    if (stateQuery.length != tabQuery.length) return false;

    for (final key in stateQuery.keys) {
      if (stateQuery[key]?.toString() !=
          tabQuery[key]?.toString()) {
        return false;
      }
    }

    return true;
  }

  @override
  Widget build(BuildContext context) {
    super.build(context);

    return BlocBuilder<HomeLayoutBloc, HomeLayoutState>(
      buildWhen: (previous, current) {
        return previous != current;
      },
      builder: (context, state) {
        final bool isMatchingQuery = switch (state) {
          HomeLayoutLoaded(:final layoutQuery) =>
              _isQueryMatching(
                layoutQuery,
                widget.layoutQuery,
              ),
          HomeLayoutFailed(:final layoutQuery) =>
              _isQueryMatching(
                layoutQuery,
                widget.layoutQuery,
              ),
          _ => true,
        };

        log('Matching Query $isMatchingQuery');

        return CustomRefreshIndicator(
          onRefresh: widget.onRefresh,
          child: CustomScrollView(
            key: ValueKey<String>(
              'home_tab_${widget.tabIndex}',
            ),
            slivers: [
              SliverOverlapInjector(
                handle:
                NestedScrollView.sliverOverlapAbsorberHandleFor(
                  context,
                ),
              ),

              if (!isMatchingQuery)
                const SliverFillRemaining(
                  hasScrollBody: false,
                  child: Center(
                    child: CustomCircularProgressIndicator(),
                  ),
                )
              else if (state is HomeLayoutLoaded)
                if (state.sections.isEmpty)
                  SliverFillRemaining(
                    hasScrollBody: false,
                    child: NoProductPage(
                      onRetry: widget.onRetry,
                    ),
                  )
                else
                  SliverPadding(
                    padding: EdgeInsets.only(
                      bottom: 30.h,
                    ),
                    sliver: SliverList(
                      delegate: SliverChildBuilderDelegate(
                            (context, index) {
                          if (index < state.sections.length) {
                            return HomeLayoutBody.buildSection(
                              context,
                              state.sections[index],
                              index,
                            );
                          }

                          return Padding(
                            padding: EdgeInsets.symmetric(
                              vertical: 16.h,
                            ),
                            child: const Center(
                              child:
                              CustomCircularProgressIndicator(),
                            ),
                          );
                        },
                        childCount: state.sections.length +
                            (state.isLoadingMore ? 1 : 0),
                      ),
                    ),
                  )
              else if (state is HomeLayoutFailed)
                  SliverFillRemaining(
                    hasScrollBody: false,
                    child: NoProductPage(
                      onRetry: widget.onRetry,
                    ),
                  )
                else
                  const SliverFillRemaining(
                    hasScrollBody: false,
                    child: Center(
                      child: CustomCircularProgressIndicator(),
                    ),
                  ),
            ],
          ),
        );
      },
    );
  }
}

