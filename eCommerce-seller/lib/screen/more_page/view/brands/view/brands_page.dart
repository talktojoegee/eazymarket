import 'package:flutter/material.dart';
import 'package:hyper_commerce_seller/extensions/translation_extension.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:hyper_commerce_seller/config/colors.dart';
import 'package:hyper_commerce_seller/utils/image_path.dart';
import 'package:hyper_commerce_seller/utils/ui_utils.dart';
import 'package:hyper_commerce_seller/widgets/custom/custom_card.dart';
import 'package:hyper_commerce_seller/bloc/screen_size/screen_size_bloc.dart';
import 'package:hyper_commerce_seller/widgets/custom/custom_scaffold.dart';
import 'package:hyper_commerce_seller/screen/more_page/view/brands/bloc/brands_bloc.dart';
import 'package:hyper_commerce_seller/screen/more_page/view/brands/model/brands_model.dart';
import 'package:hyper_commerce_seller/widgets/custom/card_shimmers.dart';
import 'package:hyper_commerce_seller/widgets/custom/custom_shimmer.dart';
import 'package:hyper_commerce_seller/widgets/custom/custom_loading_indicator.dart';
import 'package:hyper_commerce_seller/utils/debouncer.dart';
import 'package:hyper_commerce_seller/widgets/custom/custom_buttons.dart';
import 'package:hyper_commerce_seller/config/hive_storage.dart';
import 'package:hyper_commerce_seller/config/api_routes.dart';
import 'package:hyper_commerce_seller/service/external_link.dart';
import 'package:hyper_commerce_seller/widgets/ui/empty_state_widget.dart';

class BrandsPage extends StatefulWidget {
  const BrandsPage({super.key});

  @override
  State<BrandsPage> createState() => _BrandsPageState();
}

class _BrandsPageState extends State<BrandsPage> {
  final ScrollController _scrollController = ScrollController();
  final Debouncer _debouncer = Debouncer(milliseconds: 500);

  @override
  void initState() {
    super.initState();
    // Trigger initial load when entering the page
    context.read<BrandsBloc>().add(LoadBrandsInitial(search: null));
    _scrollController.addListener(_onScroll);
  }

  @override
  void dispose() {
    _scrollController.removeListener(_onScroll);
    _scrollController.dispose();
    _debouncer.dispose();
    super.dispose();
  }

  void _onScroll() {
    if (_isBottom) {
      context.read<BrandsBloc>().add(LoadMoreBrands());
    }
  }

  bool get _isBottom {
    if (!_scrollController.hasClients) return false;
    final maxScroll = _scrollController.position.maxScrollExtent;
    final currentScroll = _scrollController.offset;
    return currentScroll >= (maxScroll * 0.9);
  }

  @override
  Widget build(BuildContext context) {

    return BlocBuilder<ScreenSizeBloc, ScreenSizeState>(
      builder: (context, screenSizeState) {
        final screenType = screenSizeState.screenType;

        return CustomScaffold(
          centerTitle: true,
          showAppbar: true,
          isHaveSearch: true,
          onSearchChanged: (value) {
            _debouncer.run(() {
              context.read<BrandsBloc>().add(SearchBrands(value));
            });
          },
          title: context.tr('brands', fallback: "Brands"),
          body: BlocBuilder<BrandsBloc, BrandsState>(
            builder: (context, state) {
              // Removed early return for initial loading to show shimmers in the layout

              if (state.error != null && state.items.isEmpty) {
                return EmptyStateWidget(
                  svgPath: ImagesPath.noOrderFoundSvg,
                  title:
                      context.tr('somethingWentWrong', fallback: 'Seems like there is some issue'),
                  subtitle: context.tr('tryAgainLater', fallback: 'Please try again later'),
                  actionText: context.tr('tryAgain', fallback: 'Try Again'),
                  onAction: () {
                    context.read<BrandsBloc>().add(RefreshBrands());
                  },
                );
              }

              if (!state.isInitialLoading &&
                  !state.isRefreshing &&
                  state.items.isEmpty) {
                return EmptyStateWidget(
                  svgPath: ImagesPath.noProductFoundSvg,
                  title: context.tr('noBrandsFound', fallback: 'No brands found'),
                  subtitle:
                      context.tr('noBrandsAddedYet', fallback: 'You have not added any brands yet'),
                  actionText: (context.tr('refresh', fallback: 'Refresh')),
                  onAction: () {
                    context.read<BrandsBloc>().add(RefreshBrands());
                  },
                );
              }

              final items = state.items;
              final hasMore = state.hasMore;
              final total = state.total ?? 0;
              final isPaginating = state.isPaginating;

              return Column(
                children: [
                  Padding(
                    padding: UIUtils.pagePadding(screenType),
                    child: Row(
                      mainAxisAlignment: MainAxisAlignment.spaceBetween,
                      children: [
                        (state.isInitialLoading || state.isRefreshing) &&
                                state.items.isEmpty
                            ? CustomShimmer(
                                width: 150,
                                height: UIUtils.body(screenType),
                              )
                            : Text(
                                "${context.tr('total', fallback: "Total")} ${context.tr('brands', fallback: "Brands")} ($total)",
                                style: TextStyle(
                                  fontWeight: FontWeight.bold,
                                  fontSize: UIUtils.body(screenType),
                                ),
                              ),
                        if (state.isRefreshing)
                          const CustomLoadingIndicator(
                            size: 16,
                            strokeWidth: 2,
                          ),
                      ],
                    ),
                  ),
                  Expanded(
                    child: RefreshIndicator(
                      color: AppColors.primaryColor,
                      onRefresh: () async {
                        context.read<BrandsBloc>().add(RefreshBrands());
                      },
                      child: ListView.separated(
                        controller: _scrollController,
                        padding: UIUtils.cardsPadding(screenType),
                        separatorBuilder: (context, index) =>
                            SizedBox(height: UIUtils.gapMD(screenType)),
                        itemCount:
                            (state.isInitialLoading || state.isRefreshing) &&
                                state.items.isEmpty
                            ? 10
                            : items.length +
                                  (hasMore ? (isPaginating ? 10 : 1) : 0),
                        itemBuilder: (context, index) {
                          if ((state.isInitialLoading || state.isRefreshing) &&
                              state.items.isEmpty) {
                            return CardShimmer(
                              type: 'brand',
                              screenType: screenType,
                            );
                          }
                          if (index >= items.length) {
                            return CardShimmer(
                              type: 'brand',
                              screenType: screenType,
                            );
                          }

                          final brand = items[index];
                          return CustomCard(
                            type: CardType.brand,
                            data: _mapBrandToData(brand),
                            screenType: screenType,
                          );
                        },
                      ),
                    ),
                  ),
                ],
              );
            },
          ),
        );
      },
    );
  }

  Map<String, dynamic> _mapBrandToData(Brand brand) {
    return {
      "id": brand.id.toString(),
      "name": brand.title,
      "image": brand.logo,
      "status": brand.status,
      "category": brand.scopeCategoryTitle ?? 'N/A',
      "total_products": brand.totalProducts,
      "description": brand.description,
    };
  }
}
