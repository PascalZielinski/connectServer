<?php

namespace App\Service;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Gesdinet\JWTRefreshTokenBundle\Generator\RefreshTokenGeneratorInterface;
use Gesdinet\JWTRefreshTokenBundle\Model\RefreshTokenManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
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
        #[Autowire('%gesdinet_jwt_refresh_token.ttl%')]
        private int $refreshTokenTtl,
        #[Autowire('%env(AUTH_DUMMY_HASH)%')]
        private string $dummyHash,
    ) {
    }

    public function registerRequest(array $data, string $deviceSecret, ?string $userAgent = null): OperationResult
    {
        $fields = $this->stringFields($data, 'email', 'password', 'password_confirm');
        if ($fields === null) {
            return new OperationResult(
                ['error' => 'Email, password, and password_confirm are required as strings.'],
                400,
            );
        }

        [$email, $password, $passwordConfirm] = $fields;

        $user = $this->register($email, $password, $passwordConfirm);

        return $this->tokensOrDeviceVerification($user, $deviceSecret, $userAgent, pendingStatus: 201);
    }

    public function loginRequest(array $data, string $deviceSecret, string $ip, ?string $userAgent = null): OperationResult
    {
        if (!isset($data['email'], $data['password']) || !is_string($data['email']) || !is_string($data['password'])) {
            return new OperationResult(['error' => 'Email and password are required.'], 400);
        }

        $user = $this->authenticate($data['email'], $data['password']);

        if (!$this->trustedDeviceService->isTrusted($user, $deviceSecret, $userAgent)) {
            return new OperationResult([
                'status' => 'device_verification_required',
                'message' => 'Please confirm this device via the email we sent you.',
            ], 202);
        }

        return new OperationResult($this->issueTokens($user));
    }

    public function currentUser(mixed $user): OperationResult
    {
        if (!$user instanceof User) {
            throw new AccessDeniedHttpException();
        }

        return new OperationResult($this->userPayload($user));
    }

    private function register(string $email, string $password, string $passwordConfirm): User
    {
        if (!hash_equals($password, $passwordConfirm)) {
            $this->throwUnprocessable(['password_confirm' => ['Passwords do not match.']]);
        }

        $user = (new User())->setEmail($email)->setPassword($password);
        $this->validateUser($user);

        if ($this->users->findOneBy(['email' => $user->getEmail()]) !== null) {
            throw new ConflictHttpException('An account with this email already exists.');
        }

        $user->setPasswordHash($this->passwordHasher->hashPassword($user, $password));
        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return $user;
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

    private function tokensOrDeviceVerification(
        User $user,
        string $deviceSecret,
        ?string $userAgent,
        int $pendingStatus,
    ): OperationResult {
        if (!$this->trustedDeviceService->isTrusted($user, $deviceSecret, $userAgent)) {
            return new OperationResult([
                'status' => 'device_verification_required',
                'message' => 'Please confirm this device via the email we sent you.',
            ], $pendingStatus);
        }

        return new OperationResult($this->issueTokens($user));
    }

    private function issueTokens(User $user): array
    {
        $refreshToken = $this->refreshTokenGenerator->createForUserWithTtl($user, $this->refreshTokenTtl);
        $this->refreshTokenManager->save($refreshToken);

        return [
            'token' => $this->jwtManager->create($user),
            'refresh_token' => $refreshToken->getRefreshToken(),
            'expires_in' => 3600,
            'user' => $this->userPayload($user),
        ];
    }

    private function userPayload(User $user): array
    {
        return [
            'id' => $user->getId(),
            'uuid' => $user->getUuid(),
            'email' => $user->getEmail(),
            'created_at' => $user->getCreatedAt()->format(\DateTimeInterface::ATOM),
        ];
    }

    private function validateUser(User $user): void
    {
        $violations = $this->validator->validate($user);
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
}