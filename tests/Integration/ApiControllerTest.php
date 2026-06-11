<?php
declare(strict_types=1);

namespace App\Tests\Integration;

use App\DataFixtures\AppFixtures;
use App\Entity\UserSecret;
use Doctrine\ORM\EntityManagerInterface;
use sgoranov\IdentityLinkShared\Security\User;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\RouterInterface;

final class ApiControllerTest extends WebTestCase
{
    public function testFetchAuthRequestSuccess(): void
    {
        $client = static::createClient();
        $testUser = new User('test_admin', ['ROLE_ADMIN']);
        $client->loginUser($testUser);
        $container = $client->getContainer();
        $router = $container->get(RouterInterface::class);
        $entityManager = $container->get(EntityManagerInterface::class);

        $authRequest = $entityManager
            ->getRepository(\App\Entity\AuthRequest::class)
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
        $testUser = new User('test_admin', ['ROLE_ADMIN']);
        $client->loginUser($testUser);
        $router = $client->getContainer()->get(RouterInterface::class);

        $url = $router->generate('api_v1_fetch_auth_request', ['id' => 'd001a856-4db9-4482-be3e-0dacb3fe5567']);
        $client->request('GET', $url);

        $this->assertSame(Response::HTTP_NOT_FOUND, $client->getResponse()->getStatusCode());
    }

    public function testCreateAuthRequestSuccess(): void
    {
        $client = static::createClient();
        $testUser = new User('test_admin', ['ROLE_ADMIN']);
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

    public function testResetSecretOnNextAuthSuccess(): void
    {
        $client = static::createClient();
        $testUser = new User('test_admin', ['ROLE_ADMIN']);
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
        $testUser = new User('test_admin', ['ROLE_ADMIN']);
        $client->loginUser($testUser);
        $router = $client->getContainer()->get(RouterInterface::class);

        $url = $router->generate('api_v1_reset_secret_on_next_auth', ['id' => 'unknown-user']);
        $client->request('PUT', $url);

        $this->assertSame(Response::HTTP_NOT_FOUND, $client->getResponse()->getStatusCode());
    }
}