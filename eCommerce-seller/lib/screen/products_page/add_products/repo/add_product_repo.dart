import 'dart:convert';
import 'dart:developer';
import 'package:dio/dio.dart';
import 'package:hyper_commerce_seller/config/api_routes.dart';
import 'package:hyper_commerce_seller/screen/products_page/add_products/bloc/add_product_bloc/add_product_bloc.dart';
import 'package:hyper_commerce_seller/service/api_base_helper.dart';

class AddProductRepo {
  final ApiBaseHelper _helper = ApiBaseHelper();

  Future<dynamic> addProduct({required ProductData productData}) async {
    try {
      final Map<String, dynamic> fields = {
        "title": productData.title,
        "type": productData.type.toLowerCase(),
        "indicator": productData.indicator,
        "made_in": productData.madeIn,
        "hsn_code": productData.hsnCode,
        "minimum_order_quantity": productData.minimumOrderQuantity,
        "quantity_step_size": productData.quantityStepSize,
        "total_allowed_quantity": productData.totalAllowedQuantity,
        "is_returnable": productData.isReturnable ? "1" : "0",
        "returnable_days": productData.returnableDays,
        "is_cancelable": productData.isCancelable ? "1" : "0",
        "allow_cod": productData.isCodAllowed ? "1" : "0",
        "is_attachment_required": productData.isAttachmentRequired ? "1" : "0",
        "attachment_mode": productData.attachmentMode,
        "featured": productData.featured ? "1" : "0",
        "requires_otp": productData.requiresOtp ? "1" : "0",
        "image_fit": productData.imageFit.toLowerCase(),
        "short_description": productData.shortDescription,
        "description": productData.description,
        "video_type": productData.videoType,
        "video_link": productData.videoLink,
        "warranty_period": productData.warrantyPeriod,
        "guarantee_period": productData.guaranteePeriod,
        "pricing": "{}",
      };

      if (productData.isCancelable &&
          productData.cancelableTill != null &&
          productData.cancelableTill!.isNotEmpty) {
        fields["cancelable_till"] = productData.cancelableTill;
      }

      if (productData.categoryId != null) {
        fields["category_id"] = productData.categoryId.toString();
      }

      if (productData.brandId != null) {
        final parsedId = int.tryParse(productData.brandId.toString());
        if (parsedId != null && parsedId > 0) {
          fields["brand_id"] = parsedId.toString();
        }
      }

      for (int i = 0; i < productData.taxGroupIds.length; i++) {
        fields["tax_groups[$i]"] = productData.taxGroupIds[i].toString();
      }

      fields["is_inclusive_tax"] = productData.isInclusiveTax ? "1" : "0";

      for (int i = 0; i < productData.tags.length; i++) {
        fields["tags[$i]"] = productData.tags[i];
      }

      for (int i = 0; i < productData.customFields.length; i++) {
        fields["custom_fields[$i]"] = jsonEncode({
          "key": productData.customFields[i]['key'],
          "value": productData.customFields[i]['value'],
        });
      }

      // Media files
      if (productData.mainImage != null && productData.mainImage!.isNotEmpty) {
        fields["main_image"] = await MultipartFile.fromFile(
          productData.mainImage!,
        );
      }

      if (productData.productVideo != null && productData.productVideo!.isNotEmpty) {
        fields["product_video"] = await MultipartFile.fromFile(
          productData.productVideo!,
        );
      }

      if (productData.otherImages.isEmpty && productData.id != null) {
        fields["additional_images"] = "";
      } else {
        for (int i = 0; i < productData.otherImages.length; i++) {
          if (productData.otherImages[i].startsWith('http')) {
            fields["additional_images[$i]"] = productData.otherImages[i];
          } else if (productData.otherImages[i].isNotEmpty) {
            fields["additional_images[$i]"] = await MultipartFile.fromFile(
              productData.otherImages[i],
            );
          }
        }
      }

      // TYPE-SPECIFIC PAYLOADS
      if (productData.type == 'simple') {
        fields["barcode"] = productData.barcode ?? "";
        fields["price"] = productData.globalPrice ?? "0";
        fields["special_price"] = productData.globalSpecialPrice;
        fields["cost"] = productData.globalCost;
        fields["sku"] = productData.globalSku ?? "";

        final firstVariant =
            productData.variants.isNotEmpty ? productData.variants.first : null;
        fields["weight"] = firstVariant?.weight ?? "";
        fields["height"] = firstVariant?.height ?? "";
        fields["length"] = firstVariant?.length ?? "";
        fields["breadth"] = firstVariant?.breadth ?? "";

        // Top-level inventory array for simple products
        for (int i = 0; i < productData.locationInventory.length; i++) {
          final inv = productData.locationInventory[i];
          fields["inventory[$i][store_location_id]"] =
              inv.locationId.toString();
          fields["inventory[$i][sell_from]"] =
              inv.sellFromThisLocation ? "1" : "0";
          fields["inventory[$i][tracked]"] = inv.trackInventory ? "1" : "0";
          fields["inventory[$i][qty]"] = inv.qty;
        }

        fields["variants_json"] = "[]";
      } else if (productData.type == 'variant') {
        fields["barcode"] = null;
        fields["weight"] = null;
        fields["height"] = null;
        fields["length"] = null;
        fields["breadth"] = null;

        final List<Map<String, dynamic>> variantsJsonList = [];
        for (int i = 0; i < productData.variants.length; i++) {
          final variant = productData.variants[i];

          // Location inventory for this variant
          final variantInventory = productData.locationInventory.map((inv) {
            return {
              "store_location_id": inv.locationId,
              "sell_from": inv.sellFromThisLocation,
              "tracked": inv.trackInventory,
              "qty": int.tryParse(inv.qty) ?? 0,
            };
          }).toList();

          final double? priceVal =
              double.tryParse(productData.globalPrice ?? "");
          final double? specialPriceVal =
              productData.globalSpecialPrice != null &&
                      productData.globalSpecialPrice!.isNotEmpty
                  ? double.tryParse(productData.globalSpecialPrice!)
                  : null;
          final double? costVal = productData.globalCost != null &&
                  productData.globalCost!.isNotEmpty
              ? double.tryParse(productData.globalCost!)
              : null;

          variantsJsonList.add({
            "id": variant.id,
            "title": variant.title,
            "weight": double.tryParse(variant.weight ?? "1") ?? 1,
            "breadth": double.tryParse(variant.breadth ?? "1") ?? 1,
            "length": double.tryParse(variant.length ?? "1") ?? 1,
            "height": double.tryParse(variant.height ?? "1") ?? 1,
            "availability": variant.isAvailable,
            "barcode": variant.barcode ?? "",
            "is_default": variant.isDefault ? true : "",
            "attributes": variant.attributes,
            "price": priceVal,
            "special_price": specialPriceVal,
            "cost": costVal,
            "sku": productData.globalSku ?? "",
            "inventory": variantInventory,
          });

          if (variant.image != null && variant.image!.isNotEmpty) {
            if (variant.image!.startsWith('http')) {
              fields["variant_image${variant.id}"] = variant.image;
            } else {
              fields["variant_image${variant.id}"] =
                  await MultipartFile.fromFile(
                variant.image!,
              );
            }
          }
        }
        fields["variants_json"] = jsonEncode(variantsJsonList);
      } else if (productData.type == 'kit') {
        fields["type"] = "kit";
        if (productData.kitStoreId != null) {
          fields["kit_store_id"] = productData.kitStoreId.toString();
        }
        fields["barcode"] = productData.barcode ?? "";
        fields["price"] = productData.kitPrice ?? productData.globalPrice ?? "0";
        fields["special_price"] = productData.globalSpecialPrice;
        fields["cost"] = productData.globalCost;
        fields["sku"] = productData.globalSku ?? productData.barcode ?? "";
        fields["pricing"] = '{"store_pricing":[]}';

        // Top-level inventory array for kit products
        if (productData.locationInventory.isNotEmpty) {
          for (int i = 0; i < productData.locationInventory.length; i++) {
            final inv = productData.locationInventory[i];
            fields["inventory[$i][store_location_id]"] =
                inv.locationId.toString();
            fields["inventory[$i][sell_from]"] =
                inv.sellFromThisLocation ? "1" : "0";
            fields["inventory[$i][tracked]"] = inv.trackInventory ? "1" : "0";
            fields["inventory[$i][qty]"] = inv.qty;
          }
        }

        // Pass kit_components using indexed format matching backend expectation
        for (int i = 0; i < productData.kitComponents.length; i++) {
          final comp = productData.kitComponents[i];
          fields["kit_components[$i][product_variant_id]"] =
              comp.productVariantId.toString();
          fields["kit_components[$i][quantity]"] =
              comp.quantity.toString();
        }
        fields["variants_json"] = "[]";
      }

      // Handle Custom Product Sections
      for (int i = 0; i < productData.customProductSections.length; i++) {
        final section = productData.customProductSections[i];
        fields["custom_sections[$i][id]"] = section.id;
        fields["custom_sections[$i][title]"] = section.title;
        fields["custom_sections[$i][description]"] = section.description;
        fields["custom_sections[$i][sort_order]"] = section.sortOrder;

        for (int j = 0; j < section.fields.length; j++) {
          final field = section.fields[j];
          fields["custom_sections[$i][fields][$j][id]"] = field.id;
          fields["custom_sections[$i][fields][$j][title]"] = field.title;
          fields["custom_sections[$i][fields][$j][description]"] =
              field.description;
          fields["custom_sections[$i][fields][$j][sort_order]"] =
              field.sortOrder;

          if (field.image != null && field.image!.isNotEmpty) {
            if (field.image!.startsWith('http')) {
               fields["custom_sections[$i][fields][$j][image]"] = field.image;
            } else {
              fields["custom_sections[$i][fields][$j][image]"] =
                  await MultipartFile.fromFile(field.image!);
            }
          } else if (field.id != null) {
            // Signal to clear image for existing field
            fields["custom_sections[$i][fields][$j][remove_image]"] = "1";
          }
        }
      }

      String url = productData.id != null
          ? "${ApiRoutes.productsApi}/${productData.id}"
          : ApiRoutes.productsApi;

      if (productData.id != null) {
        fields["_method"] = "POST";
      }

      log("🚀 [AddProductRepo] Submitting Add Product Request to $url");
      log("📦 [AddProductRepo] Form Data Fields Payload:\n${const JsonEncoder.withIndent('  ').convert(fields.map((k, v) => MapEntry(k, v.toString())))}");

      final formData = FormData.fromMap(fields);
      final response = await _helper.postMultipart(url, formData);

      return response;
    } catch (e) {
      throw Exception(e.toString());
    }
  }

  Future<dynamic> getCurrencyConversion({List<int>? marketIds}) async {
    try {
      final Map<String, dynamic> queryParameters = {};
      if (marketIds != null && marketIds.isNotEmpty) {
        for (int i = 0; i < marketIds.length; i++) {
          queryParameters['market_ids[$i]'] = marketIds[i].toString();
        }
      }
      final response = await _helper.get(
        ApiRoutes.currencyConversionApi,
        queryParameters: queryParameters,
      );
      return response;
    } catch (e) {
      log("Error fetching currency conversion: $e");
      return null;
    }
  }
}
