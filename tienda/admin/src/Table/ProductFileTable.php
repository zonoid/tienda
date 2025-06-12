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
use Joomla\CMS\Filesystem\Path;
use Joomla\CMS\Filesystem\File;

class ProductFileTable extends Table
{
    /** @var int Primary key */
    public $productfile_id = null;
    /** @var int Foreign key to #__tienda_products */
    public $product_id = null;
    /** @var string Name of the file */
    public $productfile_name = null;
    /** @var string Path to the file, relative to site root (e.g., //media/com_tienda/files/myfile.zip) */
    public $productfile_path = null;
    /** @var int Order of the file for this product */
    public $ordering = 0;
    /** @var int Whether purchase is required to download */
    public $purchase_required = 1;
    /** @var int Max number of downloads (-1 for unlimited) */
    public $download_limit = -1;
    /** @var string Date this record was created */
    public $created_date = null;
    /** @var string Date this record was last modified */
    public $modified_date = null;
    /** @var int Whether the file is enabled/published */
    public $productfile_enabled = 1;
    /** @var string Absolute path to the file on server, derived in load() */
    public $absolute_productfile_path = '';

    /**
     * Constructor
     *
     * @param   DatabaseDriver  $db  A database connector object
     */
    public function __construct(DatabaseDriver $db)
    {
        parent::__construct('#__tienda_productfiles', 'productfile_id', $db);
        $this->setColumnAlias('published', 'productfile_enabled');
    }

    /**
     * Overloaded check function to ensure data integrity.
     *
     * @return  bool  True if the record is valid, false otherwise.
     */
    public function check()
    {
        if (empty($this->product_id)) {
            $this->setError(Text::_('COM_TIENDA_PRODUCT_ASSOCIATION_REQUIRED'));
            return false;
        }

        if (empty($this->productfile_name)) {
            $this->setError(Text::_('COM_TIENDA_FILE_NAME_REQUIRED'));
            return false;
        }

        $nullDate = $this->getDbo()->getNullDate();
        if (empty($this->created_date) || $this->created_date == $nullDate) {
            $this->created_date = Factory::getDate('now')->toSql();
        }
        // modified_date is set in store()

        return parent::check();
    }

    /**
     * Method to set the ordering for the records.
     * Only those records within the same product_id will be considered.
     *
     * @param   string  $where  An optional where clause for the database query.
     * @return  bool  True on success.
     */
    public function reorder($where = '')
    {
        // Ensure product_id is set for context
        if (empty($this->product_id)) {
            $this->setError(Text::_('COM_TIENDA_CANNOT_REORDER_PRODUCTFILE_WITHOUT_PRODUCT_ID'));
            return false;
        }
        return parent::reorder($this->getDbo()->quoteName('product_id') . ' = ' . (int) $this->product_id);
    }

    /**
     * Loads a row from the database and binds the fields to the object properties.
     * Also calculates the absolute_productfile_path.
     *
     * @param   mixed    $keys   An optional primary key value to load the row by, or an array of fields to match.
     *                           If not set the instance property value is used.
     * @param   boolean  $reset  True to reset the default values before loading via the bind() method.
     * @return  boolean  True if successful. False if row not found or on error.
     */
    public function load($keys = null, $reset = true)
    {
        $success = parent::load($keys, $reset);

        if ($success && !empty($this->productfile_path)) {
            // Check if path starts with '//' (site relative)
            if (strpos($this->productfile_path, DIRECTORY_SEPARATOR . DIRECTORY_SEPARATOR) === 0) {
                // Path is relative to JPATH_SITE, e.g., //media/com_tienda/files/myfile.zip
                // Remove the leading '//' and prepend JPATH_SITE
                $this->absolute_productfile_path = Path::clean(JPATH_SITE . DIRECTORY_SEPARATOR . ltrim($this->productfile_path, DIRECTORY_SEPARATOR));
            } else {
                // Path might be an old absolute path or some other format.
                // For safety, treat it as is, but Path::clean it.
                // This part might need review based on how old paths were stored.
                $this->absolute_productfile_path = Path::clean($this->productfile_path);
            }
        } elseif ($success) {
            $this->absolute_productfile_path = '';
        }
        return $success;
    }

    /**
     * Overloaded store function
     *
     * @param   boolean  $updateNulls  True to update fields even if they are null.
     * @return  boolean  True on success.
     */
    public function store($updateNulls = false)
    {
        // The old store method converted absolute paths to // relative paths.
        // Current assumption: productfile_path is set directly by model/controller with the intended DB format (e.g. //media/com_tienda/my.zip)
        // If absolute_productfile_path was the source of truth and modified, it would need conversion here.
        // For example, if absolute_productfile_path was changed and JPATH_SITE is /var/www/html:
        // and absolute_productfile_path is /var/www/html/media/com_tienda/my.zip
        // then productfile_path should become //media/com_tienda/my.zip
        // This logic is complex and depends on how forms/models handle path input.
        // TODO: Review path saving strategy. If model sets absolute_productfile_path, this store needs to convert it back to DB format.

        $this->modified_date = Factory::getDate('now')->toSql();
        return parent::store($updateNulls);
    }

    /**
     * Overloaded delete method to delete related records.
     *
     * @param   mixed  $pk  An optional primary key value to delete. If not set the instance property value is used.
     * @return  bool   True on success.
     */
    public function delete($pk = null)
    {
        $k = $this->getKeyName();
        if ($pk) {
            $this->{$k} = $pk;
        }
        $pk_val = $this->{$k};

        if (!$pk_val) {
            $this->setError(Text::_('COM_TIENDA_NO_PRIMARY_KEY_FOR_DELETE'));
            return false;
        }

        $db = $this->getDbo();

        // Delete related productdownloads
        try {
            $query = $db->getQuery(true)
                ->delete($db->quoteName('#__tienda_productdownloads'))
                ->where($db->quoteName('productfile_id') . ' = ' . (int) $pk_val);
            $db->setQuery($query)->execute();
        } catch (\Exception $e) {
            $this->setError(Text::sprintf('COM_TIENDA_ERROR_DELETING_PRODUCTDOWNLOADS_FOR_FILE', $pk_val, $e->getMessage()));
            // Optionally, return false here if this is critical
        }

        // Delete related productdownloadlogs
        try {
            $query = $db->getQuery(true)
                ->delete($db->quoteName('#__tienda_productdownloadlogs'))
                ->where($db->quoteName('productfile_id') . ' = ' . (int) $pk_val);
            $db->setQuery($query)->execute();
        } catch (\Exception $e) {
            $this->setError(Text::sprintf('COM_TIENDA_ERROR_DELETING_PRODUCTDOWNLOADLOGS_FOR_FILE', $pk_val, $e->getMessage()));
            // Optionally, return false here if this is critical
        }

        // TODO: Physical file deletion to be handled in Model, not here in the Table class.
        // The old table class did not delete physical files either.

        return parent::delete($pk);
    }

    /**
     * Determines if a user can download the file.
     * Placeholder - Full logic requires ACL, user groups, purchase checks etc.
     *
     * @param   int    $userId    The ID of the user.
     * @param   mixed  $datetime  Optional datetime for download validation.
     * @return  bool   True if download is allowed, false otherwise.
     */
    public function canDownload($userId, $datetime = null)
    {
        Factory::getApplication()->enqueueMessage('TiendaTableProductFiles::canDownload() needs full implementation (ACL, purchase checks).', 'notice');
        // Basic check from old table: if not purchase_required, then yes.
        if (empty($this->purchase_required)) {
            // return true; // This would be part of a more complex logic
        }
        return false;
    }

    /**
     * Logs a download.
     * Placeholder - Full logic for creating log record.
     *
     * @param   int  $userId  The ID of the user downloading.
     * @return  void
     */
    public function logDownload($userId)
    {
        Factory::getApplication()->enqueueMessage('TiendaTableProductFiles::logDownload() needs full implementation.', 'notice');
        return;
    }
}
?>
