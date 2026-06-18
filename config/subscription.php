<?php

return [

    'free' => [
        'max_projects' => env('USER_FREE_MAX_PROJECTS', 1),
        'max_collaborate_in_projects' => env('USER_FREE_MAX_COLLOBRATE_IN_PROJECTS', 3),
        'max_targets' => env('USER_FREE_MAX_TARGETS', 3),
        'max_targets_per_project' => env('USER_FREE_MAX_TARGETS', 3),
        'max_scans_per_month' => env('USER_FREE_MAX_SCANS_PER_MONTH', 20),
        'amount_egp' => 0,
    ],

    'starter' => [
        'max_projects' => env('USER_STARTER_MAX_PROJECTS', 5),
        'max_collaborate_in_projects' => env('USER_STARTER_MAX_COLLOBRATE_IN_PROJECTS', 7),
        'max_targets' => env('USER_STARTER_MAX_TARGETS_PER_PROJECT', 10),
        'max_targets_per_project' => env('USER_STARTER_MAX_TARGETS_PER_PROJECT', 10),
        'max_scans_per_month' => env('USER_STARTER_MAX_SCANS_PER_MONTH', 50),
        'amount_egp' => env('USER_STARTER_AMOUNT_EGP', 199),
    ],

    'pro' => [
        'max_projects' => env('USER_PRO_MAX_PROJECTS', 20),
        'max_collaborate_in_projects' => env('USER_PRO_MAX_COLLOBRATE_IN_PROJECTS', 15),
        'max_targets' => env('USER_PRO_MAX_TARGETS_PER_PROJECT', 50),
        'max_targets_per_project' => env('USER_PRO_MAX_TARGETS_PER_PROJECT', 50),
        'max_scans_per_month' => env('USER_PRO_MAX_SCANS_PER_MONTH', 100),
        'amount_egp' => env('USER_PRO_AMOUNT_EGP', 499),
    ],

];
