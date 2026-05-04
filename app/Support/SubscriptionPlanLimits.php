<?php

namespace App\Support;

class SubscriptionPlanLimits
{
    /**
     * @return array{max_projects: int, max_targets: int, max_scans_per_month: int}
     */
    public static function forPlan(string $plan): array
    {
        return match ($plan) {
            'free' => [
                'max_projects' => (int) config('subscription.free.max_projects'),
                'max_targets' => (int) config('subscription.free.max_targets'),
                'max_scans_per_month' => (int) config('subscription.free.max_scans_per_month'),
            ],
            'starter' => [
                'max_projects' => (int) config('subscription.starter.max_projects'),
                'max_targets' => (int) config('subscription.starter.max_targets'),
                'max_scans_per_month' => (int) config('subscription.starter.max_scans_per_month'),
            ],
            'pro' => [
                'max_projects' => (int) config('subscription.pro.max_projects'),
                'max_targets' => (int) config('subscription.pro.max_targets'),
                'max_scans_per_month' => (int) config('subscription.pro.max_scans_per_month'),
            ],
        };
    }
}
