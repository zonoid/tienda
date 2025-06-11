<?php
/**
 * @version 1.5
 * @package Tienda
 * @author  Dioscouri Design
 * @link    http://www.dioscouri.com
 * @copyright Copyright (C) 2007 Dioscouri Design. All rights reserved.
 * @license http://www.gnu.org/copyleft/gpl.html GNU/GPL, see LICENSE.php
 */

namespace Dioscouri\Component\Tienda\Administrator\Helper;

defined('_JEXEC') or die('Restricted access');

use Joomla\CMS\Factory\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Uri\Uri;
use Joomla\CMS\Filesystem\File;
use Joomla\CMS\Filesystem\Folder;
use Joomla\Event\Dispatcher; // Assuming it might be needed for future event handling
// use Joomla\Event\DispatcherInterface; // More specific for type hinting if injected

class ProductHelper
{
    private static array $loadedProducts = [];
    private static array $categoriesXref = [];
    // Add other static caches here if identified as needed from old helper, e.g., for layouts, gallery paths etc.
    // private static array $galleryPaths = [];
    // private static array $galleryUrls = [];
    // private static array $filePaths = [];
    // private static array $productPrices = [];


    /**
     * Loads a product by its ID
     *
     * @param int $id Product ID
     * @param bool $reset Whether to reset the cache
     * @param bool $load_eav Whether to load EAV attributes (currently a TODO)
     * @return \Dioscouri\Component\Tienda\Administrator\Table\ProductTable|null Product table object or null if not found
     */
    public static function load($id, $reset = true, $load_eav = true)
    {
        $id = (int) $id;
        if (!$reset && isset(self::$loadedProducts[$id][(int)$load_eav])) {
            return self::$loadedProducts[$id][(int)$load_eav];
        }

        // Ensure the new ProductTable class is used
        $table = new \Dioscouri\Component\Tienda\Administrator\Table\ProductTable(Factory::getDbo());

        if (!$table->load($id)) {
            // Handle error or return null
            Factory::getApplication()->enqueueMessage(Text::sprintf('COM_TIENDA_PRODUCT_LOAD_FAILED', $id), 'error');
            self::$loadedProducts[$id][(int)$load_eav] = null;
            return null;
        }

        // TODO: EAV loading for ProductTable if $load_eav is true.
        // Currently, ProductTable::load doesn't handle EAV. This would involve
        // fetching EAV attributes and values and attaching them to the $table object.
        if ($load_eav) {
            Factory::getApplication()->enqueueMessage('ProductHelper::load EAV loading part needs implementation.', 'notice');
        }

        self::$loadedProducts[$id][(int)$load_eav] = $table;
        return $table;
    }

    /**
     * Gets an image tag or URL for a product image.
     *
     * @param mixed $productIdOrImageName Product ID or image filename
     * @param string $by 'id' if first param is product ID, or 'filename' if it's an image name (not fully supported in refactor)
     * @param string $alt Alt text for the image
     * @param string $type 'thumb' or 'full' (currently not used by placeholder)
     * @param bool $url True to return URL only, false for <img> tag
     * @param bool $resize Whether to resize (currently not used by placeholder)
     * @param array $options Additional options like width/height (currently not used by placeholder)
     * @param bool $main_product (currently not used by placeholder)
     * @return string HTML <img> tag or image URL
     */
    public static function getImage($productIdOrImageName, $by = 'id', $alt = '', $type = 'thumb', $url = false, $resize = false, array $options = [], $main_product = false)
    {
        Factory::getApplication()->enqueueMessage('ProductHelper::getImage needs complete refactoring for J5 image handling, path/URL resolution, and resizing.', 'notice');

        $placeholderSrc = Uri::root(true) . '/media/com_tienda/images/placeholder_239.gif'; // Ensure this placeholder exists
        if ($url) {
            return $placeholderSrc;
        }
        $altText = htmlspecialchars($alt ?: Text::_('COM_TIENDA_PRODUCT_IMAGE'), ENT_QUOTES, 'UTF-8');
        $width = isset($options['width']) ? ' width="' . (int)$options['width'] . '"' : '';
        $height = isset($options['height']) ? ' height="' . (int)$options['height'] . '"' : '';
        return '<img src="' . $placeholderSrc . '" alt="'. $altText .'"' . $width . $height . ' />';
    }

    /**
     * Gets a rating image string.
     *
     * @param float $num The rating number (e.g., 3.5)
     * @param mixed $viewObject Deprecated
     * @param bool $clickable Deprecated
    public static function getRatingImage($ratingValue, $totalStars = 5)
    {
        $ratingValue = (float)$ratingValue;
        $starValue = '0'; // Default to 0 stars

        if ($ratingValue <= 0) { $starValue = '0'; }
        elseif ($ratingValue <= 0.5) { $starValue = '0.5'; }
        elseif ($ratingValue <= 1.0) { $starValue = '1'; }
        elseif ($ratingValue <= 1.5) { $starValue = '1.5'; }
        elseif ($ratingValue <= 2.0) { $starValue = '2'; }
        elseif ($ratingValue <= 2.5) { $starValue = '2.5'; }
        elseif ($ratingValue <= 3.0) { $starValue = '3'; }
        elseif ($ratingValue <= 3.5) { $starValue = '3.5'; }
        elseif ($ratingValue <= 4.0) { $starValue = '4'; }
        elseif ($ratingValue <= 4.5) { $starValue = '4.5'; }
        // Ensure that any rating greater than 4.5 (including 5.0) maps to '5'
        elseif ($ratingValue > 4.5) { $starValue = '5'; }
        // No else needed, as \$starValue is initialized and ratings above 5 are capped at 5.

        $ratingData = new \stdClass();
        $ratingData->starValue = $starValue;       // e.g., "3.5"
        $ratingData->originalValue = $ratingValue; // e.g., 3.78
        $ratingData->totalStars = $totalStars;     // e.g., 5

        // Example for template:
        // $imageName = 'stars_' . str_replace('.', '_', $ratingData->starValue) . '.gif';
        // $imageURL = Uri::root(true) . '/media/com_tienda/images/ratings/' . $imageName;
        // <img src="{$imageURL}" alt="{$ratingData->originalValue} / {$ratingData->totalStars}" />

        Factory::getApplication()->enqueueMessage('ProductHelper::getRatingImage now returns data. Template needs to render stars using starValue (e.g., ' . $ratingData->starValue . ').', 'notice');

        return $ratingData;
    }

    /**
     * Gets a product's list of category IDs.
     *
     * @param int $id Product ID
     * @return array Array of category IDs
     */
    public static function getCategories($id)
    {
        $id = (int) $id;
        if (isset(self::$categoriesXref[$id])) {
            return self::$categoriesXref[$id];
        }

        // TODO: Consider using CategoryModel or ProductModel relation for this for better data consistency and caching.
        $db = Factory::getDbo();
        $query = $db->getQuery(true)
            ->select($db->quoteName('category_id'))
            ->from($db->quoteName('#__tienda_productcategoryxref'))
            ->where($db->quoteName('product_id') . ' = ' . $id);

        try {
            $db->setQuery($query);
            self::$categoriesXref[$id] = $db->loadColumn();
        } catch (\Exception $e) {
            Factory::getApplication()->enqueueMessage('Error loading product categories: ' . $e->getMessage(), 'error');
            self::$categoriesXref[$id] = [];
        }
        return self::$categoriesXref[$id];
    }

    // Stubbed methods from TiendaHelperProduct
    public static function getLayouts($options = []) {
        Factory::getApplication()->enqueueMessage('ProductHelper::getLayouts needs refactoring for J5 template discovery.', 'notice');
        return [];
    }

    public static function getLayout($product_id, $options = []) {
        Factory::getApplication()->enqueueMessage('ProductHelper::getLayout needs refactoring.', 'notice');
        return 'view'; // Default layout name
    }

    public static function getUriFromPath($path) {
        Factory::getApplication()->enqueueMessage('ProductHelper::getUriFromPath needs refactoring using Uri class and JPATH_SITE.', 'notice');
        // Basic conversion, might not cover all edge cases of old method
        return str_replace(JPATH_SITE . DIRECTORY_SEPARATOR, Uri::root(), str_replace(DIRECTORY_SEPARATOR, '/', $path));
    }

    public static function consolidateGalleryImages($row, $delete_duplicates = false) {
        Factory::getApplication()->enqueueMessage('ProductHelper::consolidateGalleryImages needs refactoring with J5 Filesystem API.', 'notice');
        return null;
    }

    public static function getGalleryImages($folder = null, $options = [], $triggerEvent = true) {
        Factory::getApplication()->enqueueMessage('ProductHelper::getGalleryImages needs refactoring with J5 Filesystem API.', 'notice');
        return [];
    }

    public static function getGalleryPath($row) {
        Factory::getApplication()->enqueueMessage('ProductHelper::getGalleryPath needs refactoring using ProductTable::getImagePath or similar.', 'notice');
        // Simplified placeholder - actual logic was more complex
        if (is_object($row) && isset($row->product_id)) {
             return JPATH_SITE . '/media/com_tienda/images/products/' . $row->product_id;
        } elseif (is_numeric($row)) {
             return JPATH_SITE . '/media/com_tienda/images/products/' . $row;
        }
        return JPATH_SITE . '/media/com_tienda/images/products/unknown';
    }

    public static function getGalleryUrl($id) {
        Factory::getApplication()->enqueueMessage('ProductHelper::getGalleryUrl needs refactoring using ProductTable::getImageUrl or similar.', 'notice');
        return Uri::root() . 'media/com_tienda/images/products/' . (int)$id . '/';
    }

    public static function getFilePath($id) {
        Factory::getApplication()->enqueueMessage('ProductHelper::getFilePath needs refactoring for downloadable products path.', 'notice');
        return JPATH_SITE . '/media/com_tienda/files/products/' . (int)$id;
    }

    public static function getPrices($id) {
        Factory::getApplication()->enqueueMessage('ProductHelper::getPrices needs refactoring, likely involving ProductPricesModel.', 'notice');
        return [];
    }

    public static function getPrice($id, $quantity = '1', $group_id = '', $date = '') {
        Factory::getApplication()->enqueueMessage('ProductHelper::getPrice needs refactoring, likely involving ProductPricesModel and price calculation logic.', 'notice');
        return null; // Represents a price object placeholder
    }

    public static function getTaxTotal($product_id, $geozones, $price = null) {
        Factory::getApplication()->enqueueMessage('ProductHelper::getTaxTotal needs refactoring using TaxHelper/TaxModel.', 'notice');
        $return = new \stdClass();
        $return->tax_rates = [];
        $return->tax_amounts = [];
        $return->tax_total = 0.0;
        return $return;
    }

    public static function getTaxRate($product_id, $geozone_id, $return_object = false) {
        Factory::getApplication()->enqueueMessage('ProductHelper::getTaxRate needs refactoring using TaxHelper/TaxModel.', 'notice');
        return $return_object ? (object)['tax_rate' => 0.0] : 0.0;
    }

    public static function getAttributes($id, $parent_option = "-1") {
        Factory::getApplication()->enqueueMessage('ProductHelper::getAttributes needs refactoring using ProductAttributesModel.', 'notice');
        return [];
    }

    public static function getAttributeQuantityMap($id, $parent_options = "-1", $refresh = false) {
        Factory::getApplication()->enqueueMessage('ProductHelper::getAttributeQuantityMap needs complete refactoring (complex inventory logic).', 'notice');
        return false; // Old method returned false if no quantities set up
    }

    public static function getAvailableAttributeOptions($product_id, $aid, $fixed_aid, $fixed_pao, $parent_options = "-1", $refresh = false) {
        Factory::getApplication()->enqueueMessage('ProductHelper::getAvailableAttributeOptions needs complete refactoring (complex inventory logic).', 'notice');
        return [];
    }

    public static function getDefaultAttributeOptions( $attributes ){
        Factory::getApplication()->enqueueMessage('ProductHelper::getDefaultAttributeOptions needs complete refactoring.', 'notice');
        return [];
    }

    public static function getDefaultAttributes($id) {
        Factory::getApplication()->enqueueMessage('ProductHelper::getDefaultAttributes needs refactoring using ProductAttributesModel/ProductQuantitiesModel.', 'notice');
        return [];
    }

    public static function getFiles($id) {
        Factory::getApplication()->enqueueMessage('ProductHelper::getFiles needs refactoring using ProductFilesModel.', 'notice');
        return [];
    }

    public static function getServerFiles($folder = null, $options = []) {
        Factory::getApplication()->enqueueMessage('ProductHelper::getServerFiles needs refactoring with J5 Filesystem API.', 'notice');
        return [];
    }

    public static function getSurrounding($id) {
        Factory::getApplication()->enqueueMessage('ProductHelper::getSurrounding needs refactoring using ProductsModel logic.', 'notice');
        return ['prev' => '', 'next' => ''];
    }

    public static function getCombinations($string, $traits, $i, &$return) {
        // This method is recursive and self-contained string/array logic. Could be kept if still needed.
        // For now, marking as needs review in context of its usage.
        Factory::getApplication()->enqueueMessage('ProductHelper::getCombinations: Review if this specific recursive logic is still the best approach.', 'notice');
        if ($i >= count($traits)) {
            $return[] = str_replace(' ', ',', trim($string));
        } else {
            foreach ($traits[$i] as $trait) {
                self::getCombinations("$string $trait", $traits, $i + 1, $return);
            }
        }
    }

    public static function getProductAttributeCSVs($product_id, $attributeOptionId = '0') {
        Factory::getApplication()->enqueueMessage('ProductHelper::getProductAttributeCSVs needs refactoring (depends on getCombinations and models).', 'notice');
        return [];
    }

    public static function doProductQuantitiesReconciliation($product_id, $vendor_id = '0', $attributeOptionId = '0') {
        Factory::getApplication()->enqueueMessage('ProductHelper::doProductQuantitiesReconciliation needs complete refactoring (complex inventory logic).', 'notice');
        return false;
    }

    public static function reconcileProductAttributeCSVs($product_id, $vendor_id, $items, $csvs) {
        Factory::getApplication()->enqueueMessage('ProductHelper::reconcileProductAttributeCSVs needs complete refactoring (complex inventory logic).', 'notice');
        return $items; // Return input items as a basic fallback
    }

    public static function isShippingEnabled($id) {
        Factory::getApplication()->enqueueMessage('ProductHelper::isShippingEnabled needs refactoring (load product and check property).', 'notice');
        $product = self::load($id, false, false); // Basic load without EAV
        return $product ? (bool)$product->product_ships : false;
    }

    public static function relationshipExists($product_from, $product_to, $relation_type = 'relates') {
        Factory::getApplication()->enqueueMessage('ProductHelper::relationshipExists needs refactoring using ProductRelationsModel.', 'notice');
        return false;
    }

    public static function getProductQuantities($id) {
        Factory::getApplication()->enqueueMessage('ProductHelper::getProductQuantities needs refactoring using ProductQuantitiesModel.', 'notice');
        return [];
    }

    public static function getAvailableQuantity($id, $attribute) {
        Factory::getApplication()->enqueueMessage('ProductHelper::getAvailableQuantity needs refactoring (complex inventory logic).', 'notice');
        $return = new \stdClass();
        $return->product_name = '';
        $return->quantity = 0;
        $return->product_check_inventory = 0;
        return $return;
    }

    public static function getOrders($product_id) {
        Factory::getApplication()->enqueueMessage('ProductHelper::getOrders needs refactoring using OrderItemsModel.', 'notice');
        return [];
    }

    public static function getUserAndProductIdForReview($product_id, $user_id) {
        Factory::getApplication()->enqueueMessage('ProductHelper::getUserAndProductIdForReview needs refactoring using ProductCommentsModel.', 'notice');
        return [];
    }

    public static function getUserEmailForReview($product_id) {
        Factory::getApplication()->enqueueMessage('ProductHelper::getUserEmailForReview needs refactoring using ProductCommentsModel.', 'notice');
        return [];
    }

    public static function isFeedbackAlready($uid, $cid) {
        Factory::getApplication()->enqueueMessage('ProductHelper::isFeedbackAlready needs refactoring using ProductCommentsHelpfulnessModel.', 'notice');
        return false;
    }

    public static function getProductQuantitiesObjects($id) {
        Factory::getApplication()->enqueueMessage('ProductHelper::getProductQuantitiesObjects needs refactoring using ProductQuantitiesModel.', 'notice');
        return [];
    }

    public static function getAttributeOptionsObjects($id) {
        Factory::getApplication()->enqueueMessage('ProductHelper::getAttributeOptionsObjects needs refactoring using ProductAttributeOptionsModel.', 'notice');
        return [];
    }

    public static function updateOverallRatings() {
        Factory::getApplication()->enqueueMessage('ProductHelper::updateOverallRatings needs refactoring (looping products and updating ratings).', 'notice');
        return true;
    }

    public static function updatePriceUserGroups() {
        Factory::getApplication()->enqueueMessage('ProductHelper::updatePriceUserGroups (direct DB update) should be reviewed for necessity or moved to a migration script.', 'notice');
        return true;
    }

    public static function onAfterSaveProducts($product) {
        Factory::getApplication()->enqueueMessage('ProductHelper::onAfterSaveProducts (event-like trigger for Ambrasubs) needs review for J5 event system or direct call.', 'notice');
        // No return value in original
    }

    public static function dispayPriceWithTax($price = '0', $tax = '0', $show = '0') {
        Factory::getApplication()->enqueueMessage('ProductHelper::dispayPriceWithTax needs refactoring using CurrencyHelper and TaxHelper.', 'notice');
        // Basic fallback, does not include tax calculation
        return CurrencyHelper::format((float)$price);
    }

    public static function getCartButton($product_id, $layout = 'product_buy', $values = [], &$callback_js = '') {
        Factory::getApplication()->enqueueMessage('ProductHelper::getCartButton needs complete refactoring (view rendering logic).', 'notice');
        return '';
    }

    public static function getProductShareButtons($view, $product_id, $layout = 'product_share_buttons') {
        Factory::getApplication()->enqueueMessage('ProductHelper::getProductShareButtons needs complete refactoring (view rendering logic).', 'notice');
        return '';
    }

    public static function getSocialBookMarkUri($uri = null) {
        Factory::getApplication()->enqueueMessage('ProductHelper::getSocialBookMarkUri needs refactoring (Bit.ly or other URL shorteners via services).', 'notice');
        return $uri ?? Uri::getInstance()->toString();
    }

    public static function convertAttributesToArray($product_id, $values_csv) {
        Factory::getApplication()->enqueueMessage('ProductHelper::convertAttributesToArray needs refactoring using ProductAttributesModel.', 'notice');
        return [];
    }

    public static function getGalleryLayout($view, $product_id, $product_name = '', $exclude = '', $layout = 'product_gallery', $values = []) {
        Factory::getApplication()->enqueueMessage('ProductHelper::getGalleryLayout needs complete refactoring (view rendering logic).', 'notice');
        return '';
    }

    public static function getProductViewObject($model = null, $hidemenu = true, $dotask = true) {
        Factory::getApplication()->enqueueMessage('ProductHelper::getProductViewObject needs refactoring to use MVCFactory for view instantiation.', 'notice');
        return null;
    }

    public static function calculateProductAttributeProperty(&$product, $attributes, $product_price_property, $product_weight_property) {
        // Note: $product_price_property and $product_weight_property were variable names in old method, here they are direct property names of $product
        Factory::getApplication()->enqueueMessage('ProductHelper::calculateProductAttributeProperty needs complete refactoring (complex pricing/weight adjustment logic).', 'notice');
        // No return value, modifies $product by reference
    }

    public static function getProductSKU($product, $attributes_array = []) {
        Factory::getApplication()->enqueueMessage('ProductHelper::getProductSKU needs refactoring (SKU generation based on attributes).', 'notice');
        return $product->product_sku ?? '';
    }
}
