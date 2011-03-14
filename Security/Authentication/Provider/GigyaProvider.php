<?php
namespace OpenSky\Bundle\GigyaBundle\Security\Authentication\Provider;

use OpenSky\Bundle\GigyaBundle\Security\Authentication\Token\GigyaToken;
use OpenSky\Bundle\GigyaBundle\Socializer\SocializerInterface;
use Symfony\Component\Security\Core\User\AccountInterface;
use Symfony\Component\Security\Core\User\AccountCheckerInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\UnsupportedAccountException;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authentication\Provider\AuthenticationProviderInterface;

class GigyaProvider implements AuthenticationProviderInterface
{
    protected $socializer;
    protected $accessToken;
    protected $userProvider;
    protected $accountChecker;

    public function __construct(SocializerInterface $socializer, UserProviderInterface $userProvider = null, AccountCheckerInterface $accountChecker = null)
    {
        if (null !== $userProvider && null === $accountChecker) {
            throw new \InvalidArgumentException('$accountChecker cannot be null, if $userProvider is not null.');
        }

        $this->socializer     = $socializer;
        $this->userProvider   = $userProvider;
        $this->accountChecker = $accountChecker;
    }

    public function authenticate(TokenInterface $token)
    {
        if (!$this->supports($token)) {
            return null;
        }

        try {
            $accessToken  = $this->socializer->getAccessToken();

            if (null !== $accessToken) {
                $userId = $this->socializer->getUserId($accessToken['access_token']);
                return $this->createAuthenticatedToken($userId);
            }
        } catch (AuthenticationException $failed) {
            throw $failed;
        } catch (\Exception $failed) {
            throw new AuthenticationException('Unknown error', $failed->getMessage(), $failed->getCode(), $failed);
        }

        throw new AuthenticationException('The Gigya user could not be retrieved from the session.');
    }

    public function supports(TokenInterface $token)
    {
        return $token instanceof GigyaToken;
    }

    private function createAuthenticatedToken($uid)
    {
        if (null === $this->userProvider) {
            return new GigyaToken($uid);
        }

        $user = $this->userProvider->loadUserByUsername($uid);

        if (! $user instanceof AccountInterface) {
            throw new \RuntimeException('User provider did not return an implementation of account interface.');
        }

        $this->accountChecker->checkPreAuth($user);
        $this->accountChecker->checkPostAuth($user);

        return new GigyaToken($user, $user->getRoles());
    }

    /**
     * Finds a user by account
     *
     * @param AccountInterface $user
     */
    public function loadUserByAccount(AccountInterface $user)
    {
        throw new UnsupportedAccountException('Account is not supported.');
    }
}
