import 'dart:io';
import 'package:hyper_commerce_seller/extensions/translation_extension.dart';
import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:go_router/go_router.dart';
import 'package:hyper_commerce_seller/config/colors.dart';
import 'package:hyper_commerce_seller/config/constant.dart';
import 'package:hyper_commerce_seller/config/hive_storage.dart';
import 'package:hyper_commerce_seller/router/app_routes.dart';
import 'package:hyper_commerce_seller/screen/more_page/view/subscription_plans/widgets/subscription_plan_card.dart';
import 'package:hyper_commerce_seller/utils/image_path.dart';
import 'package:hyper_commerce_seller/utils/ui_utils.dart';
import 'package:hyper_commerce_seller/widgets/custom/custom_scaffold.dart';
import 'package:hyper_commerce_seller/widgets/custom/custom_shimmer.dart';
import 'package:hyper_commerce_seller/bloc/screen_size/screen_size_bloc.dart';
import 'package:hyper_commerce_seller/widgets/custom/custom_svg.dart';
import 'package:hyper_commerce_seller/widgets/ui/empty_state_widget.dart';
import '../bloc/subscription_plans_bloc/subscription_plan_bloc.dart';
import '../bloc/current_subscription/current_subscription_bloc.dart';
import '../bloc/buy_subscription/buy_subscription_bloc.dart';
import '../model/subscription_plans_model.dart';

class SubscriptionPlansPage extends StatefulWidget {
  const SubscriptionPlansPage({super.key});

  @override
  State<SubscriptionPlansPage> createState() => _SubscriptionPlansPageState();
}

class _SubscriptionPlansPageState extends State<SubscriptionPlansPage> {
  @override
  void initState() {
    super.initState();
    context.read<SubscriptionPlanBloc>().add(LoadSubscriptionPlansInitial());
  }

  @override
  Widget build(BuildContext context) {
    context.watch<CurrentSubscriptionBloc>();
    context.watch<BuySubscriptionBloc>();

    return BlocBuilder<ScreenSizeBloc, ScreenSizeState>(
      builder: (context, screenSizeState) {
        final screenType = screenSizeState.screenType;
        final isDark = Theme.of(context).brightness == Brightness.dark;
        return CustomScaffold(
          showAppbar: true,
          centerTitle: true,
          title: context.tr('subscriptionPlansTitle', fallback: 'Subscription Plans'),
          body: Container(
            color: isDark
                ? Theme.of(context).colorScheme.surface
                : const Color(0xFFE3F2FD).withValues(alpha: 0.5),
            child: BlocBuilder<SubscriptionPlanBloc, SubscriptionPlanState>(
              builder: (context, state) {
                if (state.error != null && state.items.isEmpty) {
                  return EmptyStateWidget(
                    svgPath: ImagesPath.noOrderFoundSvg,
                    title:
                        context.tr('somethingWentWrong', fallback: 'Seems like there is some issue'),
                    subtitle: context.tr('tryAgainLater', fallback: 'Please try again later'),
                    actionText: context.tr('tryAgain', fallback: 'Try Again'),
                    onAction: () {
                      context.read<SubscriptionPlanBloc>().add(
                        RefreshSubscriptionPlans(),
                      );
                    },
                  );
                }

                if (state.items.isEmpty &&
                    !state.isInitialLoading &&
                    !state.isRefreshing) {
                  return EmptyStateWidget(
                    svgPath: ImagesPath.noProductFoundSvg,
                    title:
                        context.tr('noSubscriptionPlansFound', fallback: 'No Subscription Plans Found!'),
                    actionText: context.tr('refresh', fallback: 'Refresh'),
                    onAction: () {
                      context.read<SubscriptionPlanBloc>().add(
                        RefreshSubscriptionPlans(),
                      );
                    },
                  );
                }

                final plans = state.items;

                return SafeArea(
                  top: true,
                  bottom: Platform.isIOS ? false : true,
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      // Plan cards list
                      Expanded(
                        child: RefreshIndicator(
                          color: AppColors.primaryColor,
                          onRefresh: () async {
                            context.read<SubscriptionPlanBloc>().add(
                              RefreshSubscriptionPlans(),
                            );
                            context.read<CurrentSubscriptionBloc>().add(
                              FetchCurrentSubscription(),
                            );
                          },
                          child: ListView.separated(
                            physics: const AlwaysScrollableScrollPhysics(),
                            padding: EdgeInsets.symmetric(
                              horizontal: UIUtils.gapMD(screenType),
                              vertical: UIUtils.gapLG(screenType),
                            ),
                            separatorBuilder: (_, __) =>
                                SizedBox(height: UIUtils.gapLG(screenType)),
                            itemCount:
                                (state.isInitialLoading ||
                                        state.isRefreshing) &&
                                    state.items.isEmpty
                                ? 4
                                : plans.length,
                            itemBuilder: (context, index) {
                              if (state.isInitialLoading ||
                                  state.isRefreshing) {
                                return const PlanCardShimmer();
                              }
                              return BlocBuilder<
                                CurrentSubscriptionBloc,
                                CurrentSubscriptionState
                              >(
                                builder: (context, currentSubState) {
                                  return PlanCard(
                                    plan: plans[index],
                                    st: screenType,
                                  );
                                },
                              );
                            },
                          ),
                        ),
                      ),
                    ],
                  ),
                );
              },
            ),
          ),
        );
      },
    );
  }
}
