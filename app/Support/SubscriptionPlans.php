<?php

namespace App\Support;

use InvalidArgumentException;

class SubscriptionPlans
{
    /**
     * @return array<string, mixed>
     */
    public static function user(string $plan): array
    {
        return self::plan('users', $plan);
    }

    /**
     * @return array<string, mixed>
     */
    public static function organization(string $plan): array
    {
        return self::plan('organizations', $plan);
    }

    /**
     * @return array<string, mixed>
     */
    public static function plan(string $workspaceType, string $plan): array
    {
        $config = config("subscriptions.{$workspaceType}.{$plan}");

        if (! is_array($config)) {
            throw new InvalidArgumentException("Unknown {$workspaceType} subscription plan [{$plan}].");
        }

        return $config;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public static function all(string $workspaceType): array
    {
        $plans = config("subscriptions.{$workspaceType}", []);

        return is_array($plans) ? $plans : [];
    }
}
