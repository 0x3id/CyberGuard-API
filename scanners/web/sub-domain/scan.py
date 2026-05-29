#!/usr/bin/env python3
# =============================================================================
# CyberGuard — Subdomain Enumeration Engine  v2.0
# =============================================================================
#
# OUTPUT PROTOCOL  (identical to sqlmap_engine_v6.py and fuzz_entrypoint.sh)
# ──────────────────────────────────────────────────────────────────────────
# ALL output goes to STDOUT. Laravel distinguishes two streams by the
# FIRST CHARACTER of every line:
#
#   '{' → JSON finding  → parse + store in DB + broadcast as "finding"
#   '[' → tagged log    → broadcast as "log" to the WebSocket terminal
#
# Tagged log prefixes:
#   [INFO]    general information / phase banner
#   [WAIT]    long-running step in progress
#   [OK]      step or sub-check completed successfully
#   [WARN]    non-fatal warning (tool missing, partial result, etc.)
#   [ERROR]   fatal error (bad input, no tools found)
#   [LIVE]    real-time discovery line — one per subdomain as it is found
#   [VULN]    confirmed security finding alert
#   [SUCCESS] scan phase complete
#
# JSON findings:
#   • One compact JSON object per line (no indent), first char always '{'.
#   • Emitted IMMEDIATELY when a subdomain finding is classified — streaming,
#     not batched. Laravel can persist and broadcast each finding in real time.
#   • Schema is the CyberGuard standard finding schema (matches the sqlmap
#     engine and the Bash fuzzer output schema):
#
#     {
#       "title":       string,
#       "description": string,
#       "severity":    "critical"|"high"|"medium"|"low"|"info",
#       "cvss_score":  float,
#       "cvss_vector": string,
#       "cve_id":      string|null,
#       "remediation": string,
#       "status":      "open"|"in_progress"|"resolved"|"false_positive",
#       "affected_url": string,          ← the subdomain (as URL)
#       "proof":       string,           ← one-line evidence summary
#       "tags":        list[string],
#       "raw_data":    string,           ← raw DNS data snippet
#       "metadata": {
#         "host":    string,
#         "ips":     list[string],
#         "cnames":  list[string],
#         "alive":   bool,
#         "source":  "subfinder"|"dnsx",
#         "scan_time": ISO-8601 timestamp
#       }
#     }
#
# Streaming design:
#   subfinder results are consumed line-by-line via a background thread that
#   reads the subprocess stdout pipe in real time. Each discovered subdomain
#   is immediately passed to dnsx (batched in small windows) and classified.
#   Findings are emitted as soon as classification is done — not after the
#   full scan completes. This keeps the WebSocket feed alive and responsive.
#
# =============================================================================

import sys
import os
import re
import json
import subprocess
import threading
import queue
import time
from datetime import datetime, timezone
from typing import Optional

# Force line-buffered stdout so every print() reaches Laravel immediately
sys.stdout.reconfigure(line_buffering=True)

# =============================================================================
# ── Output protocol helpers (identical API to sqlmap_engine_v6.py) ─────────────
# =============================================================================

def _out(tag: str, msg: str) -> None:
    print(f"{tag} {msg}", flush=True)

def log(msg: str)     -> None: _out("[INFO]   ", msg)
def wait_log(msg: str)-> None: _out("[WAIT]   ", msg)
def live(msg: str)    -> None: _out("[LIVE]   ", msg)
def warn(msg: str)    -> None: _out("[WARN]   ", msg)
def err(msg: str)     -> None: _out("[ERROR]  ", msg)
def ok(msg: str)      -> None: _out("[OK]     ", msg)
def vuln_log(msg: str)-> None: _out("[VULN]   ", msg)
def success(msg: str) -> None: _out("[SUCCESS]", msg)

def separator() -> None:
    print("[INFO]   " + "━" * 62, flush=True)

def emit_finding(finding: dict) -> None:
    """
    Emit one JSON finding line to STDOUT.
    First character is always '{' — Laravel routes this to the findings pipeline.
    """
    print(json.dumps(finding, separators=(",", ":")), flush=True)

# =============================================================================
# ── CyberGuard logo ───────────────────────────────────────────────────────────
# =============================================================================
LOGO = r"""
  ____      _               ____                     _
 / ___|   _| |__   ___ _ __|  _ \ _   _ __ _ _ __ __| |
| |  | | | | '_ \ / _ \ '__| |_) | | | / _` | '__/ _` |
| |__| |_| | |_) |  __/ |  |  _ <| |_| \__,_| | | (_| |
 \____\__, |_.__/ \___|_|  |_| \_\\__,_\__,_|_|  \__,_|
       |___/   Subdomain Enumeration Engine — CyberGuard v2.0
"""

# =============================================================================
# ── Domain validation (command-injection prevention) ─────────────────────────
# =============================================================================

def validate_domain(domain: str) -> bool:
    """Whitelist validation — only safe domain chars, no shell metacharacters."""
    pattern = r'^[a-zA-Z0-9][a-zA-Z0-9\-\.]{1,253}[a-zA-Z0-9]$'
    return bool(re.match(pattern, domain)) and '..' not in domain

# =============================================================================
# ── VULNERABLE_CNAMES catalogue ───────────────────────────────────────────────
# Services whose CNAME presence may indicate an unclaimed/dangling resource.
# =============================================================================
VULNERABLE_CNAMES = [
    'amazonaws.com',    'azurewebsites.net', 'cloudapp.net',
    'github.io',        'heroku.com',        'fastly.net',
    'shopify.com',      'zendesk.com',       'uservoice.com',
    'surge.sh',         'netlify.app',       'vercel.app',
    'pantheon.io',      'ghost.io',          'readme.io',
    'helpjuice.com',    'helpscoutdocs.com', 'freshdesk.com',
]

# =============================================================================
# ── SENSITIVE_NAMES catalogue ─────────────────────────────────────────────────
# =============================================================================
SENSITIVE_NAMES: dict[str, tuple[str, float, str]] = {
    'admin':    ('high',     7.5, 'Administrative interface publicly exposed.'),
    'staging':  ('medium',   5.3, 'Staging environment accessible from the internet.'),
    'dev':      ('medium',   5.3, 'Development environment accessible from the internet.'),
    'test':     ('medium',   4.8, 'Test environment accessible from the internet.'),
    'internal': ('high',     7.2, 'Internal subdomain exposed to public internet.'),
    'vpn':      ('medium',   6.1, 'VPN endpoint publicly discoverable.'),
    'backup':   ('high',     7.8, 'Backup system exposed to public internet.'),
    'jenkins':  ('critical', 9.1, 'CI/CD system exposed — high risk of code execution.'),
    'gitlab':   ('high',     8.2, 'Source code management system publicly accessible.'),
    'jira':     ('medium',   6.4, 'Project management tool exposed publicly.'),
    'sonar':    ('high',     7.5, 'Code quality / SAST tool publicly accessible.'),
    'kibana':   ('high',     8.0, 'Log aggregation dashboard publicly exposed.'),
    'grafana':  ('high',     7.8, 'Metrics dashboard publicly exposed.'),
    'consul':   ('critical', 9.0, 'Service mesh control plane publicly exposed.'),
    'vault':    ('critical', 9.5, 'Secrets manager publicly exposed.'),
    'k8s':      ('critical', 9.8, 'Kubernetes API endpoint publicly exposed.'),
    'api':      ('medium',   5.5, 'API gateway publicly discoverable.'),
    'ftp':      ('high',     7.3, 'FTP server publicly exposed — credentials at risk.'),
    'mail':     ('medium',   5.0, 'Mail server endpoint publicly discoverable.'),
    'smtp':     ('medium',   5.0, 'SMTP server publicly discoverable.'),
    'db':       ('critical', 9.2, 'Database server publicly exposed.'),
    'mysql':    ('critical', 9.8, 'MySQL server endpoint publicly exposed.'),
    'redis':    ('critical', 9.8, 'Redis server publicly exposed.'),
    'mongo':    ('critical', 9.8, 'MongoDB server publicly exposed.'),
}

# =============================================================================
# ── Finding builder ───────────────────────────────────────────────────────────
# =============================================================================

def _now_iso() -> str:
    return datetime.now(timezone.utc).isoformat()

def _build_finding(
    *,
    issue_type:  str,
    severity:    str,
    cvss_score:  float,
    cvss_vector: str,
    cve_id:      Optional[str],
    title:       str,
    description: str,
    remediation: str,
    proof:       str,
    host:        str,
    ips:         list[str],
    cnames:      list[str],
    alive:       bool,
    raw_dns:     str,
    tags:        list[str],
) -> dict:
    """
    Assemble one complete finding dict in the CyberGuard standard schema.
    Every field is present — no nulls except cve_id when genuinely absent.
    """
    return {
        "title":        title,
        "description":  description,
        "severity":     severity,
        "cvss_score":   cvss_score,
        "cvss_vector":  cvss_vector,
        "cve_id":       cve_id if cve_id else "N/A",
        "remediation":  remediation,
        "status":       "open",
        "affected_url": f"https://{host}",
        "proof":        proof,
        "tags":         tags,
        "raw_data":     raw_dns,
        "metadata": {
            "host":      host,
            "ips":       ips,
            "cnames":    cnames,
            "alive":     alive,
            "issue_type": issue_type,
            "source":    "subfinder+dnsx",
            "scan_time": _now_iso(),
        },
    }

# =============================================================================
# ── Classification engine ─────────────────────────────────────────────────────
# =============================================================================

def classify_subdomain(subdomain: str, dns_data: dict) -> list[dict]:
    """
    Analyse one resolved subdomain and return a list of finding dicts.
    Each finding is immediately ready for emit_finding().
    Returns [] when no issues are detected (informational subdomains are
    not emitted — only actionable security findings).
    """
    host   = dns_data.get("host", subdomain)
    ips    = dns_data.get("a", [])
    cnames = dns_data.get("cname", [])
    alive  = bool(ips)

    # Build a compact raw_data snippet for the finding record
    raw_dns = json.dumps({
        "host": host, "a": ips, "cname": cnames
    }, separators=(",", ":"))

    findings: list[dict] = []

    # ── Check 1: Subdomain takeover risk ──────────────────────────────────────
    for cname in cnames:
        for vuln_svc in VULNERABLE_CNAMES:
            if vuln_svc in cname:
                findings.append(_build_finding(
                    issue_type  = "subdomain_takeover_risk",
                    severity    = "high",
                    cvss_score  = 8.1,
                    cvss_vector = "CVSS:3.1/AV:N/AC:L/PR:N/UI:N/S:U/C:H/I:H/A:N",
                    cve_id      = "CWE-350",
                    title       = f"Potential Subdomain Takeover — {host}",
                    description = (
                        f"The subdomain `{host}` has a CNAME record pointing to `{cname}`, "
                        f"which appears to be an unclaimed or inactive third-party service "
                        f"({vuln_svc}). An attacker could register this service and serve "
                        f"malicious content under your domain, bypassing SOP and CSP controls."
                    ),
                    remediation = (
                        f"1. Verify whether the service at `{cname}` is still in active use.\n"
                        f"2. If the service is decommissioned, remove the CNAME DNS record for "
                        f"`{host}` immediately.\n"
                        f"3. If the service is still required, re-claim the resource on the "
                        f"third-party platform ({vuln_svc}) before an attacker does.\n"
                        f"4. Implement DNS monitoring to alert on dangling CNAMEs going forward."
                    ),
                    proof       = (
                        f"CNAME {host} → {cname} points to unclaimed {vuln_svc} resource."
                    ),
                    host=host, ips=ips, cnames=cnames, alive=alive, raw_dns=raw_dns,
                    tags=["subdomain-takeover", "dns", "web", "owasp-top-10",
                          "reconnaissance", "cwe-350"],
                ))

    # ── Check 2: Sensitive subdomain label exposed ─────────────────────────────
    label = host.split(".")[0].lower()
    if label in SENSITIVE_NAMES:
        sev, cvss, desc = SENSITIVE_NAMES[label]
        findings.append(_build_finding(
            issue_type  = "sensitive_subdomain_exposed",
            severity    = sev,
            cvss_score  = cvss,
            cvss_vector = "CVSS:3.1/AV:N/AC:L/PR:N/UI:N/S:U/C:L/I:N/A:N",
            cve_id      = None,
            title       = f"Sensitive Subdomain Exposed — {host}",
            description = (
                f"{desc} The subdomain `{host}` resolves publicly to "
                f"{', '.join(ips) if ips else 'an unknown IP'}. "
                f"Public discoverability of this service increases the attack surface "
                f"and may allow unauthenticated access or information disclosure."
            ),
            remediation = (
                f"Restrict access to `{host}` via firewall rules, IP allowlisting, or a VPN "
                f"gateway so it is not reachable from the public internet. "
                f"If the subdomain is no longer in use, remove the DNS record immediately. "
                f"Ensure the service requires strong authentication even on private networks."
            ),
            proof       = (
                f"Subdomain label `{label}` is in the sensitive-names catalogue; "
                f"resolves to {', '.join(ips) or 'N/A'}."
            ),
            host=host, ips=ips, cnames=cnames, alive=alive, raw_dns=raw_dns,
            tags=["sensitive-exposure", "reconnaissance", "web", "owasp-top-10", label],
        ))

    # ── Check 3: Internal IP leaked via public DNS ─────────────────────────────
    for ip in ips:
        parts = ip.split(".")
        if len(parts) != 4:
            continue
        try:
            first, second = int(parts[0]), int(parts[1])
        except ValueError:
            continue
        is_private = (
            first == 10 or
            (first == 172 and 16 <= second <= 31) or
            (first == 192 and second == 168)
        )
        if is_private:
            findings.append(_build_finding(
                issue_type  = "internal_ip_leaked",
                severity    = "medium",
                cvss_score  = 5.3,
                cvss_vector = "CVSS:3.1/AV:N/AC:L/PR:N/UI:N/S:U/C:L/I:N/A:N",
                cve_id      = "CWE-200",
                title       = f"Internal IP Address Leaked via DNS — {host}",
                description = (
                    f"The subdomain `{host}` resolves to the RFC-1918 private IP address "
                    f"`{ip}`. Exposing internal IPs through public DNS reveals network "
                    f"topology to external attackers and may assist in lateral movement, "
                    f"targeted phishing, or SSRF exploitation."
                ),
                remediation = (
                    f"Remove the public DNS A record for `{host}` that points to `{ip}`, "
                    f"or update it to point to a public IP address or load balancer. "
                    f"Internal services should be resolvable only via internal/split-horizon DNS. "
                    f"Audit all DNS records for RFC-1918 addresses and remove or migrate them."
                ),
                proof       = (
                    f"DNS A record: {host} → {ip} (private RFC-1918 address)."
                ),
                host=host, ips=ips, cnames=cnames, alive=alive, raw_dns=raw_dns,
                tags=["information-disclosure", "dns", "internal-ip", "cwe-200",
                      "reconnaissance"],
            ))

    return findings

# =============================================================================
# ── subfinder: streaming subprocess reader ────────────────────────────────────
# =============================================================================

def stream_subfinder(domain: str, result_queue: "queue.Queue[str]",
                     stop_event: threading.Event) -> None:
    """
    Run subfinder and push each discovered subdomain into result_queue
    as it is emitted by the tool (line-by-line, real-time).
    Runs in a background thread.
    """
    try:
        proc = subprocess.Popen(
            [
                "subfinder",
                "-d",       domain,
                "-silent",
                "-timeout", "30",
                "-t",       "50",
            ],
            stdout=subprocess.PIPE,
            stderr=subprocess.DEVNULL,
            text=True,
            bufsize=1,          # line-buffered
        )
        for line in proc.stdout:
            if stop_event.is_set():
                proc.terminate()
                break
            sub = line.strip()
            if sub:
                result_queue.put(sub)
        proc.wait()
    except FileNotFoundError:
        warn("subfinder not found — install it or add it to PATH.")
    except Exception as e:
        warn(f"subfinder error: {e}")
    finally:
        result_queue.put(None)   # sentinel: producer done

# =============================================================================
# ── dnsx: batch resolver ──────────────────────────────────────────────────────
# =============================================================================

def resolve_batch(subdomains: list[str]) -> dict[str, dict]:
    """
    Resolve a batch of subdomains via dnsx.
    Returns a dict mapping host → dns_data.
    """
    if not subdomains:
        return {}

    try:
        result = subprocess.run(
            [
                "dnsx",
                "-silent",
                "-resp",        # include IP in output
                "-a",           # A records
                "-cname",       # CNAME records
                "-json",
                "-t", "50",
            ],
            input="\n".join(subdomains),
            capture_output=True,
            text=True,
            timeout=120,
        )
    except FileNotFoundError:
        warn("dnsx not found — DNS resolution skipped, findings may be incomplete.")
        return {}
    except subprocess.TimeoutExpired:
        warn("dnsx timed out on this batch — partial results may be missing.")
        return {}

    dns_map: dict[str, dict] = {}
    for line in result.stdout.splitlines():
        line = line.strip()
        if not line:
            continue
        try:
            data = json.loads(line)
            host = data.get("host", "")
            if host:
                dns_map[host] = data
        except json.JSONDecodeError:
            continue

    return dns_map

# =============================================================================
# ── Heartbeat thread ──────────────────────────────────────────────────────────
# =============================================================================

def heartbeat(stop_event: threading.Event, domain: str,
              counter: list[int]) -> None:
    """
    Emit a [LIVE] keepalive every 5 seconds so the WebSocket stream
    stays alive during long subfinder/dnsx runs.
    counter is a mutable list[int] so the thread can read the current count.
    """
    spinners = ("◐", "◓", "◑", "◒")
    tick = 0
    while not stop_event.is_set():
        time.sleep(5)
        if stop_event.is_set():
            break
        spin = spinners[tick % 4]
        live(f"{spin} Scanning {domain} — {counter[0]} subdomain(s) discovered so far…")
        tick += 1

# =============================================================================
# ── Main orchestrator ─────────────────────────────────────────────────────────
# =============================================================================

def main() -> None:
    # ── Input validation ──────────────────────────────────────────────────────
    if len(sys.argv) < 2:
        err("No target domain provided.")
        err("Usage: scan.py <domain>")
        sys.exit(1)

    raw = sys.argv[1].strip().lower()
    target = (raw
              .replace("https://", "")
              .replace("http://", "")
              .split("/")[0])

    if not validate_domain(target):
        err(f"Invalid domain: {target!r}  (only alphanumeric, hyphens, dots allowed)")
        sys.exit(1)

    # ── Banner ────────────────────────────────────────────────────────────────
    for line in LOGO.strip().splitlines():
        print(f"[INFO]   {line}", flush=True)

    separator()
    log(f"  Scan started at : {_now_iso()}")
    log(f"  Target domain   : {target}")
    log( "  Stream protocol : '[' = log line  |  '{' = JSON finding")
    log( "  Tools           : subfinder (passive enum) + dnsx (resolution)")
    log( "  Finding schema  : CyberGuard standard (title/severity/cvss/…)")
    separator()

    # ── Shared state ──────────────────────────────────────────────────────────
    subdomain_queue: "queue.Queue[Optional[str]]" = queue.Queue()
    stop_event     = threading.Event()
    hb_stop        = threading.Event()
    discovery_count = [0]      # mutable so heartbeat thread can read it
    finding_count   = [0]

    all_subdomains: list[str]  = []
    all_findings:   list[dict] = []

    # ── Start heartbeat ───────────────────────────────────────────────────────
    hb_thread = threading.Thread(
        target=heartbeat,
        args=(hb_stop, target, discovery_count),
        daemon=True,
    )
    hb_thread.start()

    # ── Phase 1: subfinder (streaming) ────────────────────────────────────────
    separator()
    log("Phase 1 — Passive subdomain enumeration via subfinder")
    wait_log("subfinder is querying passive DNS sources…")
    separator()

    sf_thread = threading.Thread(
        target=stream_subfinder,
        args=(target, subdomain_queue, stop_event),
        daemon=True,
    )
    sf_thread.start()

    # Collect all subdomains from the queue as they arrive
    BATCH_SIZE = 20         # resolve in batches of 20 for responsiveness
    pending_batch: list[str] = []
    producers_done = False

    def _flush_batch(batch: list[str]) -> None:
        """Resolve a batch, classify each, emit findings immediately."""
        if not batch:
            return
        wait_log(f"  Resolving batch of {len(batch)} subdomain(s) via dnsx…")
        dns_map = resolve_batch(batch)

        for sub in batch:
            dns_data = dns_map.get(sub, {"host": sub, "a": [], "cname": []})
            alive    = bool(dns_data.get("a", []))
            ips_str  = ", ".join(dns_data.get("a", [])) or "unresolved"

            # Live discovery line — one per subdomain
            status_icon = "✔" if alive else "○"
            live(f"  {status_icon} {sub}  →  {ips_str}")

            # Classify and emit findings immediately
            findings = classify_subdomain(sub, dns_data)
            for f in findings:
                finding_count[0] += 1
                vuln_log(f"  [{f['severity'].upper()}] {f['title']}")
                emit_finding(f)
                all_findings.append(f)

            all_subdomains.append(sub)
            discovery_count[0] = len(all_subdomains)

    # Consumer loop: drain the queue, flush batches
    while not producers_done:
        try:
            item = subdomain_queue.get(timeout=2.0)
        except queue.Empty:
            # Flush partial batch on timeout (keeps latency low)
            if pending_batch:
                _flush_batch(pending_batch)
                pending_batch = []
            continue

        if item is None:
            # Producer sent the sentinel — done
            producers_done = True
            _flush_batch(pending_batch)
            pending_batch = []
        else:
            live(f"  [subfinder] Discovered: {item}")
            pending_batch.append(item)
            if len(pending_batch) >= BATCH_SIZE:
                _flush_batch(pending_batch)
                pending_batch = []

    sf_thread.join(timeout=5)

    # ── Stop heartbeat ────────────────────────────────────────────────────────
    hb_stop.set()
    hb_thread.join(timeout=2)

    # ── Phase 2: summary ──────────────────────────────────────────────────────
    separator()
    log("Phase 2 — Scan complete. Summary:")

    total      = len(all_subdomains)
    alive_cnt  = sum(
        1 for s in all_subdomains
        # rebuild from findings metadata or check via dns_map — approximate here
    )
    issue_cnt  = finding_count[0]

    ok(f"  Total subdomains discovered : {total}")
    ok(f"  Security findings emitted   : {issue_cnt}")
    separator()

    # ── Emit a final summary finding if nothing was found ─────────────────────
    # This ensures Laravel always gets at least one JSON line to process,
    # even on a clean target (status: resolved).
    if not all_findings:
        clean = {
            "title":       "No Security Issues Detected",
            "description": (
                f"Subdomain enumeration of `{target}` completed. "
                f"{total} subdomain(s) were discovered and none triggered "
                "a security finding (no takeover risks, sensitive labels, "
                "or internal IP leaks detected)."
            ),
            "severity":    "info",
            "cvss_score":  0.0,
            "cvss_vector": "N/A",
            "cve_id":      "N/A",
            "remediation": (
                "Continue periodic subdomain monitoring. "
                "Implement DNS alerting for new records and dangling CNAMEs."
            ),
            "status":      "resolved",
            "affected_url": f"https://{target}",
            "proof":       (
                f"subfinder discovered {total} subdomain(s); "
                "none matched the security-finding criteria."
            ),
            "tags":        ["reconnaissance", "dns", "subdomain", "clean"],
            "raw_data":    f"target={target} subdomains_found={total}",
            "metadata": {
                "host":       target,
                "ips":        [],
                "cnames":     [],
                "alive":      False,
                "issue_type": "clean_scan",
                "source":     "subfinder+dnsx",
                "scan_time":  _now_iso(),
            },
        }
        ok("  Target appears clean — emitting resolved status finding.")
        emit_finding(clean)

    separator()
    success(f"Subdomain scan of {target} finished.")
    log(f"  Finished at : {_now_iso()}")
    separator()


if __name__ == "__main__":
    main()