<?php


namespace Controller;

use Silex\Application;
use Silex\ControllerProviderInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Validator\Constraints as Assert;

use Model\UsersModel;

/**
 * Class UsersController
 *
 * @class UsersController
 * @package Controller
 * @author Radosław Stolarski
 * @link wierzba.wzks.uj.edu.pl/projekt_php/
 * @uses Silex\Application
 * @uses Silex\ControllerProviderInterface;
 * @uses Symfony\Component\HttpFoundation\Request;
 * @uses Symfony\Component\Validator\Constraints as Assert;
 * @uses Model\UsersModel
 */
class UsersController implements ControllerProviderInterface
{

    protected $_model;

    /**
     * Class constructor.
     *
     * @access public
     * @param Appliction $app Silex application object
     */

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
        $usersController = $app['controllers_factory'];
        $usersController->match('/info', array($this, 'info'))
            ->bind('/user/info');
        $usersController->match('/edit', array($this, 'edit'))
            ->bind('/user/edit');
        $usersController->match('/error', array($this, 'error'))
            ->bind('/user/error');

        return $usersController;
    }


    /**
     * This method gets currently logged user.
     *
     * @param Application $app application object
     * @acces public
     * @return array
     */
    protected function getCurrentUser($app)
    {
        $token = $app['security']->getToken();

        if (null !== $token) {
            $user = $token->getUser()->getUsername();
        }

        return $user;
    }

    /**
     * Create page with user information
     *
     * @access public
     * @param Application $app Silex application object
     * @param Request $request application object
     * @return  Page /user
     */
    public function info(Application $app, Request $request)
    {
        $currentUser = $this->_model->getCurrentUserInfo($app);

        if (count($currentUser)) {
            return $app['twig']->render(
                'users/info.twig', array(
                    'user' => $currentUser,
                    'userinfo' => $currentUser
                )
            );
        } else {
            $app['session']->getFlashBag()->add(
                'message', array(
                    'type' => 'danger',
                    'content' => 'Nie znaleziono użytkownika'
                )
            );
            return $app->redirect(
                $app['url_generator']->generate(
                    '/index'
                ), 301
            );
        }
    }

    /**
     * Function edits logged user.
     *
     * @param Application $app
     * @param Request     $request
     *
     * @access public
     * @return \Symfony\Component\HttpFoundation\RedirectResponse
     */
    public function edit(Application $app, Request $request)
    {
            $currentUser = $this->getCurrentUser($app);
            $currentUserInfo =  $this->_model->getUserByLogin($currentUser);

            $data = array(
                'login' => $currentUserInfo['login'],
                'email' => $currentUserInfo['email'],
                'firstname' => $currentUserInfo['firstname'],
                'lastname' => $currentUserInfo['lastname'],
                'password' => '',
                'confirm_password' => ''
            );
            $form = $app['form.factory']->createBuilder('form', $data)
            ->add(
                'login', 'text', array(
                        'label' => 'Login',
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
                                'message' => 'Wrong email'
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
                        'label' => 'Nowe hasło',
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
                var_dump($data);

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
                        ->encodePassword(
                            $data['password'], ''
                        );


                    $checkLogin = $this->_model
                        ->getUserByLogin(
                            $data['login']
                        );

                    if ($data['login'] === $checkLogin ||
                        !$checkLogin ||
                        (int)$currentUserInfo['id'] ===(int)$checkLogin['id']) {
                        try
                        {
                            $this->_model->updateUser(
                                $currentUserInfo['id'],
                                $form->getData(),
                                $password
                            );

                            $app['session']->getFlashBag()->add(
                                'message', array(
                                    'type' => 'success',
                                    'content' => 'Edycja konta udała się,
                                    możesz się teraz ponownie zalogować'
                                )
                            );
                            return $app->redirect(
                                $app['url_generator']
                                    ->generate(
                                        '/auth/logout'
                                    ), 301
                            );
                        }
                        catch (\Exception $e)
                        {
                            $errors[] = 'Edycja konta nie powiodła się';
                        }

                    } else {
                        $app['session']->getFlashBag()->add(
                            'message', array(
                                'type' => 'warning',
                                'content' => 'Login zajęty'
                            )
                        );
                        return $app['twig']->render(
                            'users/edit.twig', array(
                                'form' => $form->createView(),
                                'login' => $currentUser
                            )
                        );
                    }
                } else {
                    $app['session']->getFlashBag()->add(
                        'message', array(
                            'type' => 'warning',
                            'content' => 'Hasła różnią się'
                        )
                    );
                    return $app['twig']->render(
                        'users/edit.twig', array(
                            'form' => $form->createView(),
                            'login' => $currentUser
                        )
                    );

                }
            }
            return $app['twig']->render(
                'users/edit.twig', array(
                    'form' => $form->createView(),
                    'login' => $currentUser
                )
            );
    }

    /**
     * @param Application $app
     *
     * @return mixed
     */
    public function error(Application $app)
    {
        $link = $app['url_generator']->generate(
            '/auth/login'
        );
        return $app['twig']->render(
            'users/error.twig', array(
                'login_link' => $link
            )
        );
    }

}