import 'package:cached_network_image/cached_network_image.dart';
import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:flutter_svg/flutter_svg.dart';
import 'package:go_router/go_router.dart';
import 'package:hyper_commerce_seller/config/colors.dart';
import 'package:hyper_commerce_seller/config/hive_storage.dart';
import 'package:hyper_commerce_seller/extensions/translation_extension.dart';
import 'package:hyper_commerce_seller/router/app_routes.dart';
import 'package:hyper_commerce_seller/screen/introduction_pages/bloc/onboarding_bloc.dart';
import 'package:hyper_commerce_seller/screen/introduction_pages/model/onboarding_model.dart';
import 'package:hyper_commerce_seller/screen/introduction_pages/view/default_onboarding_page.dart'
    show DefaultOnBoardingPage;
import 'package:hyper_commerce_seller/utils/color_utils.dart';
import 'package:hyper_commerce_seller/widgets/custom/custom_loading_indicator.dart';

class DynamicOnBoardingPage extends StatelessWidget {
  const DynamicOnBoardingPage({super.key});

  @override
  Widget build(BuildContext context) {
    if (!HiveStorage.isOnboardingEnabled ||
        (!HiveStorage.isFirstTime && !kDebugMode)) {
      WidgetsBinding.instance.addPostFrameCallback((_) {
        _navigateAway(context);
      });
      return const Scaffold(
        backgroundColor: Colors.white,
        body: SizedBox.shrink(),
      );
    }

    try {
      final bloc = context.read<OnboardingBloc>();
      if (bloc.state is OnboardingInitial) {
        bloc.add(FetchOnboardingRequest());
      }
      return const _DynamicOnBoardingContentView();
    } catch (_) {
      return BlocProvider(
        create: (_) => OnboardingBloc()..add(FetchOnboardingRequest()),
        child: const _DynamicOnBoardingContentView(),
      );
    }
  }

  static void _navigateAway(BuildContext context) {
    debugPrint('DynamicOnBoardingPage: _navigateAway CALLED');
    final token = HiveStorage.userToken;
    if (token != null && token.trim().isNotEmpty) {
      if (HiveStorage.isSubscriptionAvailable &&
          (HiveStorage.activePlanId == null || HiveStorage.activePlanId == 0) &&
          HiveStorage.pendingSubscriptionId == null) {
        context.go(AppRoutes.subscriptionChoosePlan);
      } else {
        context.go(AppRoutes.home);
      }
    } else {
      context.go(AppRoutes.login);
    }
  }
}

class _DynamicOnBoardingContentView extends StatefulWidget {
  const _DynamicOnBoardingContentView();

  @override
  State<_DynamicOnBoardingContentView> createState() =>
      _DynamicOnBoardingContentViewState();
}

class _DynamicOnBoardingContentViewState
    extends State<_DynamicOnBoardingContentView> with TickerProviderStateMixin {
  final PageController _pageController = PageController();
  int currentIndex = 0;
  late AnimationController _animationController;
  late AnimationController _iconAnimationController;
  late Animation<double> _fadeAnimation;

  @override
  void initState() {
    super.initState();
    _animationController = AnimationController(
      duration: const Duration(milliseconds: 1000),
      vsync: this,
    );
    _iconAnimationController = AnimationController(
      duration: const Duration(milliseconds: 1200),
      vsync: this,
    );

    _fadeAnimation = Tween<double>(begin: 0.0, end: 1.0).animate(
      CurvedAnimation(
        parent: _animationController,
        curve: const Interval(0.0, 0.6, curve: Curves.easeOut),
      ),
    );

    _animationController.forward();
    _iconAnimationController.forward();
  }

  @override
  void dispose() {
    _pageController.dispose();
    _animationController.dispose();
    _iconAnimationController.dispose();
    super.dispose();
  }

  void _onPageChanged(int index) {
    setState(() {
      currentIndex = index;
    });
    _animationController.reset();
    _iconAnimationController.reset();
    _animationController.forward();
    _iconAnimationController.forward();
  }

  void _nextPage(int totalPages) {
    if (currentIndex < totalPages - 1) {
      _pageController.nextPage(
        duration: const Duration(milliseconds: 300),
        curve: Curves.easeInOut,
      );
    } else {
      _navigateToHome();
    }
  }

  void _navigateToHome() {
    debugPrint('DynamicOnBoardingPage: _navigateToHome CALLED');
    HiveStorage.setIsFirstTime(false);

    final token = HiveStorage.userToken;
    if (token != null && token.trim().isNotEmpty) {
      if (HiveStorage.isSubscriptionAvailable &&
          (HiveStorage.activePlanId == null || HiveStorage.activePlanId == 0) &&
          HiveStorage.pendingSubscriptionId == null) {
        context.go(AppRoutes.subscriptionChoosePlan);
      } else {
        context.go(AppRoutes.home);
      }
    } else {
      context.go(AppRoutes.login);
    }
  }

  void _skipToEnd() {
    _navigateToHome();
  }

  BoxFit _getBoxFit(String? mode) {
    switch (mode?.toLowerCase().trim()) {
      case 'illustration':
      case 'contain':
      case 'fit':
        return BoxFit.contain;
      case 'fill':
        return BoxFit.fill;
      case 'fit_width':
      case 'fitwidth':
        return BoxFit.fitWidth;
      case 'fit_height':
      case 'fitheight':
        return BoxFit.fitHeight;
      case 'cover':
        return BoxFit.cover;
      default:
        return BoxFit.contain;
    }
  }

  Offset _getSlideOffset(int index) {
    switch (index % 3) {
      case 0:
        return Offset(0, (1 - _fadeAnimation.value) * 50);
      case 1:
        return Offset((1 - _fadeAnimation.value) * 100, 0);
      case 2:
        return Offset((1 - _fadeAnimation.value) * -100, 0);
      default:
        return Offset(0, (1 - _fadeAnimation.value) * 50);
    }
  }

  Widget _buildIndicator(int index, Color activeColor, Color inactiveColor) {
    final bool isActive = currentIndex == index;
    return AnimatedContainer(
      duration: const Duration(milliseconds: 300),
      margin: const EdgeInsets.symmetric(horizontal: 4),
      height: 8,
      width: isActive ? 24 : 8,
      decoration: BoxDecoration(
        color: isActive ? activeColor : inactiveColor,
        borderRadius: BorderRadius.circular(4),
      ),
    );
  }

  Widget _buildPageBackground(OnboardingData data) {
    final bgType = data.backgroundType?.toLowerCase().trim();
    final bgColor = hexStringToColor(data.backgroundColor);

    if (bgType == 'image' &&
        data.backgroundImage != null &&
        data.backgroundImage!.isNotEmpty) {
      return Stack(
        fit: StackFit.expand,
        children: [
          Container(color: bgColor ?? Colors.white),
          OnboardingImageContainer(
            imagePath: data.backgroundImage!,
            fit: BoxFit.cover,
            width: double.infinity,
            height: double.infinity,
          ),
        ],
      );
    }

    if (bgType == 'color' && bgColor != null) {
      return Container(color: bgColor);
    }

    if (bgColor != null) {
      return Container(color: bgColor);
    }

    return Container(
      decoration: const BoxDecoration(
        gradient: LinearGradient(
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
          colors: [
            Color(0xFFF8F9FF),
            Color(0xFFFFFFFF),
          ],
        ),
      ),
    );
  }

  Widget _buildOnboardingPage(OnboardingData data, int index) {
    return AnimatedBuilder(
      animation: _animationController,
      builder: (context, child) {
        return Padding(
          padding: const EdgeInsets.symmetric(horizontal: 30),
          child: _introScreenWidget(data, index),
        );
      },
    );
  }

  Widget _introScreenWidget(OnboardingData data, int index) {
    final titleColor =
        hexStringToColor(data.fontColor) ?? const Color(0xFF1F2937);
    final subtitleColor =
        hexStringToColor(data.fontColor)?.withValues(alpha: 0.75) ??
            const Color(0xFF6B7280);

    return Column(
      mainAxisSize: MainAxisSize.min,
      children: [
        if (data.image != null && data.image!.isNotEmpty) ...[
          FadeTransition(
            opacity: _fadeAnimation,
            child: OnboardingImageContainer(
              imagePath: data.image!,
              height: 220,
              fit: _getBoxFit(data.imageDisplayMode),
            ),
          ),
          const SizedBox(height: 28),
        ],
        if (data.title != null && data.title!.isNotEmpty) ...[
          Transform.translate(
            offset: _getSlideOffset(index),
            child: Opacity(
              opacity: _fadeAnimation.value,
              child: Text(
                data.title!,
                textAlign: TextAlign.center,
                style: TextStyle(
                  fontSize: 26,
                  fontWeight: FontWeight.bold,
                  color: titleColor,
                  height: 1.25,
                ),
              ),
            ),
          ),
          const SizedBox(height: 14),
        ],
        if (data.subtitle != null && data.subtitle!.isNotEmpty) ...[
          AnimatedOpacity(
            opacity: _fadeAnimation.value > 0.5 ? 1.0 : 0.0,
            duration: const Duration(milliseconds: 300),
            child: Transform.translate(
              offset: Offset(0, (1 - _fadeAnimation.value) * 20),
              child: Text(
                data.subtitle!,
                textAlign: TextAlign.center,
                style: TextStyle(
                  fontSize: 15,
                  color: subtitleColor,
                  height: 1.5,
                ),
              ),
            ),
          ),
        ],
        const SizedBox(height: 140),
      ],
    );
  }

  @override
  Widget build(BuildContext context) {
    return BlocBuilder<OnboardingBloc, OnboardingState>(
      builder: (BuildContext context, OnboardingState state) {
        debugPrint('_DynamicOnBoardingContentView: state=$state');
        if (state is OnboardingLoading || state is OnboardingInitial) {
          return const Scaffold(
            backgroundColor: Colors.white,
            body: Center(
              child: CustomLoadingIndicator(),
            ),
          );
        }

        if (state is OnboardingFailure ||
            (state is OnboardingSuccess && state.onboardingData.isEmpty)) {
          return const DefaultOnBoardingPage();
        }

        if (state is OnboardingSuccess) {
          final onboardingData = state.onboardingData;
          final currentItem =
              onboardingData[currentIndex.clamp(0, onboardingData.length - 1)];
          final nextButtonBgColor =
              hexStringToColor(currentItem.nextButtonBackgroundColor) ??
                  AppColors.primaryColor;
          final nextButtonTextColor =
              hexStringToColor(currentItem.nextButtonTextColor) ?? Colors.white;
          final bool showSkip = currentItem.showSkip ?? true;

          return Scaffold(
            backgroundColor: Colors.white,
            body: Stack(
              children: [
                PageView.builder(
                  controller: _pageController,
                  onPageChanged: _onPageChanged,
                  itemCount: onboardingData.length,
                  itemBuilder: (context, index) {
                    final data = onboardingData[index];
                    return Stack(
                      fit: StackFit.expand,
                      children: [
                        _buildPageBackground(data),
                        SafeArea(
                          child: Center(
                            child: SingleChildScrollView(
                              physics: const BouncingScrollPhysics(),
                              child: _buildOnboardingPage(data, index),
                            ),
                          ),
                        ),
                      ],
                    );
                  },
                ),
                Positioned(
                  bottom: 0,
                  left: 0,
                  right: 0,
                  child: SafeArea(
                    child: Container(
                      padding: const EdgeInsets.all(30),
                      child: Column(
                        mainAxisSize: MainAxisSize.min,
                        children: [
                          Row(
                            mainAxisAlignment: MainAxisAlignment.center,
                            children: onboardingData.length > 1
                                ? List.generate(
                                    onboardingData.length,
                                    (index) => _buildIndicator(
                                      index,
                                      nextButtonBgColor,
                                      nextButtonBgColor.withValues(alpha: 0.25),
                                    ),
                                  )
                                : [const SizedBox.shrink()],
                          ),
                          const SizedBox(height: 40),
                          SizedBox(
                            width: double.infinity,
                            height: 54,
                            child: ElevatedButton(
                              onPressed: () => _nextPage(onboardingData.length),
                              style: ElevatedButton.styleFrom(
                                backgroundColor: nextButtonBgColor,
                                shape: RoundedRectangleBorder(
                                  borderRadius: BorderRadius.circular(16),
                                ),
                                elevation: 0,
                              ),
                              child: Text(
                                currentIndex == onboardingData.length - 1
                                    ? context.tr('getStarted',
                                        fallback: 'Get Started')
                                    : context.tr('next', fallback: 'Next'),
                                style: TextStyle(
                                  color: nextButtonTextColor,
                                  fontSize: 16,
                                  fontWeight: FontWeight.w600,
                                ),
                              ),
                            ),
                          ),
                        ],
                      ),
                    ),
                  ),
                ),
                if (showSkip)
                  Positioned(
                    top: MediaQuery.of(context).padding.top + 8,
                    right: 16,
                    child: InkWell(
                      onTap: _skipToEnd,
                      borderRadius: BorderRadius.circular(8),
                      child: Container(
                        padding: const EdgeInsets.symmetric(
                          horizontal: 16,
                          vertical: 8,
                        ),
                        child: Text(
                          context.tr('skip', fallback: 'Skip'),
                          style: TextStyle(
                            color: nextButtonBgColor,
                            fontSize: 15,
                            fontWeight: FontWeight.w600,
                          ),
                        ),
                      ),
                    ),
                  ),
              ],
            ),
          );
        }

        return const Scaffold(
          backgroundColor: Colors.white,
          body: SizedBox.shrink(),
        );
      },
    );
  }
}

class OnboardingImageContainer extends StatelessWidget {
  final String imagePath;
  final double? width;
  final double? height;
  final BoxFit fit;

  const OnboardingImageContainer({
    super.key,
    required this.imagePath,
    this.width,
    this.height,
    this.fit = BoxFit.contain,
  });

  bool _isNetworkImage(String path) {
    return path.startsWith('http://') || path.startsWith('https://');
  }

  bool _isSvgImage(String path) {
    return path.toLowerCase().contains('.svg');
  }

  @override
  Widget build(BuildContext context) {
    final isNetwork = _isNetworkImage(imagePath);
    final isSvg = _isSvgImage(imagePath);

    if (isNetwork) {
      if (isSvg) {
        return SvgPicture.network(
          imagePath,
          width: width,
          height: height,
          fit: fit,
          placeholderBuilder: (_) => SizedBox(
            width: width,
            height: height,
            child: const Center(
              child: CustomLoadingIndicator(),
            ),
          ),
        );
      } else {
        return CachedNetworkImage(
          imageUrl: imagePath,
          width: width,
          height: height,
          fit: fit,
          placeholder: (context, url) => SizedBox(
            width: width,
            height: height,
            child: const Center(
              child: CustomLoadingIndicator(),
            ),
          ),
          errorWidget: (context, url, error) => SizedBox(
            width: width,
            height: height,
            child: const Icon(
              Icons.image_not_supported_outlined,
              color: Colors.grey,
            ),
          ),
        );
      }
    } else {
      if (isSvg) {
        return SvgPicture.asset(
          imagePath,
          width: width,
          height: height,
          fit: fit,
        );
      } else {
        return Image.asset(
          imagePath,
          width: width,
          height: height,
          fit: fit,
          errorBuilder: (context, error, stackTrace) => SizedBox(
            width: width,
            height: height,
            child: const Icon(
              Icons.image_not_supported_outlined,
              color: Colors.grey,
            ),
          ),
        );
      }
    }
  }
}
