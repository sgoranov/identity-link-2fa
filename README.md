# Identity Link 2FA

![License](https://img.shields.io/github/license/sgoranov/identity-link-2fa)
![Issues](https://img.shields.io/github/issues/sgoranov/identity-link-2fa)
[![PHPUnit Tests](https://github.com/sgoranov/identity-link-2fa/actions/workflows/phpunit.yml/badge.svg)](https://github.com/sgoranov/identity-link-2fa/actions/workflows/phpunit.yml)
[![Security Audit](https://github.com/sgoranov/identity-link-2fa/actions/workflows/vulnerability-scan.yml/badge.svg)](https://github.com/sgoranov/identity-link-2fa/actions/workflows/vulnerability-scan.yml)

The two-factor authentication service of the Identity Link system. Users verify their identity with time-based
one-time passwords (TOTP) from an authenticator app such as Google Authenticator or Authy. Other Identity Link
services use its REST API to start a verification and to check its result, and the service serves the pages where
users enroll their app and enter their codes.

## Contents

- [Domain model](#domain-model)
- [Running the service](#running-the-service)
- [Configuration](#configuration)
- [Authentication and scopes](#authentication-and-scopes)
- [Verification flow](#verification-flow)
- [OpenAPI specification](#openapi-specification)
- [Running the tests](#running-the-tests)
- [License](#license)

## Domain model

| Entity           | Description                                                                                                                                         |
|------------------|-----------------------------------------------------------------------------------------------------------------------------------------------------|
| **Auth request** | One verification for one user: the user ID, the URL to return to afterwards, when it expires, and when the user entered a valid code, if they have. |
| **User secret**  | A user's TOTP secret, and whether the user must enroll again the next time they verify. Each user has at most one.                                 |

Users are identified by the `userId` the caller passes in. The service does not look users up anywhere else.

## Running the service

### With Docker Compose (recommended)

The service is designed to run as part of the full Identity Link stack. See
[identity-link-docker](https://github.com/sgoranov/identity-link-docker) for Compose files that start it
together with PostgreSQL, Redis, and the other Identity Link services, for both production and local development.

## Configuration

The configuration variables and their defaults are described in the [.env](.env) file. Credentials are read from
[Docker secrets](https://docs.docker.com/compose/how-tos/use-secrets/) when the secret files exist; otherwise
the fallback environment variables `APP_SECRET`, `DB_USER`, and `DB_PASSWORD` are used.

Besides PostgreSQL, the service needs Redis (`REDIS_DSN`) for its sessions. `TWO_FA_ISSUER` is the name the
authenticator app shows next to the user's account.

Logs are written to stderr; in `prod` they are formatted as JSON. When a request has an `X-Request-Id` header,
every line logged while handling it includes the header's value as `extra.request_id`.

## Authentication and scopes

Every endpoint under `/api` except `/api/v1/ping` and `/api/v1/openapi.yaml` requires a JWT bearer token:

```
Authorization: Bearer <access token>
```

Tokens are verified against the keys published at `JWT_JWKS_URI`, and both the issuer and the audience must
equal `JWT_URL`. The token's space-separated `scope` claim must contain the scope the endpoint requires;
otherwise the request is rejected with `403 Forbidden`.

| Scope       | Grants                                                                                         |
|-------------|------------------------------------------------------------------------------------------------|
| `2fa.read`  | Fetch an auth request with `GET /api/v1/auth/{id}`.                                            |
| `2fa.write` | Create an auth request with `POST /api/v1/auth`.                                               |
| `2fa.reset` | Make a user enroll again on their next verification with `PUT /api/v1/user/{id}/reset-secret`. |

## Verification flow

1. The caller creates an auth request with `POST /api/v1/auth`, passing the `userId` and a `redirectUri`. The
   request is valid for 30 minutes, and creating it expires any earlier pending requests for the same user.
2. The caller sends the user to `/2fa/verify/{id}`, where `{id}` is the auth request's ID. These pages are
   public and need no token. An unknown or expired ID returns `400 Bad Request`.
3. If the user has no secret yet, or a reset was requested, they are redirected to `/2fa/enroll/{id}`. That page
   creates a new secret and shows it as a QR code and as text, labelled with the user ID and `TWO_FA_ISSUER`.
   It can be reopened while that auth request is valid, and from auth requests created within 10 minutes of
   enrolling; after that, the user must be reset to enroll again.
4. The user enters the six-digit code from their app. If the code is valid, the auth request is marked as
   authenticated and the user is redirected to `redirectUri` with `id=<auth request ID>` added to its query string.
   Otherwise, the page shows an error and asks again.
5. The caller fetches the auth request with `GET /api/v1/auth/{id}`. Its `authenticated` field holds the time the
   user entered a valid code, or `null` if they have not.

The pages are translated into English and Bulgarian. Translations in `local_theme/translations/` override those in
`translations/`.

## OpenAPI specification

All endpoints, with their request and response schemas, are described in the OpenAPI specification at
[docs/openapi.yaml](docs/openapi.yaml). You can browse it with [Swagger Editor](https://editor.swagger.io/).
In the `dev` environment the service also serves it at `/api/v1/openapi.yaml`.

The specification is generated from the OpenAPI attributes in `src/`. After changing an endpoint, regenerate it with:

```bash
vendor/bin/openapi --output docs/openapi.yaml src/
```

## Running the tests

The test suite runs in Docker against a disposable PostgreSQL instance, so the only requirement is Docker with
the Compose plugin.

```bash
bin/test-run
```

This builds the `test` image, runs PHPUnit, and removes the containers and volumes afterwards. The script exits with
PHPUnit's exit code, and CI runs the same script on every push and pull request to `develop`.

To debug tests interactively, open a shell in the test container with the project directory mounted at `/app`:

```bash
bin/test-debug
# inside the container:
vendor/bin/phpunit --filter ApiControllerTest
```

Xdebug is installed in the test image.

## License

Identity Link is open source software licensed under the [MIT License](LICENSE), which permits reuse,
modification, and distribution with minimal restrictions.
