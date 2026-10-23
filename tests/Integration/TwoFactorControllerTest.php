<?php
declare(strict_types=1);

namespace App\Tests\Integration;

use App\Entity\AuthRequest;
use App\Entity\UserSecret;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use OTPHP\TOTP;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Clock\NativeClock;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

final class TwoFactorControllerTest extends WebTestCase
{
    private const USER_ID = 'two-factor-user';
    private const SECRET = 'JBSWY3DPEHPK3PXP';

    private KernelBrowser $client;

    protected function setUp(): void
    {
        // The session cookie is secure, so the browser only sends it back over HTTPS
        $this->client = static::createClient([], ['HTTPS' => 'on']);
    }

    #[DataProvider('pageProvider')]
    public function testPageRejectsAnUnknownAuthRequest(string $route): void
    {
        $this->client->request('GET', $this->url($route, 'd001a856-4db9-4482-be3e-0dacb3fe5567'));

        self::assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
    }

    #[DataProvider('pageProvider')]
    public function testPageRejectsAnExpiredAuthRequest(string $route): void
    {
        $authRequest = $this->createAuthRequest(expired: new DateTime('-1 minute'));

        $this->client->request('GET', $this->url($route, $authRequest->getId()));

        self::assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
    }

    public static function pageProvider(): iterable
    {
        yield 'verify' => ['2fa_verify'];
        yield 'enroll' => ['2fa_enroll'];
    }

    public function testVerifyRedirectsToEnrollWhenTheUserHasNoSecret(): void
    {
        $authRequest = $this->createAuthRequest();

        $this->client->request('GET', $this->url('2fa_verify', $authRequest->getId()));

        self::assertResponseRedirects($this->url('2fa_enroll', $authRequest->getId()));
    }

    public function testVerifyRedirectsToEnrollWhenAResetWasRequested(): void
    {
        $authRequest = $this->createAuthRequest();
        $this->createUserSecret(resetRequested: true);

        $this->client->request('GET', $this->url('2fa_verify', $authRequest->getId()));

        self::assertResponseRedirects($this->url('2fa_enroll', $authRequest->getId()));
    }

    #[DataProvider('redirectUriProvider')]
    public function testVerifyAcceptsAValidCode(string $redirectUri, string $separator): void
    {
        $authRequest = $this->createAuthRequest(redirectUri: $redirectUri);
        $this->createUserSecret();

        $this->submitCode($authRequest, $this->currentCode(self::SECRET));

        self::assertResponseRedirects($redirectUri . $separator . 'id=' . $authRequest->getId());
        self::assertNotNull($this->reload($authRequest)->getAuthenticated());
    }

    public static function redirectUriProvider(): iterable
    {
        yield 'without a query string' => ['https://example.com/callback', '?'];
        yield 'with a query string' => ['https://example.com/callback?state=abc', '&'];
    }

    public function testVerifyRejectsAnInvalidCode(): void
    {
        $authRequest = $this->createAuthRequest();
        $this->createUserSecret();
        $invalidCode = sprintf('%06d', ((int) $this->currentCode(self::SECRET) + 1) % 1000000);

        $this->submitCode($authRequest, $invalidCode);

        self::assertResponseRedirects($this->url('2fa_verify', $authRequest->getId()));
        $this->client->followRedirect();
        self::assertSelectorTextContains('.alert-danger', $this->translate('two_factor.invalid_code'));
        self::assertNull($this->reload($authRequest)->getAuthenticated());
    }

    public function testVerifyRejectsAForgedCsrfToken(): void
    {
        $authRequest = $this->createAuthRequest();
        $this->createUserSecret();

        $this->submitCode($authRequest, $this->currentCode(self::SECRET), 'forged-token');

        // The form is shown again, even though the code is valid
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('#tfa-form');
        self::assertNull($this->reload($authRequest)->getAuthenticated());
    }

    public function testEnrollCreatesASecretForANewUser(): void
    {
        $authRequest = $this->createAuthRequest();

        $this->client->request('GET', $this->url('2fa_enroll', $authRequest->getId()));

        self::assertResponseIsSuccessful();
        $userSecret = $this->findUserSecret();
        self::assertNotNull($userSecret);
        self::assertFalse($userSecret->isResetSecretOnNextAuth());
        self::assertSelectorTextSame('strong', $userSecret->getSecret());
    }

    public function testEnrollShowsTheSameSecretWhenReopenedWithTheSameAuthRequest(): void
    {
        $authRequest = $this->createAuthRequest();
        $url = $this->url('2fa_enroll', $authRequest->getId());

        $firstSecret = $this->client->request('GET', $url)->filter('strong')->text();
        $secondSecret = $this->client->request('GET', $url)->filter('strong')->text();

        self::assertResponseIsSuccessful();
        self::assertSame($firstSecret, $secondSecret);
    }

    public function testEnrollShowsTheExistingSecretWithinTenMinutesOfEnrolling(): void
    {
        $authRequest = $this->createAuthRequest();
        $this->createUserSecret(created: new DateTime('-9 minutes'));

        $this->client->request('GET', $this->url('2fa_enroll', $authRequest->getId()));

        self::assertResponseIsSuccessful();
        self::assertSelectorTextSame('strong', self::SECRET);
    }

    public function testEnrollRejectsReopeningMoreThanTenMinutesAfterEnrolling(): void
    {
        $authRequest = $this->createAuthRequest();
        $this->createUserSecret(created: new DateTime('-11 minutes'));

        $this->client->request('GET', $this->url('2fa_enroll', $authRequest->getId()));

        self::assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
        self::assertSame(self::SECRET, $this->findUserSecret()->getSecret());
    }

    public function testEnrollReplacesTheSecretWhenAResetWasRequested(): void
    {
        $authRequest = $this->createAuthRequest();
        $this->createUserSecret(resetRequested: true, created: new DateTime('-1 day'));

        $this->client->request('GET', $this->url('2fa_enroll', $authRequest->getId()));

        self::assertResponseIsSuccessful();
        $userSecret = $this->findUserSecret();
        self::assertNotSame(self::SECRET, $userSecret->getSecret());
        self::assertFalse($userSecret->isResetSecretOnNextAuth());
        self::assertSelectorTextSame('strong', $userSecret->getSecret());
    }

    public function testNewUserCanEnrollAndVerify(): void
    {
        $authRequest = $this->createAuthRequest();

        $this->client->request('GET', $this->url('2fa_verify', $authRequest->getId()));
        $secret = $this->client->followRedirect()->filter('strong')->text();
        $this->submitCode($authRequest, $this->currentCode($secret));

        self::assertResponseRedirects('https://example.com/callback?id=' . $authRequest->getId());
        self::assertNotNull($this->reload($authRequest)->getAuthenticated());
    }

    private function submitCode(AuthRequest $authRequest, string $code, ?string $csrfToken = null): void
    {
        $crawler = $this->client->request('GET', $this->url('2fa_verify', $authRequest->getId()));
        $form = $crawler->filter('#tfa-form')->form(['two_fa_secret[code]' => $code]);
        if ($csrfToken !== null) {
            $form['two_fa_secret[_token]'] = $csrfToken;
        }

        $this->client->submit($form);
    }

    private function currentCode(string $secret): string
    {
        $totp = TOTP::create($secret, clock: new NativeClock());

        // Wait for the next code if this one is about to expire, so that it is still valid when it is verified
        $expiresIn = $totp->expiresIn();
        if ($expiresIn <= 3) {
            sleep($expiresIn);
        }

        return $totp->now();
    }

    private function createAuthRequest(
        string $redirectUri = 'https://example.com/callback',
        ?DateTime $expired = null,
    ): AuthRequest {
        $authRequest = new AuthRequest();
        $authRequest->setUserId(self::USER_ID);
        $authRequest->setRedirectUri($redirectUri);
        $authRequest->setCreated(new DateTime());
        $authRequest->setExpired($expired ?? new DateTime('+30 minutes'));
        $authRequest->setAuthenticated(null);
        $this->save($authRequest);

        return $authRequest;
    }

    private function createUserSecret(bool $resetRequested = false, ?DateTime $created = null): void
    {
        $userSecret = new UserSecret();
        $userSecret->setUserId(self::USER_ID);
        $userSecret->setSecret(self::SECRET);
        $userSecret->setCreated($created ?? new DateTime());
        $userSecret->setResetSecretOnNextAuth($resetRequested);
        $this->save($userSecret);
    }

    private function save(object $entity): void
    {
        $entityManager = $this->entityManager();
        $entityManager->persist($entity);
        $entityManager->flush();
    }

    private function reload(AuthRequest $authRequest): AuthRequest
    {
        $entityManager = $this->entityManager();
        $entityManager->clear();

        return $entityManager->find(AuthRequest::class, $authRequest->getId());
    }

    private function findUserSecret(): ?UserSecret
    {
        $entityManager = $this->entityManager();
        $entityManager->clear();

        return $entityManager->getRepository(UserSecret::class)->findOneBy(['userId' => self::USER_ID]);
    }

    private function entityManager(): EntityManagerInterface
    {
        // Fetched on every use: the kernel is rebooted between requests, which replaces the entity manager
        return $this->client->getContainer()->get(EntityManagerInterface::class);
    }

    private function url(string $route, string $id): string
    {
        return $this->client->getContainer()->get(RouterInterface::class)->generate($route, ['id' => $id]);
    }

    private function translate(string $id): string
    {
        return $this->client->getContainer()->get(TranslatorInterface::class)->trans($id);
    }
}
