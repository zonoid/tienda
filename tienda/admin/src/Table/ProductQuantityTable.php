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

use Joomla\CMS\Language\Text;
use Joomla\CMS\Table\Table;
use Joomla\Database\DatabaseDriver;

class ProductQuantityTable extends Table
{
    /** @var int Primary key */
    public $productquantity_id = null;
    /** @var int Foreign key to #__tienda_products table */
    public $product_id = null;
    /** @var int Foreign key to #__tienda_vendors table (or 0 if not vendor-specific) */
    public $vendor_id = 0;
    /** @var string Comma-separated list of product attribute option IDs, sorted numerically */
    public $product_attributes = null;
    /** @var float The quantity of the product for this specific attribute combination and vendor */
    public $quantity = null;
    /** @var string Date this quantity record was last modified (Not in old table but good practice) */
    // public $modified_date = null; // Optional: Consider adding if tracking changes is important

    /**
     * Constructor
     *
     * @param   DatabaseDriver  $db  A database connector object
     */
    public function __construct(DatabaseDriver $db)
    {
        parent::__construct('#__tienda_productquantities', 'productquantity_id', $db);
    }

    /**
     * Overloaded check function to ensure data integrity.
     *
     * @return  bool  True if the record is valid, false otherwise.
     */
    public function check()
    {
        if (empty($this->product_id)) {
            $this->setError(Text::_('COM_TIENDA_PRODUCT_REQUIRED')); // Assuming COM_TIENDA_PRODUCT_REQUIRED is generic
            return false;
        }

        // Ensure product_attributes is a comma-separated string of numerically sorted attribute option IDs
        if (!empty($this->product_attributes)) {
            if (is_array($this->product_attributes)) {
                // If it's already an array (e.g., from form submission)
                $attributes = $this->product_attributes;
            } else {
                // If it's a string, explode it
                $attributes = explode(',', (string) $this->product_attributes);
            }

            // Remove any non-numeric values and ensure uniqueness
            $attributes = array_filter(array_unique(array_map('intval', $attributes)), function($value) {
                return $value > 0; // Keep only positive integers
            });

            sort($attributes, SORT_NUMERIC);
            $this->product_attributes = implode(',', $attributes);
        } else {
            $this->product_attributes = ''; // Ensure it's an empty string if no attributes, not null
        }

        // Ensure quantity is numeric
        if (!is_numeric($this->quantity) || $this->quantity === null) {
            $this->quantity = 0; // Default to 0 if not a valid number or null
        }


        // Optional: Set modified_date if you add this column
        // $this->modified_date = Factory::getDate('now', 'UTC')->toSql(true);

        return parent::check();
    }
}
?>
