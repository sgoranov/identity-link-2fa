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
use OTPHP\TOTP;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\Exception\BadRequestException;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class TwoFactorController extends AbstractController
{
    public function __construct(
        private readonly ParameterBagInterface $parameterBag,
        private readonly AuthRequestRepository $authRequestRepository,
        private readonly UserSecretRepository $userSecretRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly ValidatorInterface $validator,
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
        $userSecret = $this->userSecretRepository->findOneBy(['identifier' => $authRequest->getIdentifier()]);
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

                return new RedirectResponse($this->parameterBag->get('redirect_uri'));

            } else {
                $this->addFlash(
                    'danger',
                    'Invalid code. Please try again.'
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

        /** @var UserSecret $userSecret */
        $userSecret = $this->userSecretRepository->findOneBy(['identifier' => $authRequest->getIdentifier()]);
        if ($userSecret !== null && $userSecret->isResetSecretOnNextAuth() === false) {
            throw new BadRequestException();
        }

        if ($userSecret === null) {
            $userSecret = new UserSecret();
            $userSecret->setCreated(new DateTime());
            $userSecret->setIdentifier($authRequest->getIdentifier());
        }

        // Generate the secret and QR code
        $totp = TOTP::create();
        $totp->setLabel($userSecret->getIdentifier());
        $totp->setIssuer($this->parameterBag->get('issuer'));

        $secret = $totp->getSecret();

        $qrCodeUrl = $totp->getProvisioningUri();

        $qrCode = Builder::create()
            ->writer(new PngWriter())
            ->data($qrCodeUrl)
            ->encoding(new Encoding('UTF-8'))
            ->backgroundColor(new Color(255, 255, 255, 127))
            ->size(300)
            ->margin(10)
            ->labelText('Scan the code')
            ->labelFont(new NotoSans(20))
            ->build();

        // update the secret and set reset on next auth to false
        $userSecret->setResetSecretOnNextAuth(false);
        $userSecret->setSecret($secret);

        // validate the entity upon persist
        $errors = $this->validator->validate($userSecret);
        if (count($errors) > 0) {
            list($error) = $errors;

            return new Response($error->getPropertyPath() . ': ' . $error->getMessage(),
                Response::HTTP_BAD_REQUEST);
        }

        $this->entityManager->persist($userSecret);
        $this->entityManager->flush();

        return $this->render('two_factor/enroll.html.twig', [
            'secret' => $secret,
            'qrCode' => $qrCode->getDataUri(),
            'id' => $id,
        ]);
    }
}