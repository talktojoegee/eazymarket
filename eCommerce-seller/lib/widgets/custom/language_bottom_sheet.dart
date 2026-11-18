import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:hyper_commerce_seller/bloc/language/language_bloc/language_bloc.dart';
import 'package:hyper_commerce_seller/bloc/language/language_list_bloc/language_list_bloc.dart';
import 'package:hyper_commerce_seller/config/colors.dart';
import 'package:hyper_commerce_seller/config/hive_storage.dart';
import 'package:hyper_commerce_seller/extensions/translation_extension.dart';
import 'package:hyper_commerce_seller/utils/ui_utils.dart';

class LanguageBottomSheet extends StatelessWidget {
  const LanguageBottomSheet({super.key});

  static void show(BuildContext context) {
    showModalBottomSheet(
      context: context,
      isDismissible: true,
      useSafeArea: true,
      isScrollControlled: true,
      useRootNavigator: true,
      backgroundColor: Colors.transparent,
      builder: (context) => const LanguageBottomSheet(),
    );
  }

  static const List<Map<String, String>> _fallbackLanguages = [
    {'code': 'en', 'name': 'English', 'nativeName': 'English'},
    {'code': 'hi', 'name': 'Hindi', 'nativeName': 'हिंदी'},
    {'code': 'ar', 'name': 'Arabic', 'nativeName': 'العربية'},
  ];

  @override
  Widget build(BuildContext context) {
    final screenType = context.screenTypeRead;

    return Container(
      decoration: BoxDecoration(
        color: Theme.of(context).cardColor,
        borderRadius: BorderRadius.vertical(
          top: Radius.circular(UIUtils.radiusLG(screenType)),
        ),
      ),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          const SizedBox(height: 12),
          Container(
            width: 40,
            height: 4,
            decoration: BoxDecoration(
              color: Colors.grey.shade300,
              borderRadius: BorderRadius.circular(10),
            ),
          ),
          Padding(
            padding: const EdgeInsets.all(16.0),
            child: Row(
              children: [
                Expanded(
                  child: Text(
                    context.tr('selectLanguage', fallback: 'Select Language'),
                    style: TextStyle(
                      fontSize: UIUtils.pageTitle(screenType),
                      fontWeight: FontWeight.bold,
                    ),
                  ),
                ),
                GestureDetector(
                  onTap: () => Navigator.of(context).pop(),
                  child: Container(
                    padding: const EdgeInsets.all(4),
                    decoration: BoxDecoration(
                      color: Theme.of(context).colorScheme.surface,
                      shape: BoxShape.circle,
                    ),
                    child: const Icon(Icons.close, size: 20),
                  ),
                ),
              ],
            ),
          ),
          const Divider(height: 1),
          Flexible(
            child: BlocBuilder<LanguageBloc, LanguageState>(
              builder: (context, state) {
                final currentCode = state is LanguageLoaded
                    ? state.currentLanguage
                    : HiveStorage.languageCode;
                final dynamicLanguages = state is LanguageLoaded
                    ? (state.languageModel.data?.languages ?? [])
                    : [];
                final hasDynamic = dynamicLanguages.isNotEmpty;
                final itemCount =
                    hasDynamic ? dynamicLanguages.length : _fallbackLanguages.length;

                return ListView.separated(
                  shrinkWrap: true,
                  padding: EdgeInsets.symmetric(vertical: UIUtils.gapMD(screenType)),
                  itemCount: itemCount,
                  separatorBuilder: (context, index) => const SizedBox.shrink(),
                  itemBuilder: (context, index) {
                    final String code;
                    final String name;
                    final String nativeName;

                    if (hasDynamic) {
                      final lang = dynamicLanguages[index];
                      code = lang.code ?? '';
                      name = lang.name ?? '';
                      nativeName = (lang.nativeName != null && lang.nativeName!.isNotEmpty)
                          ? lang.nativeName!
                          : name;
                    } else {
                      final lang = _fallbackLanguages[index];
                      code = lang['code']!;
                      name = lang['name']!;
                      nativeName = lang['nativeName']!;
                    }

                    final isSelected = currentCode == code;

                    return Padding(
                      padding: EdgeInsets.symmetric(
                        horizontal: UIUtils.gapMD(screenType),
                        vertical: 2,
                      ),
                      child: InkWell(
                        onTap: () {
                          context.read<LanguageBloc>().add(ChangeLanguage(code));
                          context.read<LanguageListBloc>().add(FetchLanguageList(locale: code));
                          Navigator.pop(context);
                        },
                        borderRadius: BorderRadius.circular(UIUtils.radiusMD(screenType)),
                        child: Container(
                          padding: UIUtils.tilePadding(screenType),
                          decoration: BoxDecoration(
                            color: isSelected
                                ? AppColors.primaryColor.withValues(alpha: 0.08)
                                : Colors.transparent,
                            borderRadius:
                                BorderRadius.circular(UIUtils.radiusMD(screenType)),
                            border: Border.all(
                              color: isSelected
                                  ? AppColors.primaryColor.withValues(alpha: 0.3)
                                  : Colors.transparent,
                            ),
                          ),
                          child: Row(
                            children: [
                              Container(
                                width: 36,
                                height: 36,
                                decoration: BoxDecoration(
                                  color: isSelected
                                      ? AppColors.primaryColor.withValues(alpha: 0.15)
                                      : Colors.grey.shade100,
                                  borderRadius: BorderRadius.circular(8),
                                ),
                                alignment: Alignment.center,
                                child: Text(
                                  code.toUpperCase(),
                                  style: TextStyle(
                                    color: isSelected
                                        ? AppColors.primaryColor
                                        : Colors.grey.shade700,
                                    fontWeight: FontWeight.bold,
                                    fontSize: 12,
                                  ),
                                ),
                              ),
                              SizedBox(width: UIUtils.gapMD(screenType)),
                              Expanded(
                                child: Column(
                                  crossAxisAlignment: CrossAxisAlignment.start,
                                  children: [
                                    Text(
                                      nativeName,
                                      style: TextStyle(
                                        fontWeight:
                                            isSelected ? FontWeight.w600 : FontWeight.w400,
                                        fontSize: 15,
                                      ),
                                    ),
                                    if (name != nativeName)
                                      Text(
                                        name,
                                        style: TextStyle(
                                          color: Colors.grey.shade600,
                                          fontSize: 12,
                                        ),
                                      ),
                                  ],
                                ),
                              ),
                              if (isSelected)
                                const Icon(
                                  Icons.check_circle,
                                  color: AppColors.primaryColor,
                                  size: 20,
                                ),
                            ],
                          ),
                        ),
                      ),
                    );
                  },
                );
              },
            ),
          ),
          SizedBox(height: MediaQuery.of(context).padding.bottom + 12),
        ],
      ),
    );
  }
}
