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
#[OA\PathItem(
    path: "/api/v1/ping",
    get: new OA\Get(
        description: "Returns 'pong' if the service is alive.",
        summary: "Health check endpoint",
        tags: ["Health"],
        responses: [
            new OA\Response(
                response: 200,
                description: "Pong response",
                content: new OA\JsonContent(
                    type: "string",
                    example: "pong"
                )
            )
        ]
    )
)]
final class OpenApiInfo
{
}