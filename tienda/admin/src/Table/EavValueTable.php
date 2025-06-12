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
use Joomla\CMS\Date\Date;
use Joomla\CMS\Table\Table;
use Joomla\Database\DatabaseDriver;
use Joomla\CMS\Component\ComponentHelper;
use Dioscouri\Component\Tienda\Administrator\Helper\BaseHelper; // For date conversions

class EavValueTable extends Table
{
    private bool $active = false;
    protected string $type = ''; // Current suffix type (e.g., 'int', 'varchar')
    private array $allowed_types = ['int', 'varchar', 'decimal', 'text', 'datetime', 'time'];

    // Common EAV value table columns
    public $eavvalue_id = null;
    public $eavattribute_id = null;
    public $eaventity_id = null;
    public $eavvalue_value = null; // This will hold the actual value
    public $created_date = null;
    public $modified_date = null;
    public $eaventity_type = null; // e.g., 'products', 'users'

    /**
     * Constructor
     *
     * @param   DatabaseDriver  $db  A database connector object
     */
    public function __construct(DatabaseDriver $db)
    {
        // Defer actual parent::__construct and table name setting to setType,
        // as the table name depends on the EAV type.
        $this->_tbl_key = 'eavvalue_id'; // Primary key is common
        $this->_db = $db;
        // $this->setTableProperties(); // Cannot do this until _tbl is set
    }

    /**
     * Set the type of the EAV value, which determines the actual database table.
     *
     * @param   string  $type  The EAV type (e.g., 'int', 'varchar').
     * @return  bool    True on success, false if type is invalid and couldn't default.
     */
    public function setType($type): bool
    {
        $type = strtolower(trim($type));
        if (!in_array($type, $this->allowed_types)) {
            $type = 'varchar'; // Default to varchar if type is invalid or not allowed
        }
        $this->type = $type;

        // Construct table name based on type
        $tbl_name = '#__tienda_eavvalues' . $this->type;

        // Set the table name for the parent Table class
        // This is how JTable itself sets the table name.
        $this->setTableName($tbl_name);

        // Now that _tbl is set, we might need to re-initialize some parent properties
        // or call parts of parent constructor if it wasn't fully done.
        // Joomla's Table constructor does more than just set _tbl and _tbl_key.
        // A cleaner way might be to pass $tbl_name to parent::__construct, but that means
        // setType must be called before any operation that relies on parent constructor logic.
        // For now, assuming setTableName is sufficient for basic operations.
        // If issues arise, may need to call parent::__construct($tbl_name, $this->_tbl_key, $this->_db);
        // but this can lead to issues if parent constructor is called multiple times or with changing table names.
        // A common pattern for dynamic table names is to have separate classes per table type,
        // or to ensure setType is called VERY early.

        // Resetting properties might be needed if switching types on an existing object.
        // $this->reset(); // This might clear loaded data if called after load.
        // $this->setTableProperties(); // JTable method to load columns if not done by constructor

        $this->active = true;
        return true;
    }

    /**
     * Get the current EAV type.
     *
     * @return  string
     */
    public function getType(): string
    {
        return $this->type;
    }

    private function ensureActive(): void
    {
        if (!$this->active) {
            $this->setType(''); // Set to default type ('varchar') if not already active
        }
    }

    public function store($updateNulls = false)
    {
        $this->ensureActive();
        if ($this->getType() === 'datetime' && isset($this->eavvalue_value)) {
            $nullDate = $this->getDbo()->getNullDate();
            if (empty($this->eavvalue_value) || $this->eavvalue_value == $nullDate) {
                $this->eavvalue_value = $nullDate;
            } else {
                // Convert local time to GMT for storage
                $this->eavvalue_value = BaseHelper::local_to_GMT_data($this->eavvalue_value);
            }
        }
        return parent::store($updateNulls);
    }

    public function load($keys = null, $reset = true)
    {
        $this->ensureActive();
        return parent::load($keys, $reset);
    }

    // save() is an alias for store() in JTable
    public function save($src = null, $orderingFilter = '', $ignore = '')
    {
        $this->ensureActive();
        if (is_array($src)) { // Bind if array is passed
            if (!$this->bind($src, $ignore)) return false;
        } elseif (is_object($src)) {
             if (!$this->bind($src, $ignore)) return false;
        }
        if (!$this->check()) return false;
        if (!$this->store()) return false;
        if (!$this->checkin()) return false; // If using checkin/checkout
        if ($orderingFilter) { // If ordering field is present
            $this->reorder($orderingFilter);
        }
        $this->setError(''); // Clear errors on success
        return true;
    }

    public function reset()
    {
        // Common properties are defined in this class.
        // Parent reset will set them to their default values (null for objects/arrays, '' for strings etc.)
        // We need to ensure our defaults are reapplied if they are different.
        parent::reset();
        $this->eavvalue_id = null;
        $this->eavattribute_id = null;
        $this->eaventity_id = null;
        $this->eavvalue_value = null;
        $this->created_date = null;
        $this->modified_date = null;
        $this->eaventity_type = null;
        // $this->active = false; // Do not reset active status, type might still be needed
    }

    public function check()
    {
        $this->ensureActive();
        $db = $this->getDbo();
        $nullDate = $db->getNullDate();

        if (empty($this->created_date) || $this->created_date == $nullDate) {
             $this->created_date = Factory::getDate('now', Factory::getApplication()->get('offset'))->toSql();
        }
        $this->modified_date = Factory::getDate('now')->toSql(); // Always GMT

        return parent::check(); // JTable::check() is very basic, mostly for _tbl and _tbl_key.
    }

    public function delete($pk = null)
    {
        $this->ensureActive();
        return parent::delete($pk);
    }

    public function move($delta, $where = '')
    {
        $this->ensureActive();
        return parent::move($delta, $where);
    }

    public function bind($from, $ignore = array())
    {
        $this->ensureActive();
        // If $from is an object, convert to array
        if (is_object($from)) {
            $from = get_object_vars($from);
        }
        return parent::bind($from, $ignore);
    }

    public function getAllowedTypes(): array
    {
        return $this->allowed_types;
    }
}
