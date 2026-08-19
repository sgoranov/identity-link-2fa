<?php
declare(strict_types=1);

namespace App\Tests\Integration;

use PHPUnit\Framework\Attributes\DataProvider;
use sgoranov\IdentityLinkShared\Security\User;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class ScopeAuthorizationTest extends WebTestCase
{
    #[DataProvider('scopeProtectedEndpointProvider')]
    public function testEndpointAllowsTheRequiredScope(
        string $method,
        string $path,
        string $requiredScope,
        int $expectedStatus,
    ): void {
        $client = static::createClient();
        $client->loginUser(new User('test', [$requiredScope]));

        $client->request($method, $path);

        self::assertResponseStatusCodeSame($expectedStatus);
    }

    #[DataProvider('scopeProtectedEndpointProvider')]
    public function testEndpointRejectsAnAuthenticatedUserWithoutTheRequiredScope(
        string $method,
        string $path,
    ): void {
        $client = static::createClient();
        $client->loginUser(new User('test', ['2fa.unrelated']));

        $client->request($method, $path);

        self::assertResponseStatusCodeSame(403);
    }

    public function testApiRejectsAnonymousRequests(): void
    {
        $client = static::createClient();

        $client->request('POST', '/api/v1/auth');

        self::assertResponseStatusCodeSame(401);
    }

    public function testLegacyAdminRoleDoesNotBypassScopeChecks(): void
    {
        $client = static::createClient();
        $client->loginUser(new User('test', ['ROLE_ADMIN']));

        $client->request('POST', '/api/v1/auth');

        self::assertResponseStatusCodeSame(403);
    }

    public static function scopeProtectedEndpointProvider(): iterable
    {
        yield 'read auth request' => [
            'GET',
            '/api/v1/auth/d001a856-4db9-4482-be3e-0dacb3fe5567',
            '2fa.read',
            404,
        ];
        yield 'create auth request' => ['POST', '/api/v1/auth', '2fa.write', 400];
        yield 'reset user secret' => ['PUT', '/api/v1/user/unknown-user/reset-secret', '2fa.reset', 404];
    }
}
