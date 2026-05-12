<?php

return [
    'drivers' => [
        'subdomain-scan' => [
            'display_name' => 'Custom Subdomain Enum & Analysis',
            'image' => 'sub-domain-scan:latest',
            'category' => 'recon',
            'command_pattern' => '{{TARGET}}', // The python script accepts target as the first argument
            'output_format' => 'json',
            'timeout_seconds' => 2000,
            'default_flags' => [], // The python script does not take flags directly
            'supports_streaming' => false,
            'triggers' => [],
            'field_map' => [
                'title' => "path: title",
                'severity' => "path: severity",
                'description' => "path: description",
                'remediation' => "path: remediation",
                'cvss_score' => "path: cvss_score",
                'cvss_vector' => "path: cvss_vector",
                'cve_id' => "path: cve_id",
                'metadata.issue_type' => "path: type",
            ]
        ],
        'web-endpoint-fuzzer' => [
            'display_name' => 'Web Endpoint Fuzzer & Classifier',
            'image' => 'endpoint-fuzzer:latest',
            'category' => 'recon',
            'command_pattern' => '{{TARGET}}', // The python script accepts target as the first argument
            'output_format' => 'json',
            'timeout_seconds' => 2000,
            'default_flags' => [], // The python script does not take flags directly
            'supports_streaming' => false,
            'triggers' => [],
            'field_map' => [
                'title' => "path: title",
                'severity' => "path: severity",
                'description' => "path: description",
                'remediation' => "path: remediation",
                'cvss_score' => "path: cvss_score",
                'cvss_vector' => "path: cvss_vector",
                'cve_id' => "path: cve_id",
                'metadata.issue_type' => "path: type",
                'metadata.risk_score' => "path: metadata.risk_score",
                'metadata.urls' => "path: metadata.urls",
                'affected_url' => "path: affected_url",
                'proof' => "path: proof",
                'tags' => "path: tags"
            ]
        ],
    ]
];
