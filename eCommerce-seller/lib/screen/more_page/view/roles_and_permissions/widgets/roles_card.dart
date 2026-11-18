import 'package:flutter/material.dart';
import 'package:hyper_commerce_seller/extensions/translation_extension.dart';
import 'package:go_router/go_router.dart';
import 'package:hyper_commerce_seller/config/colors.dart';
import 'package:hyper_commerce_seller/utils/time_utils.dart';
import 'package:hyper_commerce_seller/utils/ui_utils.dart';

class RolesCard extends StatelessWidget {
  final Map<String, dynamic> data;
  final ScreenType screenType;

  const RolesCard({super.key, required this.data, required this.screenType});

  @override
  Widget build(BuildContext context) {

    return Column(
      mainAxisAlignment: MainAxisAlignment.start,
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          data['name'] ?? 'Unnamed Role',
          style: TextStyle(
            fontSize: UIUtils.tileTitle(screenType),
            fontWeight: FontWeight.bold,
          ),
          maxLines: 2,
          overflow: TextOverflow.ellipsis,
        ),

        SizedBox(height: UIUtils.gapMD(screenType)),

        RichText(
          text: TextSpan(
            text:  context.tr('guardName', fallback: 'Guard Name: '),
            style: TextStyle(
              fontSize: UIUtils.caption(screenType),
              fontWeight: FontWeight.bold,
              color: Theme.of(context).colorScheme.onSurface,
            ),
            children: [
              TextSpan(
                text: data['guard_name'] ?? 'Unnamed Guard',
                style: TextStyle(
                  fontSize: UIUtils.caption(screenType),
                  fontWeight: FontWeight.normal,
                ),
              ),
            ],
          ),
        ),

        SizedBox(height: UIUtils.gapMD(screenType)),

        Row(
          children: [
            Expanded(
              child: InkWell(
                onTap: () {
                  context.pushNamed(
                    '/permissions',
                    queryParameters: {
                      'role': (data['name'] ?? '').toString(),
                      'roleId': (data['id'] ?? '').toString(),
                    },
                  );
                },
                child: Row(
                  children: [
                    Icon(
                      Icons.login_outlined,
                      color: AppColors.primaryColor,
                      size: 20,
                    ),
                    const SizedBox(width: 8),
                    Text(
                      context.tr('permission', fallback: 'Permission'),
                      style: TextStyle(
                        fontSize: UIUtils.caption(screenType),
                        fontWeight: FontWeight.bold,
                        color: AppColors.primaryColor,
                      ),
                    ),
                  ],
                ),
              ),
            ),

            Text(
              TimeUtils.formatTimeAgo(data['created_at'] ?? '', context),
              style: TextStyle(
                fontSize: UIUtils.caption(screenType),
                fontWeight: FontWeight.normal,
              ),
            ),
          ],
        ),
      ],
    );
  }
}
