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

use Joomla\CMS\Factory\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Date\Date;
use Joomla\CMS\Table\Table;
use Joomla\Database\DatabaseDriver;
use Dioscouri\Component\Tienda\Administrator\Helper\BaseHelper; // For date conversions

class ProductPriceTable extends Table
{
    /** @var int Primary key */
    public $product_price_id = null;
    /** @var int Foreign key to #__tienda_products table */
    public $product_id = null;
    /** @var int Joomla user group ID */
    public $group_id = null; // Typically, 0 for public or a specific group ID
    /** @var float The price of the product for this group */
    public $product_price = null;
    /** @var string Start date for this price */
    public $product_price_startdate = null;
    /** @var string End date for this price */
    public $product_price_enddate = null;
    /** @var int Minimum quantity for this price to apply (usually 1) */
    public $price_quantity_start = 1;
    /** @var int Maximum quantity for this price to apply (usually 0 for no limit) */
    public $price_quantity_end = 0;
    /** @var string Date this record was created */
    public $created_date = null;
    /** @var string Date this record was last modified */
    public $modified_date = null;
    /** @var string Date this price was overridden (if applicable, not standard) */
    // public $price_override_date = null; // Example of a non-standard field

    /**
     * Constructor
     *
     * @param   DatabaseDriver  $db  A database connector object
     */
    public function __construct(DatabaseDriver $db)
    {
        parent::__construct('#__tienda_productprices', 'product_price_id', $db);
    }

    /**
     * Overloaded check function to ensure data integrity.
     * Assumes working dates (startdate, enddate) might be in local time from form submission,
     * so converts them to GMT before saving.
     *
     * @return  bool  True if the record is valid, false otherwise.
     */
    public function check()
    {
        if (empty($this->product_id)) {
            $this->setError(Text::_('COM_TIENDA_PRODUCT_ASSOCIATION_REQUIRED'));
            return false;
        }

        $db = $this->getDbo();
        $nullDate = $db->getNullDate();

        // Convert start and end dates from local to GMT if they are set
        if (!empty($this->product_price_startdate) && $this->product_price_startdate != $nullDate) {
            $this->product_price_startdate = BaseHelper::local_to_GMT_data($this->product_price_startdate);
        }
        if (!empty($this->product_price_enddate) && $this->product_price_enddate != $nullDate) {
            $this->product_price_enddate = BaseHelper::local_to_GMT_data($this->product_price_enddate);
        }

        // Set created_date if it's a new record
        if (empty($this->product_price_id) && (empty($this->created_date) || $this->created_date == $nullDate)) {
            $this->created_date = Factory::getDate('now', 'UTC')->toSql(true); // Ensure UTC for created_date
        }

        // Always set modified_date
        $this->modified_date = Factory::getDate('now', 'UTC')->toSql(true); // Ensure UTC for modified_date

        // Ensure group_id is set, default to 0 (public) if not provided or invalid
        if (!isset($this->group_id) || !is_numeric($this->group_id) || $this->group_id < 0) {
             // Assuming 0 is not a valid Joomla group ID, but often used as 'public' or 'guest' in custom logic.
             // Standard Joomla groups start from 1. Guest is typically 1. Registered 2.
             // For Tienda, group_id = 0 may have meant "all/public/guest".
             // Let's default to guest group (ID 1 in standard Joomla) if it's not explicitly set or is invalid.
             // Or, if 0 was a valid Tienda convention for "all users not in other groups", keep it.
             // For now, let's assume 0 is acceptable for "all" if that was the convention.
             // If a specific default group is needed (e.g. Public or Guest), that ID should be used.
             // This part needs clarification on Tienda's group_id conventions for prices.
             // For now, if it's not set, let's not force it to 0 but ensure it's an int.
             $this->group_id = (int) ($this->group_id ?? 0); // Default to 0 if null, ensure int
        }


        return parent::check();
    }
}
?>
