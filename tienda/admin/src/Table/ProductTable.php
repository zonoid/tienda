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

    public function getImagePath($check = true)
    {
        $appParams = ComponentHelper::getParams('com_tienda');
        $sha1_images = $appParams->get('sha1_images', '0');

        // TODO: Replace Tienda::getPath('products_images') with a robust path generation.
        // For now, using a placeholder relative to JPATH_SITE. This needs a proper ImageManager or config.
        $default_dir = JPATH_SITE . '/media/com_tienda/images/products'; // Example, adjust as per actual structure

        $dir = $default_dir;

        // TODO: Refactor TiendaHelperBase::getInstance() and checkDirectory
        // For now, assume checkDirectory is a static helper or part of this class if simple enough.
        // This part is highly dependent on how directory creation/checking is handled in J5.
        // Factory::getApplication()->enqueueMessage('getImagePath needs TiendaHelperBase/checkDirectory refactoring.', 'notice');

        if (!empty($this->product_images_path) /* && self::checkDirectory($this->product_images_path, $check) */) {
            // Assuming product_images_path is an absolute path or resolvable path
            // $dir = $this->product_images_path;
             Factory::getApplication()->enqueueMessage('getImagePath: product_images_path override logic needs review for path validation.', 'notice');
        } else {
            $subPath = '';
            if ($sha1_images == '1' && !empty($this->product_sku)) {
                $subPath = $this->getSha1Subfolders($this->product_sku) . $this->product_sku;
            } elseif ($sha1_images == '1' && !empty($this->product_id)) {
                 $subPath = $this->getSha1Subfolders($this->product_id) . $this->product_id;
            } elseif (!empty($this->product_sku)) {
                $subPath = $this->product_sku;
            } elseif(!empty($this->product_id)) {
                $subPath = (string) $this->product_id;
            }

            if (!empty($subPath)) {
                $image_dir = $default_dir . DIRECTORY_SEPARATOR . $subPath;
                // if (self::checkDirectory($image_dir, $check)) { $dir = $image_dir; }
                // For now, we'll just construct it. Directory checking/creation should be robust.
                $dir = $image_dir;
            }
        }

        return $dir;
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

    public function getImageUrl()
    {
        $appParams = ComponentHelper::getParams('com_tienda');
        $sha1_images = $appParams->get('sha1_images', '0');

        // TODO: Replace Tienda::getUrl('products_images')
        $default_url = Uri::root() . 'media/com_tienda/images/products/'; // Example

        $url = $default_url;

        // Factory::getApplication()->enqueueMessage('getImageUrl needs TiendaHelperBase/checkDirectory refactoring and robust URL generation.', 'notice');

        if (!empty($this->product_images_path) /* && self::checkDirectory($this->product_images_path, false) */) {
            // $url = str_replace(JPATH_SITE . DIRECTORY_SEPARATOR, Uri::root(), $this->product_images_path);
            // $url = rtrim(str_replace(DIRECTORY_SEPARATOR, '/', $url), '/') . '/';
            Factory::getApplication()->enqueueMessage('getImageUrl: product_images_path override logic needs review for path to URL conversion.', 'notice');

        } else {
            $subPathUrl = '';
            if ($sha1_images == '1' && !empty($this->product_sku)) {
                $subPathUrl = $this->getSha1Subfolders($this->product_sku, '/') . $this->product_sku;
            } elseif ($sha1_images == '1' && !empty($this->product_id)) {
                $subPathUrl = $this->getSha1Subfolders($this->product_id, '/') . $this->product_id;
            } elseif (!empty($this->product_sku)) {
                $subPathUrl = $this->product_sku;
            } elseif(!empty($this->product_id)){
                $subPathUrl = (string) $this->product_id;
            }

            if(!empty($subPathUrl)){
                $url = $default_url . rtrim($subPathUrl, '/') . '/';
            }
        }
        return $url;
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
