import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:hyper_commerce_seller/config/colors.dart';
import 'package:hyper_commerce_seller/config/hive_storage.dart';
import 'package:hyper_commerce_seller/extensions/translation_extension.dart';
import 'package:hyper_commerce_seller/router/app_routes.dart';

class DefaultOnBoardingPage extends StatefulWidget {
  const DefaultOnBoardingPage({super.key});

  @override
  DefaultOnBoardingPageState createState() => DefaultOnBoardingPageState();
}

class DefaultOnBoardingPageState extends State<DefaultOnBoardingPage>
    with TickerProviderStateMixin {
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

  void _nextPage() {
    if (currentIndex < _getOnboardingData().length - 1) {
      _pageController.nextPage(
        duration: const Duration(milliseconds: 300),
        curve: Curves.easeInOut,
      );
    } else {
      _navigateToHome();
    }
  }

  void _navigateToHome() {
    debugPrint('DefaultOnBoardingPage: _navigateToHome CALLED');
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

  List<DefaultOnboardingData> _getOnboardingData() => [
        DefaultOnboardingData(
          title: context.tr(
            'introPage1Title',
            fallback: context.tr('growYourStoreOnline', fallback: 'Grow Your Store Online'),
          ),
          description: context.tr(
            'introPage1Description',
            fallback: context.tr(
              'growYourStoreOnlineDesc',
              fallback: 'List products, manage inventory, and expand your business reach seamlessly.',
            ),
          ),
          image: 'assets/images/png/slider_1.png',
        ),
        DefaultOnboardingData(
          title: context.tr(
            'introPage2Title',
            fallback: context.tr('manageOrdersSeamlessly', fallback: 'Manage Orders Seamlessly'),
          ),
          description: context.tr(
            'introPage2Description',
            fallback: context.tr(
              'manageOrdersSeamlesslyDesc',
              fallback: 'Receive real-time order alerts, prepare packages, and track delivery effortlessly.',
            ),
          ),
          image: 'assets/images/png/slider_2.png',
        ),
        DefaultOnboardingData(
          title: context.tr(
            'introPage3Title',
            fallback: context.tr('trackEarningsGetPaid', fallback: 'Track Earnings & Get Paid'),
          ),
          description: context.tr(
            'introPage3Description',
            fallback: context.tr(
              'trackEarningsGetPaidDesc',
              fallback: 'Monitor daily sales, view commission breakdowns, and withdraw earnings directly.',
            ),
          ),
          image: 'assets/images/png/slider_3.png',
        ),
      ];

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

  Widget _buildIndicator(int index) {
    final bool isActive = currentIndex == index;
    return AnimatedContainer(
      duration: const Duration(milliseconds: 300),
      margin: const EdgeInsets.symmetric(horizontal: 4),
      height: 8,
      width: isActive ? 24 : 8,
      decoration: BoxDecoration(
        color: isActive ? AppColors.primaryColor : AppColors.primaryColor.withValues(alpha: 0.25),
        borderRadius: BorderRadius.circular(4),
      ),
    );
  }

  Widget _buildOnboardingPage(DefaultOnboardingData data, int index) {
    return AnimatedBuilder(
      animation: _animationController,
      builder: (context, child) {
        return Padding(
          padding: const EdgeInsets.symmetric(horizontal: 30),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              FadeTransition(
                opacity: _fadeAnimation,
                child: Image.asset(
                  data.image,
                  height: 220,
                  fit: BoxFit.contain,
                  errorBuilder: (context, error, stackTrace) => const SizedBox(
                    height: 220,
                    child: Center(
                      child: Icon(
                        Icons.image_not_supported_outlined,
                        size: 48,
                        color: Colors.grey,
                      ),
                    ),
                  ),
                ),
              ),
              const SizedBox(height: 28),
              Transform.translate(
                offset: _getSlideOffset(index),
                child: Opacity(
                  opacity: _fadeAnimation.value,
                  child: Text(
                    data.title,
                    textAlign: TextAlign.center,
                    style: const TextStyle(
                      fontSize: 26,
                      fontWeight: FontWeight.bold,
                      color: Color(0xFF1F2937),
                      height: 1.25,
                    ),
                  ),
                ),
              ),
              const SizedBox(height: 14),
              AnimatedOpacity(
                opacity: _fadeAnimation.value > 0.5 ? 1.0 : 0.0,
                duration: const Duration(milliseconds: 300),
                child: Transform.translate(
                  offset: Offset(0, (1 - _fadeAnimation.value) * 20),
                  child: Text(
                    data.description,
                    textAlign: TextAlign.center,
                    style: const TextStyle(
                      fontSize: 15,
                      color: Color(0xFF6B7280),
                      height: 1.5,
                    ),
                  ),
                ),
              ),
              const SizedBox(height: 140),
            ],
          ),
        );
      },
    );
  }

  @override
  Widget build(BuildContext context) {
    debugPrint('DefaultOnBoardingPage: build CALLED');
    final onboardingData = _getOnboardingData();

    return Scaffold(
      backgroundColor: Colors.white,
      body: Stack(
        children: [
          Container(
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
          ),
          PageView.builder(
            controller: _pageController,
            onPageChanged: _onPageChanged,
            itemCount: onboardingData.length,
            itemBuilder: (context, index) {
              return Stack(
                fit: StackFit.expand,
                children: [
                  SafeArea(
                    child: Center(
                      child: SingleChildScrollView(
                        physics: const BouncingScrollPhysics(),
                        child: _buildOnboardingPage(onboardingData[index], index),
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
                      children: List.generate(
                        onboardingData.length,
                        (index) => _buildIndicator(index),
                      ),
                    ),
                    const SizedBox(height: 40),
                    SizedBox(
                      width: double.infinity,
                      height: 54,
                      child: ElevatedButton(
                        onPressed: _nextPage,
                        style: ElevatedButton.styleFrom(
                          backgroundColor: AppColors.primaryColor,
                          shape: RoundedRectangleBorder(
                            borderRadius: BorderRadius.circular(16),
                          ),
                          elevation: 0,
                        ),
                        child: Text(
                          currentIndex == onboardingData.length - 1
                              ? context.tr('getStarted', fallback: 'Get Started')
                              : context.tr('next', fallback: 'Next'),
                          style: const TextStyle(
                            color: Colors.white,
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
                  style: const TextStyle(
                    color: AppColors.primaryColor,
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
}

class DefaultOnboardingData {
  final String title;
  final String description;
  final String image;

  DefaultOnboardingData({
    required this.title,
    required this.description,
    required this.image,
  });
}
