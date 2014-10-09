<?php
/**
 * Stocks model
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

use Silex\Application;

/**
 * Class StocksModel
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
class StocksModel
{
    /**
     * Database access object.
     *
     * @access protected
     * @var $_db Doctrine\DBAL
     */
    protected $_db;

    /**
     * Class constructor.
     *
     * @param Application $app Silex application object
     *
     * @access public
     */
    public function __construct(Application $app)
    {
        $this->_db = $app['db'];
    }

    /**
     * Gets wallet logged user
     *
     * @param Integer $id
     * @access public
     * @return mixed
     */
    public function getWallet($id)
    {
        $query = 'SELECT * FROM `wallet` WHERE `idwallet` = ?';
        return $this->_db->fetchAssoc($query, array($id));
    }

    /**
     * @param Integer $id
     * @access public
     * @return mixed
     */
    public function getUserStocks($id)
    {
        $query = 'SELECT * FROM `user_stocks` WHERE `id_user` = ?';
        return $this->_db->fetchAll($query, array($id));
    }

    /**
     * Gets orders from order sheet logged user
     *
     * @param Integer $id
     * @access public
     * @return mixed
     */
    public function getOrderSheetOfCurrentUser($id)
    {
        $query = 'SELECT * FROM `order_sheet`
        WHERE `id_user` = ?
        AND `realized` != ?';
        $return = $this->_db->fetchAll($query, array($id, 1));

        return $return;
    }

    /**
     * Gets all order sheet
     *
     * @access public
     * @return mixed
     */
    public function getOrderSheet()
    {
        $query = 'SELECT * FROM `order_sheet`';
        return $this->_db->fetchAll($query);
    }


    /**
     * Function which search order sheet to find contrary offer.
     *
     * @param $formData
     * @param $loggedUserId
     *
     * @access public
     * @return array
     */
    public function searchOrderSheet($formData, $loggedUserId)
    {
        $break = false;
        $orderSheet = $this->getOrderSheet();

        foreach ($orderSheet as $key => $row) {
            $id = ($orderSheet[$key]['idorder_sheet'] = $row['idorder_sheet']);
            $name = ($orderSheet[$key]['stock_name'] = $row['stock_name']);
            (int)$buysell = ($orderSheet[$key]['buysell'] = $row['buysell']);
            $amount = ($orderSheet[$key]['amount'] = $row['amount']);
            $realizedAmount = (
            $orderSheet[$key]['realized_amount'] = $row['realized_amount']
            );
            $price = ($orderSheet[$key]['price'] = $row['price']);
            $idUser = ($orderSheet[$key]['id_principal'] = $row['id_user']);
            $realized = ($orderSheet[$key]['realized'] = $row['realized']);

            if ($name == $formData['stock_name'] &&
                ((int)$buysell) !== $formData['buysell'] &&
                $price == $formData['price'] &&
                $idUser != $loggedUserId &&
                $realized != '1'
            ) {
                $order = array(
                    'idorder_sheet' => $id,
                    'stock_name' => $name,
                    'buysell' => $buysell,
                    'amount' => $amount,
                    'realized_amount' =>$realizedAmount,
                    'price' => $price,
                    'id_user' => $idUser,
                    'realized' => $realized
                );
                $break = true;
            }
            if ($break) {
                break;
            }
        }
        if($order) {
            return $order;
        }
    }

    /**
     * Gets history of orders of logged user
     *
     * @param Integer $currentUser
     * @access public
     * @return mixed
     */
    public function getHistoryOfOrders($currentUser)
    {
        $query = 'SELECT * FROM `order_sheet`
        WHERE `id_user` = ?
        AND `realized` = ?';
        return $this->_db->fetchAll($query, array($currentUser, 1));
    }

    /**
     * Function changes cash in user's wallet
     *
     * @param $cash
     * @param $currentUser
     * @access public
     * @return void
     */
    public function updateUserCash($cash, $currentUser)
    {
        $query = 'UPDATE `wallet`
        SET `cash`= `cash` - ?, `blocked_cash`= `blocked_cash` + ?
        WHERE `idwallet`= ?';
        $this->_db->executeQuery($query, array($cash, $cash, $currentUser));
    }

    /**
     * Updates cash in wallet both users who took part in transaction
     *
     * @param $formdata
     * @param $valueOfOrder
     * @param $orderSheet
     * @param $currentUser
     * @access public
     * @return void
     */
    public function updateCash(
        $formData, $orderSheet, $checkAmount, $currentUser
    )
    {
        if ($checkAmount == 'less' || $checkAmount == 'equal') {
            $valueOfOrder = $formData['amount'] * $formData['price'];
        } else {
            $valueOfOrder = $orderSheet['amount'] * $orderSheet['price'];
        }

        $adminId = 1;
        if ($formData['buysell'] === 0) {
            $query = 'UPDATE `wallet`
                      SET `cash`= `cash` - ?
                      WHERE `idwallet`= ?';
            $queryTwo = 'UPDATE `wallet`
                       SET `cash`= `cash` + ?
                       WHERE `idwallet`= ?';

            if ($currentUser['id'] != (int)$adminId) {
                $this->_db->executeQuery(
                    $query, array(
                        $valueOfOrder,
                        (int)$currentUser['id']
                    )
                );
            }
            $this->_db->executeQuery(
                $queryTwo, array(
                    $valueOfOrder,
                    (int)$orderSheet['id_user']
                )
            );
        } else {
            $query = 'UPDATE `wallet`
                      SET `cash`= `cash` + ?
                      WHERE `idwallet`= ?';
            $queryTwo = 'UPDATE `wallet`
                       SET `blocked_cash` = `blocked_cash` - ?
                       WHERE `idwallet`= ?';

            $this->_db->executeQuery(
                $query, array(
                    $valueOfOrder,
                    (int)$currentUser['id']
                )
            );
            if ($currentUser['id'] != $adminId) {
                $this->_db->executeQuery(
                    $queryTwo, array(
                        $valueOfOrder,
                        (int)$orderSheet['id_user']
                    )
                );
            }
        }
    }

    /**
     * Checks, if user has stocks in his wallet.
     *
     * @param $currentUserId
     * @param $stockName
     *
     * @access public
     * @return mixed Array
     */
    public function checkUserStocks($currentUserId, $stockName)
    {
        $query = 'SELECT *
                  FROM user_stocks
                  WHERE `stock_name` = ?
                  AND `id_user` = ?';
        $row = $this->_db->fetchAssoc(
            $query, array(
                $stockName,
                $currentUserId
            )
        );
        return $row;
    }

    /**
     * Updates stocks after buying
     *
     * @param $formData
     * @param $orderSheet
     * @param $currentUser
     * @param $date
     * @param $checker
     *
     * @access public
     * @return mixed
     */
    public function updateBuyerStocks(
        $formData, $orderSheet, $currentUser, $date, $checker
    )
    {
        if ((int)$formData['buysell'] === 0) {
            $blockedZero = 0;

            $userStock = $this->checkUserStocks(
                $currentUser['id'],
                $formData['stock_name']
            );

            if (!$userStock) {
                $query = 'INSERT INTO `user_stocks`(
                `stock_name`,
                `amount`,
                `blocked_stocks`,
                `purchase_price`,
                `id_user`,
                `datetime`,
                `value`
                )
                VALUES (?, ?, ?, ?, ?, ?, ?)';

                if ($checker == 'less') {
                    $this->_db->executeQuery(
                        $query, array(
                            $formData['stock_name'],
                            $formData['amount'],
                            $blockedZero,
                            $formData['price'],
                            $currentUser['id'],
                            $date,
                            $formData['amount']*$formData['price']
                        )
                    );
                } else {
                    $this->_db->executeQuery(
                        $query, array(
                            $orderSheet['stock_name'],
                            $orderSheet['amount'],
                            $blockedZero,
                            $orderSheet['price'],
                            $currentUser['id'],
                            $date,
                            $orderSheet['amount']*$orderSheet['price']
                        )
                    );
                }
            } else {
                if ($checker == 'less') {
                    $value = $formData['amount'] * $formData['price']
                        + $userStock['value'];
                    $price = $value / ((int)$formData['amount'] +
                            (int)$userStock['amount'] +
                            (int)$userStock['blocked_stocks']);
                    $query = 'UPDATE `user_stocks`
                          SET `amount`= `amount` + ?,
                              `purchase_price`= ?,
                              `value`= ?,
                              `datetime` = ?
                          WHERE `stock_name`= ? AND `id_user` = ?';
                    $this->_db->executeQuery(
                        $query, array(
                            $formData['amount'],
                            $price,
                            $value,
                            $date,
                            $formData['stock_name'],
                            $currentUser['id']
                        )
                    );
                } else {
                    $value = $formData['amount'] * $formData['price']
                        + $userStock['value'];
                    $price = $value / ((int)$formData['amount'] +
                            (int)$userStock['amount'] +
                            (int)$userStock['blocked_stocks']);

                    $query = 'UPDATE `user_stocks`
                          SET `amount`= `amount` + ?,
                              `purchase_price`= ?,
                              `value`= `value` + ?,
                              `datetime` = ?
                          WHERE `stock_name`= ?
                          AND `id_user` = ?';
                    $this->_db->executeQuery(
                        $query, array(
                            $orderSheet['amount'],
                            $price,
                            $orderSheet['price']*$orderSheet['amount'],
                            $date,
                            $orderSheet['stock_name'],
                            $currentUser['id']
                        )
                    );
                }
            }
            return $userStock;
        } else {
            $blockedZero = 0;

            $userStock = $this->checkUserStocks(
                $orderSheet['id_user'],
                $formData['stock_name']
            );

            if (!$userStock) {
                $query = 'INSERT INTO `user_stocks`(
                `stock_name`,
                `amount`,
                `blocked_stocks`,
                `purchase_price`,
                `id_user`,
                `datetime`,
                `value`
                )
                VALUES (?, ?, ?, ?, ?, ?, ?)';

                if ($checker == 'less') {
                    $this->_db->executeQuery(
                        $query, array(
                            $formData['stock_name'],
                            $formData['amount'],
                            $blockedZero,
                            $formData['price'],
                            $orderSheet['id_user'],
                            $date,
                            $formData['amount']*$formData['price']
                        )
                    );
                } else {
                    $this->_db->executeQuery(
                        $query, array(
                            $orderSheet['stock_name'],
                            $orderSheet['amount'],
                            $blockedZero,
                            $orderSheet['price'],
                            $orderSheet['id_user'],
                            $date,
                            $orderSheet['amount']*$orderSheet['price']
                        )
                    );
                }
            } else {
                if ($checker == 'less') {
                    $value = $formData['amount'] * $formData['price']
                        + $userStock['value'];
                    $price = $value /
                        ($formData['amount'] + $userStock['amount']);
                    $query = 'UPDATE `user_stocks`
                          SET `amount`= `amount` + ?,
                              `purchase_price`= ?,
                              `value`= ?,
                              `datetime` = ?
                          WHERE `stock_name`= ? AND `id_user` = ?';
                    $this->_db->executeQuery(
                        $query, array(
                            $formData['amount'],
                            $price,
                            $value,
                            $date,
                            $formData['stock_name'],
                            $orderSheet['id_user']
                        )
                    );
                } else {
                    $value = $formData['amount'] * $formData['price']
                        + $userStock['value'];
                    $price = $value /
                        ($formData['amount'] + $userStock['amount']);

                    $query = 'UPDATE `user_stocks`
                          SET `amount`= `amount` + ?,
                              `purchase_price`= ?,
                              `value`= ?,
                              `datetime` = ?
                          WHERE `stock_name`= ?
                          AND `id_user` = ?';
                    $this->_db->executeQuery(
                        $query, array(
                            $orderSheet['amount'],
                            $price,
                            $value,
                            $date,
                            $orderSheet['stock_name'],
                            $orderSheet['id_user']
                        )
                    );
                }
            }
            return $userStock;
        }

    }

    public function deleteFromSellerStocks($ownedStocks)
    {
        $query = 'DELETE FROM `user_stocks`
                  WHERE `stock_name` = ? AND `id_user` = ?';
        $this->_db->executeQuery(
            $query, array(
                $ownedStocks['stock_name'],
                $ownedStocks['id_user']
            )
        );
    }

    public function searchSellerStocks($orderSheet)
    {
        $query = 'SELECT * FROM `user_stocks`
                  WHERE `stock_name` = ?
                  AND `id_user` = ?';
        $return = $this->_db->fetchAssoc(
            $query, array(
                $orderSheet['stock_name'],
                (int)$orderSheet['id_user']
            )
        );

        if ((int)$return['amount'] === 0 &&
            (int)$return['blocked_stocks'] === 0) {
            $this->deleteFromSellerStocks($return);
        }
    }

    /**
     * Function updates seller stocks after transaction
     *
     * @param $formData
     * @param $orderSheet
     * @param $checker
     *
     * @access public
     * @return mixed
     */
    public function updateSellerStocks($formData, $orderSheet, $checker)
    {
        if ($orderSheet['id_user'] != '1') {
            if ($checker == 'less') {
                $query = 'UPDATE `user_stocks`
                          SET `blocked_stocks`= `blocked_stocks` - ?,
                          `value` = `amount` +
                          `blocked_stocks` * `purchase_price`,
                          `purchase_price` = `value` /
                          (`blocked_stocks` + `amount`)
                          WHERE `id_user`= ?';
                $this->_db->executeQuery(
                    $query, array(
                        $formData['amount'],
                        $orderSheet['id_user']
                    )
                );
            } else {

                $query = 'UPDATE `user_stocks`
                          SET `blocked_stocks`= `blocked_stocks` - ?,
                              `value` = `amount` + `blocked_stocks` *
                              `purchase_price`,
                              `purchase_price` = `value` /
                              (`blocked_stocks` + `amount`)
                          WHERE `id_user`= ?';
                $this->_db->executeQuery(
                    $query, array(
                        $orderSheet['amount'],
                        $orderSheet['id_user']
                    )
                );

            }
        } else {
            return false;
        }
    }

    /**
     * Adds realized amount of stocks to order sheet
     *
     * @param $formData
     * @param $orderSheet
     * @param $currentUser
     * @param $checkAmount
     * @param $date
     *
     * @access public
     */
    public function addRealizedToOrderSheet(
        $formData, $orderSheet, $currentUser, $checkAmount, $date
    )
    {
        if ($formData['buysell'] === 0) {
            if ($checkAmount == 'less') {
                $query = 'INSERT INTO `order_sheet`(
                        `stock_name`,
                        `buysell`,
                        `realized_amount`,
                        `price`,
                        `datetime`,
                        `id_user`,
                        `realized`
                        )
                      VALUES (?, ?, ?, ?, ?, ?, ?)';
                $this->_db->executeQuery(
                    $query, array(
                        $formData['stock_name'],
                        $formData['buysell'],
                        $formData['amount'],
                        $formData['price'],
                        $date,
                        $currentUser['id'],
                        1,
                    )
                );

            } else {
                $query = 'INSERT INTO `order_sheet`(
                      `stock_name`,
                      `buysell`,
                      `realized_amount`,
                      `price`,
                      `datetime`,
                      `id_user`,
                      `realized`
                      )
                      VALUES (?, ?, ?, ?, ?, ?, ?)';
                $this->_db->executeQuery(
                    $query, array(
                        $orderSheet['stock_name'],
                        $formData['buysell'],
                        $orderSheet['amount'],
                        $orderSheet['price'],
                        $date,
                        $currentUser['id'],
                        1,
                    )
                );

            }
        } else {
            if ($checkAmount === 'less') {
                $query = 'INSERT INTO `order_sheet`(
                      `stock_name`,
                      `buysell`,
                      `realized_amount`,
                      `price`,
                      `datetime`,
                      `id_user`,
                      `realized`
                      )
                      VALUES (?, ?, ?, ?, ?, ?, ?)';
                $this->_db->executeQuery(
                    $query, array(
                        $formData['stock_name'],
                        $formData['buysell'],
                        $formData['amount'],
                        $formData['price'],
                        $date,
                        $currentUser['id'],
                        1,
                    )
                );
            } else {
                $query = 'INSERT INTO `order_sheet`(
                      `stock_name`,
                      `buysell`,
                      `realized_amount`,
                      `price`,
                      `datetime`,
                      `id_user`,
                      `realized`
                      )
                      VALUES (?, ?, ?, ?, ?, ?, ?)';
                $this->_db->executeQuery(
                    $query, array(
                        $orderSheet['stock_name'],
                        $formData['buysell'],
                        $orderSheet['amount'],
                        $orderSheet['price'],
                        $date,
                        $currentUser['id'],
                        1,
                    )
                );
            }
        }
    }

    /**
     * Updates order sheet
     *
     * @param $formData
     * @param $orderSheet
     * @param $checkAmount
     *
     * @access public
     *
     * @return void
     */
    public function updateOrderSheet($formData, $orderSheet, $checkAmount)
    {
        if ($checkAmount == 'less') {
            $query = 'UPDATE `order_sheet`
                                SET `amount`= `amount` - ?,
                                    `realized_amount`= `realized_amount` + ?
                                WHERE `idorder_sheet`= ?';
            $this->_db->executeQuery(
                $query, array(
                    $formData['amount'],
                    $formData['amount'],
                    (int)$orderSheet['idorder_sheet']
                )
            );
        } else {
            $query = 'UPDATE `order_sheet`
                      SET `amount`= `amount` - ?,
                          `realized_amount`= `realized_amount` + ?,
                          `realized` = ?
                      WHERE `idorder_sheet`= ?';
            $this->_db->executeQuery(
                $query, array(
                    $orderSheet['amount'],
                    $orderSheet['amount'],
                    1,
                    (int)$orderSheet['idorder_sheet']
                )
            );
        }

    }

    /**
     * Updates main sheet after transaction.
     *
     * @param $price
     * @param $name
     *
     * @access public
     * @return void
     */
    public function updatePriceInMainSheet($formData, $orderSheet)
    {
        $query = 'UPDATE `stocks`
                  SET `current_price`= ?
                  WHERE `stock_name`= ?';
        $this->_db->executeQuery(
            $query, array(
                $formData['price'], $orderSheet['stock_name']
            )
        );
    }


    /**
     * Adds offer to order sheet when there is no contrary offer
     *
     * @param $data
     * @param $datetime
     * @param $id
     *
     * @access public
     * @return void
     */
    public function addOffer($data, $datetime, $id)
    {
        $query = 'INSERT INTO `order_sheet`
                 (`stock_name`,
                  `buysell`,
                  `amount`,
                  `realized_amount`,
                  `price`, `datetime`,
                  `id_user`,
                  `realized`
                  )
        VALUES (?, ?, ?, 0, ?, ?, ?, 0)';
        $this->_db->executeQuery(
            $query, array(
                $data['stock_name'],
                $data['buysell'],
                $data['amount'],
                $data['price'],
                $datetime,
                $id
            )
        );

    }

    /**
     * Checks offer id if exist.
     *
     * @param $id
     * @access public
     * @return bool
     */
    public function checkId($id)
    {
        $query = 'SELECT * FROM order_sheet
                  WHERE `idorder_sheet` = ?';
        $return = $this->_db->fetchAssoc(
            $query,
            array(
                $id
            )
        );
        if (!$return) {
            return false;
        } else {
            return true;
        }
    }

    /**
     * Gets offer to edit
     *
     * @param $id
     * @acces public
     * @return mixed
     */
    public function getOffer($id)
    {
        $query = 'SELECT * FROM `order_sheet`
                  WHERE `idorder_sheet` = ? LIMIT 1';
        $return = $this->_db->fetchAssoc(
            $query,
            array(
                $id
            )
        );
        return $return;
    }

    /**
     * Updates wallet of user after editing his offer
     *
     * @param $cash
     * @param $blockedCash
     * @param $currentUser
     *
     * @access public
     * @return void
     */
    public function updateWalletAfterEdit($cash, $blockedCash, $currentUser)
    {
        $query = 'UPDATE `wallet`
                  SET `cash`= ?, `blocked_cash`= ?
                  WHERE `idwallet`= ?';
        $this->_db->executeQuery(
            $query, array(
                $cash,
                $blockedCash,
                $currentUser
            )
        );
    }

    /**
     * Updates order sheet after edit
     *
     * @param $id
     * @param $formData
     * @access public
     * @return void
     */
    public function updateOrderSheetAfterEdit($id, $formData)
    {
        $query = 'UPDATE `order_sheet`
                  SET `amount`= ?, `price`= ?
                  WHERE `idorder_sheet`= ?';
        $this->_db->executeQuery(
            $query, array(
                $formData['amount'],
                $formData['price'],
                $id
            )
        );
    }

    public function updateUserStocksAfterEdit(
        $formData,
        $orderSheet
    )
    {
            $query = 'UPDATE `user_stocks`
                      SET `amount`= (`amount` + ?) - ?,
                      `blocked_stocks`= (`blocked_stocks` - ?) + ?
                      WHERE `stock_name` = ?
                      AND`id_user`= ?';
            $this->_db->executeQuery(
                $query, array(
                    $orderSheet['amount'],
                    $formData['amount'],
                    $orderSheet['amount'],
                    $formData['amount'],
                    $orderSheet['stock_name'],
                    $orderSheet['id_user']
            )
            );

    }

    /**
     * Updates wallet after delete
     *
     * @param $offerValue
     * @param $id
     *
     * @access public
     * @return void
     */
    public function updateWalletAfterDelete($offerValue, $id)
    {
        $query = 'UPDATE `wallet`
                  SET `cash`= `cash` + ?, `blocked_cash`= `blocked_cash` - ?
                  WHERE `idwallet`= ?';
        $this->_db->executeQuery(
            $query, array(
                $offerValue,
                $offerValue,
                $id
            )
        );
    }

    /**
     * Function updates user stocks table after delete offer
     *
     * @access public
     * @param $offer
     * @return void
     */
    public function updateUserStocksAfterDelete($offer)
    {
        $query = 'UPDATE `user_stocks`
        SET `amount`= `amount` + ?,
            `blocked_stocks`= `blocked_stocks` - ?
        WHERE `stock_name` = ?
        AND `id_user`= ?';
        $this->_db->executeQuery(
            $query, array(
                (int)$offer['amount'],
                (int)$offer['amount'],
                $offer['stock_name'],
                (int)$offer['id_user']
            )
        );
    }

    /**
     * Deletes offer
     *
     * @param Integer $id
     * @access public
     * @return void
     */
    public function deleteOffer($offer)
    {
        if ((int)$offer['realized_amount'] === 0) {
            $query = 'DELETE FROM `order_sheet`
            WHERE `idorder_sheet` = ?';
            $this->_db->executeQuery(
                $query, array(
                    $offer['idorder_sheet']
                )
            );
        } else {
            $realized = 1;
            $query = 'UPDATE `order_sheet`
            SET `amount`= `amount` - ?,
                `realized`= ?
            WHERE `idorder_sheet`= ?';
            $this->_db->executeQuery(
                $query, array(
                    $offer['amount'],
                    $realized,
                    $offer['idorder_sheet']
                )
            );
        var_dump($offer);
        }

    }

    //************** sprzedaż **********

    /**
     * Function search user stocks
     *
     * @param $formData
     * @param $currentUser
     * @access public
     * @return mixed
     */
    public function searchUserStocks($formData, $currentUser)
    {
        $query = 'SELECT * FROM user_stocks
                  WHERE `stock_name` = ? AND `id_user` = ?';
        return $this->_db->fetchAssoc(
            $query, array(
                $formData['stock_name'],
                $currentUser['id']
            )
        );
    }

    /**
     * Function block stocks if they were added to order sheet
     *
     * @acces public
     * @param $formData
     * @param $currentUser
     * @return void
     */
    public function blockStocks($formData, $currentUser)
    {
        $query = 'UPDATE `user_stocks`
        SET `amount`= `amount` - ?, `blocked_stocks`= `blocked_stocks` + ?
        WHERE `stock_name` = ? AND `id_user`= ?';
        $this->_db->executeQuery(
            $query, array($formData['amount'],
                          $formData['amount'],
                          $formData['stock_name'],
                          $currentUser
            )
        );

    }

    /**
     * Function deletes stocks from user stocks
     *
     * @param $ownedStock
     * @access public
     * @return void
     */
    public function deleteFromUserStocks($ownedStock)
    {
        $query = 'DELETE FROM `user_stocks` WHERE `idstocks` = ?';
        $this->_db->executeQuery($query, array((int)$ownedStock['idstocks']));
    }

    /**
     * Function updates seller stocks after selling transaction
     *
     * @param $formData
     * @param $ownedStock
     * @param $orderSheet
     * @param $checker
     */
    public function updateSellerStocksTwo(
        $formData,
        $ownedStock,
        $orderSheet,
        $checker)
    {
        if ((int)$formData['amount'] === (int)$ownedStock['amount'] &&
            (int)$ownedStock['blocked_stocks'] === 0) {
            $this->deleteFromUserStocks($ownedStock);
        } else {
            if ($checker === 'less') {
                $query ='UPDATE `user_stocks`
                         SET `amount`= `amount` - ?,
                         `value`= `amount` +
                         `blocked_stocks` * `purchase_price`,
                         `purchase_price` = `value` /
                         (`blocked_stocks` + `amount`)
                         WHERE `idstocks`= ?';
                $this->_db->executeQuery(
                    $query, array(
                        (int)$formData['amount'],
                        (int)$ownedStock['idstocks'])
                );
            } else {
                $query ='UPDATE `user_stocks`
                         SET `amount`= `amount` - ?,
                         `value`= `amount` +
                         `blocked_stocks` * `purchase_price`,
                         `purchase_price` = `value` /
                         (`blocked_stocks` + `amount`)
                         WHERE `idstocks`= ?';
                $this->_db->executeQuery(
                    $query, array(
                        (int)$orderSheet['amount'],
                        (int)$ownedStock['idstocks'])
                );
            }
        }
    }

    /**
     * Function gets all stocks
     *
     * @access public
     * @return mixed
     */
    public function getAllStocks()
    {
        $query = 'SELECT * FROM `stocks`';
        $return = $this->_db->fetchAll($query);
        return $return;
    }

    /**
     * Function gets one stock by name
     *
     * @param $formData
     *
     * @access public
     * @return mixed
     */
    public function getStockByName($formData)
    {
        $query = 'SELECT *
        FROM `stocks`
        WHERE `stock_name` = ?';

        $return = $this->_db->fetchAssoc(
            $query, array(
                $formData['new_stock_name']
            )
        );
        return $return;
    }


    /**
     * Function checks, if stock exist
     *
     * @param $id
     * @access public
     * @return mixed
     */
    public function checkStocks($id)
    {
        $query = 'SELECT *
        FROM `stocks`
        WHERE `idstocks` = ?';
        $return = $this->_db->fetchAssoc(
            $query, array(
                $id,
            )
        );
        return $return;
    }

    public function checkStocksByName($formData)
    {
        $query = 'SELECT *
        FROM `stocks`
        WHERE `stock_name` = ?';
        $return = $this->_db->fetchAssoc(
            $query, array(
                $formData['new_stock_name'],
            )
        );
        return $return;
    }

    /**
     * Function adds new stocks
     *
     * @access public
     * @param $formData
     */
    public function insertIntoStocks($formData)
    {
        $query = 'INSERT INTO `stocks` (`stock_name`, `current_price`)
         VALUES (?, ?)';
        $this->_db->executeQuery(
            $query, array(
                $formData['new_stock_name'],
                $formData['price']
            )
        );
    }

    /**
     * Function updates name of stock in every table
     *
     * @acces public
     * @param $stock
     * @param $formData
     * @return void
     */
    public function updateStocksEverywhere($stock, $formData)
    {
        $queryOne = 'UPDATE `user_stocks`
        SET `stock_name`= ?
        WHERE `stock_name`= ?';
        $queryTwo = 'UPDATE `order_sheet`
        SET `stock_name`= ?
        WHERE `stock_name`= ?';
        $queryThree = 'UPDATE `stocks`
        SET `stock_name`= ?
        WHERE `stock_name`= ?';

        $this->_db->executeQuery(
            $queryOne, array(
            $formData['new_stock_name'],
            $stock['stock_name']
            )
        );

        $this->_db->executeQuery(
            $queryTwo, array(
                $formData['new_stock_name'],
                $stock['stock_name']
            )
        );

        $this->_db->executeQuery(
            $queryThree, array(
                $formData['new_stock_name'],
                $stock['stock_name']
            )
        );
    }

    /**
     * Function makes buy transaction
     *
     * @param $formData
     * @param $orderSheet
     * @param $checkAmount
     * @param $currentUser
     * @param $date
     *
     * @acces public
     * @return void
     */
    public function makeBuy(
        $formData,
        $orderSheet,
        $checkAmount,
        $currentUser,
        $date
    )
    {
        $this->updateCash(
            $formData,
            $orderSheet,
            $checkAmount,
            $currentUser
        );
        $this->updateBuyerStocks(
            $formData,
            $orderSheet,
            $currentUser,
            $date,
            $checkAmount
        );
        $this->updateSellerStocks(
            $formData,
            $orderSheet,
            $checkAmount
        );
        $this->addRealizedToOrderSheet(
            $formData,
            $orderSheet,
            $currentUser,
            $checkAmount,
            $date
        );
        $this->updateOrderSheet(
            $formData, $orderSheet, $checkAmount
        );
        $this->updatePriceInMainSheet(
            $formData, $orderSheet
        );

    }

    /**
     * Function makes sell transaction
     *
     * @param $formData
     * @param $orderSheet
     * @param $checkAmount
     * @param $currentUser
     * @param $ownedStock
     * @param $date
     *
     * @access public
     * @return void
     *
     */
    public function makeSell(
        $formData,
        $orderSheet,
        $checkAmount,
        $currentUser,
        $ownedStock,
        $date
    )
    {
        $this->updateCash(
            $formData,
            $orderSheet,
            $checkAmount,
            $currentUser
        );
        $this->updateSellerStocksTwo(
            $formData,
            $ownedStock,
            $orderSheet,
            $checkAmount
        );
        $this->updateBuyerStocks(
            $formData,
            $orderSheet,
            $currentUser,
            $date,
            $checkAmount
        );
        $this->addRealizedToOrderSheet(
            $formData,
            $orderSheet,
            $currentUser,
            $checkAmount,
            $date
        );
        $this->updateOrderSheet(
            $formData,
            $orderSheet,
            $checkAmount
        );
        $this->updatePriceInMainSheet(
            $formData,
            $orderSheet
        );
    }

}