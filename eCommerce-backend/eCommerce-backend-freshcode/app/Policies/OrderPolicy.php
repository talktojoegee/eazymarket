<?php

namespace App\Policies;

use App\Enums\AdminPermissionEnum;
use App\Enums\DefaultSystemRolesEnum;
use App\Enums\GuardNameEnum;
use App\Enums\Order\OrderItemStatusEnum;
use App\Enums\SellerPermissionEnum;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\SellerOrder;
use App\Models\SellerOrderItem;
use App\Models\User;
use App\Traits\ChecksPermissions;
use App\Traits\PanelAware;

class OrderPolicy
{
    use ChecksPermissions, PanelAware;

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        if ($this->getPanel() === 'admin') {
            return $this->hasPermission(AdminPermissionEnum::ORDER_VIEW());
        }

        if (
            $user->hasRole(DefaultSystemRolesEnum::SELLER()) ||
            $this->hasPermission(SellerPermissionEnum::ORDER_VIEW())
        ) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, $order): bool
    {
        try {
            if ($this->getPanel() === 'admin') {
                return $this->hasPermission(AdminPermissionEnum::ORDER_VIEW());
            }

            if ($user->seller()->id == $order->seller_id) {
                if (
                    $user->hasRole(DefaultSystemRolesEnum::SELLER()) ||
                    $this->hasPermission(SellerPermissionEnum::ORDER_VIEW())
                ) {
                    return true;
                }
            }

            return false;
        } catch (\Exception) {
            return false;
        }
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, SellerOrder $order): bool
    {
        try {
            if ($this->getPanel() === 'admin') {
                return $this->hasPermission(AdminPermissionEnum::ORDER_EDIT());
            }

            if ($user->seller()->id == $order->seller_id) {
                if (
                    $user->hasRole(DefaultSystemRolesEnum::SELLER()) ||
                    $this->hasPermission(SellerPermissionEnum::ORDER_EDIT())
                ) {
                    return true;
                }
            }

            return false;
        } catch (\Exception) {
            return false;
        }
    }

    /**
     * Determine whether the user can update the status of an order item.
     */
    public function updateStatus(User $user, SellerOrderItem $orderItem): bool
    {
        try {
            if ($this->getPanel() === 'admin') {
                return $this->hasPermission(AdminPermissionEnum::ORDER_EDIT());
            }

            if ($user->seller()->id == $orderItem->sellerOrder->seller_id) {
                if (
                    $user->hasRole(DefaultSystemRolesEnum::SELLER()) ||
                    $this->hasPermission(SellerPermissionEnum::ORDER_UPDATE_STATUS())
                ) {
                    return true;
                }
            }

            return false;
        } catch (\Exception) {
            return false;
        }
    }

    public function viewInvoice(User $user, $orderData): bool
    {
        try {
            if ($this->hasPermission(AdminPermissionEnum::ORDER_VIEW())) {
                return true;
            }
            if ($orderData['user_id'] == $user->id) {
                return true;
            }
            return false;
        } catch (\Exception) {
            return false;
        }
    }

    /**
     * Phase 1D — seller cancelling an item they had already accepted.
     * Pre-collect only.
     * Phase 3 — admin path opened up via the unified ORDER_EDIT permission.
     */
    public function cancelItem(User $user, OrderItem $orderItem): bool
    {
        try {
            if ($this->getPanel() === 'admin') {
                return $this->hasPermission(AdminPermissionEnum::ORDER_EDIT());
            }

            $belongs = SellerOrderItem::where('order_item_id', $orderItem->id)
                ->whereHas('sellerOrder', fn($q) => $q->where('seller_id', $user->seller()->id))
                ->exists();
            if (!$belongs) {
                return false;
            }

            if ((string) $orderItem->status !== (string) OrderItemStatusEnum::ACTIVE()) {
                return false;
            }

            return $user->hasRole(DefaultSystemRolesEnum::SELLER())
                || $this->hasPermission(SellerPermissionEnum::ORDER_CANCEL_POST_ACCEPT());
        } catch (\Exception) {
            return false;
        }
    }

    /**
     * Admin order actions are gated by a four-way permission split, each
     * independent (ORDER_VIEW remains read-only):
     *   force-status / force-cancel / reassign-rider / add-note → ORDER_EDIT
     *   force-refund                                            → ORDER_REFUND
     *   accept-payment                                          → ORDER_PAYMENT_ACCEPT
     */
    public function forceUpdateStatus(User $user, OrderItem|Order $orderItem): bool
    {
        return $this->adminCanEditOrder($user);
    }

    public function forceCancel(User $user, OrderItem $orderItem): bool
    {
        return $this->adminCanEditOrder($user);
    }

    public function forceRefund(User $user, $order): bool
    {
        return $this->adminCanRefundOrder($user);
    }

    public function acceptPayment(User $user, $order): bool
    {
        return $this->adminCanAcceptPayment($user);
    }

    public function reassignRider(User $user, $order): bool
    {
        return $this->adminCanEditOrder($user);
    }

    public function addNote(User $user, $order): bool
    {
        return $this->adminCanEditOrder($user);
    }

    /**
     * Helper — admin (non-seller) user with the ORDER_EDIT permission.
     */
    private function adminCanEditOrder(User $user): bool
    {
        try {
            return $this->hasPermission(AdminPermissionEnum::ORDER_EDIT());
        } catch (\Exception) {
            return false;
        }
    }

    /**
     * Helper — admin (non-seller) user with the ORDER_REFUND permission.
     */
    private function adminCanRefundOrder(User $user): bool
    {
        try {
            return $this->hasPermission(AdminPermissionEnum::ORDER_REFUND());
        } catch (\Exception) {
            return false;
        }
    }

    /**
     * Helper — admin (non-seller) user with the ORDER_PAYMENT_ACCEPT permission.
     */
    private function adminCanAcceptPayment(User $user): bool
    {
        try {
            return $this->hasPermission(AdminPermissionEnum::ORDER_PAYMENT_ACCEPT());
        } catch (\Exception) {
            return false;
        }
    }
}
