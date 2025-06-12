<?php
/**
 * @version 0.1
 * @package Tienda
 * @author Dioscouri Design
 * @link http://www.dioscouri.com
 * @copyright Copyright (C) 2007 Dioscouri Design. All rights reserved.
 * @license http://www.gnu.org/copyleft/gpl.html GNU/GPL, see LICENSE.php
 */

namespace Dioscouri\Component\Tienda\Administrator\Table;

defined('_JEXEC') or die('Restricted access');

use Joomla\CMS\Factory\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Table\Table;
use Joomla\Database\DatabaseDriver;

class EavAttributeOptionTable extends Table
{
    public $eavattributeoption_id = null;
    public $eavattribute_id = null;
    public $eavattributeoption_name = null;

    // If eavattributeoption_value is distinct from the name/display value.
    // If not, this can be removed and 'name' is used as the value.
    public $eavattributeoption_value = null;

    public $ordering = 0;

    // The following fields were part of the old TiendaTableProductAttributeOptions
    // which seems to be the conceptual equivalent for product-specific attribute options.
    // Including them here if this table is meant to serve that purpose directly for EAV.
    // If this table is purely for generic EAV options, these might not belong or might be stored differently.
    public $productattributeoption_price = null;
    public $productattributeoption_code = null;
    public $productattributeoption_prefix = null;
    public $productattributeoption_weight = null;
    public $productattributeoption_prefix_weight = null;
    public $is_blank = null;

    /**
     * Constructor
     *
     * @param   DatabaseDriver  $db  A database connector object
     */
    public function __construct(DatabaseDriver $db)
    {
        parent::__construct('#__tienda_eavattributeoptions', 'eavattributeoption_id', $db);
    }

    /**
     * Overloaded check function to ensure data integrity.
     *
     * @return  boolean  True if the instance is sane and able to be stored according to the business rules.
     */
    public function check()
    {
        if (empty($this->eavattribute_id)) {
            $this->setError(Text::_('COM_TIENDA_ATTRIBUTE_ID_REQUIRED_FOR_OPTION'));
            return false;
        }
        if (trim($this->eavattributeoption_name ?? '') === '') {
            $this->setError(Text::_('COM_TIENDA_OPTION_NAME_REQUIRED'));
            return false;
        }

        // If eavattributeoption_value is allowed to be empty, no specific check here.
        // If it should default to name if empty and the field exists:
        // if (property_exists($this, 'eavattributeoption_value') && empty($this->eavattributeoption_value)) {
        //     $this->eavattributeoption_value = $this->eavattributeoption_name;
        // }

        return parent::check(); // Call parent for further checks if any (e.g., key checks)
    }
}
