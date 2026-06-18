<?php

return [
    'plans' => [
        'starter' => [
            'name' => 'Starter',
            'max_projects' => env('ORG_STARTER_MAX_PROJECTS', 10),
            'max_targets_per_project' => env('ORG_STARTER_MAX_TARGETS_PER_PROJECT', 20),
            'max_scans_per_month' => env('ORG_STARTER_MAX_SCANS_PER_MONTH', 50),
            'max_members' => env('ORG_STARTER_MAX_MEMBERS', 10),
            'amount_egp' => env('ORG_STARTER_AMOUNT_EGP', 2999),
        ],
        'pro' => [
            'name' => 'Pro',
            'max_projects' => env('ORG_PRO_MAX_PROJECTS', 15),
            'max_targets_per_project' => env('ORG_PRO_MAX_TARGETS_PER_PROJECT', 25),
            'max_scans_per_month' => env('ORG_PRO_MAX_SCANS_PER_MONTH', 100),
            'max_members' => env('ORG_PRO_MAX_MEMBERS', env('ORG_PRO_PRO_MAX_MEMBERS', 15)),
            'amount_egp' => env('ORG_PRO_AMOUNT_EGP', 3999),
        ],
        'enterprise' => [
            'name' => 'Enterprise',
            'max_projects' => env('ORG_ENTERPRISE_MAX_PROJECTS', 50),
            'max_targets_per_project' => env('ORG_ENTERPRISE_MAX_TARGETS_PER_PROJECT', 100),
            'max_scans_per_month' => env('ORG_ENTERPRISE_MAX_SCANS_PER_MONTH', 500),
            'max_members' => env('ORG_ENTERPRISE_MAX_MEMBERS', 50),
            'amount_egp' => env('ORG_ENTERPRISE_AMOUNT_EGP', 5999),
        ],
    ],
];
