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
use Joomla\CMS\Filter\OutputFilter;
use Joomla\CMS\Table\Table;
use Joomla\Database\DatabaseDriver;

class EavAttributeTable extends Table
{
    public $eavattribute_id = null;
    public $eavattribute_alias = null;
    public $eavattribute_label = null;
    public $eaventity_type = null; // e.g., 'products', 'users', 'orders'
    public $eavattribute_type = null; // e.g., 'varchar', 'text', 'int', 'decimal', 'datetime', 'bool', 'hidden'
    public $eavattribute_enabled = 1;
    public $ordering = null;
    public $editable_by = null; // Who can edit this attribute's value (e.g., 0=no one, 1=admin, 2=all/user)
    public $eavattribute_format_strftime = null; // For datetime type, strftime format for display
    public $eavattribute_format_date = null; // For datetime type, date() format for display
    public $required = 0; // Is this attribute required when editing an entity

    /**
     * Constructor
     *
     * @param   DatabaseDriver  $db  A database connector object
     */
    public function __construct(DatabaseDriver $db)
    {
        parent::__construct('#__tienda_eavattributes', 'eavattribute_id', $db);
    }

    /**
     * Overloaded check function to ensure data integrity.
     *
     * @return  boolean  True if the instance is sane and able to be stored according to the business rules.
     */
    public function check()
    {
        if (trim($this->eavattribute_label ?? '') === '') {
            $this->setError(Text::_('COM_TIENDA_LABEL_REQUIRED'));
            return false;
        }
        if (trim($this->eaventity_type ?? '') === '') {
            $this->setError(Text::_('COM_TIENDA_ENTITY_TYPE_REQUIRED'));
            return false;
        }
        if (trim($this->eavattribute_type ?? '') === '') {
            $this->setError(Text::_('COM_TIENDA_TYPE_REQUIRED'));
            return false;
        }

        if (empty($this->eavattribute_alias)) {
            $this->eavattribute_alias = $this->eavattribute_label;
        }
        // Assuming stringURLSafe is appropriate for alias generation.
        // The old stringDBSafe might have slightly different rules (e.g. allowing '_').
        // If precise old behavior is needed, stringDBSafe might need to be ported to a helper.
        $this->eavattribute_alias = OutputFilter::stringURLSafe($this->eavattribute_alias);
        // If underscores are desired:
        // $this->eavattribute_alias = str_replace('-', '_', OutputFilter::stringURLSafe($this->eavattribute_alias));


        if ($this->eavattribute_type === 'datetime') {
            if (empty($this->eavattribute_format_strftime)) {
                $this->eavattribute_format_strftime = '%Y-%m-%d %H:%M:%S';
            }
            if (empty($this->eavattribute_format_date)) {
                $this->eavattribute_format_date = 'Y-m-d H:i:s';
            }
        }

        return true;
    }
}
