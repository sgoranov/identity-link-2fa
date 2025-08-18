<?php
declare(strict_types=1);

namespace App\Controller;

use App\Entity\UserSecret;
use App\Form\Type\TwoFaSecretType;
use App\Repository\AuthRequestRepository;
use App\Repository\UserSecretRepository;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Color\Color;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\Label\Font\NotoSans;
use Endroid\QrCode\Writer\PngWriter;
use Endroid\QrCode\Writer\Result\ResultInterface;
use OTPHP\TOTP;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\Exception\BadRequestException;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

final class TwoFactorController extends AbstractController
{
    public function __construct(
        private readonly ParameterBagInterface $parameterBag,
        private readonly AuthRequestRepository $authRequestRepository,
        private readonly UserSecretRepository $userSecretRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly TranslatorInterface $translator,
    )
    {
    }

    #[Route('/2fa/verify/{id}', name: '2fa_verify', methods: ['POST', 'GET'])]
    public function verify(Request $request, string $id): Response
    {
        $authRequest = $this->authRequestRepository->findOneByIdAndNotExpired($id);
        if ($authRequest === null) {
            throw new BadRequestException('Invalid authorization request');
        }

        /** @var UserSecret $userSecret */
        $userSecret = $this->userSecretRepository->findOneBy(['userId' => $authRequest->getUserId()]);
        if ($userSecret === null || $userSecret->isResetSecretOnNextAuth()) {
            return $this->redirectToRoute('2fa_enroll', ['id' => $id]);
        }

        $form = $this->createForm(TwoFaSecretType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

            $totp = TOTP::create($userSecret->getSecret());

            if ($totp->verify($form->get('code')->getData())) {

                // Mark the user as authenticated for 2FA
                $authRequest->setAuthenticated(new DateTime());

                $this->entityManager->persist($authRequest);
                $this->entityManager->flush();

                $separator = str_contains($authRequest->getRedirectUri(), '?') ? '&' : '?';
                return new RedirectResponse(
                    $authRequest->getRedirectUri() . $separator . 'id=' . urlencode($authRequest->getId())
                );

            } else {
                $this->addFlash(
                    'danger',
                    $this->translator->trans('two_factor.invalid_code')
                );

                return $this->redirectToRoute('2fa_verify', ['id' => $id]);
            }
        }

        return $this->render('two_factor/verify.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route('/2fa/enroll/{id}', name: '2fa_enroll', methods: 'GET')]
    public function enroll(string $id): Response
    {
        $authRequest = $this->authRequestRepository->findOneByIdAndNotExpired($id);
        if ($authRequest === null) {
            throw new BadRequestException('Invalid authorization request');
        }

        $totp = TOTP::create();

        /** @var UserSecret $userSecret */
        $userSecret = $this->userSecretRepository->findOneBy(['userId' => $authRequest->getUserId()]);
        if ($userSecret !== null) {
            if ($userSecret->isResetSecretOnNextAuth()) {
                // with the secret, reset created to now
                $userSecret->setCreated(new DateTime());
                $userSecret->setResetSecretOnNextAuth(false);
                $userSecret->setSecret($this->generateSecret($totp, $userSecret->getUserId()));

            } else if ($userSecret->getCreated()->add(new \DateInterval('PT10M')) >= $authRequest->getCreated()) {
                $totp->setSecret($userSecret->getSecret());
                $totp->setLabel($userSecret->getUserId());
                $totp->setIssuer($this->parameterBag->get('issuer'));
            } else {
                // in case the user already has a secret, and secret reset was not requested, and the
                // secret is not created as with the current authorization request, then throw an exception
                throw new BadRequestException();
            }

        } else {
            $userSecret = new UserSecret();
            $userSecret->setCreated(new DateTime());
            $userSecret->setUserId($authRequest->getUserId());
            $userSecret->setResetSecretOnNextAuth(false);
            $userSecret->setSecret($this->generateSecret($totp, $userSecret->getUserId()));
        }

        $this->entityManager->persist($userSecret);
        $this->entityManager->flush();

        return $this->render('two_factor/enroll.html.twig', [
            'secret' => $userSecret->getSecret(),
            'qrCode' => $this->generateQrCode($totp)->getDataUri(),
            'id' => $id,
        ]);
    }

    private function generateQrCode(TOTP $totp): ResultInterface
    {
        return Builder::create()
            ->writer(new PngWriter())
            ->data($totp->getProvisioningUri())
            ->encoding(new Encoding('UTF-8'))
            ->backgroundColor(new Color(255, 255, 255, 127))
            ->size(300)
            ->margin(10)
            ->labelText($this->translator->trans('enroll.scan'))
            ->labelFont(new NotoSans(20))
            ->build();
    }

    private function generateSecret(TOTP $totp, string $userId): string
    {
        $totp->setLabel($userId);
        $totp->setIssuer($this->parameterBag->get('issuer'));

        return $totp->getSecret();
    }
}