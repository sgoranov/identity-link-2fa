<?php
declare(strict_types=1);

namespace App\Controller;

use App\Entity\AuthRequest;
use App\Entity\UserSecret;
use App\Repository\AuthRequestRepository;
use App\Repository\UserSecretRepository;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use sgoranov\IdentityLinkShared\Serializer\Deserializer;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Serializer\SerializerInterface;

#[Route('/api/v1', name: 'api_v1_')]
final class ApiController extends AbstractController
{
    public function __construct(
        private readonly SerializerInterface $serializer,
        private readonly EntityManagerInterface $entityManager,
        private readonly Deserializer $deserializer,
        private readonly AuthRequestRepository $authRequestRepository,
        private readonly UserSecretRepository $userSecretRepository,
    )
    {
    }

    #[Route('/auth/{id}', name: 'fetch_auth_request', methods: 'GET')]
    public function fetch(string $id): Response
    {
        $authRequest = $this->authRequestRepository->findOneByIdAndNotExpired($id);
        if ($authRequest === null) {
            throw new NotFoundHttpException();
        }

        return new JsonResponse([
            'response' => ['auth' => json_decode($this->serializer->serialize($authRequest, 'json'))]
        ]);
    }

    #[Route('/auth', name: 'create_auth_request', methods: 'POST')]
    public function create(): Response
    {
        $authRequest = new AuthRequest();
        if (!$this->deserializer->deserialize($authRequest, ['create'])) {
            return $this->deserializer->respondWithError();
        }

        $now = new DateTime();
        $expiresAt = (clone $now)->modify('+30 minutes');

        $authRequest->setCreated($now);
        $authRequest->setExpired($expiresAt);
        $authRequest->setAuthenticated(null);

        // mark all previous non expired requests as expired
        $this->authRequestRepository->updateExpiredToNow($authRequest->getIdentifier());

        $this->entityManager->persist($authRequest);
        $this->entityManager->flush();

        return new JsonResponse([
            'response' => ['auth' => json_decode($this->serializer->serialize($authRequest, 'json'))]
        ], Response::HTTP_CREATED);
    }

    #[Route('/user/{id}/reset-secret', name: 'reset_secret_on_next_auth', methods: 'PUT')]
    public function resetSecretOnNextAuth(string $id): Response
    {
        /** @var UserSecret $userSecret */
        $userSecret = $this->userSecretRepository->findOneBy(['identifier' => $id]);
        if ($userSecret === null) {
            throw new NotFoundHttpException();
        }

        $userSecret->setResetSecretOnNextAuth(true);
        $this->entityManager->persist($userSecret);
        $this->entityManager->flush();

        return new JsonResponse([
            'response' => ['result' => true]
        ]);
    }
}