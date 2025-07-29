# Identity Link 2FA

![License](https://img.shields.io/github/license/sgoranov/identity-link-2fa)
![Last Commit](https://img.shields.io/github/last-commit/sgoranov/identity-link-2fa)
![Issues](https://img.shields.io/github/issues/sgoranov/identity-link-2fa)
[![Security Audit](https://github.com/sgoranov/identity-link-2fa/actions/workflows/vulnerability-scan.yml/badge.svg)](https://github.com/sgoranov/identity-link-2fa/actions/workflows/vulnerability-scan.yml)

This project provides Two-Factor Authentication (2FA) support for the 
Identity Link system using TOTP (Time-Based One-Time Passwords). 
It integrates seamlessly with authentication apps like Google 
Authenticator, Authy, and similar tools to enhance account security.

Users can link their accounts to a TOTP-compatible app and verify 
their identity through time-based codes in addition to standard 
credentials, ensuring stronger protection against unauthorized access.

## API Documentation

The OpenAPI specification is available at:

[docs/openapi.yaml](docs/openapi.yaml)

You can visualize it using:

- [Swagger Editor](https://editor.swagger.io/)
- Swagger UI (if using Docker)


## License

Identity Link is open source software licensed under the [MIT License](LICENSE), which permits reuse,
modification, and distribution with minimal restrictions.
