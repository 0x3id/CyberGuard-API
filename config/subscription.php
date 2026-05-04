<?php

return [

    'free' => [
        'max_projects' => env('FREE_MAX_PROJECTS', 3),
        'max_targets' => env('FREE_MAX_TARGETS', 10),
        'max_scans_per_month' => env('FREE_MAX_SCANS_PER_MONTH', 10),
    ],

    'starter' => [
        'max_projects' => env('STARTER_MAX_PROJECTS', 10),
        'max_targets' => env('STARTER_MAX_TARGETS', 50),
        'max_scans_per_month' => env('STARTER_MAX_SCANS_PER_MONTH', 100),
        'amount_egp' => env('BILLING_STARTER_AMOUNT_EGP', 199),
    ],

    'pro' => [
        'max_projects' => env('PRO_MAX_PROJECTS', PHP_INT_MAX),
        'max_targets' => env('PRO_MAX_TARGETS', PHP_INT_MAX),
        'max_scans_per_month' => env('PRO_MAX_SCANS_PER_MONTH', PHP_INT_MAX),
        'amount_egp' => env('BILLING_PRO_AMOUNT_EGP', 499),
    ],

];
