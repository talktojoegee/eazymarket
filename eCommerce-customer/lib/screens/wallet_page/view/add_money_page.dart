import 'package:flutter/material.dart';
import 'package:flutter_html/flutter_html.dart';
import 'package:hyper_commerce/utils/extensions/translation_extension.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:flutter_screenutil/flutter_screenutil.dart';
import 'package:go_router/go_router.dart';
import 'package:hyper_commerce/config/helper.dart';
import 'package:hyper_commerce/config/theme.dart';
import 'package:hyper_commerce/router/app_routes.dart';
import 'package:hyper_commerce/screens/payment_options/bloc/payment_event.dart';
import 'package:hyper_commerce/screens/wallet_page/bloc/user_wallet/user_wallet_bloc.dart';
import 'package:hyper_commerce/utils/widgets/custom_button.dart';
import 'package:hyper_commerce/utils/widgets/custom_scaffold/custom_scaffold.dart';
import 'package:hyper_commerce/utils/widgets/custom_textfield.dart';
import 'package:hyper_commerce/utils/widgets/custom_toast.dart';
import 'package:hyper_commerce/utils/widgets/whole_page_progress.dart';
import '../../../config/global.dart';
import '../../../config/payment_config.dart';
import '../../../config/settings_data_instance.dart';
import '../../payment_options/bloc/payment_bloc.dart';
import '../../payment_options/bloc/payment_state.dart';
import '../../payment_options/widgets/webview_payment.dart';

class AddMoneyPage extends StatefulWidget {
  const AddMoneyPage({super.key});

  @override
  State<AddMoneyPage> createState() => _AddMoneyPageState();
}

class _AddMoneyPageState extends State<AddMoneyPage> {
  final TextEditingController _amountController = TextEditingController();
  String? _selectedAmount;
  String? selectedPaymentMethod;
  PaymentMethodType? selectedPaymentMethodType;

  late List<String> _suggestedAmounts = [];

  @override
  void initState() {
    super.initState();
    if(SettingsData.instance.system!.walletRechargePresetAmounts!.isNotEmpty) {
      _suggestedAmounts = SettingsData.instance.system!.walletRechargePresetAmounts ?? AppHelpers.defaultWalletRechargeAmounts;
    }
    _amountController.text = _suggestedAmounts.first.toString();
    _selectedAmount = _suggestedAmounts.first.toString();
  }

  @override
  void dispose() {
    _amountController.dispose();
    super.dispose();
  }

  void _onAmountSelected(String amount) {
    setState(() {
      _selectedAmount = amount;
      _amountController.text = amount;
    });
  }

  @override
  Widget build(BuildContext context) {
    return BlocConsumer<PaymentBloc, PaymentState>(
      listener: (context, state) {
        if(state is PaymentSuccess) {
          if(selectedPaymentMethod == 'flutterwave' ||
              selectedPaymentMethod == 'mercadopago' ||
              selectedPaymentMethod == 'mercado_pago' ||
              selectedPaymentMethod == 'xendit') {
            Navigator.of(context).push(
                MaterialPageRoute(builder: (ctx) => WebViewPaymentPage(
                  paymentUrl: state.orderId,
                  onPaymentSuccess: () {},
                  onPaymentFailure: () {
                    Navigator.of(ctx).pop();
                    ctx.read<UserWalletBloc>().add(FetchUserWallet());
                  },
                ))
            );
          } else {
            Navigator.of(context).pop();
            context.read<UserWalletBloc>().add(FetchUserWallet());
          }
        }
        else if (state is PaymentFailure) {
          ToastManager.show(
            context: context,
            message: context.tr('paymentFailed')
          );
        }
      },
      builder: (context, state) {
        return Stack(
          children: [
            CustomScaffold(
              showViewCart: false,
              appBar: AppBar(
                title: Text(context.tr('addMoney')),
              ),
              body: Container(
                color: Theme.of(context).colorScheme.surface,
                child: Column(
                  children: [
                    Container(
                      height: isTablet(context) ? 300 : 250,
                      color: Theme.of(context).colorScheme.surface,
                      child: SingleChildScrollView(
                        padding: EdgeInsets.symmetric(horizontal: 16.w, vertical: 10.h),
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            // Enter Amount label
                            Text(
                              context.tr('enterAmount'),
                              style: TextStyle(
                                fontSize: isTablet(context) ? 24 : 16.sp,
                                fontWeight: FontWeight.w500,
                                color: Theme.of(context).colorScheme.onSurface,
                                fontFamily: AppTheme.fontFamily,
                              ),
                            ),
                            SizedBox(height: 12.h),
                            // Amount input field
                            CustomTextFormField(
                              controller: _amountController,
                              keyboardType: const TextInputType.numberWithOptions(decimal: true),
                              onChanged: (value){
                                setState(() {
                                  _selectedAmount = value;
                                });
                              },
                            ),
                            SizedBox(height: 20.h),
                            // Suggested amount buttons
                            Row(
                              mainAxisAlignment: MainAxisAlignment.spaceBetween,
                              children: _suggestedAmounts.map((amount) {
                                final isSelected = _selectedAmount == amount;
                                return Expanded(
                                  child: Padding(
                                    padding: EdgeInsets.only(
                                      right: amount != _suggestedAmounts.last ? 12.w : 0,
                                    ),
                                    child: ElevatedButton(
                                      onPressed: () => _onAmountSelected(amount),
                                      style: ElevatedButton.styleFrom(
                                        backgroundColor: isSelected
                                            ? const Color(0xFFE3F2FD)
                                            : Colors.grey.shade100,
                                        foregroundColor: isSelected
                                            ? AppTheme.primaryColor
                                            : Colors.black87,
                                        side: BorderSide(
                                          color: isSelected
                                              ? AppTheme.primaryColor
                                              : Colors.grey.shade300,
                                          width: 1.5,
                                        ),
                                        padding: EdgeInsets.symmetric(vertical: 8.h),
                                        shape: RoundedRectangleBorder(
                                          borderRadius: BorderRadius.circular(12.r),
                                        ),
                                        elevation: 0,
                                      ),
                                      child: Text(
                                        amount,
                                        style: TextStyle(
                                          fontSize: isTablet(context) ? 18 : 14.sp,
                                          fontWeight: FontWeight.w500,
                                          fontFamily: AppTheme.fontFamily,
                                        ),
                                      ),
                                    ),
                                  ),
                                );
                              }).toList(),
                            ),
                          ],
                        ),
                      ),
                    ),
                    if(SettingsData.instance.system!.walletPolicy!.isNotEmpty)
                      Expanded(
                      child: Container(
                        color: Theme.of(context).colorScheme.surfaceContainer,
                        padding: const EdgeInsets.all(16),
                        /*child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text(
                              context.tr('note'),
                              style: TextStyle(
                                fontSize: isTablet(context) ? 18 : 14.sp,
                                fontWeight: FontWeight.w600,
                                fontFamily: AppTheme.fontFamily,
                              ),
                            ),
                            const SizedBox(height: 10,),
                            _buildBulletPoint(
                              context.tr('walletBalanceValidFor1Year'),
                            ),
                            SizedBox(height: 8.h),
                            _buildBulletPoint(
                              context.tr('walletBalanceCannotBeTransferred'),
                            ),
                          ],
                        ),*/
                        child: Html(
                          data: SettingsData.instance.system!.walletPolicy,
                        ),
                      )
                    ),
                  ],
                ),
              ),
              bottomNavigationBar: Container(
                color: Theme.of(context).colorScheme.surfaceContainer,
                padding: EdgeInsets.symmetric(horizontal: 20.h, vertical: 15.h),
                child: SizedBox(
                  height: 60,
                  width: double.infinity,
                  child: CustomButton(
                    onPressed: () async {
                      // Validate amount
                      if (_selectedAmount == null || parseInt(_selectedAmount!) < 1) {
                        ToastManager.show(
                          context: context,
                          message: context.tr('pleaseEnterAnAmountGreaterThanOrEqualTo1'),
                          type: ToastType.error,
                        );
                        return;
                      }

                      final paymentMethodType = await context.push(
                        AppRoutes.paymentOptions,
                        extra: {
                          'totalAmount': _selectedAmount,
                          'isFromAddMoney': true
                        },
                      );

                      if (paymentMethodType != null && paymentMethodType is PaymentMethodType) {
                        final paymentMethod = PaymentConfig.getPaymentMethodByType(paymentMethodType);
                        if (paymentMethod != null) {
                          setState(() {
                            selectedPaymentMethod = paymentMethod.id;
                            selectedPaymentMethodType = paymentMethod.type;
                          });
                        }
                      }
                      if(context.mounted){
                        context.read<PaymentBloc>().add(InitiatePaymentEvent(
                            amount: parseDouble(_selectedAmount!),
                            paymentMethodType: selectedPaymentMethodType!,
                            additionalData: {
                              'customerName': Global.userData?.name.toString() ?? '',
                              'email': Global.userData?.email.toString() ?? '',
                              'phone': Global.userData?.mobile.toString() ?? '',
                            },
                            description: '',
                            addMoneyToWallet: true,
                            context: context
                        ));
                      }
                    },
                    child: Row(
                      mainAxisAlignment: MainAxisAlignment.center,
                      children: [
                        Text(
                          context.tr('addMoney'),
                          style: TextStyle(
                            fontSize: isTablet(context) ? 24 : 16.sp,
                            fontWeight: FontWeight.w600,
                            fontFamily: AppTheme.fontFamily,
                          ),
                        ),
                        SizedBox(width: 8.w),
                        Icon(
                          Icons.arrow_forward,
                          size: isTablet(context) ? 12.sp : 20.sp,
                        ),
                      ],
                    ),
                  ),
                ),
              ),
            ),

            if(state is PaymentLoading)
              const WholePageProgress()
          ],
        );
      },
    );
  }

}
