<?php
/**
 * Stocks controller
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
 * Class StocksController
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
class StocksController implements ControllerProviderInterface
{
    /**
     * StocksModel object.
     *
     * @var $_model
     * @access protected
     */
    protected $_model;

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
        $this->_model = new StocksModel($app);
        $this->_user = new UsersModel($app);
        $authController = $app['controllers_factory'];
        $authController->match('/wallet', array($this, 'wallet'))
            ->bind('/stocks/wallet');
        $authController->match('/addoffer', array($this, 'addoffer'))
            ->bind('/stocks/addoffer');
        $authController->match('/editoffer', array($this, 'editoffer'))
            ->bind('/stocks/editoffer');
        $authController->match('/deleteoffer', array($this, 'deleteoffer'))
            ->bind('/stocks/deleteoffer');
        $authController->match('/controlpanel', array($this, 'controlpanel'))
            ->bind('/stocks/controlpanel');

        return $authController;
    }

    /**
     * Function which checks, if amount from form is less/equal/bigger
     * than from order
     *
     * @param $formAmount
     * @param $orderAmount
     *
     * @return string
     */
    function checkAmount($formAmount, $orderAmount)
    {
        if ((int)$formAmount < (int)$orderAmount) {
            //jest mniej akcji w formularzu niż w zleceniu
            $case = 'less';
        } elseif ((int)$formAmount === (int)$orderAmount) {
            $case = 'equal';
        } elseif ((int)$formAmount > (int)$orderAmount) {
            $case = 'more';
        }
        return $case;
    }

    /**
     * Function which updates user cash in wallet.
     *
     * @param $formData
     * @param $currentUser
     */
    function updateUserCash($formData, $currentUser)
    {
        (int)$cash = $formData['amount'] * $formData['price'];
        $this->_model->updateUserCash($cash, $currentUser);
    }

    /**
     * Display user his wallet and stocks.
     *
     * @param Application $app     application object
     * @param Request     $request request
     *
     * @access public
     * @return mixed Generates page.
     */
    public function wallet(Application $app)
    {
        $currentUser = $this->_user->getCurrentUserInfo($app);

        if ((int)$currentUser['id'] !== 1) {
            $wallet = $this->_model->getWallet($currentUser['id']);

            $userStocks = $this->_model->getUserStocks($currentUser['id']);

            return $app['twig']->render(
                'stocks/wallet.twig', array(
                    'wallet' => $wallet,
                    'user_stocks' => $userStocks,
                )
            );
        } else {
            return $app->redirect(
                $app['url_generator']->generate(
                    '/index'
                ), 301
            );
        }

    }

    /**
     * Function which allows user to add his offer.
     *
     * @param Application $app
     * @param Request $request
     *
     * @access public
     * @return mixed Generate page or redirect
     */
    public function addOffer(Application $app, Request $request)
    {

        $currentUser = $this->_user->getCurrentUserInfo($app);

        if ((int)$currentUser['id'] !== 1) {
            $wallet = $this->_model->getWallet($currentUser['id']);

            $userStocks = $this->_model->getUserStocks($currentUser['id']);

            $orderSheetOfCurrentUser = $this->_model
                ->getOrderSheetOfCurrentUser(
                    $currentUser['id']
                );
            //var_dump($orderSheetOfCurrentUser);

            $historyOfOrders = $this->_model
                ->getHistoryOfOrders($currentUser['id']);

            $data = array();

            $stocks = $this->_model->getAllStocks();
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
                        'label' => 'Ilość',
                        'constraints' => array(
                        new Assert\NotBlank(),
                        new Assert\Type(
                            array(
                                'type' => 'numeric',
                                'message' => 'Podaj liczbę całkowita',
                            )
                        ),
                        new Assert\GreaterThan(
                            array(
                                'value' => '0',
                                'message' => 'Value should be greater than 0',
                            )
                        ),
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
                                'message' => 'Value should be greater than 0',
                            )
                        ),
                    )
                )
                )
                ->add(
                    'buysell', 'choice', array(
                        'label' => 'Rodzaj oferty',
                        'choices' => array(
                            0 => 'kupno',
                            1 => 'sprzedaż'
                    ),
                    'multiple' => false,
                    'constraints' => array(
                        new Assert\NotBlank()
                    )
                )
                )
                ->getForm();

            $form->handleRequest($request);

            if ($form->isValid()) {

                $formData = $form->getData();
                $date = date("Y-m-d H:i:s");

                if ($formData['buysell'] === 0) {

                    if ($formData['price'] * $formData['amount'] >
                        $wallet['cash']) {
                        $app['session']->getFlashBag()->add(
                            'message', array(
                            'type' => 'warning',
                            'content' => 'Nie masz tylu pieniędzy'
                            )
                        );
                    } else {
                        $orderSheet = $this->_model->searchOrderSheet(
                            $formData, $currentUser['id']
                        );

                        if (!$orderSheet) {
                            try
                            {
                                $this->_model->addOffer(
                                    $formData, $date, $currentUser['id']
                                );
                                $this->updateUserCash(
                                    $formData, $currentUser['id']
                                );

                                $app['session']->getFlashBag()->add(
                                    'message', array(
                                        'type' => 'success',
                                        'content' => 'Oferta została dodana!'
                                    )
                                );

                                return $app->redirect(
                                    $app['url_generator']->generate(
                                        '/stocks/addoffer'
                                    ), 301
                                );
                            }
                            catch (\Exception $e)
                            {
                                $errors[] = 'Nie udało się dodać oferty';
                            }
                        } else {
                            $checkAmount = $this->checkAmount(
                                $formData['amount'], $orderSheet['amount']
                            );

                            try
                            {
                                $this->_model->makeBuy(
                                    $formData,
                                    $orderSheet,
                                    $checkAmount,
                                    $currentUser,
                                    $date
                                );
                            }
                            catch (\Exception $e)
                            {
                                $errors[] =
                                    'Nie udało się
                                    przeprowadzić akcji kupna';
                            }

                            if ($checkAmount == 'more') {
                                $boughtAmount = $orderSheet['amount'];
                                $amountToBuy = $formData['amount'] -
                                    $orderSheet['amount'];

                                $app['session']->getFlashBag()->add(
                                    'message', array(
                                        'type' => 'warning',
                                        'content' =>
                                            'Kupiłeś '
                                            . $boughtAmount .
                                            ' akcji. Jeśli chcesz jeszcze '
                                            . $amountToBuy .
                                            ' dodaj kolejną ofertę!'
                                    )
                                );
                            } else {
                                $app['session']->getFlashBag()->add(
                                    'message', array(
                                        'type' => 'success',
                                        'content' => 'Właśnie kupiłeś '
                                            . $formData['amount'] .
                                            ' akcji '
                                            . $formData['stock_name'] .
                                            ' za '
                                            . $formData['price'] .
                                            ' PLN.'
                                    )
                                );
                                return $app->redirect(
                                    $app['url_generator']->generate(
                                        '/stocks/wallet'
                                    ), 301
                                );
                            }
                        }
                    }
                } else {

                    $ownedStock = $this->_model->searchUserStocks(
                        $formData,
                        $currentUser
                    );

                    //jesli nie posiada akcji
                    if (!$ownedStock) {
                        $app['session']->getFlashBag()->add(
                            'message', array(
                                'type' => 'warning',
                                'content' => 'Nie masz akcji'
                                 . $formData['stock_name'] .
                                 '.'
                            )
                        );
                    } else {

                        //jesli chce sprzedac wiecej niz ma
                        if ($formData['amount'] > $ownedStock['amount']) {
                            $app['session']->getFlashBag()->add(
                                'message', array(
                                    'type' => 'warning',
                                    'content' => 'Nie masz az tylu akcji!'
                                )
                            );
                        } else {//jesli chce sprzedac tyle ile ma
                            $orderSheet = $this->_model->searchOrderSheet(
                                $formData, $currentUser['id']
                            );

                            if (!$orderSheet) {
                                try
                                {
                                    $this->_model->addOffer(
                                        $formData,
                                        $date,
                                        $currentUser['id']
                                    );

                                    $this->_model->blockStocks(
                                        $formData,
                                        $currentUser['id']
                                    );

                                    $app['session']->getFlashBag()->add(
                                        'message', array(
                                            'type' => 'success',
                                            'content' => 'Dodałeś '
                                                . $formData['amount'] .
                                                ' akcji '
                                                . $formData['stock_name'] .
                                                'do arkusza!'
                                        )
                                    );
                                    return $app->redirect(
                                        $app['url_generator']->generate(
                                            '/stocks/addoffer'
                                        ), 301
                                    );
                                }
                                catch (\Exception $e)
                                {
                                    $errors[] =
                                        'Nie udało się dodać oferty';
                                }


                            } else { //jesli istnieje zlecenie kupna

                                $checkAmount = $this->checkAmount(
                                    (int)$formData['amount'],
                                    (int)$orderSheet['amount']
                                );

                                try
                                {
                                    $this->_model->makeSell(
                                        $formData,
                                        $orderSheet,
                                        $checkAmount,
                                        $currentUser,
                                        $ownedStock,
                                        $date,
                                        $formData['price'],
                                        $orderSheet['stock_name']
                                    );
                                }
                                catch (\Exception $e)
                                {
                                    $errors[] =
                                        'Nie udało przeprowadzić
                                        akcji sprzedaży';
                                }



                                if ($checkAmount == 'more') {
                                    $boughtAmount = $orderSheet['amount'];
                                    $amountToBuy = $formData['amount'] -
                                        $orderSheet['amount'];

                                    $app['session']->getFlashBag()->add(
                                        'message', array(
                                            'type' => 'warning',
                                            'content' =>
                                                'Sprzedałeś '
                                                . $boughtAmount .
                                                ' akcji '
                                                . $formData['stock_name'] .
                                                '. Jeśli chcesz kolejne'
                                                . $amountToBuy .
                                                ' dodaj następną ofertę!'
                                        )
                                    );
                                } else {
                                    $app['session']->getFlashBag()->add(
                                        'message', array(
                                            'type' => 'success',
                                            'content' => 'Sprzedałeś '
                                                . $formData['amount'] .
                                                ' akcji '
                                                . $formData['stock_name'] .
                                                ' za '
                                                . $formData['price'] .
                                                ' PLN.'
                                        )
                                    );
                                    return $app->redirect(
                                        $app['url_generator']->generate(
                                            '/stocks/wallet'
                                        ), 301
                                    );
                                }
                            }
                        }
                    }
                }
            }

            return $app['twig']->render(
                'stocks/addoffer.twig', array(
                    'form' => $form->createView(),
                    'order_sheet' => $orderSheetOfCurrentUser,
                    'user_stocks' => $userStocks,
                    'history_of_orders' => $historyOfOrders,
                    'wallet' => $wallet,
                )
            );
        } else {
            return $app->redirect(
                $app['url_generator']->generate(
                    '/index'
                ), 301
            );
        }
    }

    /**
     * Function allows user to edit his offer.
     *
     * @param Application $app
     * @param Request     $request
     *
     * @access public
     * @return mixed Generate page or redirect
     */
    public function editOffer(Application $app, Request $request)
    {
        $currentUser = $this->_user->getCurrentUserInfo($app);
        $id = (int)$request->get('id', 0);

        $offer = $this->_model->getOffer($id);

        if (((int)$offer['id_user']) != $currentUser['id']
            || (int)$offer['realized'] === 1) {
            $app['session']->getFlashBag()->add(
                'message', array(
                    'type' => 'danger',
                    'content' => 'Nie możesz edytować tej oferty!'
                )
            );
            return $app->redirect(
                $app['url_generator']->generate(
                    '/stocks/addoffer'
                ), 301
            );
        }

        if ((int)$currentUser['id'] !== 1) {

            $checkId = $this->_model->checkId($id);

            if (!$checkId) {
                $app['session']->getFlashBag()->add(
                    'message', array(
                        'type' => 'danger',
                        'content' => 'Nie znaleziono oferty!'
                    )
                );
                return $app->redirect(
                    $app['url_generator']->generate(
                        '/stocks/addoffer'
                    ), 301
                );
            } else {

                if ($offer['buysell'] == 0) {
                    $buysell = 'Kupno';
                } else {
                    $buysell = 'Sprzedaż';
                }

                if (count($offer)) {
                    $form = $app['form.factory']->createBuilder(
                        'form'
                    )
                    ->add(
                        'id', 'hidden', array(
                            'data' => $id,
                        )
                    )
                    ->add(
                        'stock_name', 'text', array(
                                'label' => 'Nazwa akcji',
                                'data' => $offer['stock_name'],
                            'disabled' => true
                        )
                    )
                    ->add(
                        'amount', 'integer', array(
                                'label' => 'Ilość',
                                'constraints' => array(
                                new Assert\NotBlank(),
                                new Assert\Type(
                                    array(
                                        'type' => 'numeric',
                                        'message' =>
                                            'Wartość powinna być
                                            liczbą całkowitą',
                                    )
                                ),
                                new Assert\GreaterThan(
                                    array(
                                        'value' => '0',
                                        'message' =>
                                            'Wartość musi być większa od 0',
                                    )
                                ),
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
                                        'message' => 'Wartość powinna
                                        być większa od 0',
                                    )
                                ),
                            )
                        )
                    )
                    ->add(
                        'buysell', 'text', array(
                                'label' => 'Rodzaj oferty',
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
                            if (!$app['security']->isGranted('ROLE_ADMIN')) {
                                $wallet = $this->_model->getWallet(
                                    $currentUser['id']
                                );
                            } else {
                                $wallet = $this->_model->getWallet(
                                    $offer['id_user']
                                );
                            }

                            $offerValue = $offer['amount'] * $offer['price'];
                            $availableCash = $wallet['cash'] + $offerValue;
                            $formValue =
                                $formData['amount'] * $formData['price'];

                            $cash = ($wallet['cash'] + $offerValue)
                                - $formValue;
                            $blockedCash = ($wallet['blocked_cash'] -
                                    ($offerValue)) +
                                ((double)$formValue);

                            if ($availableCash < $formValue) {

                                $app['session']->getFlashBag()->add(
                                    'message', array(
                                        'type' => 'warning',
                                        'content' => 'Nie masz tylu pieniedzy'
                                    )
                                );

                                return $app->redirect(
                                    $app['url_generator']->generate(
                                        '/stocks/addoffer'
                                    ), 301
                                );
                            } else {
                                try
                                {
                                    if (!$app['security']->
                                        isGranted('ROLE_ADMIN')
                                    ) {
                                        $this->_model->updateWalletAfterEdit(
                                            $cash,
                                            $blockedCash,
                                            $currentUser['id']
                                        );
                                    } elseif ($offer['id_user'] != 1) {
                                        $this->_model->updateWalletAfterEdit(
                                            $cash,
                                            $blockedCash,
                                            $offer['id_user']
                                        );
                                    }

                                    $this->_model->updateOrderSheetAfterEdit(
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
                                            '/stocks/addoffer'
                                        ), 301
                                    );
                                }
                                catch (\Exception $e)
                                {
                                    $errors[] =
                                        'Nie udało się edytować oferty';
                                }
                            }
                        } else {
                            $ownedStocks = $this->_model->searchUserStocks(
                                $offer,
                                $currentUser
                            );
                            if ((int)$formData['amount'] >
                                ((int)$ownedStocks['amount']
                                + (int)$offer['amount'])) {

                                $app['session']->getFlashBag()->add(
                                    'message', array(
                                        'type' => 'success',
                                        'content' => 'Nie masz tylu akcji!'
                                    )
                                );
                            } else {

                                try
                                {
                                    $this->_model->updateOrderSheetAfterEdit(
                                        $id,
                                        $formData
                                    );

                                    $this->_model->updateUserStocksAfterEdit(
                                        $formData,
                                        $offer
                                    );
                                    $app['session']->getFlashBag()->add(
                                        'message', array(
                                            'type' => 'success',
                                            'content' =>
                                                'Oferta sprzedaży zmieniona'
                                        )
                                    );

                                    return $app->redirect(
                                        $app['url_generator']->generate(
                                            '/stocks/addoffer'
                                        ), 301
                                    );
                                }
                                catch (\Exception $e)
                                {
                                    $errors[] = 'Nie udało
                                    się zaktualizować';
                                }

                            }
                        }
                    }
                    return $app['twig']->render(
                        'stocks/editoffer.twig', array(
                            'form' => $form->createView()
                        )
                    );
                }
            }
        } else {
            return $app->redirect(
                $app['url_generator']->generate(
                    '/index'
                ), 301
            );
        }
    }

    /**
     * Function allows user to delete his offer.
     *
     * @param Application $app
     * @param Request     $request
     *
     * @return mixed Generate page or redirect
     */
    public function deleteOffer(Application $app, Request $request)
    {
        $currentUser = $this->_user->getCurrentUserInfo($app);

        $id = (int)$request->get('id', 0);

        $checkId = $this->_model->checkId($id);
        $offer = $this->_model->getOffer($id);

        if (((int)$offer['id_user']) != $currentUser['id']
            || (int)$offer['realized'] === 1) {
            $app['session']->getFlashBag()->add(
                'message', array(
                    'type' => 'danger',
                    'content' => 'Nie możesz edytować tej oferty!'
                )
            );
            return $app->redirect(
                $app['url_generator']->generate(
                    '/stocks/addoffer'
                ), 301
            );
        }

        if (!$checkId) {
            $app['session']->getFlashBag()->add(
                'message', array(
                    'type' => 'danger',
                    'content' => 'Nie znaleziono oferty!'
                )
            );
            return $app->redirect(
                $app['url_generator']->generate(
                    '/stocks/addoffer'
                ), 301
            );
        } else {

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
                                    $this->_model->updateWalletAfterDelete(
                                        $offerValue,
                                        $currentUser['id']
                                    );
                                } elseif ($offer['id_user'] != 1) {
                                    $this->_model->updateWalletAfterDelete(
                                        $offerValue,
                                        $offer['id_user']
                                    );
                                }

                                $this->_model->deleteOffer($offer);

                                $app['session']->getFlashBag()->add(
                                    'message', array(
                                        'type' => 'danger',
                                        'content' => 'Oferta usunięta!'
                                    )
                                );
                                return $app->redirect(
                                    $app['url_generator']->generate(
                                        '/stocks/addoffer'
                                    ), 301
                                );
                            }
                            catch (\Exception $e)
                            {
                                $errors[] = 'Something went wrong';
                            }
                        } else {
                            $this->_model->updateUserStocksAfterDelete($offer);
                            $this->_model->deleteOffer($offer);

                            $app['session']->getFlashBag()->add(
                                'message', array(
                                    'type' => 'danger',
                                    'content' => 'Oferta usunięta!'
                                )
                            );

                            return $app->redirect(
                                $app['url_generator']->generate(
                                    '/stocks/addoffer'
                                ), 301
                            );
                        }

                    } else {
                        return $app->redirect(
                            $app['url_generator']->generate(
                                '/stocks/addoffer'
                            ), 301
                        );
                    }
                }
            }
        }
        return $app['twig']->render(
            'stocks/deleteoffer.twig', array(
                'form' => $form->createView()
            )
        );

    }
}