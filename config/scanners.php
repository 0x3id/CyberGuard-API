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
            'supports_streaming' => true,
            'triggers' => [],
            'field_map' => [
                'title' => "path: title",
                'severity' => "path: severity",
                'description' => "path: description",
                'remediation' => "path: remediation",
                'cvss_score' => "path: cvss_score",
                'cvss_vector' => "path: cvss_vector",
                'cve_id' => "path: cve_id",
                'tags'  => "path: tags",
                'affected_url' => "path: affected_url",
                'metadata.host' => "path: metadata.host",
                'metadata.ips' => "path: metadata.ips",
                'metadata.cnames' => "path: metadata.cnames",
                'metadata.alive' => "path: metadata.alive",
                'metadata.scan_time' => "path: metadata.scan_time",
                'metadata.issue_type' => "path: metadata.issue_type",
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
            'supports_streaming' => true,
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
        "sqli" => [
            'display_name' => 'SQL Injection Scanner',
            'description' => 'Automated detection and analysis of SQL Injection vulnerabilities in web applications.',
            'image' => 'sql-tester-image',
            'category' => 'web',
            'command_pattern' => '{{TARGET}}', // The python script accepts target as the first argument
            'output_format' => 'json',
            'timeout_seconds' => 2000,
            'default_flags' => ["category"], // The python script does not take flags directly
            'supports_streaming' => true,
            'triggers' => [],
            'field_map' => [
                'title' => "path: title",
                'severity' => "path: severity",
                'description' => "path: description",
                'remediation' => "path: remediation",
                'cvss_score' => "path: cvss_score",
                'cvss_vector' => "path: cvss_vector",
                'cve_id' => "path: cve_id",
                'metadata.payloads' => "path: metadata.payloads ",
                'metadata.request' => "path: metadata.request",
                'metadata.response' => "path: metadata.response",
                'affected_url' => "path: affected_url",
                'proof' => "path: proof",
                'tags' => "path: tags"
            ]
        ],
    ]
];
