<?php
declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\AuthRequest;
use App\Entity\UserSecret;
use DateTime;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\DependencyInjection\Attribute\When;

#[When(env: "test")]
class AppFixtures extends Fixture
{
    public const AUTH_USER_ID = 'user-123';
    public const SECRET_USER_ID = 'target-user-uuid';

    public function load(ObjectManager $manager): void
    {
        $authRequest = new AuthRequest();
        $authRequest->setRedirectUri('https://example.com/callback');
        $authRequest->setUserId(self::AUTH_USER_ID);
        $authRequest->setCreated(new DateTime());
        $authRequest->setExpired((new DateTime())->modify('+20 minutes'));
        $authRequest->setAuthenticated(null);
        $manager->persist($authRequest);

        $userSecret = new UserSecret();
        $userSecret->setUserId(self::SECRET_USER_ID);
        $userSecret->setSecret('super-secret-mock-token-string');
        $userSecret->setCreated(new \DateTime());
        $userSecret->setResetSecretOnNextAuth(false);
        $manager->persist($userSecret);

        $manager->flush();
    }
}