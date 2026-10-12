<?php

namespace App\Policies;

use App\Enums\PayrollPeriodStatus;
use App\Enums\UserRole;
use App\Models\PayrollPeriod;
use App\Models\User;

/**
 * Periode payroll borongan (FR-015, FR-016).
 *
 * Payroll adalah data sensitif lintas klien, sehingga hanya Finance dan Owner
 * yang boleh melihat dan mengelolanya. Periode yang sudah disetujui atau
 * dibayar terkunci dan tidak dapat dihitung ulang (NFR-005).
 */
class PayrollPeriodPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->canView($user);
    }

    public function view(User $user, PayrollPeriod $period): bool
    {
        return $this->canView($user);
    }

    public function create(User $user): bool
    {
        return $this->canManage($user);
    }

    public function update(User $user, PayrollPeriod $period): bool
    {
        return $this->canManage($user) && ! $period->isLocked();
    }

    public function delete(User $user, PayrollPeriod $period): bool
    {
        return false;
    }

    /**
     * Menjalankan perhitungan borongan, uang makan, dan pool bonus QC.
     */
    public function calculate(User $user, PayrollPeriod $period): bool
    {
        return $this->canManage($user) && ! $period->isLocked();
    }

    /**
     * Persetujuan hanya masuk akal setelah periode dihitung (FR-026).
     */
    public function approve(User $user, PayrollPeriod $period): bool
    {
        return $this->canManage($user)
            && $period->status === PayrollPeriodStatus::CALCULATED;
    }

    public function pay(User $user, PayrollPeriod $period): bool
    {
        return $this->canManage($user)
            && $period->status === PayrollPeriodStatus::APPROVED;
    }

    private function canView(User $user): bool
    {
        return $user->hasRoleSatisfying(
            fn (UserRole $role): bool => $role->canManagePayroll() || $role->canConfigureSystem(),
        );
    }

    private function canManage(User $user): bool
    {
        return $user->hasRoleSatisfying(fn (UserRole $role): bool => $role->canManagePayroll());
    }
}
