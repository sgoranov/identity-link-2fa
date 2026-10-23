<?php
declare(strict_types=1);

namespace App\Tests\Integration;

use App\DataFixtures\AppFixtures;
use App\Entity\AuthRequest;
use App\Entity\UserSecret;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use sgoranov\IdentityLinkShared\Security\User;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\RouterInterface;

final class ApiControllerTest extends WebTestCase
{
    public function testFetchAuthRequestSuccess(): void
    {
        $client = static::createClient();
        $testUser = new User('test_admin', ['2fa.read']);
        $client->loginUser($testUser);
        $container = $client->getContainer();
        $router = $container->get(RouterInterface::class);
        $entityManager = $container->get(EntityManagerInterface::class);

        $authRequest = $entityManager
            ->getRepository(AuthRequest::class)
            ->findOneBy(['userId' => AppFixtures::AUTH_USER_ID]);
        $this->assertNotNull($authRequest, sprintf('No AuthRequest found for user "%s".', AppFixtures::AUTH_USER_ID));

        $url = $router->generate('api_v1_fetch_auth_request', [
            'id' => $authRequest->getId(),
        ]);
        $client->request('GET', $url);
        $response = $client->getResponse();

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('auth', $data['response']);
    }

    public function testFetchAuthRequestNotFound(): void
    {
        $client = static::createClient();
        $testUser = new User('test_admin', ['2fa.read']);
        $client->loginUser($testUser);
        $router = $client->getContainer()->get(RouterInterface::class);

        $url = $router->generate('api_v1_fetch_auth_request', ['id' => 'd001a856-4db9-4482-be3e-0dacb3fe5567']);
        $client->request('GET', $url);

        $this->assertSame(Response::HTTP_NOT_FOUND, $client->getResponse()->getStatusCode());
    }

    public function testCreateAuthRequestSuccess(): void
    {
        $client = static::createClient();
        $testUser = new User('test_admin', ['2fa.write']);
        $client->loginUser($testUser);
        $router = $client->getContainer()->get(RouterInterface::class);

        $payload = [
            'userId' => 'user-456',
            'redirectUri' => 'https://example.com/callback'
        ];

        $url = $router->generate('api_v1_create_auth_request');

        $client->request(
            'POST',
            $url,
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($payload)
        );

        $response = $client->getResponse();

        $this->assertSame(Response::HTTP_CREATED, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('auth', $data['response']);
        $this->assertNull($data['response']['auth']['authenticated']);
    }

    public function testCreateAuthRequestExpiresEarlierPendingRequestsOfTheSameUser(): void
    {
        $client = static::createClient();
        $testUser = new User('test_admin', ['2fa.write']);
        $client->loginUser($testUser);
        $container = $client->getContainer();
        $router = $container->get(RouterInterface::class);
        $entityManager = $container->get(EntityManagerInterface::class);

        $earlierRequest = new AuthRequest();
        $earlierRequest->setUserId('user-456');
        $earlierRequest->setRedirectUri('https://example.com/callback');
        $earlierRequest->setCreated(new DateTime('-5 minutes'));
        $earlierRequest->setExpired(new DateTime('+25 minutes'));
        $earlierRequest->setAuthenticated(null);
        $entityManager->persist($earlierRequest);
        $entityManager->flush();

        $client->request(
            'POST',
            $router->generate('api_v1_create_auth_request'),
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['userId' => 'user-456', 'redirectUri' => 'https://example.com/callback'])
        );

        $this->assertSame(Response::HTTP_CREATED, $client->getResponse()->getStatusCode());

        $entityManager->clear();
        $now = new DateTime();
        $this->assertLessThanOrEqual($now, $entityManager->find(AuthRequest::class, $earlierRequest->getId())->getExpired());

        // Pending requests of other users are left alone
        $otherUserRequest = $entityManager
            ->getRepository(AuthRequest::class)
            ->findOneBy(['userId' => AppFixtures::AUTH_USER_ID]);
        $this->assertGreaterThan($now, $otherUserRequest->getExpired());
    }

    #[DataProvider('invalidAuthRequestProvider')]
    public function testCreateAuthRequestRejectsAnInvalidPayload(array $payload, string $invalidProperty): void
    {
        $client = static::createClient();
        $testUser = new User('test_admin', ['2fa.write']);
        $client->loginUser($testUser);
        $router = $client->getContainer()->get(RouterInterface::class);

        $client->request(
            'POST',
            $router->generate('api_v1_create_auth_request'),
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($payload)
        );

        $response = $client->getResponse();
        $this->assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertStringStartsWith(sprintf('Invalid %s.', $invalidProperty), $data['error']);
    }

    public static function invalidAuthRequestProvider(): iterable
    {
        $redirectUri = 'https://example.com/callback';

        yield 'missing userId' => [['redirectUri' => $redirectUri], 'userId'];
        yield 'userId longer than 50 characters' => [['userId' => str_repeat('u', 51), 'redirectUri' => $redirectUri], 'userId'];
        yield 'missing redirectUri' => [['userId' => 'user-456'], 'redirectUri'];
        yield 'empty redirectUri' => [['userId' => 'user-456', 'redirectUri' => ''], 'redirectUri'];
        yield 'redirectUri is not a URL' => [['userId' => 'user-456', 'redirectUri' => 'not a url'], 'redirectUri'];
        yield 'redirectUri with an FTP scheme' => [['userId' => 'user-456', 'redirectUri' => 'ftp://example.com/callback'], 'redirectUri'];
        yield 'redirectUri with a JavaScript scheme' => [['userId' => 'user-456', 'redirectUri' => 'javascript:alert(1)'], 'redirectUri'];
        yield 'redirectUri longer than 2048 characters' => [['userId' => 'user-456', 'redirectUri' => 'https://example.com/' . str_repeat('a', 2030)], 'redirectUri'];
    }

    public function testCreateAuthRequestAcceptsARedirectUriWithoutATopLevelDomain(): void
    {
        $client = static::createClient();
        $testUser = new User('test_admin', ['2fa.write']);
        $client->loginUser($testUser);
        $router = $client->getContainer()->get(RouterInterface::class);

        $client->request(
            'POST',
            $router->generate('api_v1_create_auth_request'),
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['userId' => 'user-456', 'redirectUri' => 'http://identity-link-bff:8080/login/2fa'])
        );

        $this->assertSame(Response::HTTP_CREATED, $client->getResponse()->getStatusCode());
    }

    public function testResetSecretOnNextAuthSuccess(): void
    {
        $client = static::createClient();
        $testUser = new User('test_admin', ['2fa.reset']);
        $client->loginUser($testUser);
        $container = $client->getContainer();
        $router = $container->get(RouterInterface::class);
        $entityManager = $container->get(EntityManagerInterface::class);

        $url = $router->generate('api_v1_reset_secret_on_next_auth', [
            'id' => AppFixtures::SECRET_USER_ID,
        ]);
        $client->request('PUT', $url);
        $response = $client->getResponse();

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['response']['result']);

        $entityManager->clear();
        $updatedSecret = $entityManager
            ->getRepository(UserSecret::class)
            ->findOneBy(['userId' => AppFixtures::SECRET_USER_ID]);

        $this->assertNotNull($updatedSecret);
        $this->assertTrue($updatedSecret->isResetSecretOnNextAuth());
    }

    public function testResetSecretOnNextAuthUserNotFound(): void
    {
        $client = static::createClient();
        $testUser = new User('test_admin', ['2fa.reset']);
        $client->loginUser($testUser);
        $router = $client->getContainer()->get(RouterInterface::class);

        $url = $router->generate('api_v1_reset_secret_on_next_auth', ['id' => 'unknown-user']);
        $client->request('PUT', $url);

        $this->assertSame(Response::HTTP_NOT_FOUND, $client->getResponse()->getStatusCode());
    }
}
