import 'package:flutter/material.dart';
import 'package:hyper_commerce_seller/config/app_text_styles.dart';
import 'package:hyper_commerce_seller/config/colors.dart';

class ReviewStep extends StatelessWidget {
  final String name;
  final String email;
  final String phone;
  final String address;
  final String city;
  final String state;
  final String zipcode;
  final String country;
  final String bankName;
  final String accountType;
  final String accountHolderName;
  final String accountNumber;
  final List<String> documentLabels;
  final List<String>? dynamicBankSummary;
  final ValueChanged<int> onEdit;

  const ReviewStep({
    super.key,
    required this.name,
    required this.email,
    required this.phone,
    required this.address,
    required this.city,
    required this.state,
    required this.zipcode,
    required this.country,
    required this.bankName,
    required this.accountType,
    required this.accountHolderName,
    required this.accountNumber,
    required this.documentLabels,
    this.dynamicBankSummary,
    required this.onEdit,
  });

  String get _maskedAccount {
    final n = accountNumber.trim();
    if (n.length <= 4) return n;
    return "•••• ${n.substring(n.length - 4)}";
  }

  String get _prettyType {
    if (accountType.isEmpty) return '';
    return accountType[0].toUpperCase() + accountType.substring(1);
  }

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          "Check and submit",
          style: AppTextStyles.headline.copyWith(
            fontWeight: FontWeight.w800,
            letterSpacing: -0.5,
            color: Theme.of(context).colorScheme.tertiary,
          ),
        ),
        const SizedBox(height: 6),
        Text(
          "One request goes to our servers — take one last look.",
          style: AppTextStyles.bodySmall.copyWith(color: Colors.grey.shade600, height: 1.4),
        ),
        const SizedBox(height: 20),
        _card(context, "Account", 1, [
          "$name · $email",
          phone,
        ]),
        const SizedBox(height: 12),
        _card(context, "Address", 2, [
          address,
          [city, state, zipcode, country].where((e) => e.trim().isNotEmpty).join(', '),
        ]),
        const SizedBox(height: 12),
        _docCard(context),
        const SizedBox(height: 12),
        _card(
          context,
          "Payout account",
          4,
          (dynamicBankSummary != null && dynamicBankSummary!.isNotEmpty)
              ? dynamicBankSummary!
              : [
                  [bankName, _prettyType].where((e) => e.trim().isNotEmpty).join(' · '),
                  [accountHolderName, _maskedAccount].where((e) => e.trim().isNotEmpty).join(' · '),
                ],
        ),
      ],
    );
  }

  Widget _card(BuildContext context, String title, int step, List<String> lines) {
    final scheme = Theme.of(context).colorScheme;
    return Container(
      padding: const EdgeInsets.fromLTRB(15, 14, 15, 14),
      decoration: BoxDecoration(
        color: scheme.surface,
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: scheme.outlineVariant, width: 1.2),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              Text(title, style: AppTextStyles.body.copyWith(fontWeight: FontWeight.w800, color: scheme.tertiary)),
              GestureDetector(
                onTap: () => onEdit(step),
                behavior: HitTestBehavior.opaque,
                child: Text("Edit", style: AppTextStyles.label.copyWith(color: AppColors.primaryColor, fontWeight: FontWeight.w700)),
              ),
            ],
          ),
          const SizedBox(height: 8),
          ...lines.where((l) => l.trim().isNotEmpty).map(
                (l) => Padding(
                  padding: const EdgeInsets.only(bottom: 3),
                  child: Text(l, style: AppTextStyles.bodySmall.copyWith(color: Colors.grey.shade600, height: 1.5)),
                ),
              ),
        ],
      ),
    );
  }

  Widget _docCard(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;
    const green = Color(0xFF1D9A63);
    return Container(
      padding: const EdgeInsets.fromLTRB(15, 14, 15, 14),
      decoration: BoxDecoration(
        color: scheme.surface,
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: scheme.outlineVariant, width: 1.2),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              Text("Documents", style: AppTextStyles.body.copyWith(fontWeight: FontWeight.w800, color: scheme.tertiary)),
              GestureDetector(
                onTap: () => onEdit(3),
                behavior: HitTestBehavior.opaque,
                child: Text("Edit", style: AppTextStyles.label.copyWith(color: AppColors.primaryColor, fontWeight: FontWeight.w700)),
              ),
            ],
          ),
          const SizedBox(height: 10),
          if (documentLabels.isEmpty)
            Text("No documents added", style: AppTextStyles.bodySmall.copyWith(color: Colors.grey.shade600))
          else
            Wrap(
              spacing: 6,
              runSpacing: 6,
              children: documentLabels
                  .map((d) => Container(
                        padding: const EdgeInsets.symmetric(horizontal: 9, vertical: 5),
                        decoration: BoxDecoration(
                          color: green.withValues(alpha: 0.12),
                          borderRadius: BorderRadius.circular(8),
                        ),
                        child: Text(d, style: AppTextStyles.label.copyWith(color: green, fontWeight: FontWeight.w600)),
                      ))
                  .toList(),
            ),
        ],
      ),
    );
  }
}
