<?php
/**
 * Registration controller
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
use Symfony\Component\Form;
use Model\UsersModel;

/**
 * Class RegistrationController
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
class RegistrationController implements ControllerProviderInterface
{
    /**
     * StocksModel object.
     *
     * @var $_model
     * @access protected
     */
    protected $_model;

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
        $this->_model = new UsersModel($app);
        $authController = $app['controllers_factory'];
        $authController->match('/', array($this, 'register'))
            ->bind('/register/');
        $authController->match('/success', array($this, 'success'))
            ->bind('/register/success');
        return $authController;
    }

    /**
     * Function adds new user to database
     *
     * @param Application $app
     * @param Request     $request
     *
     * @access public
     * @return \Symfony\Component\HttpFoundation\RedirectResponse
     */
    public function register(Application $app, Request $request)
    {
        $data = array();
        $form = $app['form.factory']->createBuilder('form', $data)
            ->add(
                'login', 'text', array(
                    'constraints' => array(
                        new Assert\NotBlank()
                    )
                )
            )
            ->add(
                'email', 'text', array(
                    'label' => 'Email',
                    'constraints' => array(
                        new Assert\NotBlank(),
                        new Assert\Email(
                            array(
                                'message' => 'Email nie jest poprawny'
                            )
                        )
                    )
                )
            )
            ->add(
                'firstname', 'text', array(
                    'label' => 'Imię',
                    'constraints' => array(
                        new Assert\NotBlank(),
                        new Assert\Length(
                            array(
                            'min' => 3
                            )
                        )
                    )
                )
            )
            ->add(
                'lastname', 'text', array(
                    'label' => 'Nazwisko',
                    'constraints' => array(
                        new Assert\NotBlank(),
                        new Assert\Length(
                            array('min' => 3)
                        )
                    )
                )
            )
            ->add(
                'password', 'password', array(
                    'label' => 'Hasło',
                    'constraints' => array(
                        new Assert\NotBlank()
                    )
                )
            )
            ->add(
                'confirm_password', 'password', array(
                    'label' => 'Potwierdź hasło',
                    'constraints' => array(
                        new Assert\NotBlank()
                    )
                )
            )
            ->getForm();


        $form->handleRequest($request);

        if ($form->isValid()) {
            $data = $form->getData();

            $data['login'] = $app
                ->escape($data['login']);
            $data['email'] = $app
                ->escape($data['email']);
            $data['firstname'] = $app
                ->escape($data['firstname']);
            $data['lastname'] = $app
                ->escape($data['lastname']);
            $data['password'] = $app
                ->escape($data['password']);
            $data['confirm_password'] = $app
                ->escape($data['confirm_password']);

            if ($data['password'] === $data['confirm_password']) {

                $password = $app['security.encoder.digest']
                    ->encodePassword($data['password'], '');

                $checkLogin = $this->_model->getUserByLogin(
                    $data['login']
                );

                if (!$checkLogin) {
                    try
                    {
                        $this->_model->register(
                            $form->getData(),
                            $password
                        );
                        $lastId = $app['db']->lastInsertId();
                        $this->_model->addStartCash(
                            $lastId
                        );
                        return $app->redirect(
                            $app['url_generator']
                                ->generate(
                                    '/register/success'
                                ), 301
                        );
                    }
                    catch (\Exception $e)
                    {
                        $errors[] = 'Rejestracja się nie powiodła,
                        spróbuj jeszcze raz';
                    }
                } else {
                    $app['session']->getFlashBag()->add(
                        'message', array(
                            'type' => 'warning', 'content' => 'Login zajęty'
                        )
                    );
                    return $app['twig']->render(
                        'users/register.twig', array(
                            'form' => $form->createView()
                        )
                    );
                }
            } else {
                $app['session']->getFlashBag()->add(
                    'message', array(
                        'type' => 'warning',
                        'content' => 'Hasła różnią się między sobą'
                    )
                );
                return $app['twig']->render(
                    'users/register.twig', array(
                        'form' => $form->createView()
                    )
                );
            }
        }

        return $app['twig']->render(
            'users/register.twig', array(
                'form' => $form->createView()
            )
        );
    }

    /**
     * Generates page with information about successful registration
     *
     * @param Application $app
     * @access public
     * @return mixed
     */
    public function success(Application $app)
    {
        $link = $app['url_generator']->generate(
            '/auth/login'
        );
        return $app['twig']->render(
            'users/successfulRegistration.twig', array(
                'login_link' => $link
            )
        );
    }
}
