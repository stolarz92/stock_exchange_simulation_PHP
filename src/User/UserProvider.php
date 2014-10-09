<?php
/**
 * User Provider
 *
 * PHP version 5
 *
 * @category UserProvider
 * @package  UserProvider
 * @author   Radosław Stolarski <stolarz92@gmail.com>
 * @license  http://www.gnu.org/copyleft/gpl.html GNU General Public License
 * @version  SVN: $id$
 * @link     wierzba.wzks.uj.edu.pl/~12_stolarski
 */
namespace User;

use Symfony\Component\Security\Core\User\UserProviderInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\User;
use Symfony\Component\Security\Core\Exception\UsernameNotFoundException;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;

use Model\UsersModel;

/**
 * Class UserProvider
 *
 * @category UserProvider
 * @package  UserProvider
 * @author   Radosław Stolarski <stolarz92@gmail.com>
 * @license  http://www.gnu.org/copyleft/gpl.html GNU General Public License
 * @version  Release: <package_version>
 * @link     wierzba.wzks.uj.edu.pl/~12_stolarski
 * @uses Symfony\Component\Security\Core\User\UserProviderInterface;
 * @uses Symfony\Component\Security\Core\User\UserInterface;
 * @uses Symfony\Component\Security\Core\User\User;
 * @uses Symfony\Component\Security\Core\Exception\UsernameNotFoundException;
 * @uses Symfony\Component\Security\Core\Exception\UnsupportedUserException;
 */
class UserProvider implements UserProviderInterface
{
    /**
     * Database access object.
     *
     * @access protected
     * @var $_app
     */
    protected $_app;

    /**
     * @param $app
     */
    public function __construct($app)
    {
        $this->_app = $app;
    }

    /**
     * @param string $login
     *
     * @return User|UserInterface
     */
    public function loadUserByUsername($login)
    {
        $userModel = new UsersModel($this->_app);
        $user = $userModel->loadUserByLogin($login);
        return new User(
            $user['login'],
            $user['password'],
            $user['roles'], true, true, true, true
        );
    }

    /**
     * @param UserInterface $user
     *
     * @return User|UserInterface
 * @throws \Symfony\Component\Security\Core\Exception\UnsupportedUserException
     */
    public function refreshUser(UserInterface $user)
    {
        if (!$user instanceof User) {
            throw new UnsupportedUserException(
                sprintf(
                    'Instances of "%s" are not supported.', get_class(
                        $user
                    )
                )
            );
        }
        return $this->loadUserByUsername($user->getUsername());
    }

    /**
     * @param string $class
     *
     * @return bool
     */
    public function supportsClass($class)
    {
        return $class === 'Symfony\Component\Security\Core\User\User';
    }
}