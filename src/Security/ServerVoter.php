<?php

namespace App\Security;

use App\Entity\Server;
use App\Entity\User;
use App\Enum\MemberRole;
use App\Repository\MemberRepository;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

final class ServerVoter extends Voter
{
    public const VIEW = 'SERVER_VIEW';
    public const MANAGE = 'SERVER_MANAGE';
    public const MANAGE_CHANNELS = 'SERVER_MANAGE_CHANNELS';

    public function __construct(private readonly MemberRepository $members)
    {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return in_array($attribute, [self::VIEW, self::MANAGE, self::MANAGE_CHANNELS], true)
            && $subject instanceof Server;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();
        if (!$user instanceof User || !$subject instanceof Server) {
            return false;
        }

        $membership = $this->members->findMembership($subject, $user);
        if ($attribute === self::VIEW) {
            return $membership !== null || $subject->getSettings()?->isPublicy() === true;
        }

        if ($attribute === self::MANAGE) {
            return $membership?->getRole() === MemberRole::Owner;
        }

        return in_array($membership?->getRole(), [MemberRole::Owner, MemberRole::Editor], true);
    }
}
