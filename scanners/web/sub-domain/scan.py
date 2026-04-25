# docker/subdomain-scanner/scan.py

import subprocess
import json
import sys
import os
import re
from datetime import datetime, timezone

def validate_domain(domain: str) -> bool:
    """Whitelist validation — منع command injection"""
    pattern = r'^[a-zA-Z0-9][a-zA-Z0-9\-\.]{1,253}[a-zA-Z0-9]$'
    return bool(re.match(pattern, domain)) and '..' not in domain

def run_subfinder(domain: str) -> list[str]:
    """يجيب subdomains من passive sources"""
    result = subprocess.run(
        [
            'subfinder',
            '-d', domain,
            '-silent',          # output فقط بدون logs
            '-timeout', '30',
            '-t', '50',         # 50 concurrent threads
        ],
        capture_output=True,
        text=True,
        timeout=600
    )
    if result.returncode != 0 and not result.stdout:
        return []

    return [
        line.strip()
        for line in result.stdout.splitlines()
        if line.strip()
    ]

def run_dnsx(subdomains: list[str]) -> list[dict]:
    """
    يـresolve كل subdomain ويجيب:
    - IP address
    - نوع الـ DNS record
    - هل alive؟
    """
    if not subdomains:
        return []

    # Pass subdomains عن طريق stdin
    input_data = '\n'.join(subdomains)

    result = subprocess.run(
        [
            'dnsx',
            '-silent',
            '-resp',            # يرجّع الـ IP
            '-a',               # A records
            '-cname',           # CNAME records
            '-json',            # JSON output
            '-t', '50',
        ],
        input=input_data,
        capture_output=True,
        text=True,
        timeout=600
    )

    resolved = []
    for line in result.stdout.splitlines():
        line = line.strip()
        if not line:
            continue
        try:
            data = json.loads(line)
            resolved.append(data)
        except json.JSONDecodeError:
            continue

    return resolved

def classify_subdomain(subdomain: str, dns_data: dict) -> dict:
    """
    يحلل الـ subdomain ويحدد:
    - هل فيه مشكلة أمنية؟
    - إيه الـ severity؟
    - إيه التوصية؟
    """
    issues = []
    host = dns_data.get('host', subdomain)
    ips  = dns_data.get('a', [])
    cnames = dns_data.get('cname', [])

    # ── Check 1: Subdomain Takeover risk ─────────────────────────────
    # لو فيه CNAME بيشاور على service مش موجود
    VULNERABLE_CNAMES = [
        'amazonaws.com', 'azurewebsites.net', 'cloudapp.net',
        'github.io', 'heroku.com', 'fastly.net', 'shopify.com',
        'zendesk.com', 'uservoice.com', 'surge.sh',
    ]
    for cname in cnames:
        for vuln in VULNERABLE_CNAMES:
            if vuln in cname:
                issues.append({
                    'type':        'subdomain_takeover_risk',
                    'severity':    'high',
                    'cvss_score':  8.1,
                    'cvss_vector': 'AV:N/AC:L/PR:N/UI:N/S:U/C:H/I:H/A:N',
                    'cve_id':      None,
                    'title':       f'Potential Subdomain Takeover — {host}',
                    'description': (
                        f'The subdomain `{host}` has a CNAME record pointing to `{cname}`, '
                        f'which appears to be an unclaimed or inactive third-party service. '
                        f'An attacker could register this service and serve malicious content '
                        f'under your domain.'
                    ),
                    'remediation': (
                        f'1. Verify whether the service at `{cname}` is still in use.\n'
                        f'2. If not, remove the CNAME DNS record for `{host}` immediately.\n'
                        f'3. If still needed, re-claim the resource on the third-party platform.'
                    ),
                })

    # ── Check 2: Sensitive subdomain names exposed ────────────────────
    SENSITIVE_NAMES = {
        'admin':    ('high',   7.5, 'Administrative interface publicly exposed.'),
        'staging':  ('medium', 5.3, 'Staging environment accessible from the internet.'),
        'dev':      ('medium', 5.3, 'Development environment accessible from the internet.'),
        'test':     ('medium', 4.8, 'Test environment accessible from the internet.'),
        'internal': ('high',   7.2, 'Internal subdomain exposed to public internet.'),
        'vpn':      ('medium', 6.1, 'VPN endpoint publicly discoverable.'),
        'backup':   ('high',   7.8, 'Backup system exposed to public internet.'),
        'jenkins':  ('critical', 9.1, 'CI/CD system exposed — high risk of code execution.'),
        'gitlab':   ('high',   8.2, 'Source code management system publicly accessible.'),
        'jira':     ('medium', 6.4, 'Project management tool exposed publicly.'),
    }

    subdomain_label = host.split('.')[0].lower()
    if subdomain_label in SENSITIVE_NAMES:
        sev, cvss, desc = SENSITIVE_NAMES[subdomain_label]
        issues.append({
            'type':        'sensitive_subdomain_exposed',
            'severity':    sev,
            'cvss_score':  cvss,
            'cvss_vector': 'AV:N/AC:L/PR:N/UI:N/S:U/C:L/I:N/A:N',
            'cve_id':      None,
            'title':       f'Sensitive Subdomain Exposed — {host}',
            'description': f'{desc} The subdomain `{host}` resolves to {", ".join(ips) or "unknown"}.',
            'remediation': (
                f'Restrict access to `{host}` via firewall rules or VPN. '
                f'If the subdomain is no longer needed, remove the DNS record.'
            ),
        })

    # ── Check 3: Internal IPs leaked via DNS ─────────────────────────
    for ip in ips:
        octets = ip.split('.')
        if len(octets) == 4:
            first, second = int(octets[0]), int(octets[1])
            is_private = (
                first == 10 or
                (first == 172 and 16 <= second <= 31) or
                (first == 192 and second == 168)
            )
            if is_private:
                issues.append({
                    'type':        'internal_ip_leaked',
                    'severity':    'medium',
                    'cvss_score':  5.3,
                    'cvss_vector': 'AV:N/AC:L/PR:N/UI:N/S:U/C:L/I:N/A:N',
                    'cve_id':      None,
                    'title':       f'Internal IP Address Leaked — {host}',
                    'description': (
                        f'The subdomain `{host}` resolves to the private IP `{ip}`. '
                        f'This reveals internal network topology to external attackers '
                        f'and may assist in lateral movement or targeted attacks.'
                    ),
                    'remediation': (
                        f'Remove the DNS record for `{host}` or update it to point to '
                        f'a public IP / load balancer. Internal services should not be '
                        f'resolvable from public DNS.'
                    ),
                })

    return {
        'host':    host,
        'ips':     ips,
        'cnames':  cnames,
        'alive':   bool(ips),
        'issues':  issues,
    }

def main():
    # ── Input validation ─────────────────────────────────────────────
    if len(sys.argv) < 2:
        print(json.dumps({'error': 'No target provided'}))
        sys.exit(1)

    target = sys.argv[1].strip().lower()
    target = target.replace('https://', '').replace('http://', '').split('/')[0]

    if not validate_domain(target):
        print(json.dumps({'error': f'Invalid domain: {target}'}))
        sys.exit(1)

    # ── Run enumeration ──────────────────────────────────────────────
    raw_subdomains = run_subfinder(target)

    if not raw_subdomains:
        print(json.dumps({
            'target':     target,
            'subdomains': [],
            'findings':   [],
            'stats':      {'total': 0, 'alive': 0, 'with_issues': 0},
            'scanned_at': datetime.now(timezone.utc).isoformat(),
        }))
        return

    # ── Resolve subdomains ───────────────────────────────────────────
    dns_results = run_dnsx(raw_subdomains)

    # Build lookup map: host → dns_data
    dns_map = {r.get('host', ''): r for r in dns_results}

    # ── Classify each subdomain ──────────────────────────────────────
    subdomains = []
    all_findings = []

    for sub in raw_subdomains:
        dns_data   = dns_map.get(sub, {'host': sub, 'a': [], 'cname': []})
        classified = classify_subdomain(sub, dns_data)
        subdomains.append(classified)
        all_findings.extend(classified['issues'])

    # ── Stats ────────────────────────────────────────────────────────
    alive_count  = sum(1 for s in subdomains if s['alive'])
    issues_count = sum(1 for s in subdomains if s['issues'])

    # ── Output JSON to stdout ────────────────────────────────────────
    output = {
        'target':     target,
        'subdomains': subdomains,
        'findings':   all_findings,
        'stats': {
            'total':       len(subdomains),
            'alive':       alive_count,
            'with_issues': issues_count,
        },
        'scanned_at': datetime.now(timezone.utc).isoformat(),
    }

    print(json.dumps(output, indent=2))

if __name__ == '__main__':
    main()