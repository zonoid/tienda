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
use Joomla\CMS\Date\Date;
use Joomla\Database\DatabaseDriver;
use Joomla\CMS\Table\Nested;

class CategoryTable extends Nested
{
    public $category_id = null;
    public $category_name = null;
    public $category_alias = null;
    public $parent_id = null;
    public $lft = null;
    public $rgt = null;
    public $category_enabled = 1; // Default to enabled
    public $created_date = null;
    public $modified_date = null;
    public $category_layout = null;
    public $categoryproducts_layout = null;
    public $isroot = 0; // Default to not root
    public $asset_id = 0; // For Joomla ACL, from JTableNested
    public $level = 0; // From JTableNested
    public $path = ''; // From JTableNested
    // public $ordering = null; // If you still use a separate ordering field within siblings

    /**
     * Constructor
     *
     * @param   DatabaseDriver  $db  A database connector object
     */
    public function __construct(DatabaseDriver $db)
    {
        parent::__construct('#__tienda_categories', 'category_id', $db);

        // Set column alias for Nested Table compatibility if needed
        // $this->setColumnAlias('name', 'category_name'); // Nested uses 'title' by default for name, if you want 'name' to point to 'category_name'
        $this->setColumnAlias('published', 'category_enabled'); // For JTableNested's publish/unpublish
        // $this->setColumnAlias('ordering', 'ordering'); // If you have an 'ordering' column for siblings

        // JTableNested also uses 'parent_id', 'lft', 'rgt', 'level', 'path', 'asset_id'.
        // Ensure these properties exist in your class or are handled by the parent.
    }

    /**
     * Overloaded check function to ensure data integrity.
     *
     * @return  boolean  True if the instance is sane and able to be stored according to the business rules.
     */
    public function check()
    {
        $db = $this->getDbo();
        $nullDate = $db->getNullDate();

        if (empty($this->created_date) || $this->created_date == $nullDate) {
            $this->created_date = Factory::getDate('now', Factory::getApplication()->get('offset'))->toSql();
        }

        // Modified date is always updated to GMT
        // $this->modified_date = Factory::getDate('now')->toSql(); // This is handled by store method

        // HTML filtering should be done at input or display layer.
        // $this->filterHTML('category_name');

        if (trim($this->category_name ?? '') === '') {
            $this->setError(Text::_('COM_TIENDA_NAME_REQUIRED'));
            return false;
        }

        if (empty($this->category_alias)) {
            $this->category_alias = $this->category_name;
        }
        $this->category_alias = OutputFilter::stringURLSafe($this->category_alias);

        // Ensure parent_id is set, TableNested might handle root creation/defaulting.
        // If $this->parent_id is empty and it's a new record, TableNested::store will typically make it a child of the root.
        // Or, if you want to enforce a specific root if parent_id is 0 and this is not the root itself:
        // if (empty($this->parent_id) && !$this->isRoot()) {
        //    $root = $this->getRoot(); // This getRoot will be from parent TableNested
        //    if ($root) {
        //        $this->parent_id = $root->{$this->getKeyName()};
        //    } else {
        //        // This case should ideally be handled by an installer ensuring a root node exists
        //        $this->setError(Text::_('COM_TIENDA_ERROR_NO_ROOT_CATEGORY'));
        //        return false;
        //    }
        // }

        return parent::check(); // Call parent JTableNested::check()
    }

    /**
     * Stores the object
     * @param boolean $updateNulls
     * @return boolean
     */
    public function store($updateNulls = false)
    {
        $this->modified_date = Factory::getDate('now')->toSql(); // GMT
        return parent::store($updateNulls);
    }

    /**
     * Attempts base getRoot() and if it fails, creates root entry (original Tienda logic)
     * For J5, rely on parent::getRoot() and handle root creation separately if needed.
     */
    // public function getRoot() // Custom getRoot from TiendaTableCategories
    // {
        // // TODO: Implement Tienda-specific root category auto-creation if needed (e.g., in an installer or helper).
        // // For now, rely on parent::getRoot() from Joomla\CMS\Table\Nested.
        // // The parent::getRoot() will typically find the node with lft = 1.
        // // If it doesn't exist, it might return false or throw an error depending on JTableNested.
        // $root = parent::getRoot();
        // if (!$root) {
        //     // Logic from old getRoot to create one if it doesn't exist.
        //     // This should ideally be an installation/migration step.
        //     Factory::getApplication()->enqueueMessage('CategoryTable::getRoot - Root category not found, auto-creation logic needs review/implementation as install step.', 'warning');
        //     // ... (old code to insert root and rebuild) ...
        //     // return parent::getRoot(); // Try again after creation
        // }
        // return $root;
    // }

    // getTree() method removed - belongs in Model.

    /**
     * Rebuilds the Tree Ordering based on 'ordering' field.
     * Note: Joomla's TableNested::rebuild primarily uses lft/rgt.
     * This method was for custom ordering within siblings.
     *
     * @param int $parentId
     * @param int $leftId
     * @param int $level
     * @param string $path
     * @return boolean
     */
    public function rebuildTreeOrdering($parentId = null, $leftId = 0, $level = 0, $path = '')
    {
        // TODO: This method used 'ordering' field. Joomla's TableNested::rebuild uses lft/rgt.
        // If simple sibling ordering is needed, it's often handled by setting the 'ordering' field
        // and then calling parent::rebuild() or by specific model logic.
        // For now, falling back to parent::rebuild which correctly rebuilds lft/rgt.
        // If custom ordering logic based on the 'ordering' field is essential, this method needs careful porting.
        Factory::getApplication()->enqueueMessage('CategoryTable::rebuildTreeOrdering custom logic based on "ordering" field needs review. Falling back to parent::rebuild for lft/rgt.', 'notice');
        return parent::rebuild($parentId, $leftId, $level, $path);

        /* Original Logic (needs careful porting if kept):
        $db = $this->getDbo();
        $keyName = $this->getKeyName();

        if ($parentId === null) {
            $root = parent::getRoot(); // Use parent's getRoot
            if ($root === false) {
                $this->setError("Cannot find root for table: " . $this->getTableName());
                return false;
            }
            $parentId = $root->$keyName;
        }

        $right = $leftId + 1;

        $query = $db->getQuery(true)
            ->select($db->quoteName($keyName))
            ->from($this->getTableName())
            ->where($db->quoteName('parent_id') . ' = ' . $db->quote($parentId))
            ->order($db->quoteName('ordering') . ' ASC'); // Assumes 'ordering' field exists for sibling order

        $db->setQuery($query);
        $children = $db->loadObjectList();

        foreach ($children as $child) {
            $right = $this->rebuildTreeOrdering($child->$keyName, $right); // Recursive call
             if ($right === false) { return false; } // Propagate error
        }

        // Lock and update current node
        if (!$this->_lock()) { return false; } // Assuming _lock is available or handled by parent

        $queryUpdate = $db->getQuery(true)
            ->update($this->getTableName())
            ->set([
                $db->quoteName('rgt') . ' = ' . (int)$right,
                $db->quoteName('lft') . ' = ' . (int)$leftId,
                // Level and Path might be set by parent::rebuild or need manual update here if not using parent.
            ])
            ->where($db->quoteName($keyName) . ' = ' . $db->quote($parentId));

        $db->setQuery($queryUpdate);
        if (!$db->execute()) {
            $this->setError($db->getErrorMsg());
            $this->_unlock(); // Assuming _unlock
            return false;
        }
        $this->_unlock();

        return $right + 1;
        */
    }

    /**
     * Updates parent_id for orphaned categories (original Tienda logic).
     * @param int $parentId
     * @return boolean
     */
    public function updateParents($parentId = null)
    {
        $db = $this->getDbo();
        $keyName = $this->getKeyName();

        if ($parentId === null) {
            $root = parent::getRoot(); // Use parent's getRoot
            if ($root === false) {
                $this->setError(Text::sprintf('COM_TIENDA_CANNOT_GET_ROOT_NODE_FOR_TABLE', $this->getTableName()));
                // Attempt to create a root node if one does not exist.
                // This is a basic root node, more specific fields might be needed.
                $this->category_name = 'ROOT';
                $this->category_alias = 'root';
                $this->parent_id = 0; // Important for TableNested::store to recognize as root
                $this->setLocation(0, 'last-child'); // Store as root
                if (!$this->store()) {
                    return false;
                }
                $parentId = $this->{$keyName};
                if (!$parentId) return false; // Still failed
            } else {
                 $parentId = $root->$keyName;
            }
        }

        $query = $db->getQuery(true)
            ->update($this->getTableName())
            ->set($db->quoteName('parent_id') . ' = ' . (int)$parentId)
            ->where($db->quoteName('parent_id') . ' = 0')
            ->where($db->quoteName($keyName) . ' != ' . (int)$parentId);

        $db->setQuery($query);
        try {
            $db->execute();
        } catch (\Exception $e) {
            $this->setError($e->getMessage());
            return false;
        }
        return true;
    }
}
