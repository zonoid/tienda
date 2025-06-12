<?php
/**
 * @package     Tienda
 * @subpackage  Administrator
 * @author      Dioscouri Design
 * @link        http://www.dioscouri.com
 * @copyright   (C) 2024 Dioscouri Design. All rights reserved.
 * @license     GNU/GPL V2 or later
 */

namespace Dioscouri\Component\Tienda\Administrator\Table;

defined('_JEXEC') or die('Restricted access');

use Joomla\CMS\Table\Table;
use Joomla\Database\DatabaseDriver;

class ProductCategoryXrefTable extends Table
{
    /** @var int Product ID (Part of composite primary key) */
    public $product_id = null;

    /** @var int Category ID (Part of composite primary key) */
    public $category_id = null;

    /**
     * Constructor
     *
     * @param   DatabaseDriver  $db  A database connector object
     */
    public function __construct(DatabaseDriver $db)
    {
        parent::__construct('#__tienda_productcategoryxref', ['product_id', 'category_id'], $db);
    }
}
?>
