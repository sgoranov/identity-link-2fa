<?php
declare(strict_types=1);

namespace App\Controller;

use App\Entity\AuthRequest;
use App\Entity\UserSecret;
use App\Repository\AuthRequestRepository;
use App\Repository\UserSecretRepository;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use OpenApi\Attributes as OA;
use sgoranov\IdentityLinkShared\Serializer\Deserializer;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
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
    #[OA\Get(
        path: '/api/v1/auth/{id}',
        summary: 'Fetch a valid and non-expired AuthRequest by ID',
        tags: ['Auth'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                description: 'UUID of the AuthRequest',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'string', format: 'uuid')
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'AuthRequest fetched successfully',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(
                            property: 'response',
                            properties: [
                                new OA\Property(
                                    property: 'auth',
                                    ref: '#/components/schemas/AuthRequest'
                                )
                            ],
                            type: 'object'
                        )
                    ],
                    type: 'object'
                )
            ),
            new OA\Response(
                response: 404,
                description: 'AuthRequest not found or expired'
            )
        ]
    )]
    #[IsGranted('2fa.read')]
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
    #[OA\Post(
        path: '/api/v1/auth',
        summary: 'Create a new AuthRequest',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/AuthRequest')
        ),
        tags: ['Auth'],
        responses: [
            new OA\Response(
                response: 201,
                description: 'AuthRequest created successfully',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(
                            property: 'response',
                            properties: [
                                new OA\Property(
                                    property: 'auth',
                                    ref: '#/components/schemas/AuthRequest'
                                )
                            ],
                            type: 'object'
                        )
                    ],
                    type: 'object'
                )
            ),
            new OA\Response(
                response: 400,
                description: 'Validation failed'
            )
        ]
    )]
    #[IsGranted('2fa.write')]
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
        $this->authRequestRepository->updateExpiredToNow($authRequest->getUserId());

        $this->entityManager->persist($authRequest);
        $this->entityManager->flush();

        return new JsonResponse([
            'response' => ['auth' => json_decode($this->serializer->serialize($authRequest, 'json'))]
        ], Response::HTTP_CREATED);
    }

    #[Route('/user/{id}/reset-secret', name: 'reset_secret_on_next_auth', methods: 'PUT')]
    #[OA\Put(
        path: '/api/v1/user/{id}/reset-secret',
        summary: 'Reset the secret for a user on next authentication',
        tags: ['UserSecret'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                description: 'User userId',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'string')
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Secret reset flag set successfully',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(
                            property: 'response',
                            properties: [
                                new OA\Property(
                                    property: 'result',
                                    type: 'boolean',
                                    example: true
                                )
                            ],
                            type: 'object'
                        )
                    ],
                    type: 'object'
                )
            ),
            new OA\Response(
                response: 404,
                description: 'User not found'
            )
        ]
    )]
    #[IsGranted('2fa.reset')]
    public function resetSecretOnNextAuth(string $id): Response
    {
        /** @var UserSecret $userSecret */
        $userSecret = $this->userSecretRepository->findOneBy(['userId' => $id]);
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
