<?php

namespace App\Security;

use App\Entity\User;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\DependencyInjection\ParameterBag\ContainerBagInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

class UserVoter extends Voter
{
    public const ACCESS = 'access';

    private $doctrine;
    private $params;

    public function __construct(ManagerRegistry $doctrine, ContainerBagInterface $params)
    {
        $this->doctrine = $doctrine;
        $this->params = $params;
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        // if the voter doesn't support this attribute, return false
        if (!in_array($attribute, [self::ACCESS])) {
            return false;
        }

        return true;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $user = $token->getUser();

        if ($user instanceof AdminUser || $this->params->get('admin_auth_bypass') == 'true') {
            // admins can alway access everything
            return true;
        }

        if (!$this->params->get('user_auth_enabled') == 'true') {
            // cannot log in as a user
            $vote?->addReason('Only admins can log in');

            return false;
        }

        if (!$user instanceof NormalUser) {
            // the user must be logged in; if not, deny access
            $vote?->addReason('The user is not logged in.');

            return false;
        }

        $userId = $subject;

        return match ($attribute) {
            self::ACCESS => $this->canAccess($user, $userId, $vote),
            default => throw new \LogicException('This code should not be reached!'),
        };
    }

    private function canAccess(NormalUser $logged_user, ?int $userId, ?Vote $vote): bool
    {
        // trying as a user to access a page with no "userId" specified, reject
        if (is_null($userId)) {
            $vote?->addReason(sprintf(
                'Cannot access admin resource'
            ));

            return false;
        }

        $user = $this->doctrine->getRepository(User::class)->findOneById($userId);
        if (!$user) {
            $vote?->addReason(sprintf(
                'Id %d does not exist',
                $userId
            ));

            return false;
        }

        if ($logged_user->getUsername() === $user->getUsername()) {
            return true;
        }

        $vote?->addReason(sprintf(
            'The logged in user (username: %s) is not (id: %d)',
            $logged_user->getUsername(), $userId
        ));

        return false;
    }
}
