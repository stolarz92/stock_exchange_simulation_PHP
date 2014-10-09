<?php
/**
 * Index model
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
 * Class ProjectsModel
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
class IndexModel
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
     * Gets table with all stocks
     *
     * @return mixed
     */
    public function getStocksTable()
    {
        $query = 'SELECT * FROM `stocks`';
        return $this->_db->fetchAll($query);
    }

}