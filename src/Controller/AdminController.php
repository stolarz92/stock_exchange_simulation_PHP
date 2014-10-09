<?php
/**
 * Projects controller
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
use Model\StocksModel;
use Model\UsersModel;

/**
 * Class AdminController
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
 * @uses Model\StocksModel
 * @uses Model\UsersModel
 */
class AdminController implements ControllerProviderInterface
{
    /**
     * StocksModel object.
     *
     * @var $_model
     * @access protected
     */
    protected $_stocks;

    /**
     * UsersModel object.
     *
     * @var $_model
     * @access protected
     */
    protected $_user;

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
        $this->_stocks = new StocksModel($app);
        $this->_user = new UsersModel($app);
        $authController = $app['controllers_factory'];
        $authController->match('/stocks', array($this, 'stocks'))
            ->bind('/admin/stocks');
        $authController->match('/editstock', array($this, 'editstock'))
            ->bind('/admin/editstock');
        $authController->match('/controlpanel', array($this, 'controlpanel'))
            ->bind('/admin/controlpanel');
        $authController->match('/addoffer', array($this, 'addoffer'))
            ->bind('/admin/addoffer');
        $authController->match('/editoffer', array($this, 'editoffer'))
            ->bind('/admin/editoffer');
        $authController->match('/deleteoffer', array($this, 'deleteoffer'))
            ->bind('/admin/deleteoffer');


        return $authController;
    }

    /**
     * Function, which allows admin to view all stocks and add new
     *
     * @param Application $app
     * @param Request     $request
     * @access public
     * @return \Symfony\Component\HttpFoundation\RedirectResponse
     */
    public function Stocks(Application $app, Request $request)
    {
        $stocks = $this->_stocks->getAllStocks();
        $form = $app['form.factory']->createBuilder('form')
            ->add(
                'new_stock_name', 'text', array(
                    'label' => 'Nazwa nowych akcji',
                    'constraints' => array(
                        new Assert\NotBlank()
                    )
                )
            )
            ->add(
                'price', 'money', array(
                    'label' => 'Cena',
                    'currency' => 'disabled',
                    'constraints' => array(
                        new Assert\NotBlank(),
                        new Assert\Type(
                            array(
                                'type' => 'numeric',
                                'message' => 'Format ceny: 25.25',
                            )
                        ),
                        new Assert\GreaterThan(
                            array(
                                'value' => '0',
                                'message' =>
                                    'Wartość powinna być większa od 0',
                            )
                        ),
                    )
                )
            )
            ->getForm();

        $form->handleRequest($request);

        if ($form->isValid()) {
            $formData = $form->getData();

            $ifStockExist = $this->_stocks->checkStocksByName(
                $formData
            );

            if ($ifStockExist) {
                $app['session']->getFlashBag()->add(
                    'message', array(
                        'type' => 'danger',
                        'content' => 'Akcje już istnieją!'
                    )
                );
                return $app->redirect(
                    $app['url_generator']->generate(
                        '/admin/stocks'
                    ), 301
                );
            } else {
                $this->_stocks->insertIntoStocks($formData);

                $app['session']->getFlashBag()->add(
                    'message', array(
                        'type' => 'danger',
                        'content' => 'Dodałeś spółkę '
                        . $formData['new_stock_name'].
                        '.'
                    )
                );
                return $app->redirect(
                    $app['url_generator']->generate(
                        '/admin/stocks'
                    ), 301
                );
            }
        }

        return $app['twig']->render(
            'admin/stocks.twig', array(
                'form' => $form->createView(),
                'stocks' => $stocks,
            )
        );
    }

    /**
     * Function allows admin to edit stocks
     *
     * @param Application $app
     * @param Request     $request
     *
     * @access public
     * @return \Symfony\Component\HttpFoundation\RedirectResponse
     */
    public function editStock(Application $app, Request $request)
    {
        $id = (int)$request->get('id', 0);
        $checkStocks = $this->_stocks->checkStocks($id);
        if (!$checkStocks) {
            $app['session']->getFlashBag()->add(
                'message', array(
                    'type' => 'danger',
                    'content' => 'Nie znaleziono akcji!'
                )
            );
            return $app->redirect(
                $app['url_generator']->generate(
                    '/admin/stocks'
                ), 301
            );
        } else {
            $form = $app['form.factory']->createBuilder('form')
                ->add(
                    'new_stock_name', 'text', array(
                        'constraints' => array(
                            new Assert\NotBlank()
                        )
                    )
                )
                ->getForm();

            $form->handleRequest($request);

            if ($form->isValid()) {
                $formData = $form->getData();
                $stock = $this->_stocks->getStockByName(
                    $formData
                );
                if ($stock) {
                    $app['session']->getFlashBag()->add(
                        'message', array(
                            'type' => 'warning',
                            'content' => 'Akcje o nazwie '
                                .$formData['new_stock_name'].
                                ' już istnieją'
                        )
                    );
                    return $app->redirect(
                        $app['url_generator']->generate(
                            '/admin/stocks'
                        ), 301
                    );
                } else {
                $this->_stocks->updateStocksEverywhere(
                    $checkStocks,
                    $formData
                );
                $app['session']->getFlashBag()->add(
                    'message', array(
                        'type' => 'success',
                        'content' => 'Zaktualizowałeś akcje ' .
                            $checkStocks['stock_name'].
                            '!'
                    )
                );
                return $app->redirect(
                    $app['url_generator']->generate(
                        '/admin/stocks'
                    ), 301
                );
                }
            }
        }
            return $app['twig']->render(
                'admin/editstock.twig', array(
                    'form' => $form->createView(),
                    'stock_name' => $checkStocks['stock_name']
                )
            );

    }


    /**
     * Functions shows whole order sheet.
     *
     * @param Application $app
     *
     * @access public
     * @return mixed
     */
    public function controlPanel(Application $app)
    {
        $orderSheet = $this->_stocks->getOrderSheet();
        return $app['twig']->render(
            'admin/controlpanel.twig', array(
                'order_sheet' => $orderSheet
            )
        );
    }

    /**
     * Function allows admin to add sell offer to order sheet
     *
     * @param Application $app
     * @param Request     $request
     *
     * @access public
     * @return mixed
     */
    public function addOffer(Application $app, Request $request)
    {
        $currentUser = $this->_user->getCurrentUserInfo($app);

        $stocks = $this->_stocks->getAllStocks();
        foreach ($stocks as $stock) {
            $stocksArray[] = $stock['stock_name'];
        }
        $result = array_combine($stocksArray, $stocksArray);
        $form = $app['form.factory']->createBuilder('form')
            ->add(
                'stock_name', 'choice', array(
                    'choices' => $result,
                    'multiple' => false
                )
            )

            ->add(
                'amount', 'integer', array(
                    'constraints' => array(
                        new Assert\NotBlank(),
                        new Assert\Type(
                            array(
                                'type' => 'numeric',
                                'message' =>
                                    'Wartość powinna
                                    być liczbą całkowitą',
                            )
                        ),
                        new Assert\GreaterThan(
                            array(
                                'value' => '0',
                                'message' =>
                                    'Wartość powinna być większa od 0',
                            )
                        ),
                    )
                )
            )
            ->add(
                'price', 'money', array(
                    'currency' => 'disabled',
                    'constraints' => array(
                        new Assert\NotBlank(),
                        new Assert\Type(
                            array(
                                'type' => 'numeric',
                                'message' => 'Format ceny: 25.25',
                            )
                        ),
                        new Assert\GreaterThan(
                            array(
                                'value' => '0',
                                'message' =>
                                    'Wartość powinna być większa od 0',
                            )
                        ),
                    )
                )
            )
            ->add(
                'buysell', 'text', array(
                    'data' => 'Sell',
                    'disabled' => true,
                    'constraints' => array(new Assert\NotBlank())
                )
            )
            ->getForm();

        $form->handleRequest($request);

        if ($form->isValid()) {
            $formData = $form->getData();
            $date = date("Y-m-d H:i:s");

            $formData['buysell'] = 1;

            try
            {
                $this->_stocks->addOffer($formData, $date, $currentUser['id']);

                $app['session']->getFlashBag()->add(
                    'message', array(
                        'type' => 'success',
                        'content' => 'Dodałeś '
                            . $formData['amount'] .
                            ' akcji '
                            . $formData['stock_name'] .
                            ' za '
                            . $formData['price'] .
                            ' PLN!'

                    )
                );

                return $app->redirect(
                    $app['url_generator']->generate(
                        '/admin/addoffer'
                    ), 301
                );

/*                return $app['twig']->render(
                    'admin/addoffer.twig', array()
                );*/
            }
            catch (\Exception $e)
            {
                $errors[] = 'Nie udało się dodać oferty';
            }
        }
        return $app['twig']->render(
            'admin/addoffer.twig', array(
                'form' => $form->createView()
            )
        );
    }

    /**
     * Function which edits offer
     *
     * @param Application $app
     * @param Request     $request
     *
     * @access public
     * @return \Symfony\Component\HttpFoundation\RedirectResponse
     */
    public function editOffer(Application $app, Request $request)
    {

        $currentUser = $this->_user->getCurrentUserInfo($app);

        $id = (int)$request->get('id', 0);

        $checkId = $this->_stocks->checkId($id);

        if (!$checkId) {
            $app['session']->getFlashBag()->add(
                'message', array(
                    'type' => 'danger',
                    'content' => 'Nie znaleziono oferty!'
                )
            );
            return $app->redirect(
                $app['url_generator']->generate(
                    '/admin/controlpanel'
                ), 301
            );
        } else {
            $offer = $this->_stocks->getOffer($id);

            if ((int)$offer['realized'] === 1) {
                $app['session']->getFlashBag()->add(
                    'message', array(
                        'type' => 'danger',
                        'content' => 'Nie możesz edytować tej oferty!'
                    )
                );
                return $app->redirect(
                    $app['url_generator']->generate(
                        '/admin/controlpanel'
                    ), 301
                );
            }
            if ($offer['buysell'] == 0) {
                $buysell = 'Buy';
            } else {
                $buysell = 'Sell';
            }

            if (count($offer)) {
                $form = $app['form.factory']->createBuilder('form')
                    ->add(
                        'id', 'hidden', array(
                            'data' => $id,
                        )
                    )
                    ->add(
                        'stock_name', 'text', array(
                            'data' => $offer['stock_name'],
                            'disabled' => true
                        )
                    )
                    ->add(
                        'amount', 'integer', array(
                            'constraints' => array(
                                new Assert\NotBlank(),
                                new Assert\Type(
                                    array(
                                        'type' => 'numeric',
                                        'message' =>
                                            'Ta wartość powinna
                                            być liczbą całkowitą',
                                    )
                                ),
                                new Assert\GreaterThan(
                                    array(
                                        'value' => '0',
                                        'message' =>
                                            'Wartość powinna
                                            być większa od 0',
                                    )
                                ),
                            )
                        )
                    )
                    ->add(
                        'price', 'money', array(
                            'currency' => 'disabled',
                            'constraints' => array(
                                new Assert\NotBlank(),
                                new Assert\Type(
                                    array(
                                        'type' => 'numeric',
                                        'message' => 'Format ceny: 25.25',
                                    )
                                ),
                                new Assert\GreaterThan(
                                    array(
                                        'value' => '0',
                                        'message' =>
                                            'Wartość powinna
                                            być większa od 0',
                                    )
                                ),
                            )
                        )
                    )
                    ->add(
                        'buysell', 'text', array(
                            'data' => $buysell,
                            'disabled' => true,
                            'constraints' => array(
                                new Assert\NotBlank()
                            )
                        )
                    )
                    ->getForm();

                $form->handleRequest($request);

                if ($form->isValid()) {
                    $formData = $form->getData();

                    if ((int)$offer['buysell'] === 0) {
                        $wallet = $this->_stocks->getWallet($offer['id_user']);

                        $offerValue = $offer['amount'] * $offer['price'];
                        $availableCash = $wallet['cash'] + $offerValue;
                        $formValue = $formData['amount'] * $formData['price'];

                        $cash = ($wallet['cash'] + $offerValue) - $formValue;
                        $blockedCash = ($wallet['blocked_cash'] -
                                ($offerValue)) +
                            ((double)$formValue);

                        if ($availableCash < $formValue &&
                            (int)$offer['id_user'] != 1) {
                            $app['session']->getFlashBag()->add(
                                'message', array(
                                    'type' => 'warning',
                                    'content' =>
                                        'Użytkownik nie ma tylu pieniędzy'
                                )
                            );

                            return $app->redirect(
                                $app['url_generator']->generate(
                                    '/admin/controlpanel'
                                ), 301
                            );
                        } else {
                            try
                            {
                                $this->_stocks->updateWalletAfterEdit(
                                    $cash,
                                    $blockedCash,
                                    $offer['id_user']
                                );


                                $this->_stocks->updateOrderSheetAfterEdit(
                                    $id, $formData
                                );

                                $app['session']->getFlashBag()->add(
                                    'message', array(
                                        'type' => 'success',
                                        'content' => 'Edytowałeś ofertę!'
                                    )
                                );

                                return $app->redirect(
                                    $app['url_generator']->generate(
                                        '/admin/controlpanel'
                                    ), 301
                                );
                            }
                            catch (\Exception $e)
                            {
                                $errors[] = 'Nie udało się edytować';
                            }
                        }
                    } else {

                        $userStocks = $this->_stocks->checkUserStocks(
                            $offer['id_user'],
                            $offer['stock_name']
                        );

                        if ($formData['amount'] >
                            ($offer['amount'] + $userStocks['amount'])) {

                            $app['session']->getFlashBag()->add(
                                'message', array(
                                    'type' => 'warning',
                                    'content' =>
                                        'Użytkownik nie ma tylu akcji!'
                                )
                            );

                            return $app->redirect(
                                $app['url_generator']->generate(
                                    '/admin/controlpanel'
                                ), 301
                            );
                        } else {
                            $this->_stocks->updateUserStocksAfterEdit(
                                $formData,
                                $offer
                            );
                            $this->_stocks->updateOrderSheetAfterEdit(
                                $offer['idorder_sheet'],
                                $formData
                            );

                            return $app->redirect(
                                $app['url_generator']->generate(
                                    '/admin/controlpanel'
                                ), 301
                            );
                        }
                    }
                }
                return $app['twig']->render(
                    '/admin/editoffer.twig', array(
                        'form' => $form->createView()
                    )
                );
            }
        }
    }


    /**
     * Function which deletes offer
     *
     * @param Application $app
     * @param Request     $request
     *
     * @access public
     * @return \Symfony\Component\HttpFoundation\RedirectResponse
     */
    public function deleteOffer(Application $app, Request $request)
    {

        $currentUser = $this->_user->getCurrentUserInfo($app);

        $id = (int)$request->get('id', 0);

        $checkId = $this->_stocks->checkId($id);

        if (!$checkId) {
            $app['session']->getFlashBag()->add(
                'message', array(
                    'type' => 'danger',
                    'content' => 'Nie znaleziono oferty!'
                )
            );
            return $app->redirect(
                $app['url_generator']->generate(
                    '/admin/controlpanel'
                ), 301
            );
        } else {
            $offer = $this->_stocks->getOffer($id);

            if ((int)$offer['realized'] === 1) {
                $app['session']->getFlashBag()->add(
                    'message', array(
                        'type' => 'danger',
                        'content' => 'Nie możesz edytować tej oferty!'
                    )
                );
                return $app->redirect(
                    $app['url_generator']->generate(
                        '/admin/controlpanel'
                    ), 301
                );
            }
            if (count($offer)) {
                $form = $app['form.factory']->createBuilder('form')
                    ->add(
                        'idoffer', 'hidden', array(
                            'data' => $id,
                        )
                    )
                    ->add('Tak', 'submit')
                    ->add('Nie', 'submit')
                    ->getForm();

                $form->handleRequest($request);

                if ($form->isValid()) {
                    if ($form->get('Tak')->isClicked()) {
                        $formData = $form->getData();

                        if ((int)$offer['buysell'] === 0) {
                            $offerValue = $offer['amount'] * $offer['price'];

                            try
                            {
                                if (!$app['security']->
                                    isGranted('ROLE_ADMIN')) {
                                    $this->_stocks->updateWalletAfterDelete(
                                        $offerValue, $currentUser['id']
                                    );
                                } elseif ($offer['id_user'] != 1) {
                                    $this->_stocks->updateWalletAfterDelete(
                                        $offerValue, $offer['id_user']
                                    );
                                }

                                $this->_stocks->deleteOffer($offer);

                                $app['session']->getFlashBag()->add(
                                    'message', array(
                                        'type' => 'danger',
                                        'content' => 'Oferta usunięta!'
                                    )
                                );
                                return $app->redirect(
                                    $app['url_generator']->generate(
                                        '/admin/controlpanel'
                                    ), 301
                                );
                            }
                            catch (\Exception $e)
                            {
                                $errors[] = 'Nie udało się usunąć oferty';
                            }

                        } else {
                            $this->_stocks->updateUserStocksAfterDelete($offer);
                            $this->_stocks->deleteOffer($offer);

                            $app['session']->getFlashBag()->add(
                                'message', array(
                                    'type' => 'danger',
                                    'content' => 'Oferta usunięta!'
                                )
                            );
                            return $app->redirect(
                                $app['url_generator']->generate(
                                    '/admin/controlpanel'
                                ), 301
                            );
                        }
                    } else {
                        return $app->redirect(
                            $app['url_generator']->generate(
                                'admin/controlpanel/'
                            ), 301
                        );
                    }
                }
            }
        }
        return $app['twig']->render(
            'admin/deleteoffer.twig', array(
                'form' => $form->createView()
            )
        );
    }
}
