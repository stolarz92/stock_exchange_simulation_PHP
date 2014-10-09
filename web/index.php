<?php
use Symfony\Component\HttpFoundation\Response;

require_once __DIR__.'/../vendor/autoload.php';

$app = new Silex\Application();
$app['debug'] = true;

/* Twig */
$app->register(
    new Silex\Provider\TwigServiceProvider(), array(
        'twig.path' => __DIR__.'/../src/Views',
    )
);

/* Doctrine */
$app->register(
    new Silex\Provider\DoctrineServiceProvider(), array(
        'db.options' => array(
            'driver'    => 'pdo_mysql',
            'host'      => 'localhost',
            'dbname'    => '', //fill
            'user'      => '', //fill
            'password'  => '', //fill
            'charset'   => 'utf8',
        ),
    )
);

/* Validation */
$app->register(new Silex\Provider\ValidatorServiceProvider());

/* Forms */
$app->register(new Silex\Provider\FormServiceProvider());

/* Translation */
$app->register(
    new Silex\Provider\TranslationServiceProvider(), array(
        'translator.domains' => array(),
    )
);



/* Session */
$app->register(new Silex\Provider\SessionServiceProvider());

/* URLs */
$app->register(new Silex\Provider\UrlGeneratorServiceProvider());

$app->register(
    new Silex\Provider\SecurityServiceProvider(), array(
        'security.firewalls' => array(
            'admin' => array(
                'pattern' => '^.*$',
                'form' => array(
                    'login_path' => '/auth/login',
                    'check_path' => '/user/login_check',
                    'default_target_path'=> '/',
                    'username_parameter' => 'form[username]',
                    'password_parameter' => 'form[password]',
                ),
                'logout'  => true,
                'anonymous' => true,
                'logout' => array(
                    'logout_path' => '/auth/logout'
                ),
                'users' => $app->share(
                    function() use ($app) {
                        return new User\UserProvider($app);
                    }
                ),
            ),
        ),
        'security.access_rules' => array(
            array('^/user$', 'ROLE_USER'),
            array('^/user.*$', 'ROLE_USER'),
            array('^/stocks$', 'ROLE_USER'),
            array('^/stocks.*$', 'ROLE_USER'),
            array('^/admin/.*$', 'ROLE_ADMIN'),
            array('^/admin/$', 'ROLE_ADMIN'),


        ),
        'security.role_hierarchy' => array(
            'ROLE_ADMIN' => array('ROLE_USER', 'ROLE_ANONYMUS'),
            'ROLE_USER' => array('ROLE_ANONYMUS'),
        ),
    )
);

$app->error(
    function (\Exception $e, $code) use ($app) {
        if ($code == 404) {
            return new Response(
                $app['twig']->render('404.twig'), 404
            );
        }
    }
);
/* Routing */
$app->mount('/', new Controller\IndexController());
$app->mount('/auth/', new Controller\AuthController());
$app->mount('/register/', new Controller\RegistrationController());
$app->mount('/user/', new Controller\UsersController());
$app->mount('/stocks/', new Controller\StocksController());
$app->mount('/admin/', new Controller\AdminController());


$app->run();