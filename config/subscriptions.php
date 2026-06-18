<?php

return [
    'users' => [
        'free' => [
            'name' => 'Free',
            'max_projects' => (int) env('USER_FREE_MAX_PROJECTS', 1),
            'max_collaborate_in_projects' => (int) env('USER_FREE_MAX_COLLOBRATE_IN_PROJECTS', 3),
            'max_targets_per_project' => (int) env('USER_FREE_MAX_TARGETS', 3),
            'max_scans_per_month' => (int) env('USER_FREE_MAX_SCANS_PER_MONTH', 20),
            'amount_egp' => 0,
        ],
        'starter' => [
            'name' => 'Starter',
            'max_projects' => (int) env('USER_STARTER_MAX_PROJECTS', 5),
            'max_collaborate_in_projects' => (int) env('USER_STARTER_MAX_COLLOBRATE_IN_PROJECTS', 7),
            'max_targets_per_project' => (int) env('USER_STARTER_MAX_TARGETS_PER_PROJECT', 10),
            'max_scans_per_month' => (int) env('USER_STARTER_MAX_SCANS_PER_MONTH', 50),
            'amount_egp' => (int) env('USER_STARTER_AMOUNT_EGP', 199),
        ],
        'pro' => [
            'name' => 'Pro',
            'max_projects' => (int) env('USER_PRO_MAX_PROJECTS', 20),
            'max_collaborate_in_projects' => (int) env('USER_PRO_MAX_COLLOBRATE_IN_PROJECTS', 15),
            'max_targets_per_project' => (int) env('USER_PRO_MAX_TARGETS_PER_PROJECT', 50),
            'max_scans_per_month' => (int) env('USER_PRO_MAX_SCANS_PER_MONTH', 100),
            'amount_egp' => (int) env('USER_PRO_AMOUNT_EGP', 499),
        ],
    ],

    'organizations' => [
        'starter' => [
            'name' => 'Starter',
            'max_projects' => (int) env('ORG_STARTER_MAX_PROJECTS', 10),
            'max_targets_per_project' => (int) env('ORG_STARTER_MAX_TARGETS_PER_PROJECT', 20),
            'max_scans_per_month' => (int) env('ORG_STARTER_MAX_SCANS_PER_MONTH', 50),
            'max_members' => (int) env('ORG_STARTER_MAX_MEMBERS', 10),
            'amount_egp' => (int) env('ORG_STARTER_AMOUNT_EGP', 2999),
        ],
        'pro' => [
            'name' => 'Pro',
            'max_projects' => (int) env('ORG_PRO_MAX_PROJECTS', 15),
            'max_targets_per_project' => (int) env('ORG_PRO_MAX_TARGETS_PER_PROJECT', 25),
            'max_scans_per_month' => (int) env('ORG_PRO_MAX_SCANS_PER_MONTH', 100),
            'max_members' => (int) env('ORG_PRO_MAX_MEMBERS', env('ORG_PRO_PRO_MAX_MEMBERS', 15)),
            'amount_egp' => (int) env('ORG_PRO_AMOUNT_EGP', 3999),
        ],
        'enterprise' => [
            'name' => 'Enterprise',
            'max_projects' => (int) env('ORG_ENTERPRISE_MAX_PROJECTS', 50),
            'max_targets_per_project' => (int) env('ORG_ENTERPRISE_MAX_TARGETS_PER_PROJECT', 100),
            'max_scans_per_month' => (int) env('ORG_ENTERPRISE_MAX_SCANS_PER_MONTH', 500),
            'max_members' => (int) env('ORG_ENTERPRISE_MAX_MEMBERS', 50),
            'amount_egp' => (int) env('ORG_ENTERPRISE_AMOUNT_EGP', 5999),
        ],
    ],
];
