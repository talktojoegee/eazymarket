import 'package:hyper_commerce_seller/service/json_parser.dart';
import 'package:hyper_commerce_seller/screen/more_page/view/markets/model/market_model.dart';

const String walletModelName = 'wallet_model';

class WalletModel {
  bool? success;
  String? message;
  WalletData? data;

  WalletModel({this.success, this.message, this.data});

  factory WalletModel.fromJson(Map<String, dynamic> json) {
    return WalletModel(
      success: JsonParser.boolValue(json['success'] ?? false),
      message: JsonParser.string(json['message'] ?? ''),
      data: json['data'] != null
          ? WalletData.fromJson(json['data'] as Map<String, dynamic>)
          : null,
    );
  }

  Map<String, dynamic> toJson() {
    final Map<String, dynamic> data = <String, dynamic>{};
    data['success'] = success;
    data['message'] = message;
    if (this.data != null) {
      data['data'] = this.data!.toJson();
    }
    return data;
  }
}

class WalletData {
  int? id;
  int? userId;
  String? balance;
  String? blockedBalance;
  String? formattedBalance;
  String? availableBalance;
  String? availableFormattedBalance;
  String? currencyCode;
  String? createdAt;
  String? updatedAt;
  CurrencyPayload? currency;

  WalletData({
    this.id,
    this.userId,
    this.balance,
    this.blockedBalance,
    this.formattedBalance,
    this.availableBalance,
    this.availableFormattedBalance,
    this.currencyCode,
    this.createdAt,
    this.updatedAt,
    this.currency,
  });

  factory WalletData.fromJson(Map<String, dynamic> json) {
    return WalletData(
      id: JsonParser.intValue(json['id']),
      userId: JsonParser.intValue(json['user_id']),
      balance: JsonParser.string(json['balance']),
      blockedBalance: JsonParser.string(json['blocked_balance']),
      formattedBalance: json['formatted_balance'] != null ? JsonParser.string(json['formatted_balance']) : '',
      availableBalance: json['available_balance'] != null ? JsonParser.string(json['available_balance']) : '',
      availableFormattedBalance: json['available_formatted_balance'] != null ? JsonParser.string(json['available_formatted_balance']) : '',
      currencyCode: JsonParser.string(json['currency_code']),
      createdAt: JsonParser.string(json['created_at'] ?? ''),
      updatedAt: JsonParser.string(json['updated_at'] ?? ''),
      currency: json['currency'] is Map<String, dynamic>
          ? CurrencyPayload.fromJson(json['currency'] as Map<String, dynamic>)
          : null,
    );
  }

  Map<String, dynamic> toJson() {
    final Map<String, dynamic> data = <String, dynamic>{};
    data['id'] = id;
    data['user_id'] = userId;
    data['balance'] = balance;
    data['blocked_balance'] = blockedBalance;
    data['formatted_balance'] = formattedBalance;
    data['available_balance'] = availableBalance;
    data['available_formatted_balance'] = availableFormattedBalance;
    data['currency_code'] = currencyCode;
    data['created_at'] = createdAt;
    data['updated_at'] = updatedAt;
    return data;
  }
}
