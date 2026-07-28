# Security policy

## Supported release

Security fixes are provided for the latest public release only.

## Reporting

Do not open a public issue for a suspected vulnerability. Use a private GitHub
security advisory in this repository and include:

- affected version;
- deployment type and PHP/Asterisk versions;
- minimal reproduction steps;
- impact;
- request IDs or redacted logs, with all secrets removed.

Never attach API secrets, AMI credentials, private keys, call destinations or
customer URLs to a public issue.

## Deployment boundary

Each customer operates their own backend and remains responsible for TLS,
firewall policy, credential rotation, log retention, PBX authorization and
extension assignment.

Required controls:

- expose the PHP endpoints through HTTPS only;
- keep AMI on loopback unless a documented restricted network is unavoidable;
- use one API client per person or managed device;
- allow only the user’s real extension and required protocol;
- use a fail-closed outbound number policy;
- keep `config.php` outside the web root with mode `0640`;
- run a supported PHP 8.x release;
- rotate secrets after any disclosure.

The browser API secret is not recoverable from the server configuration because
only its SHA-256 digest is stored there. The clear-text secret still grants call
origination and must be treated like a password.
