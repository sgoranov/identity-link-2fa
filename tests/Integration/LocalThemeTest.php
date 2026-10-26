<?php
declare(strict_types=1);

namespace App\Tests\Integration;

use App\Entity\AuthRequest;
use App\Entity\UserSecret;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Yaml\Yaml;

final class LocalThemeTest extends WebTestCase
{
    // The base template's header has its own h1, so select the one in the page content
    private const PAGE_HEADING = '.py-4 h1';

    /** @var list<string> Files and directories created by the test, removed afterwards */
    private array $createdPaths = [];

    private ?string $cacheDir = null;

    protected function tearDown(): void
    {
        parent::tearDown();

        $filesystem = new Filesystem();
        $filesystem->remove($this->createdPaths);

        if ($this->cacheDir !== null) {
            unset($_SERVER['APP_CACHE_DIR']);
            $filesystem->remove($this->cacheDir);
        }
    }

    public function testPagesUseTheDefaultTemplatesWithoutALocalTheme(): void
    {
        $this->skipIfThemeFileExists('templates/two_factor/verify.html.twig');

        $this->requestVerifyPage();

        self::assertSelectorExists('#tfa-form');
    }

    public function testTemplatesInLocalThemeOverrideTheDefaults(): void
    {
        $this->createThemeFile(
            'templates/two_factor/verify.html.twig',
            "{% extends 'base.html.twig' %}{% block content %}<h1>Local theme template</h1>{% endblock %}",
        );

        $this->requestVerifyPage();

        self::assertSelectorTextSame(self::PAGE_HEADING, 'Local theme template');
        self::assertSelectorNotExists('#tfa-form');
    }

    public function testPagesUseTheDefaultTranslationsWithoutALocalTheme(): void
    {
        $this->skipIfThemeFileExists('translations/messages.en.yaml');

        $this->requestVerifyPage();

        self::assertSelectorTextSame(self::PAGE_HEADING, $this->defaultTranslation('title'));
    }

    public function testTranslationsInLocalThemeOverrideTheDefaults(): void
    {
        $this->createThemeFile('translations/messages.en.yaml', "two_factor:\n  title: Local theme title\n");
        $this->useFreshCache();

        $this->requestVerifyPage();

        self::assertSelectorTextSame(self::PAGE_HEADING, 'Local theme title');
        // Keys the theme does not override keep their default text
        self::assertAnySelectorTextContains('p', $this->defaultTranslation('instructions'));
    }

    public function testPublicThemeDirectoryIsLinkedFromThePublicDirectory(): void
    {
        $link = $this->projectDir() . '/public/theme';

        self::assertTrue(is_link($link), 'public/theme must be a symbolic link');
        self::assertSame(realpath($this->projectDir() . '/local_theme/public'), realpath($link));
    }

    private function requestVerifyPage(): void
    {
        $client = static::createClient();
        $entityManager = $client->getContainer()->get(EntityManagerInterface::class);

        $authRequest = new AuthRequest();
        $authRequest->setUserId('local-theme-user');
        $authRequest->setRedirectUri('https://example.com/callback');
        $authRequest->setCreated(new DateTime());
        $authRequest->setExpired(new DateTime('+30 minutes'));
        $authRequest->setAuthenticated(null);
        $entityManager->persist($authRequest);

        $userSecret = new UserSecret();
        $userSecret->setUserId('local-theme-user');
        $userSecret->setSecret('JBSWY3DPEHPK3PXP');
        $userSecret->setCreated(new DateTime());
        $userSecret->setResetSecretOnNextAuth(false);
        $entityManager->persist($userSecret);
        $entityManager->flush();

        $router = $client->getContainer()->get(RouterInterface::class);
        $client->request('GET', $router->generate('2fa_verify', ['id' => $authRequest->getId()]));

        self::assertResponseIsSuccessful();
    }

    private function createThemeFile(string $path, string $contents): void
    {
        $file = $this->projectDir() . '/local_theme/' . $path;
        // Never overwrite a real theme, for example when running the tests with bin/test-debug
        $this->skipIfThemeFileExists($path);

        // Remember the topmost directory this creates, so that removing it also removes the file
        $created = $file;
        for ($dir = dirname($file); !is_dir($dir); $dir = dirname($dir)) {
            $created = $dir;
        }
        $this->createdPaths[] = $created;

        (new Filesystem())->dumpFile($file, $contents);
    }

    private function skipIfThemeFileExists(string $path): void
    {
        if (file_exists($this->projectDir() . '/local_theme/' . $path)) {
            self::markTestSkipped(sprintf('local_theme/%s exists.', $path));
        }
    }

    /**
     * Translation files are collected when the container is built, so build it in an empty cache directory.
     * Relying on Symfony noticing the new file is not enough: file times have one-second resolution.
     */
    private function useFreshCache(): void
    {
        $this->cacheDir = sys_get_temp_dir() . '/' . uniqid('local-theme-test-', true);
        $_SERVER['APP_CACHE_DIR'] = $this->cacheDir;
    }

    private function defaultTranslation(string $key): string
    {
        return Yaml::parseFile($this->projectDir() . '/translations/messages.en.yaml')['two_factor'][$key];
    }

    private function projectDir(): string
    {
        return dirname(__DIR__, 2);
    }
}
