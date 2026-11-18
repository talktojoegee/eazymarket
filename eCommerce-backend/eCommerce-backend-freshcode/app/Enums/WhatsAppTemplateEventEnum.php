<?php

namespace App\Enums;

enum WhatsAppTemplateEventEnum: string
{
    case CUSTOMER_ORDER_PLACED = 'customer_order_placed';
    case CUSTOMER_ORDER_SHIPPED = 'customer_order_shipped';
    case CUSTOMER_ORDER_DELIVERED_INVOICE = 'customer_order_delivered_invoice';
    case CUSTOMER_ABANDONED_CART = 'customer_abandoned_cart';
    // case CUSTOMER_PAYMENT_RECEIVED = 'customer_payment_received';
    // case CUSTOMER_ORDER_STATUS = 'customer_order_status';
    // case SELLER_NEW_ORDER = 'seller_new_order';
    // case SELLER_ORDER_STATUS = 'seller_order_status';


    public function label(): string
    {
        return match ($this) {
            self::CUSTOMER_ORDER_PLACED => 'Order placed',
            self::CUSTOMER_ORDER_SHIPPED => 'Order shipped',
            self::CUSTOMER_ORDER_DELIVERED_INVOICE => 'Order delivered',
            self::CUSTOMER_ABANDONED_CART => 'Abandoned cart',
            // self::CUSTOMER_PAYMENT_RECEIVED => 'Payment received',
            // self::CUSTOMER_ORDER_STATUS, 
            // self::SELLER_ORDER_STATUS => 'Order status changed',
            // self::SELLER_NEW_ORDER => 'New order',
        };
    }

    public function audience(): string
    {
        return match ($this) {
            self::CUSTOMER_ORDER_PLACED,
            self::CUSTOMER_ORDER_SHIPPED,
            self::CUSTOMER_ORDER_DELIVERED_INVOICE,
            self::CUSTOMER_ABANDONED_CART,
            self::CUSTOMER_PAYMENT_RECEIVED,
            self::CUSTOMER_ORDER_STATUS => 'Customer',
            self::SELLER_NEW_ORDER, 
            self::SELLER_ORDER_STATUS => 'Seller', 
        };
    }

    public function fields(): array
    {
        return match ($this) {
            self::CUSTOMER_ORDER_PLACED => ['customer_name', 'order_number', 'total_amount', 'order_date'],
            self::CUSTOMER_ORDER_SHIPPED => ['customer_name', 'order_number', 'order_date'],
            self::CUSTOMER_ORDER_DELIVERED_INVOICE => ['customer_name', 'order_number', 'order_date'],
            self::CUSTOMER_ABANDONED_CART => ['customer_name'],
            // self::CUSTOMER_PAYMENT_RECEIVED => ['customer_name', 'order_number', 'total_amount', 'payment_method'],
            // self::CUSTOMER_ORDER_STATUS => ['customer_name', 'order_number', 'order_status'],
            // self::SELLER_NEW_ORDER => ['seller_name', 'order_number', 'total_amount', 'customer_name', 'order_date'],
            // self::SELLER_ORDER_STATUS => ['seller_name', 'order_number', 'order_status'],
        };
    }

    public static function options(): array
    {
        return array_map(fn (self $event) => [
            'value' => $event->value,
            'label' => $event->audience().' · '.$event->label(),
            'fields' => $event->fields(),
        ], self::cases());
    }

    public static function fieldsByValue(): array
    {
        $events = [];
        foreach (self::cases() as $event) {
            $events[$event->value] = $event->fields();
        }

        return $events;
    }
}
