<?php

namespace App\Service;

use App\Entity\RefreshToken;
use App\Entity\User;
use App\Event\ExistingAccountRegistrationEvent;
use App\Event\UserRegisteredEvent;
use App\Repository\UserRepository;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Gesdinet\JWTRefreshTokenBundle\Generator\RefreshTokenGeneratorInterface;
use Gesdinet\JWTRefreshTokenBundle\Model\RefreshTokenManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final readonly class AuthService
{
    public function __construct(
        private UserRepository $users,
        private EntityManagerInterface $entityManager,
        private UserPasswordHasherInterface $passwordHasher,
        private ValidatorInterface $validator,
        private JWTTokenManagerInterface $jwtManager,
        private RefreshTokenGeneratorInterface $refreshTokenGenerator,
        private RefreshTokenManagerInterface $refreshTokenManager,
        private TrustedDeviceService $trustedDeviceService,
        private ManagerRegistry $registry,
        private EventDispatcherInterface $dispatcher,
        #[Autowire('%gesdinet_jwt_refresh_token.ttl%')]
        private int $refreshTokenTtl,
        #[Autowire('%env(AUTH_DUMMY_HASH)%')]
        private string $dummyHash,
        #[Autowire('%lexik_jwt_authentication.token_ttl%')]
        private int $accessTokenTtl,
    ) {
    }

    public function registerRequest(array $data): OperationResult
    {
        $fields = $this->stringFields($data, 'email', 'password', 'password_confirm');
        if ($fields === null) {
            return new OperationResult(
                ['error' => 'Email, password, and password_confirm are required as strings.'],
                400,
            );
        }

        [$email, $password, $passwordConfirm] = $fields;

        $this->register($email, $password, $passwordConfirm);

        return new OperationResult(
            ['message' => 'If this address can be registered, we have sent you an email.'],
            202,
        );
    }

    public function loginRequest(
        array $data,
        string $deviceSecret,
        string $ip,
        ?string $userAgent = null,
    ): OperationResult {
        if (!isset($data['email'], $data['password']) || !is_string($data['email']) || !is_string($data['password'])) {
            return new OperationResult(['error' => 'Email and password are required.'], 400);
        }

        $user = $this->authenticate($data['email'], $data['password']);

        if (!$user->isVerified()) {
            return new OperationResult([
                'status' => 'email_verification_required',
                'message' => 'Please verify your email address before logging in.',
            ], 403);
        }

        if (!$this->trustedDeviceService->isTrusted($user, $deviceSecret)) {
            $this->trustedDeviceService->challenge($user, $deviceSecret, $ip, $userAgent);

            return new OperationResult([
                'status' => 'device_verification_required',
                'message' => 'Please confirm this device via the email we sent you.',
            ], 202);
        }

        $this->trustedDeviceService->recordLogin($user, $deviceSecret, $ip);

        return new OperationResult($this->issueTokens($user, $deviceSecret));
    }

    public function currentUser(mixed $user): OperationResult
    {
        if (!$user instanceof User) {
            throw new AccessDeniedHttpException();
        }

        return new OperationResult($this->userPayload($user));
    }

    private function register(
        string $email,
        string $password,
        string $passwordConfirm
    ): void {
        if (!hash_equals($password, $passwordConfirm)) {
            $this->throwUnprocessable([
                'password_confirm' => ['Passwords do not match.'],
            ]);
        }

        $email = strtolower(trim($email));

        $user = (new User())
            ->setEmail($email)
            ->setPlainPassword($password);

        $this->validateUser($user);

        $user->setPasswordHash(
            $this->passwordHasher->hashPassword($user, $password)
        );
        $user->eraseCredentials();

        $existing = $this->users->findOneBy(['email' => $email]);

        if ($existing !== null && $existing->isVerified()) {
            $this->dispatcher->dispatch(new ExistingAccountRegistrationEvent($email));

            return;
        }

        try {
            $this->entityManager->wrapInTransaction(function () use ($existing, $user): void {
                if ($existing !== null) {
                    $this->entityManager->remove($existing);
                    $this->entityManager->flush();
                }
                $this->entityManager->persist($user);
                $this->entityManager->flush();
            });
        } catch (UniqueConstraintViolationException) {
            $this->registry->resetManager();
            $this->dispatcher->dispatch(new ExistingAccountRegistrationEvent($email));

            return;
        }

        $this->dispatcher->dispatch(new UserRegisteredEvent($user));
    }

    private function authenticate(string $email, string $password): User
    {
        $user = $this->users->findOneBy(['email' => strtolower(trim($email))]);

        $candidate = $user ?? (new User())->setPasswordHash($this->dummyHash);
        $passwordValid = $this->passwordHasher->isPasswordValid($candidate, $password);

        if ($user === null || !$passwordValid) {
            throw new UnauthorizedHttpException('Bearer', 'Invalid email or password.');
        }

        return $user;
    }

    private function issueTokens(User $user, string $deviceSecret): array
    {
        $refreshToken = $this->refreshTokenGenerator->createForUserWithTtl($user, $this->refreshTokenTtl);

        if (!$refreshToken instanceof RefreshToken) {
            throw new \LogicException('Refresh token class must be ' . RefreshToken::class);
        }

        $refreshToken->setDeviceHash(hash('sha256', $deviceSecret));
        $this->refreshTokenManager->save($refreshToken);

        return [
            'token' => $this->jwtManager->create($user),
            'refresh_token' => $refreshToken->getRefreshToken(),
            'expires_in' => $this->accessTokenTtl,
            'user' => $this->userPayload($user),
        ];
    }

    private function userPayload(User $user): array
    {
        return [
            'uuid' => $user->getUuid(),
            'email' => $user->getEmail(),
            'created_at' => $user->getCreatedAt()->format(\DateTimeInterface::ATOM),
        ];
    }

    private function validateUser(User $user): void
    {
        $violations = $this->validator->validate($user, null, ['Default', 'registration']);
        if (count($violations) === 0) {
            return;
        }

        $errors = [];
        foreach ($violations as $violation) {
            $errors[$violation->getPropertyPath()][] = $violation->getMessage();
        }

        $this->throwUnprocessable($errors);
    }

    private function throwUnprocessable(array $errors): never
    {
        throw new UnprocessableEntityHttpException(json_encode($errors, JSON_THROW_ON_ERROR));
    }

    private function stringFields(array $data, string ...$keys): ?array
    {
        $values = [];
        foreach ($keys as $key) {
            if (!isset($data[$key]) || !is_string($data[$key])) {
                return null;
            }
            $values[] = $data[$key];
        }

        return $values;
    }

    public function refreshRequest(array $data, string $deviceSecret): OperationResult
    {
        if (!isset($data['refresh_token']) || !is_string($data['refresh_token'])) {
            return new OperationResult(['error' => 'refresh_token is required.'], 400);
        }

        $token = $this->refreshTokenManager->get($data['refresh_token']);

        if (
            !$token instanceof RefreshToken
            || !$token->isValid()
            || !hash_equals((string) $token->getDeviceHash(), hash('sha256', $deviceSecret))
        ) {
            throw new UnauthorizedHttpException('Bearer', 'Invalid refresh token.');
        }

        $user = $this->users->findOneBy(['email' => $token->getUsername()]);

        if (
            $user === null
            || !$user->isVerified()
            || !$this->trustedDeviceService->isTrusted($user, $deviceSecret)
        ) {
            throw new UnauthorizedHttpException('Bearer', 'Invalid refresh token.');
        }

        $tokens = $this->entityManager->wrapInTransaction(
            function () use ($token, $user, $deviceSecret): array {
                if (!$this->consumeRefreshToken($token)) {
                    throw new UnauthorizedHttpException('Bearer', 'Invalid refresh token.');
                }

                return $this->issueTokens($user, $deviceSecret);
            }
        );

        return new OperationResult($tokens);
    }

    private function consumeRefreshToken(RefreshToken $token): bool
    {
        $deleted = $this->entityManager
            ->createQuery('DELETE FROM ' . RefreshToken::class . ' t WHERE t.id = :id')
            ->setParameter('id', $token->getId())
            ->execute();

        return $deleted === 1;
    }

    public function logoutRequest(array $data): OperationResult
    {
        if (!isset($data['refresh_token']) || !is_string($data['refresh_token'])) {
            return new OperationResult(['error' => 'refresh_token is required.'], 400);
        }

        $token = $this->refreshTokenManager->get($data['refresh_token']);

        if ($token instanceof RefreshToken) {
            $this->consumeRefreshToken($token);
        }

        return new OperationResult([], 204);
    }

    public function revokeAllTokens(User $user): void
    {
        $this->entityManager
            ->createQuery('DELETE FROM ' . RefreshToken::class . ' t WHERE t.username = :u')
            ->setParameter('u', $user->getUserIdentifier())
            ->execute();
    }

    public function revokeDevice(User $user, string $deviceSecretHash): void
    {
        $this->entityManager
            ->createQuery('DELETE FROM ' . RefreshToken::class . ' t WHERE t.username = :u AND t.deviceHash = :h')
            ->setParameter('u', $user->getUserIdentifier())
            ->setParameter('h', $deviceSecretHash)
            ->execute();
    }
}