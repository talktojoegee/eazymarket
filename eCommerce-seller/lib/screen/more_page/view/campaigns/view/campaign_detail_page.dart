import 'dart:developer';
import 'package:hyper_commerce_seller/extensions/translation_extension.dart';

import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:hyper_commerce_seller/config/colors.dart';
import 'package:hyper_commerce_seller/config/hive_storage.dart';
import 'package:hyper_commerce_seller/utils/ui_utils.dart';
import 'package:hyper_commerce_seller/widgets/custom/card_shimmers.dart';
import 'package:hyper_commerce_seller/widgets/custom/custom_scaffold.dart';
import 'package:hyper_commerce_seller/widgets/custom/custom_snackbar.dart';

import '../bloc/campaign_list_bloc/campaign_list_bloc.dart';
import '../bloc/create_campaign_bloc/create_campaign_bloc.dart';
import '../model/campaign_model.dart';
import '../repo/campaign_repo.dart';
import 'package:hyper_commerce_seller/widgets/custom/custom_loading_indicator.dart';

class CampaignDetailPage extends StatefulWidget {
  final int campaignId;

  const CampaignDetailPage({super.key, required this.campaignId});

  @override
  State<CampaignDetailPage> createState() => _CampaignDetailPageState();
}

class _CampaignDetailPageState extends State<CampaignDetailPage> {
  CampaignData? _campaign;
  bool _isLoading = true;
  String? _error;
  bool _wasModified = false;

  @override
  void initState() {
    super.initState();
    _fetchDetail();
  }

  Future<void> _fetchDetail() async {
    setState(() {
      _isLoading = true;
      _error = null;
    });
    try {
      final campaign =
          await CampaignRepository().fetchCampaignDetail(widget.campaignId);
      setState(() {
        _campaign = campaign;
        _isLoading = false;
      });
    } catch (e) {
      setState(() {
        _error = e.toString();
        _isLoading = false;
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    final screenType = context.screenType;
    final theme = Theme.of(context);

    // ignore: deprecated_member_use
    return WillPopScope(
      onWillPop: () async {
        Navigator.pop(context, _wasModified);
        return false;
      },
      child: BlocListener<CreateCampaignBloc, CreateCampaignState>(
        listener: (context, state) {
          if (state.actionStatus == CampaignActionStatus.success) {
            showCustomSnackbar(
              context: context,
              message: state.actionMessage ?? 'Action completed',
            );
            _wasModified = true;
            context.read<CampaignListBloc>().add(const LoadCampaignsInitial());
            if (state.updatedCampaign != null) {
              setState(() {
                _campaign = state.updatedCampaign;
              });
            } else {
              _fetchDetail();
            }
            context.read<CreateCampaignBloc>().add(ClearCampaignAction());
          } else if (state.actionStatus == CampaignActionStatus.failure) {
            showCustomSnackbar(
              context: context,
              message: state.error ?? 'Action failed',
              isWarning: true,
            );
            context.read<CreateCampaignBloc>().add(ClearCampaignAction());
          }
        },
        child: CustomScaffold(
          title: context.tr('campaignDetails', fallback: 'Campaign Details'),
          showAppbar: true,
          centerTitle: true,
          body: _buildBody(screenType, theme),
        ),
      ),
    );
  }

  Widget _buildBody(
      ScreenType screenType, ThemeData theme) {
    if (_isLoading) {
      return SingleChildScrollView(
        padding: UIUtils.pagePadding(screenType),
        child: CardShimmer(type: 'campaignDetail', screenType: screenType),
      );
    }

    if (_error != null) {
      return Center(
        child: Padding(
          padding: EdgeInsets.all(UIUtils.gapXL(screenType)),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              Icon(
                Icons.error_outline_rounded,
                size: 64,
                color: Colors.red.shade300,
              ),
              SizedBox(height: UIUtils.gapMD(screenType)),
              Text(
                context.tr('somethingWentWrong', fallback: 'Something went wrong'),
                style: TextStyle(
                  fontSize: UIUtils.tileTitle(screenType),
                  fontWeight: FontWeight.w600,
                  color: Colors.grey.shade600,
                ),
              ),
              SizedBox(height: UIUtils.gapLG(screenType)),
              OutlinedButton.icon(
                onPressed: _fetchDetail,
                icon: const Icon(Icons.refresh),
                label: Text(context.tr('retry', fallback: 'Retry')),
                style: OutlinedButton.styleFrom(
                  foregroundColor: AppColors.primaryColor,
                  side: BorderSide(color: AppColors.primaryColor),
                  shape: RoundedRectangleBorder(
                    borderRadius:
                        BorderRadius.circular(UIUtils.radiusMD(screenType)),
                  ),
                ),
              ),
            ],
          ),
        ),
      );
    }

    if (_campaign == null) {
      return Center(
        child: Text(
          context.tr('campaignNotFound', fallback: 'Campaign not found'),
          style: TextStyle(fontSize: UIUtils.body(screenType)),
        ),
      );
    }

    return RefreshIndicator(
      onRefresh: _fetchDetail,
      color: AppColors.primaryColor,
      child: SingleChildScrollView(
        physics: const AlwaysScrollableScrollPhysics(),
        padding: UIUtils.pagePadding(screenType),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            _buildProductInfo(screenType, theme),
            SizedBox(height: UIUtils.gapXL(screenType)),
            _buildStatusSection(screenType, theme),
            SizedBox(height: UIUtils.gapXL(screenType)),
            _buildBudgetSection(screenType, theme),
            SizedBox(height: UIUtils.gapXL(screenType)),
            _buildStatsSection(screenType, theme),
            /*SizedBox(height: UIUtils.gapXL(screenType)),
            _buildPlacementsSection(screenType, theme),*/
            if (_campaign!.rejectionReason != null) ...[
              SizedBox(height: UIUtils.gapXL(screenType)),
              _buildRejectionSection(screenType),
            ],
            if (_campaign!.forceStopReason != null) ...[
              SizedBox(height: UIUtils.gapXL(screenType)),
              _buildForceStopSection(screenType),
            ],
            SizedBox(height: UIUtils.gapXL(screenType)),
            _buildActionButtons(screenType),
            SizedBox(height: UIUtils.gapXL(screenType)),
          ],
        ),
      ),
    );
  }

  Widget _buildProductInfo(ScreenType screenType, ThemeData theme) {
    return Container(
      padding: EdgeInsets.all(UIUtils.gapMD(screenType)),
      decoration: BoxDecoration(
        color: theme.cardColor,
        borderRadius: BorderRadius.circular(UIUtils.radiusMD(screenType)),
        border: Border.all(color: theme.dividerColor.withValues(alpha: 0.2)),
      ),
      child: Row(
        children: [
          ClipRRect(
            borderRadius: BorderRadius.circular(UIUtils.radiusSM(screenType)),
            child: Image.network(
              _campaign!.productImage,
              width: 70,
              height: 70,
              fit: BoxFit.cover,
              errorBuilder: (_, __, ___) => Container(
                width: 70,
                height: 70,
                color: Colors.grey.shade200,
                child: const Icon(Icons.image, color: Colors.grey),
              ),
            ),
          ),
          SizedBox(width: UIUtils.gapMD(screenType)),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  _campaign!.productTitle,
                  style: TextStyle(
                    fontSize: UIUtils.tileTitle(screenType),
                    fontWeight: FontWeight.w600,
                  ),
                  maxLines: 2,
                  overflow: TextOverflow.ellipsis,
                ),
                SizedBox(height: UIUtils.gapXS(screenType)),
                Text(
                  'ID: ${_campaign!.productId}',
                  style: TextStyle(
                    fontSize: UIUtils.caption(screenType),
                    color: Colors.grey,
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildStatusSection(
    ScreenType screenType,
    ThemeData theme,
  ) {
    final statusColor = _getStatusColor(_campaign!.status);

    return Row(
      children: [
        Container(
          padding: EdgeInsets.symmetric(
            horizontal: UIUtils.gapMD(screenType),
            vertical: UIUtils.gapSM(screenType),
          ),
          decoration: BoxDecoration(
            color: statusColor.withValues(alpha: 0.1),
            borderRadius: BorderRadius.circular(UIUtils.radiusSM(screenType)),
          ),
          child: Text(
            _campaign!.statusLabel,
            style: TextStyle(
              color: statusColor,
              fontSize: UIUtils.body(screenType),
              fontWeight: FontWeight.w600,
            ),
          ),
        ),
        const Spacer(),
        Text(
          _campaign!.createdAt.split('T').first,
          style: TextStyle(
            fontSize: UIUtils.caption(screenType),
            color: Colors.grey,
          ),
        ),
      ],
    );
  }

  Widget _buildBudgetSection(
    ScreenType screenType,
    ThemeData theme,
  ) {
    return Container(
      padding: EdgeInsets.all(UIUtils.gapMD(screenType)),
      decoration: BoxDecoration(
        color: theme.cardColor,
        borderRadius: BorderRadius.circular(UIUtils.radiusMD(screenType)),
        border: Border.all(color: theme.dividerColor.withValues(alpha: 0.2)),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            context.tr('budgetAndSpend', fallback: 'Budget & Spend'),
            style: TextStyle(
              fontSize: UIUtils.tileTitle(screenType),
              fontWeight: FontWeight.w600,
            ),
          ),
          SizedBox(height: UIUtils.gapMD(screenType)),
          ClipRRect(
            borderRadius: BorderRadius.circular(4),
            child: LinearProgressIndicator(
              value: _campaign!.spendProgress / 100,
              backgroundColor: Colors.grey.shade200,
              valueColor:
                  AlwaysStoppedAnimation<Color>(AppColors.primaryColor),
              minHeight: 8,
            ),
          ),
          SizedBox(height: UIUtils.gapMD(screenType)),
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              _buildBudgetItem(
                context.tr('totalBudget', fallback: 'Total Budget'),
                '${HiveStorage.currencySymbol}${_campaign!.budget.toStringAsFixed(2)}',
                screenType,
              ),
              _buildBudgetItem(
                context.tr('spent', fallback: 'Spent'),
                '${HiveStorage.currencySymbol}${_campaign!.spent.toStringAsFixed(2)}',
                screenType,
              ),
              _buildBudgetItem(
                context.tr('remaining', fallback: 'Remaining'),
                '${HiveStorage.currencySymbol}${_campaign!.remainingBudget.toStringAsFixed(2)}',
                screenType,
              ),
            ],
          ),
          SizedBox(height: UIUtils.gapSM(screenType)),
          Text(
            '${context.tr('cpcRate', fallback: "CPC Rate")}: ${HiveStorage.currencySymbol}${_campaign!.cpcRateSnapshot}',
            style: TextStyle(
              fontSize: UIUtils.caption(screenType),
              color: Colors.grey,
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildBudgetItem(
      String label, String value, ScreenType screenType) {
    return Column(
      children: [
        Text(
          value,
          style: TextStyle(
            fontSize: UIUtils.tileTitle(screenType),
            fontWeight: FontWeight.bold,
          ),
        ),
        const SizedBox(height: 2),
        Text(
          label,
          style: TextStyle(
            fontSize: UIUtils.caption(screenType),
            color: Colors.grey,
          ),
        ),
      ],
    );
  }

  Widget _buildStatsSection(
    ScreenType screenType,
    ThemeData theme,
  ) {
    return Container(
      padding: EdgeInsets.all(UIUtils.gapMD(screenType)),
      decoration: BoxDecoration(
        color: theme.cardColor,
        borderRadius: BorderRadius.circular(UIUtils.radiusMD(screenType)),
        border: Border.all(color: theme.dividerColor.withValues(alpha: 0.2)),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            context.tr('performance', fallback: 'Performance'),
            style: TextStyle(
              fontSize: UIUtils.tileTitle(screenType),
              fontWeight: FontWeight.w600,
            ),
          ),
          SizedBox(height: UIUtils.gapMD(screenType)),
          Row(
            children: [
              Expanded(
                child: _buildStatItem(
                  Icons.visibility_outlined,
                  context.tr('impressions', fallback: 'Impressions'),
                  _campaign!.totalImpressions.toString(),
                  screenType,
                ),
              ),
              Expanded(
                child: _buildStatItem(
                  Icons.touch_app_outlined,
                  context.tr('clicks', fallback: 'Clicks'),
                  _campaign!.totalClicks.toString(),
                  screenType,
                ),
              ),
              Expanded(
                child: _buildStatItem(
                  Icons.payments_outlined,
                  context.tr('totalSpent', fallback: 'Total Spent'),
                  '${HiveStorage.currencySymbol}${_campaign!.totalSpentStats.toStringAsFixed(2)}',
                  screenType,
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }

  Widget _buildStatItem(
    IconData icon,
    String label,
    String value,
    ScreenType screenType,
  ) {
    return Column(
      children: [
        Icon(icon, size: 24, color: AppColors.primaryColor),
        SizedBox(height: UIUtils.gapXS(screenType)),
        Text(
          value,
          style: TextStyle(
            fontSize: UIUtils.tileTitle(screenType),
            fontWeight: FontWeight.bold,
          ),
        ),
        const SizedBox(height: 2),
        Text(
          label,
          style: TextStyle(
            fontSize: UIUtils.caption(screenType),
            color: Colors.grey,
          ),
          textAlign: TextAlign.center,
        ),
      ],
    );
  }

  Widget _buildRejectionSection(
      ScreenType screenType) {
    return Container(
      padding: EdgeInsets.all(UIUtils.gapMD(screenType)),
      decoration: BoxDecoration(
        color: Colors.red.withValues(alpha: 0.05),
        borderRadius: BorderRadius.circular(UIUtils.radiusMD(screenType)),
        border: Border.all(color: Colors.red.withValues(alpha: 0.2)),
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Icon(Icons.error_outline, color: Colors.red, size: 20),
          SizedBox(width: UIUtils.gapSM(screenType)),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  context.tr('rejectionReason', fallback: 'Rejection Reason'),
                  style: TextStyle(
                    fontSize: UIUtils.body(screenType),
                    fontWeight: FontWeight.w600,
                    color: Colors.red,
                  ),
                ),
                SizedBox(height: UIUtils.gapXS(screenType)),
                Text(
                  _campaign!.rejectionReason!,
                  style: TextStyle(fontSize: UIUtils.body(screenType)),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildForceStopSection(
      ScreenType screenType) {
    return Container(
      padding: EdgeInsets.all(UIUtils.gapMD(screenType)),
      decoration: BoxDecoration(
        color: Colors.orange.withValues(alpha: 0.05),
        borderRadius: BorderRadius.circular(UIUtils.radiusMD(screenType)),
        border: Border.all(color: Colors.orange.withValues(alpha: 0.2)),
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Icon(Icons.warning_amber, color: Colors.orange, size: 20),
          SizedBox(width: UIUtils.gapSM(screenType)),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  context.tr('forceStoppedByAdmin', fallback: 'Force Stopped by Admin'),
                  style: TextStyle(
                    fontSize: UIUtils.body(screenType),
                    fontWeight: FontWeight.w600,
                    color: Colors.orange,
                  ),
                ),
                SizedBox(height: UIUtils.gapXS(screenType)),
                Text(
                  _campaign!.forceStopReason!,
                  style: TextStyle(fontSize: UIUtils.body(screenType)),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildActionButtons(ScreenType screenType) {
    final status = _campaign!.status;

    if (status != 'running' && status != 'paused') {
      return const SizedBox.shrink();
    }

    return BlocBuilder<CreateCampaignBloc, CreateCampaignState>(
      builder: (context, state) {
        final isLoading = state.actionStatus == CampaignActionStatus.loading;

        if (status == 'running') {
          return SizedBox(
            width: double.infinity,
            child: OutlinedButton.icon(
              onPressed: isLoading
                  ? null
                  : () => _showConfirmDialog(
                        title: context.tr('pauseCampaign', fallback: 'Pause Campaign'),
                        message: context.tr('pauseCampaignConfirm', fallback: 'Are you sure you want to pause this campaign? It will stop receiving impressions.'),
                        confirmLabel: context.tr('pause', fallback: 'Pause'),
                        confirmColor: Colors.orange,
                        onConfirm: () {
                          context.read<CreateCampaignBloc>().add(
                                PauseCampaign(campaignId: _campaign!.id),
                              );
                        },
                      ),
              icon: isLoading
                  ? const SizedBox(
                      width: 16,
                      height: 16,
                      child: CustomLoadingIndicator(strokeWidth: 2),
                    )
                  : const Icon(Icons.pause_circle_outline),
              label: Text(context.tr('pauseCampaign', fallback: 'Pause Campaign')),
              style: OutlinedButton.styleFrom(
                foregroundColor: Colors.orange,
                side: const BorderSide(color: Colors.orange),
                padding: EdgeInsets.symmetric(
                  vertical: UIUtils.gapMD(screenType),
                ),
                shape: RoundedRectangleBorder(
                  borderRadius: BorderRadius.circular(
                    UIUtils.radiusMD(screenType),
                  ),
                ),
              ),
            ),
          );
        }

        return SizedBox(
          width: double.infinity,
          child: ElevatedButton.icon(
            onPressed: isLoading
                ? null
                : () => _showConfirmDialog(
                      title: context.tr('resumeCampaign', fallback: 'Resume Campaign'),
                      message: context.tr('resumeCampaignConfirm', fallback: 'Are you sure you want to resume this campaign? Budget will start being consumed again.'),
                      confirmLabel: context.tr('resume', fallback: 'Resume'),
                      confirmColor: Colors.green,
                      onConfirm: () {
                        context.read<CreateCampaignBloc>().add(
                              ResumeCampaign(campaignId: _campaign!.id),
                            );
                      },
                    ),
            icon: isLoading
                ? const SizedBox(
                    width: 16,
                    height: 16,
                    child: CustomLoadingIndicator(
                      strokeWidth: 2,
                      color: Colors.white,
                    ),
                  )
                : const Icon(Icons.play_circle_outline),
            label: Text(context.tr('resumeCampaign', fallback: 'Resume Campaign')),
            style: ElevatedButton.styleFrom(
              backgroundColor: Colors.green,
              foregroundColor: Colors.white,
              padding: EdgeInsets.symmetric(
                vertical: UIUtils.gapMD(screenType),
              ),
              shape: RoundedRectangleBorder(
                borderRadius: BorderRadius.circular(
                  UIUtils.radiusMD(screenType),
                ),
              ),
            ),
          ),
        );
      },
    );
  }

  void _showConfirmDialog({
    required String title,
    required String message,
    required String confirmLabel,
    required Color confirmColor,
    required VoidCallback onConfirm,
  }) {
    showDialog(
      context: context,
      builder: (dialogContext) => AlertDialog(
        title: Text(title),
        content: Text(message),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(dialogContext),
            child: Text(context.tr('cancel', fallback: 'Cancel')),
          ),
          ElevatedButton(
            onPressed: () {
              Navigator.pop(dialogContext);
              onConfirm();
            },
            style: ElevatedButton.styleFrom(
              backgroundColor: confirmColor,
              foregroundColor: Colors.white,
            ),
            child: Text(confirmLabel),
          ),
        ],
      ),
    );
  }

  Color _getStatusColor(String status) {
    switch (status) {
      case 'running':
        return Colors.green;
      case 'paused':
        return Colors.orange;
      case 'pending_approval':
        return Colors.blue;
      case 'rejected':
        return Colors.red;
      default:
        return Colors.grey;
    }
  }
}
