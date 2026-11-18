import 'package:flutter/material.dart';
import 'package:hyper_commerce_seller/extensions/translation_extension.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:hyper_commerce_seller/config/hive_storage.dart';
import 'package:hyper_commerce_seller/service/demo_guard.dart';
import 'package:hyper_commerce_seller/config/app_permissions.dart';
import 'package:hyper_commerce_seller/utils/permission_checker.dart';
import 'package:hyper_commerce_seller/utils/ui_utils.dart';
import 'package:hyper_commerce_seller/widgets/custom/custom_scaffold.dart';
import 'package:hyper_commerce_seller/widgets/custom/custom_loading_indicator.dart';
import 'package:hyper_commerce_seller/widgets/custom/custom_snackbar.dart';

import '../bloc/wallet_bloc/wallet_bloc.dart';

class WithdrawRequestPage extends StatefulWidget {
  const WithdrawRequestPage({super.key});

  @override
  State<WithdrawRequestPage> createState() => _WithdrawRequestPageState();
}

class _WithdrawRequestPageState extends State<WithdrawRequestPage> {
  String _amount = '';

  @override
  void initState() {
    super.initState();
    context.read<WalletBloc>().add(ClearWithdrawState());
    if (context.read<WalletBloc>().state.walletData == null) {
      context.read<WalletBloc>().add(FetchWallet());
    }
  }

  double _getAvailableAmount(WalletState state) {
    final avail = state.walletData?.availableBalance;
    if (avail != null && avail.trim().isNotEmpty) {
      final clean = avail.replaceAll(',', '').trim();
      final parsed = double.tryParse(clean);
      if (parsed != null) return parsed;
    }
    final bal = state.walletData?.balance;
    if (bal != null && bal.trim().isNotEmpty) {
      final clean = bal.replaceAll(',', '').trim();
      final parsed = double.tryParse(clean);
      if (parsed != null) return parsed;
    }
    return 0.0;
  }

  String _getAvailableBalanceDisplay(WalletState state) {
    final formatted = state.walletData?.availableFormattedBalance;
    if (formatted != null && formatted.trim().isNotEmpty) {
      return formatted;
    }
    final avail = state.walletData?.availableBalance;
    if (avail != null && avail.trim().isNotEmpty) {
      return '${HiveStorage.currencySymbol}$avail';
    }
    final bal = state.walletData?.balance ?? '0.00';
    return '${HiveStorage.currencySymbol}$bal';
  }

  void _onNumberTap(String number) {
    if (number == '.') {
      if (_amount.contains('.')) return;
      if (_amount.isEmpty) {
        setState(() {
          _amount = '0.';
        });
        return;
      }
    }
    if (number == '0' && _amount == '0') return;
    if (_amount.contains('.')) {
      final parts = _amount.split('.');
      if (parts.length > 1 && parts[1].length >= 2) return;
    }
    setState(() {
      if (_amount == '0' && number != '.') {
        _amount = number;
      } else {
        _amount += number;
      }
    });
  }

  void _onBackspace() {
    if (_amount.isNotEmpty) {
      setState(() {
        _amount = _amount.substring(0, _amount.length - 1);
      });
    }
  }

  void _onSubmit() {
    if (!PermissionChecker.hasPermission(AppPermissions.withdrawalRequest)) {
      showCustomSnackbar(
        context: context,
        message:
            context.tr('noPermissionRequestWithdrawal', fallback: "You don't have permission to request withdrawal"),
        isWarning: true,
      );
      return;
    }
    if (!DemoGuard.shouldProceed(context)) {
      return;
    }
    final double? entered = double.tryParse(_amount);
    if (_amount.isEmpty || entered == null || entered <= 0) {
      showCustomSnackbar(
        context: context,
        message:
            context.tr('enterValidAmount', fallback: 'Please enter a valid amount'),
        isError: true,
      );
      return;
    }

    final walletState = context.read<WalletBloc>().state;
    final available = _getAvailableAmount(walletState);
    if (entered > available) {
      showCustomSnackbar(
        context: context,
        message: context.tr(
          'amountExceedsBalance',
          fallback: 'Amount exceeds available balance',
        ),
        isError: true,
      );
      return;
    }

    context.read<WalletBloc>().add(SubmitWithdrawRequest(amount: _amount));
  }

  @override
  Widget build(BuildContext context) {
    final screenType = context.screenType;
    final theme = Theme.of(context);

    return BlocListener<WalletBloc, WalletState>(
      listenWhen: (previous, current) =>
          previous.withdrawStatus != current.withdrawStatus,
      listener: (context, state) {
        if (state.withdrawStatus == WithdrawStatus.success) {
          showCustomSnackbar(
            context: context,
            message:
                state.withdrawMessage ??
                'Withdrawal request submitted successfully',
          );
          if (mounted) {
            Navigator.of(context).pop();
          }
        } else if (state.withdrawStatus == WithdrawStatus.failure) {
          showCustomSnackbar(
            context: context,
            message:
                state.withdrawMessage ??
                'Withdrawal request submitted successfully',
            isError: true,
          );
        }
      },
      child: CustomScaffold(
        title: context.tr('withdrawRequest', fallback: 'Withdraw Request'),
        showAppbar: true,
        centerTitle: true,
        body: Column(
          children: [
            Expanded(
              child: SingleChildScrollView(
                padding: UIUtils.pagePadding(screenType),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    // Amount Display
                    Center(
                      child: Column(
                        children: [
                          Text(
                            context.tr('amountToWithdraw', fallback: 'Amount to Withdraw'),
                            style: TextStyle(
                              fontSize: UIUtils.body(screenType),
                              color: Colors.grey,
                            ),
                          ),
                          SizedBox(height: UIUtils.gapMD(screenType)),
                          BlocBuilder<WalletBloc, WalletState>(
                            builder: (context, state) {
                              final availableAmount = _getAvailableAmount(state);
                              final enteredAmount = double.tryParse(_amount) ?? 0.0;
                              final isExceeding = enteredAmount > availableAmount;

                              return Column(
                                children: [
                                  Row(
                                    mainAxisAlignment: MainAxisAlignment.center,
                                    crossAxisAlignment: CrossAxisAlignment.start,
                                    children: [
                                      Text(
                                        HiveStorage.currencySymbol,
                                        style: TextStyle(
                                          fontSize: UIUtils.pageTitle(screenType),
                                          fontWeight: FontWeight.bold,
                                          color: isExceeding ? Colors.red : null,
                                        ),
                                      ),
                                      const SizedBox(width: 4),
                                      Text(
                                        _amount.isEmpty ? '00' : _amount,
                                        style: TextStyle(
                                          fontSize: UIUtils.pageTitle(screenType) * 2,
                                          fontWeight: FontWeight.bold,
                                          color: isExceeding ? Colors.red : null,
                                        ),
                                      ),
                                    ],
                                  ),
                                  SizedBox(height: UIUtils.gapSM(screenType)),
                                  Text(
                                    '${_getAvailableBalanceDisplay(state)} ${context.tr('availableForWithdraw', fallback: 'Available for Withdraw')}',
                                    style: TextStyle(
                                      fontSize: UIUtils.caption(screenType),
                                      color: Colors.grey,
                                    ),
                                  ),
                                  if (isExceeding) ...[
                                    SizedBox(height: UIUtils.gapXS(screenType)),
                                    Text(
                                      context.tr(
                                        'amountExceedsBalance',
                                        fallback: 'Amount exceeds available balance',
                                      ),
                                      style: TextStyle(
                                        fontSize: UIUtils.caption(screenType),
                                        color: Colors.red,
                                        fontWeight: FontWeight.w500,
                                      ),
                                    ),
                                  ],
                                ],
                              );
                            },
                          ),
                        ],
                      ),
                    ),
                  ],
                ),
              ),
            ),

            // Submit Button
            Padding(
              padding: UIUtils.pagePadding(screenType),
              child: BlocBuilder<WalletBloc, WalletState>(
                builder: (context, state) {
                  final isLoading =
                      state.withdrawStatus == WithdrawStatus.submitting;
                  final availableAmount = _getAvailableAmount(state);
                  final enteredAmount = double.tryParse(_amount) ?? 0.0;
                  final isExceeding = enteredAmount > availableAmount;
                  final isButtonDisabled = isLoading || isExceeding;
                  final isDark = theme.brightness == Brightness.dark;

                  return SizedBox(
                    width: double.infinity,
                    child: ElevatedButton(
                      onPressed: isButtonDisabled ? null : _onSubmit,
                      style: ElevatedButton.styleFrom(
                        backgroundColor: Colors.black,
                        foregroundColor: Colors.white,
                        disabledBackgroundColor: isDark
                            ? Colors.white10
                            : Colors.grey.shade300,
                        disabledForegroundColor: isDark
                            ? Colors.white38
                            : Colors.grey.shade600,
                        padding: EdgeInsets.symmetric(
                          vertical: UIUtils.gapMD(screenType),
                        ),
                        shape: RoundedRectangleBorder(
                          borderRadius: BorderRadius.circular(
                            UIUtils.radiusMD(screenType),
                          ),
                        ),
                      ),
                      child: isLoading
                          ? const CustomLoadingIndicator(
                              size: 20,
                              strokeWidth: 2,
                              color: Colors.white,
                            )
                          : Text(
                              context.tr('withdrawRequest', fallback: 'Withdraw Request'),
                              style: TextStyle(
                                fontSize: UIUtils.tileTitle(screenType),
                                fontWeight: FontWeight.w600,
                              ),
                            ),
                    ),
                  );
                },
              ),
            ),

            // Custom Numeric Keyboard
            Container(
              padding: UIUtils.pagePadding(screenType),
              decoration: BoxDecoration(
                color: theme.cardColor,
                border: Border(
                  top: BorderSide(
                    color: theme.dividerColor.withValues(alpha: 0.2),
                  ),
                ),
              ),
              child: Column(
                children: [
                  // Row 1: 1, 2, 3
                  _buildKeyboardRow(screenType, ['1', '2', '3']),
                  SizedBox(height: UIUtils.gapSM(screenType)),

                  // Row 2: 4, 5, 6
                  _buildKeyboardRow(screenType, ['4', '5', '6']),
                  SizedBox(height: UIUtils.gapSM(screenType)),

                  // Row 3: 7, 8, 9
                  _buildKeyboardRow(screenType, ['7', '8', '9']),
                  SizedBox(height: UIUtils.gapSM(screenType)),

                  // Row 4: Clear, 0, Backspace
                  Row(
                    children: [
                      Expanded(
                        child: _buildKeyButton(
                          screenType,
                          label: '.',
                          onTap: () => _onNumberTap('.'),
                        ),
                      ),
                      SizedBox(width: UIUtils.gapSM(screenType)),
                      Expanded(
                        child: _buildKeyButton(
                          screenType,
                          label: '0',
                          onTap: () => _onNumberTap('0'),
                        ),
                      ),
                      SizedBox(width: UIUtils.gapSM(screenType)),
                      Expanded(
                        child: _buildKeyButton(
                          screenType,
                          icon: Icons.backspace_outlined,
                          onTap: _onBackspace,
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

  Widget _buildKeyboardRow(ScreenType screenType, List<String> numbers) {
    return Row(
      children: numbers.map((number) {
        return Expanded(
          child: Padding(
            padding: EdgeInsets.only(
              right: number != numbers.last ? UIUtils.gapSM(screenType) : 0,
            ),
            child: _buildKeyButton(
              screenType,
              label: number,
              onTap: () => _onNumberTap(number),
            ),
          ),
        );
      }).toList(),
    );
  }

  Widget _buildKeyButton(
    ScreenType screenType, {
    String? label,
    IconData? icon,
    required VoidCallback onTap,
  }) {
    final theme = Theme.of(context);
    final isDark = theme.brightness == Brightness.dark;

    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(UIUtils.radiusMD(screenType)),
      child: Container(
        height: 60,
        decoration: BoxDecoration(
          color: isDark ? const Color(0xFF1C1C1E) : Colors.grey.shade100,
          borderRadius: BorderRadius.circular(UIUtils.radiusMD(screenType)),
          border: Border.all(color: theme.dividerColor.withValues(alpha: 0.1)),
        ),
        child: Center(
          child: label != null
              ? Text(
                  label,
                  style: TextStyle(
                    fontSize: UIUtils.pageTitle(screenType),
                    fontWeight: FontWeight.w600,
                  ),
                )
              : Icon(icon, size: UIUtils.appBarIcon(screenType)),
        ),
      ),
    );
  }
}
