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

class ProductRelationTable extends Table
{
    /** @var int Primary key */
    public $productrelation_id = null;
    /** @var int The ID of the first product in the relation */
    public $product_id_from = null;
    /** @var int The ID of the second product in the relation */
    public $product_id_to = null;
    /** @var string Type of relation (e.g., 'relates', 'requires', 'parent_of', 'child_of') */
    public $relation_type = null;
    /** @var string Optional notes about the relation */
    public $relation_notes = null;
    // public $ordering = null; // If ordering of relations is needed

    /**
     * Constructor
     *
     * @param   DatabaseDriver  $db  A database connector object
     */
    public function __construct(DatabaseDriver $db)
    {
        parent::__construct('#__tienda_productrelations', 'productrelation_id', $db);
    }

    /**
     * Overloaded check function to ensure data integrity.
     *
     * @return  bool  True if the record is valid, false otherwise.
     */
    public function check()
    {
        if (empty($this->product_id_from)) {
            $this->setError(Text::_('COM_TIENDA_PRODUCT_FROM_REQUIRED'));
            return false;
        }

        if (empty($this->product_id_to)) {
            $this->setError(Text::_('COM_TIENDA_PRODUCT_TO_REQUIRED'));
            return false;
        }

        if (empty($this->relation_type)) {
            $this->setError(Text::_('COM_TIENDA_RELATION_TYPE_REQUIRED'));
            return false;
        }

        // Ensure relation_type is a safe string if it's coming from user input directly
        $this->relation_type = preg_replace('/[^a-zA-Z0-9_]/', '', strtolower((string)$this->relation_type));
        if (empty($this->relation_type)) { // Re-check after sanitization
             $this->setError(Text::_('COM_TIENDA_RELATION_TYPE_INVALID_FORMAT'));
             return false;
        }

        return parent::check();
    }
}
?>
