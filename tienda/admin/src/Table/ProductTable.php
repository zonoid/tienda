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
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Table\Table;
use Joomla\CMS\Uri\Uri; // Added for getImageUrl
use Joomla\CMS\Filesystem\Folder;
use Joomla\CMS\Filesystem\Path;

class ProductTable extends Table
{
    // Standard Product Table Columns (based on common Tienda structure and TiendaTableProducts)
    public $product_id = null;
    public $product_name = null;
    public $product_alias = null;
    public $product_sku = null;
    public $product_model = null;
    public $manufacturer_id = null;
    public $product_description = null;
    public $product_description_short = null;
    public $product_width = null;
    public $product_length = null;
    public $product_height = null;
    public $product_weight = null;
    public $product_enabled = 1;
    public $product_quantity = null; // This might be derived or a default, actual stock is often in another table
    public $tax_class_id = null;
    public $product_recurs = 0;
    public $product_full_image = null;
    public $product_images_path = null; // Path override
    public $product_params = null;
    public $product_rating = null;
    public $product_comments = null;
    public $created_date = null;
    public $modified_date = null;
    public $publish_date = null; // from JTable
    public $unpublish_date = null; // from JTable
    public $product_notforsale = 0;
    public $product_check_inventory = 0;
    public $product_ships = 0;
    public $product_attributes_display = 0;
    public $product_class_suffix = null;
    public $product_itemid = null; // If used for linking
    public $ordering = null;
    // Recurring Subscription related fields (if applicable to base product table)
    public $subscription_period_unit = 'D';
    public $subscription_period_interval = 1;
    public $subscription_trial_period_unit = 'D';
    public $subscription_trial_period_interval = 0;
    public $recurring_price = null;
    public $recurring_period_unit = 'D';
    public $recurring_period_interval = 1;
    public $recurring_trial = 0;
    public $recurring_trial_price = null;
    public $recurring_trial_period_unit = 'D';
    public $recurring_trial_period_interval = 0;
    public $subscription_prorated = 0;
    public $subscription_prorated_date = null;
    public $subscription_prorated_charge = null;
    public $subscription_prorated_term = null;

    public function __construct(DatabaseDriver $db) // Updated type hint for J5
    {
        parent::__construct('#__tienda_products', 'product_id', $db);
        $this->setColumnAlias('published', 'product_enabled');
    }

    public function check()
    {
        $db = Factory::getDbo(); // J5 way to get DBO if needed inside table methods
        $nullDate = $db->getNullDate();

        if (empty($this->created_date) || $this->created_date == $nullDate) {
            $this->created_date = (new Date('now'))->toSql();
        }

        if (empty($this->product_alias)) {
            $this->product_alias = $this->product_name;
        }
        $this->product_alias = OutputFilter::stringURLSafe($this->product_alias);

        $this->modified_date = (new Date('now'))->toSql();

        return true;
    }

    public function getImagePath($gallery = false, $check = true)
    {
        $appParams = ComponentHelper::getParams('com_tienda');
        $sha1_images = $appParams->get('sha1_images', '0');
        $baseDir = JPATH_MEDIA . '/com_tienda/products'; // Standard base directory

        $defaultPathing = true;
        $finalPath = $baseDir; // Initialize with baseDir

        if (!empty($this->product_images_path)) {
            $overridePath = $this->product_images_path;
            // Normalize separators for checks
            $normalizedOverridePath = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $overridePath);

            if (strpos($normalizedOverridePath, DIRECTORY_SEPARATOR . DIRECTORY_SEPARATOR) === 0) {
                // Old Tienda style: //path/relative/to/site_root
                $finalPath = Path::clean(JPATH_SITE . DIRECTORY_SEPARATOR . ltrim($normalizedOverridePath, DIRECTORY_SEPARATOR));
                $defaultPathing = false;
            } elseif (Path::isAbsolute($normalizedOverridePath)) {
                // Absolute server path. Use it directly.
                // For security, one might want to check if it's within JPATH_SITE or JPATH_MEDIA, but for now, trust it if set.
                $finalPath = $normalizedOverridePath;
                $defaultPathing = false;
                 Factory::getApplication()->enqueueMessage('ProductTable::getImagePath: Using direct absolute path for product_images_path. Ensure this path is intended and secure.', 'notice');
            } else {
                // Relative path, assume it's relative to the $baseDir
                $finalPath = Path::clean($baseDir . DIRECTORY_SEPARATOR . $normalizedOverridePath);
                $defaultPathing = false; // Still an override, but relative to our media base
            }
        }

        if ($defaultPathing) { // No override, or override was not an absolute/special path
            // Construct path based on ID/SKU and SHA1 settings relative to $baseDir
            $subPath = '';
            if ($sha1_images == '1') {
                $identifier = !empty($this->product_sku) ? $this->product_sku : (!empty($this->product_id) ? (string)$this->product_id : '');
                if ($identifier) {
                    $subPath = $this->getSha1Subfolders($identifier) . $identifier;
                }
            } else {
                $identifier = !empty($this->product_sku) ? $this->product_sku : (!empty($this->product_id) ? (string)$this->product_id : '');
                if ($identifier) {
                    $subPath = $identifier;
                }
            }

            if (!empty($subPath)) {
                // If $defaultPathing is true, $finalPath is $baseDir. If false, $finalPath is already the resolved override.
                // This logic needs to ensure subPath is appended correctly ONLY if we are doing default pathing.
                // The structure was: if override, use override. Else, use default + subpath.
                // Corrected logic: $finalPath is already set if override is used. Don't append subPath to it.
                // Only append subPath if we are NOT using an override path.
                // This was already handled by the $defaultPathing flag logic.
                // The $finalPath will be $baseDir if $defaultPathing is true.
                 $finalPath = $baseDir . DIRECTORY_SEPARATOR . $subPath; // This is correct for default pathing
            } elseif (!empty($this->product_id)) {
                $finalPath = $baseDir . DIRECTORY_SEPARATOR . (string)$this->product_id;
            } else {
                 $finalPath = $baseDir . DIRECTORY_SEPARATOR . 'unknown';
            }
        }
        // If an override path was used ($defaultPathing = false), $finalPath is already set.
        // If default pathing was used ($defaultPathing = true), $finalPath has been constructed based on ID/SKU.

        if ($gallery) {
            $finalPath .= DIRECTORY_SEPARATOR . 'gallery';
        }

        $finalPath = Path::clean($finalPath);

        if ($check) {
            if (!Folder::exists($finalPath)) {
                if (!Folder::create($finalPath, 0755)) { // Changed from 0777 for security
                    Factory::getApplication()->enqueueMessage(Text::sprintf('COM_TIENDA_COULD_NOT_CREATE_DIRECTORY', $finalPath), 'error');
                    // Optionally return a default path or false if creation failed
                    return Path::clean($baseDir . DIRECTORY_SEPARATOR . 'unknown' . ($gallery ? DIRECTORY_SEPARATOR . 'gallery' : ''));
                }
            }
        }
        return $finalPath;
    }

    protected function getSha1Subfolders($string, $separator = DIRECTORY_SEPARATOR)
    {
        $sha1 = strtoupper(sha1((string)$string)); // Ensure string cast
        $i = 0;
        $subdirs = '';
        while ($i < 4) {
            if (strlen($sha1) > $i) { // Check against sha1 length
                $subdirs .= $sha1[$i] . $separator;
            }
            $i++;
        }
        return $subdirs;
    }

    public function getImageUrl($gallery = false)
    {
        $appParams = ComponentHelper::getParams('com_tienda');
        $sha1_images = $appParams->get('sha1_images', '0');
        $baseUrl = Uri::root(true) . 'media/com_tienda/products/'; // Base URL, Uri::root(true) is path from site root.

        $defaultPathing = true;
        $finalUrl = $baseUrl; // Initialize

        if (!empty($this->product_images_path)) {
            $overridePath = $this->product_images_path;
            $normalizedOverridePath = str_replace(['/', '\\'], '/', $overridePath); // Normalize to fwd slashes for URL logic

            if (strpos($normalizedOverridePath, '//') === 0) {
                // Old Tienda style: //path/relative/to/site_root
                $finalUrl = Uri::root(true) . ltrim($normalizedOverridePath, '/');
                $defaultPathing = false;
            } elseif (Path::isAbsolute($overridePath)) { // Check original path for server absolute
                // Absolute server path. Convert to URL if it's within JPATH_SITE.
                if (strpos($overridePath, JPATH_SITE) === 0) {
                    $relativeToServerRoot = str_replace(JPATH_SITE, '', $overridePath);
                    $finalUrl = Uri::root(true) . ltrim(str_replace(DIRECTORY_SEPARATOR, '/', $relativeToServerRoot), '/');
                    $defaultPathing = false;
                } else {
                    // Absolute path not within JPATH_SITE, cannot reliably form a URL. Fallback.
                    Factory::getApplication()->enqueueMessage('ProductTable::getImageUrl: product_images_path is an absolute server path outside JPATH_SITE, cannot form URL. Using default.', 'warning');
                    // $finalUrl remains $baseUrl (default)
                }
            } else {
                // Relative path, assume it's relative to the $baseUrl
                $finalUrl = rtrim($baseUrl, '/') . '/' . ltrim($normalizedOverridePath, '/');
                $defaultPathing = false;
            }
        }

        if ($defaultPathing) {
            // Construct URL based on ID/SKU and SHA1 settings, relative to $baseUrl
            $subPathUrl = '';
            if ($sha1_images == '1') {
                $identifier = !empty($this->product_sku) ? $this->product_sku : (!empty($this->product_id) ? (string)$this->product_id : '');
                if ($identifier) {
                    $subPathUrl = $this->getSha1Subfolders($identifier, '/') . $identifier;
                }
            } else {
                $identifier = !empty($this->product_sku) ? $this->product_sku : (!empty($this->product_id) ? (string)$this->product_id : '');
                if ($identifier) {
                    $subPathUrl = $identifier;
                }
            }

            if (!empty($subPathUrl)) {
                $finalUrl = $baseUrl . $subPathUrl;
            } elseif (!empty($this->product_id)) {
                 $finalUrl = $baseUrl . (string)$this->product_id;
            } else {
                 $finalUrl = $baseUrl . 'unknown';
            }
        }
        // If an override path was used ($defaultPathing = false), $finalUrl is already set.
        // If default pathing was used ($defaultPathing = true), $finalUrl has been constructed based on ID/SKU.

        if ($gallery) {
            $finalUrl .= '/gallery';
        }

        // Ensure no double slashes except for protocol and ensure trailing slash
        $finalUrl = rtrim(str_replace('//', '/', str_replace(':/', '://', $finalUrl)), '/') . '/';

        return $finalUrl;
    }

    public function updateOverallRating($save = false)
    {
        // TODO: Refactor to use new ModelFactory or direct model instantiation for ProductCommentsModel.
        // JModel::addIncludePath( JPATH_ADMINISTRATOR . DS . 'components' . DS . 'com_tienda' . DS . 'models' );
        // $model = JModel::getInstance( 'ProductComments', 'TiendaModel' );
        // $model->setState( 'filter_product', $this->product_id );
        // $model->setState( 'filter_enabled', '1' );
        // $count = $model->getResult( true );
        // $model->setState( 'select', 'SUM(productcomment_rating)' );
        // $sum = $model->getResult( true );
        // $avg = $count ? $sum / $count : 0;
        // $this->product_rating = $avg;
        // $this->product_comments = $count;
        // if ( $save ) { $this->store(); // store() is J5, old was save() }
        Factory::getApplication()->enqueueMessage('updateOverallRating method body needs complete refactoring for Model interaction.', 'notice');
    }

    // Custom create, update, delete methods from TiendaTableProducts
    // These often contain business logic that should ideally be in the Model.
    public function create()
    {
        Factory::getApplication()->enqueueMessage('Table-level create() method logic should be moved to Model. This method is deprecated here.', 'warning');
        // Original body commented out as per instructions
        /*
        if ( $this->product_id ) { ... }
        ...
        return false; // or true
        */
        return false;
    }

    public function update() // Assuming this was a custom method, not JTable::update
    {
        Factory::getApplication()->enqueueMessage('Table-level update() method logic should be moved to Model. This method is deprecated here.', 'warning');
        // Original body commented out
        /*
        if ( !$this->product_id ) { ... }
        ...
        return false; // or true
        */
        return false;
    }

    public function delete($oid = null)
    {
        Factory::getApplication()->enqueueMessage('Table-level cascading delete logic (deleteItems, etc.) should be moved to Model. This custom delete() is deprecated here.', 'warning');
        // Original body commented out
        /*
        $k = $this->_tbl_key;
        if ($oid) { $this->$k = intval( $oid ); }
        // ... logic to check orderitems ...
        // ... logic to call deleteItems, deleteItemsXref ...
        // return parent::delete( $this->$k ); // Call to standard JTable delete
        */
        // For now, to prevent accidental data loss, we make it a no-op or call parent delete directly
        // return parent::delete($oid); // This would perform a simple delete without cascading logic.
        // Or, make it a true no-op for safety during refactor:
        $this->setError(Text::_('Custom table delete logic needs review and porting to Model layer.'));
        return false;
    }
}
