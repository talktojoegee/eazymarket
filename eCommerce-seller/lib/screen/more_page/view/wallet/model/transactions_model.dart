import 'package:hyper_commerce_seller/service/json_parser.dart';

class TransactionHistoryModel {
  final bool success;
  final String message;
  final int currentPage;
  final int lastPage;
  final int perPage;
  final int total;
  final List<TransactionData> data;

  TransactionHistoryModel({
    required this.success,
    required this.message,
    required this.currentPage,
    required this.lastPage,
    required this.perPage,
    required this.total,
    required this.data,
  });

  factory TransactionHistoryModel.fromJson(Map<String, dynamic> json) {
    // ignore: avoid_print
    print('RAW TRANSACTIONS JSON: $json');
    bool success = JsonParser.boolValue(
      json['success'] ?? true,
    ); // Default to true if not present
    String message = JsonParser.string(json['message'] ?? '');

    // The data might be directly in 'data' field or nested
    dynamic jsonData = json['data'];
    List<TransactionData> items = [];

    // Default pagination values from root if available
    int currentPage = JsonParser.intValue(json['current_page'] ?? 1);
    int lastPage = JsonParser.intValue(json['last_page'] ?? 1);
    int perPage = JsonParser.intValue(json['per_page'] ?? 15);
    int total = JsonParser.intValue(json['total'] ?? 0);

    if (jsonData is Map<String, dynamic>) {
      // Check if 'data' contains the list nested inside (standard Laravel)
      if (jsonData.containsKey('data') && jsonData['data'] is List) {
        currentPage = JsonParser.intValue(
          jsonData['current_page'] ?? currentPage,
        );
        lastPage = JsonParser.intValue(jsonData['last_page'] ?? lastPage);
        perPage = JsonParser.intValue(jsonData['per_page'] ?? perPage);
        total = JsonParser.intValue(jsonData['total'] ?? total);
        items = (jsonData['data'] as List)
            .map((e) => TransactionData.fromJson(e as Map<String, dynamic>))
            .toList();
      } else {
        // 'data' is a Map but doesn't have nested 'data' list?
        // Maybe 'data' is actually the record itself (if single)?
        // For history, this is less likely.
      }
    } else if (jsonData is List) {
      // 'data' is a direct list
      items = jsonData
          .map((e) => TransactionData.fromJson(e as Map<String, dynamic>))
          .toList();
      if (total == 0) total = items.length;
    }

    return TransactionHistoryModel(
      success: success,
      message: message,
      currentPage: currentPage,
      lastPage: lastPage,
      perPage: perPage,
      total: total,
      data: items,
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'success': success,
      'message': message,
      'data': {
        'current_page': currentPage,
        'last_page': lastPage,
        'per_page': perPage,
        'total': total,
        'data': data.map((e) => e.toJson()).toList(),
      },
    };
  }
}

class TransactionData {
  final int id;
  final int? walletId;
  final int? userId;
  final int? orderId;
  final int? storeId;
  final String type;
  final String? paymentMethod;
  final String amount;
  final String? currencyCode;
  final String? currencySymbol;
  final String? formattedAmount;
  final String? status;
  final String? transactionReference;
  final String? description;
  final String? message;
  final String createdAt;
  final String? updatedAt;
  final String? transactionId;
  final String? balance;

  TransactionData({
    required this.id,
    this.walletId,
    this.userId,
    this.orderId,
    this.storeId,
    required this.type,
    this.paymentMethod,
    required this.amount,
    this.currencyCode,
    this.currencySymbol,
    this.formattedAmount,
    this.status,
    this.transactionReference,
    this.description,
    this.message,
    required this.createdAt,
    this.updatedAt,
    this.transactionId,
    this.balance,
  });

  factory TransactionData.fromJson(Map<String, dynamic> json) {
    String? extractText(dynamic value) {
      if (value == null) return null;
      final str = JsonParser.string(value).trim();
      return str.isNotEmpty ? str : null;
    }

    final rawDesc = extractText(json['description']) ??
        extractText(json['message']) ??
        extractText(json['title']) ??
        extractText(json['remarks']) ??
        extractText(json['remark']) ??
        extractText(json['details']) ??
        extractText(json['note']);

    final rawRef = extractText(json['transaction_reference']) ??
        extractText(json['transaction_id']) ??
        extractText(json['reference']);

    final rawTxId = extractText(json['transaction_id']) ?? rawRef;

    return TransactionData(
      id: JsonParser.intValue(json['id']),
      walletId: json['wallet_id'] != null
          ? JsonParser.intValue(json['wallet_id'])
          : null,
      userId: json['user_id'] != null
          ? JsonParser.intValue(json['user_id'])
          : null,
      orderId: json['order_id'] != null
          ? JsonParser.intValue(json['order_id'])
          : null,
      storeId: json['store_id'] != null
          ? JsonParser.intValue(json['store_id'])
          : null,
      type: JsonParser.string(json['transaction_type'] ?? json['type']),
      paymentMethod: extractText(json['payment_method']),
      amount: JsonParser.string(json['amount']),
      currencyCode: extractText(json['currency_code']),
      currencySymbol: extractText(json['currency_symbol']),
      formattedAmount: extractText(json['formatted_amount']),
      status: extractText(json['status']),
      transactionReference: rawRef,
      description: rawDesc,
      message: rawDesc,
      createdAt: JsonParser.string(json['created_at']),
      updatedAt: extractText(json['updated_at']),
      transactionId: rawTxId,
      balance: extractText(json['balance']),
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'id': id,
      if (walletId != null) 'wallet_id': walletId,
      if (userId != null) 'user_id': userId,
      if (orderId != null) 'order_id': orderId,
      if (storeId != null) 'store_id': storeId,
      'transaction_type': type,
      if (paymentMethod != null) 'payment_method': paymentMethod,
      'amount': amount,
      if (currencyCode != null) 'currency_code': currencyCode,
      if (currencySymbol != null) 'currency_symbol': currencySymbol,
      if (formattedAmount != null) 'formatted_amount': formattedAmount,
      if (status != null) 'status': status,
      if (transactionReference != null)
        'transaction_reference': transactionReference,
      if (description != null) 'description': description,
      if (message != null) 'message': message,
      'created_at': createdAt,
      if (updatedAt != null) 'updated_at': updatedAt,
      if (transactionId != null) 'transaction_id': transactionId,
      if (balance != null) 'balance': balance,
    };
  }

  bool get isCredit =>
      type.toLowerCase() == 'deposit' || type.toLowerCase() == 'credit';

  bool get isDebit => !isCredit;

  bool get isCompleted => status?.toLowerCase() == 'completed';
}
