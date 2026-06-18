<?php

namespace App\Support;

class SubscriptionPlanLimits
{
    /**
     * @return array{max_projects: int, max_targets: int, max_scans_per_month: int}
     */
    public static function forPlan(string $plan): array
    {
        $limits = SubscriptionPlans::user($plan);

        return [
            'max_projects' => (int) $limits['max_projects'],
            'max_targets' => (int) $limits['max_targets_per_project'],
            'max_scans_per_month' => (int) $limits['max_scans_per_month'],
        ];
    }
}
