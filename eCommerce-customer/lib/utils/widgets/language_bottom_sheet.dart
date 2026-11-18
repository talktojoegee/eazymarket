import 'package:flutter/material.dart';
import 'package:hyper_commerce/utils/extensions/translation_extension.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:flutter_screenutil/flutter_screenutil.dart';
import 'package:hyper_commerce/config/app_text_styles.dart';
import 'package:hyper_commerce/config/theme.dart';
import '../../bloc/language_bloc/language_bloc/language_bloc.dart';
import '../../bloc/language_bloc/language_list_bloc/language_list_bloc.dart';

import '../../config/global.dart';
class LanguageBottomSheet extends StatefulWidget {
  const LanguageBottomSheet({super.key});

  @override
  State<LanguageBottomSheet> createState() => _LanguageBottomSheetState();

  // Static show method - clean and consistent with your sort sheet
  static void show(BuildContext context) {
    showModalBottomSheet(
      context: context,
      backgroundColor: Colors.transparent,
      isScrollControlled: true,
      useRootNavigator: true,
      builder: (context) => Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          // Floating close button (same as sort sheet)
          Padding(
            padding: const EdgeInsets.symmetric(vertical: 30),
            child: Center(
              child: GestureDetector(
                onTap: () => Navigator.of(context).pop(),
                child: Container(
                  padding: const EdgeInsets.all(10),
                  decoration: BoxDecoration(
                    color: Theme.of(context).colorScheme.surface,
                    shape: BoxShape.circle,
                    boxShadow: [
                      BoxShadow(
                        color: Colors.black.withValues(alpha: 0.1),
                        blurRadius: 8,
                        offset: const Offset(0, 2),
                      ),
                    ],
                  ),
                  child: const Icon(Icons.close, size: 20,),
                ),
              ),
            ),
          ),
          const LanguageBottomSheet(),
        ],
      ),
    );
  }
}

class _LanguageBottomSheetState extends State<LanguageBottomSheet> {
  @override
  Widget build(BuildContext context) {
    return Container(
      decoration: BoxDecoration(
        color: Theme.of(context).colorScheme.surface,
        borderRadius: BorderRadius.only(
          topLeft: Radius.circular(14.r),
          topRight: Radius.circular(14.r),
        ),
      ),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [

          // Header
          Padding(
            padding: EdgeInsets.symmetric(horizontal: 16.w, vertical: 14.h),
            child: Row(
              children: [
                Text(
                  context.tr('selectLanguage', fallback: 'Select Language'),
                  style: AppTextStyles.titleMedium,
                ),
              ],
            ),
          ),

          Divider(height: 1.h, color: Theme.of(context).colorScheme.outlineVariant),

          // Language List
          Flexible(
            child: BlocBuilder<LanguageBloc, LanguageState>(
              builder: (context, state) {
                final currentCode = state is LanguageLoaded ? state.currentLanguage : 'en';
                final dynamicLanguages = state is LanguageLoaded ? (state.languageModel.data?.languages ?? []) : [];
                final hasDynamic = dynamicLanguages.isNotEmpty;
                final itemCount = hasDynamic ? dynamicLanguages.length : Global.supportedLanguages.length;

                return RadioGroup<String>(
                  groupValue: currentCode,
                  onChanged: (value) {
                    if (value != null) {
                      context.read<LanguageBloc>().add(ChangeLanguage(value));
                      context.read<LanguageListBloc>().add(FetchLanguageList(locale: value));
                      Navigator.pop(context);
                    }
                  },
                  child: ListView.separated(
                    shrinkWrap: true,
                    padding: EdgeInsets.symmetric(vertical: 8.h),
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
                        final language = Global.supportedLanguages[index];
                        code = language['code']!;
                        name = language['name']!;
                        nativeName = language['nativeName']!;
                      }

                      final isSelected = currentCode == code;

                      return RadioListTile<String>(
                        title: Text(
                          nativeName,
                          style: AppTextStyles.body.copyWith(
                            fontWeight: isSelected ? FontWeight.w600 : FontWeight.w400,
                            color: isSelected ? AppTheme.primaryColor : Colors.grey[800],
                          ),
                        ),
                        subtitle: Text(
                          name,
                          style: AppTextStyles.label.copyWith(
                            color: Colors.grey[600],
                          ),
                        ),
                        value: code,
                        fillColor: WidgetStateProperty.resolveWith((states) {
                          if (states.contains(WidgetState.selected)) {
                            return AppTheme.primaryColor;
                          }
                          return AppTheme.primaryColor;
                        }),
                        activeColor: AppTheme.primaryColor,
                        contentPadding: EdgeInsets.symmetric(horizontal: 8.w),
                        dense: true,
                        visualDensity: VisualDensity.compact,
                      );
                    },
                  ),
                );
              },
            ),

          ),

          // Bottom safe area
          SizedBox(height: MediaQuery.of(context).padding.bottom + 10.h),
        ],
      ),
    );
  }
}