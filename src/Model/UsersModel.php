<?php
/**
 * Users model
 *
 * PHP version 5
 *
 * @category Model
 * @package  Model
 * @author   Radosław Stolarski <stolarz92@gmail.com>
 * @license  http://www.gnu.org/copyleft/gpl.html GNU General Public License
 * @version  SVN: $id$
 * @link     wierzba.wzks.uj.edu.pl/~12_stolarski
 */

namespace Model;

use Doctrine\DBAL\DBALException;
use Silex\Application;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\Exception\UsernameNotFoundException;

/**
 * Class UsersModel
 *
 * @category Model
 * @package  Model
 * @author   Radosław Stolarski <stolarz92@gmail.com>
 * @license  http://www.gnu.org/copyleft/gpl.html GNU General Public License
 * @version  Release: <package_version>
 * @link     wierzba.wzks.uj.edu.pl/~12_stolarski
 * @uses Doctrine\DBAL\DBALException
 * @uses Silex\Application
 */
class UsersModel
{
    /**
     * Silex application object
     *
     * @access protected
     * @var $_app Silex\Application
     */
    protected $_app;
    /**
     * Database access object.
     *
     * @access protected
     * @var $_db Doctrine\DBAL
     */
    protected $_db;

    /**
     * Constructor
     *
     * @param Application $app
     *
     * @access public
     * @return void
     */
    public function __construct(Application $app)
    {
        $this->_app = $app;
        $this->_db = $app['db'];
    }

    /**
     * Puts one user to database.
     *
     * @param  Array $data Associative array contains all necessary information
     * @param string $password encoded password
     *
     * @access public
     * @return Void
     */
    public function register($data, $password)
    {
        $role = 2;
        $query = 'INSERT INTO `users`
                  (`login`, `email`, `password`, `firstname`, `lastname`)
                  VALUES (?, ?, ?, ?, ?)';
        $this->_db->executeQuery(
            $query, array(
                $data['login'],
                $data['email'],
                $password,
                $data['firstname'],
                $data['lastname']
            )
        );

        $queryTwo = "SELECT * FROM users
                   WHERE login =\"".$data['login']."\";";
        $user = $this->_db->fetchAssoc($queryTwo);

        $queryThree = 'INSERT INTO users_roles (`id`,`user_id`, `role_id` )
                   VALUES (NULL, ?, ?)';
        $this->_db->executeQuery($queryThree, array($user['id'], $role));

    }

    /**
     * Adds money to wallet.
     *
     * @param Integer $id
     *
     * @return mixed
     */
    public function addStartCash($id)
    {
        $query = "INSERT INTO `wallet` (`idwallet`, `cash`, `blocked_cash`)
                  VALUES (?, '10000', '0')";
        return $this->_db->executeQuery($query, array($id));
    }

    /**
     * Load user by login.
     *
     * @param String $login
     *
     * @access public
     * @return array
     */
    public function loadUserByLogin($login)
    {
        $data = $this->getUserByLogin($login);

        if (!$data) {
            throw new UsernameNotFoundException(
                sprintf(
                    'Username "%s" does not exist.', $login
                )
            );
        }

        $roles = $this->getUserRoles($data['id']);

        if (!$roles) {
            throw new UsernameNotFoundException(
                sprintf(
                    'Username "%s" does not exist.', $login
                )
            );
        }

        $user = array(
            'login' => $data['login'],
            'password' => $data['password'],
            'roles' => $roles
        );

        return $user;
    }

    /**
     * Get users role.
     *
     * @param String $userId
     *
     * @access public
     * @return Array
     */
    public function getUserRoles($userId)
    {
        $sql = '
                SELECT
                    roles.role
                FROM
                    users_roles
                INNER JOIN
                    roles
                ON users_roles.role_id=roles.id
                WHERE
                    users_roles.user_id = ?
                ';

        $result = $this->_db->fetchAll($sql, array((string) $userId));

        $roles = array();
        foreach ($result as $row) {
            $roles[] = $row['role'];
        }

        return $roles;
    }


    /**
     * Changes information about user
     *
     * @param $id
     * @param $data
     * @param $password
     */
    public function updateUser($id, $data, $password)
    {
        if (isset($id) && ctype_digit((string)$id)) {

            $query = 'UPDATE `users`
                  SET `login`= ?,
                      `email`= ?,
                      `password`= ?,
                      `firstname`= ?,
                      `lastname`= ?
                  WHERE `id`= ?';

        $this->_db->executeQuery(
            $query, array(
                $data['login'],
                $data['email'],
                $password,
                $data['firstname'],
                $data['lastname'],
                $id
            )
        );
        } else {

        }

    }


    /**
     * This method gets currently logged user.
     *
     * @access public
     * @param application
     *
     * @return array $user
     *
     */
    protected function getCurrentUsername($app)
    {
        $token = $app['security']->getToken();

        if (null !== $token) {
            $user = $token->getUser()->getUsername();
        }

        return $user;
    }

    /**
     * Gets user by login.
     *
     * @param string $login
     *
     * @access public
     * @return Array Information about searching user.
     */
    public function getUserByLogin($login)
    {
        $sql = 'SELECT * FROM users WHERE login = ?';
        return $this->_db->fetchAssoc($sql, array((string) $login));
    }

    /**
     * @param $app
     * @access public
     *
     * @return Array
     */
    public function getCurrentUserInfo($app)
    {
        $login = $this->getCurrentUsername($app);
        $info = $this->getUserByLogin($login);

        return $info;
    }


}