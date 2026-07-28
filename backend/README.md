# Public backend v1.4.0

The backend is installed on the customer’s FreePBX/Asterisk host. It accepts
authenticated JSON calls over HTTPS and submits a restricted AMI `Originate`
action through loopback.

No database or external SaaS connection is required.

Start with:

- [`../docs/INSTALLATION-GR.md`](../docs/INSTALLATION-GR.md)
- [`../docs/INSTALLATION-EN.md`](../docs/INSTALLATION-EN.md)
- [`config/config.php.example`](config/config.php.example)

The installer does not modify AMI, Asterisk dialplan, firewall, TLS or web-server
configuration automatically.
