<?php

namespace App\Service;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Gesdinet\JWTRefreshTokenBundle\Generator\RefreshTokenGeneratorInterface;
use Gesdinet\JWTRefreshTokenBundle\Model\RefreshTokenManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
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
        #[Autowire('%gesdinet_jwt_refresh_token.ttl%')]
        private int $refreshTokenTtl,
    ) {
    }

    public function register(string $email, string $password, string $passwordConfirm): array
    {
        if (!hash_equals($password, $passwordConfirm)) {
            throw new \Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException(
                json_encode(['password_confirm' => ['Passwords do not match.']], JSON_THROW_ON_ERROR),
            );
        }

        $user = (new User())->setEmail($email)->setPassword($password);
        $this->validateUser($user);

        if ($this->users->findOneBy(['email' => $user->getEmail()]) !== null) {
            throw new ConflictHttpException('An account with this email already exists.');
        }

        $user->setPasswordHash($this->passwordHasher->hashPassword($user, $password));
        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return $this->issueTokens($user);
    }

    public function login(string $email, string $password): array
    {
        $user = $this->users->findOneBy(['email' => strtolower(trim($email))]);

        if ($user === null || !$this->passwordHasher->isPasswordValid($user, $password)) {
            throw new UnauthorizedHttpException('Bearer', 'Invalid email or password.');
        }

        return $this->issueTokens($user);
    }

    private function validateUser(User $user): void
    {
        $violations = $this->validator->validate($user);
        if (count($violations) > 0) {
            $errors = [];
            foreach ($violations as $violation) {
                $errors[$violation->getPropertyPath()][] = $violation->getMessage();
            }

            throw new \Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException(
                json_encode($errors, JSON_THROW_ON_ERROR),
            );
        }
    }

    private function issueTokens(User $user): array
    {
        $refreshToken = $this->refreshTokenGenerator->createForUserWithTtl($user, $this->refreshTokenTtl);
        $this->refreshTokenManager->save($refreshToken);

        return [
            'token' => $this->jwtManager->create($user),
            'refresh_token' => $refreshToken->getRefreshToken(),
            'expires_in' => 3600,
            'user' => [
                'id' => $user->getId(),
                'uuid' => $user->getUuid(),
                'email' => $user->getEmail(),
                'created_at' => $user->getCreatedAt()->format(\DateTimeInterface::ATOM),
            ],
        ];
    }
}
