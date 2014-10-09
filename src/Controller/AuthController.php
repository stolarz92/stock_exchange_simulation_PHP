<?php
/**
 * Auth controller
 *
 * PHP version 5
 *
 * @category Controller
 * @package  Controller
 * @author   Radosław Stolarski <stolarz92@gmail.com>
 * @license  http://www.gnu.org/copyleft/gpl.html GNU General Public License
 * @version  SVN: $id$
 * @link     wierzba.wzks.uj.edu.pl/~12_stolarski
 */

namespace Controller;

use Silex\Application;
use Silex\ControllerProviderInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Validator\Constraints as Assert;
use Model\UsersModel;

/**
 * Class AuthController
 *
 * @category Controller
 * @package  Controller
 * @author   Radosław Stolarski <stolarz92@gmail.com>
 * @license  http://www.gnu.org/copyleft/gpl.html GNU General Public License
 * @version  Release: <package_version>
 * @link     wierzba.wzks.uj.edu.pl/~12_stolarski
 * @uses Silex\Application
 * @uses Silex\ControllerProviderInterface
 * @uses Symfony\Component\HttpFoundation\Request
 * @uses Symfony\Component\Validator\Constraints
 * @uses Model\UsersModel
 */
class AuthController implements ControllerProviderInterface
{
    /**
     * Connection
     *
     * @param Application $app application object
     *
     * @access public
     * @return \Silex\ControllerCollection
     */
    public function connect(Application $app)
    {
        $authController = $app['controllers_factory'];
        $authController->match('/login', array($this, 'login'))
            ->bind('/auth/login');
        $authController->match('/logout', array($this, 'logout'))
            ->bind('/auth/logout');
        return $authController;
    }

    /**
     * Logging
     *
     * @param Application $app
     * @param Request     $request
     *
     * @access public
     * @return mixed
     */
    public function login(Application $app, Request $request)
    {
        $data = array();

        $form = $app['form.factory']->createBuilder('form')
            ->add(
                'username', 'text', array(
                    'label' => 'Login',
                    'data' => $app['session']
                            ->get(
                                '_security.last_username'
                            )
                )
            )
            ->add(
                'password', 'password', array(
                    'label' => 'Hasło'
                )
            )
            ->add('login', 'submit')
            ->getForm();

        return $app['twig']->render(
            'auth/login.twig', array(
                'form' => $form->createView(),
                'error' => $app['security.last_error']($request)
            )
        );
    }

    /**
     * Logging out
     *
     * @param Application $app
     * @param Request     $request
     *
     * @access public
     * @return mixed
     */
    public function logout(Application $app, Request $request)
    {
        $app['session']->clear();
        return $app['twig']->render(
            'auth/logout.twig'
        );
    }

}