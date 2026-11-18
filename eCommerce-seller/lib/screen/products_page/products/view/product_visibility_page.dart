import 'package:flutter/material.dart';
import 'package:hyper_commerce_seller/extensions/translation_extension.dart';
import 'package:hyper_commerce_seller/screen/products_page/products/model/product_visibility_model.dart';
import 'package:hyper_commerce_seller/screen/products_page/products/repo/products_repo.dart';
import 'package:hyper_commerce_seller/widgets/custom/custom_loading_indicator.dart';
import 'package:hyper_commerce_seller/widgets/custom/custom_scaffold.dart';

/// Shows why a product is (or isn't) visible to customers — the publish gates
/// (status / verification / variants / image) plus overall status.
class ProductVisibilityPage extends StatefulWidget {
  final int productId;
  const ProductVisibilityPage({super.key, required this.productId});

  @override
  State<ProductVisibilityPage> createState() => _ProductVisibilityPageState();
}

class _ProductVisibilityPageState extends State<ProductVisibilityPage> {
  final _repo = ProductsRepo();
  ProductVisibility? _data;
  String? _error;
  bool _loading = true;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final res = await _repo.getProductVisibility(widget.productId);
      setState(() {
        _data = ProductVisibility.fromJson(res);
        _loading = false;
      });
    } catch (e) {
      setState(() {
        _error = e.toString();
        _loading = false;
      });
    }
  }

  Color _statusColor(String s) {
    switch (s) {
      case 'live':
        return Colors.green;
      case 'partial':
        return Colors.orange;
      default:
        return Colors.red;
    }
  }

  @override
  Widget build(BuildContext context) {
    return CustomScaffold(
      title: context.tr('visibilityTitle', fallback: 'Visibility'),
      showAppbar: true,
      centerTitle: true,
      onBackTap: () => Navigator.of(context).pop(),
      body: _loading
          ? const Center(child: CustomLoadingIndicator())
          : _error != null
              ? Center(child: Padding(padding: const EdgeInsets.all(24), child: Text(_error!, textAlign: TextAlign.center)))
              : RefreshIndicator(
                  onRefresh: _load,
                  child: ListView(
                    padding: const EdgeInsets.all(16),
                    children: [
                      Container(
                        padding: const EdgeInsets.all(16),
                        decoration: BoxDecoration(
                          color: _statusColor(_data!.status).withValues(alpha: 0.12),
                          borderRadius: BorderRadius.circular(12),
                        ),
                        child: Row(children: [
                          Icon(
                            _data!.status == 'live' ? Icons.visibility : Icons.visibility_off,
                            color: _statusColor(_data!.status),
                          ),
                          const SizedBox(width: 12),
                          Text(
                            _data!.status.toUpperCase(),
                            style: TextStyle(fontWeight: FontWeight.bold, color: _statusColor(_data!.status)),
                          ),
                        ]),
                      ),
                      const SizedBox(height: 16),
                      ..._data!.checks.map(_buildCheck),
                    ],
                  ),
                ),
    );
  }

  Widget _buildCheck(VisibilityCheck c) {
    final color = c.passed ? Colors.green : Colors.red;
    return Container(
      margin: const EdgeInsets.only(bottom: 10),
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        borderRadius: BorderRadius.circular(10),
        border: Border.all(color: Colors.black12),
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Icon(c.passed ? Icons.check_circle : Icons.cancel, color: color, size: 20),
          const SizedBox(width: 10),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(c.label, style: const TextStyle(fontWeight: FontWeight.w600, fontSize: 13.5)),
                const SizedBox(height: 2),
                Text(c.message, style: TextStyle(fontSize: 12.5, color: Colors.grey.shade600)),
                if (c.fix != null && c.fix!.isNotEmpty) ...[
                  const SizedBox(height: 4),
                  Text(c.fix!, style: TextStyle(fontSize: 12, color: color, fontStyle: FontStyle.italic)),
                ],
              ],
            ),
          ),
        ],
      ),
    );
  }
}
