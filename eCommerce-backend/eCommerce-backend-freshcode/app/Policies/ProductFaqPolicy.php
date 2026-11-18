<?php

namespace App\Policies;

use App\Enums\AdminPermissionEnum;
use App\Enums\DefaultSystemRolesEnum;
use App\Enums\SellerPermissionEnum;
use App\Models\ProductFaq;
use App\Models\User;
use App\Traits\ChecksPermissions;
use App\Traits\PanelAware;
use Illuminate\Auth\Access\Response;

class ProductFaqPolicy
{
    use ChecksPermissions, PanelAware;

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        try {
            if (method_exists($this, 'getPanel') ? $this->getPanel() == 'admin' : true) {
                return $this->hasPermission(AdminPermissionEnum::PRODUCT_FAQS_VIEW());
            }
            return $user->hasRole(DefaultSystemRolesEnum::SELLER())
                || $this->hasPermission(SellerPermissionEnum::PRODUCT_FAQ_VIEW());
        } catch (\Exception) {
            return false;
        }
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, ProductFaq $productFaq): bool
    {
        try {
            if (method_exists($this, 'getPanel') ? $this->getPanel() == 'admin' : true) {
                return $this->hasPermission(AdminPermissionEnum::PRODUCT_FAQS_VIEW());
            }

            if ($user->seller() == null || empty($productFaq->seller)) {
                return false;
            }
            if ($user->seller()->id == $productFaq->seller->id) {
                return $user->hasRole(DefaultSystemRolesEnum::SELLER())
                    || $this->hasPermission(SellerPermissionEnum::PRODUCT_FAQ_VIEW());
            }
            return false;
        } catch (\Exception) {
            return false;
        }
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        try {
            if ($user->seller() == null) {
                return false;
            }

            if (
                $user->hasRole(DefaultSystemRolesEnum::SELLER()) ||
                $this->hasPermission(SellerPermissionEnum::PRODUCT_FAQ_CREATE())
            ) {
                return true;
            }

            return false;
        } catch (\Exception) {
            return false;
        }
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, ProductFaq $productFaq): bool
    {
        try {
            if ($user->seller() == null || empty($productFaq->seller)) {
                return false;
            }
            if ($user->seller()->id == $productFaq->seller->id) {
                if (
                    $user->hasRole(DefaultSystemRolesEnum::SELLER()) ||
                    $this->hasPermission(SellerPermissionEnum::PRODUCT_FAQ_EDIT())
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
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, ProductFaq $productFaq): bool
    {
        try {
            if ($user->seller() == null || empty($productFaq->seller)) {
                return false;
            }
            if ($user->seller()->id == $productFaq->seller->id) {
                if (
                    $user->hasRole(DefaultSystemRolesEnum::SELLER()) ||
                    $this->hasPermission(SellerPermissionEnum::PRODUCT_FAQ_DELETE())
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
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, ProductFaq $productFaq): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, ProductFaq $productFaq): bool
    {
        return false;
    }
}
