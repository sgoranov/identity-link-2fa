<?php
declare(strict_types=1);

namespace App\OpenApi;

use OpenApi\Attributes as OA;

// To generate OpenAPI YAML file run:
// ./vendor/bin/openapi --output docs/openapi.yaml src/
// This scans the `src/` directory for OpenAPI attributes and outputs the spec to docs/openapi.yaml
#[OA\OpenApi(
    info: new OA\Info(
        version: "1.0",
        description: "API documentation for Identity Link 2FA",
        title: "Identity Link 2FA API"
    ),
    servers: [
        new OA\Server(
            url: "/2fa",
            description: "2FA API base path"
        )
    ],
    security: [["bearerAuth" => []]],
    components: new OA\Components(
        securitySchemes: [
            new OA\SecurityScheme(
                securityScheme: "bearerAuth",
                type: "http",
                description: "JWT Bearer token authorization",
                bearerFormat: "JWT",
                scheme: "bearer"
            )
        ]
    )
)]
final class OpenApiInfo
{
}