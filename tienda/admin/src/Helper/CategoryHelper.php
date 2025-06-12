<?php
/**
 * @version 0.1
 * @package Tienda
 * @author Dioscouri Design
 * @link http://www.dioscouri.com
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
use Joomla\CMS\Router\Route;
// Assuming CategoryTable will be in Dioscouri\Component\Tienda\Administrator\Table
// use Dioscouri\Component\Tienda\Administrator\Table\CategoryTable;

class CategoryHelper
{
    private static array $loadedCategories = [];

    /**
     * Loads a category by its ID
     *
     * @param int $id Category ID
     * @return \Dioscouri\Component\Tienda\Administrator\Table\CategoryTable|null Category table object or null if not found
     */
    private static function loadCategory($id)
    {
        $id = (int) $id;
        if (isset(self::$loadedCategories[$id])) {
            return self::$loadedCategories[$id];
        }

        // TODO: Ensure CategoryTable is refactored and available.
        // For now, assuming it will be located at Dioscouri\Component\Tienda\Administrator\Table\CategoryTable
        // If not, this will cause an error until CategoryTable is created.
        try {
            $table = new \Dioscouri\Component\Tienda\Administrator\Table\CategoryTable(Factory::getDbo());
            if (!$table->load($id)) {
                self::$loadedCategories[$id] = null;
                // Factory::getApplication()->enqueueMessage(Text::sprintf('COM_TIENDA_UNABLE_TO_LOAD_CATEGORY', $id), 'error'); // Avoid during helper load
                return null;
            }
            self::$loadedCategories[$id] = $table;
            return $table;
        } catch (\Exception $e) {
            Factory::getApplication()->enqueueMessage('Error loading CategoryTable: ' . $e->getMessage() . '. Ensure CategoryTable.php is refactored and in the correct location.', 'error');
            self::$loadedCategories[$id] = null;
            return null;
        }
    }

    /**
     * Returns a formatted path for the category
     * @param int $id Category ID
     * @param string $format 'flat', 'array', 'links', 'bullet'
     * @param bool $linkSelf Whether to link the last item in 'links' format
     * @return mixed Formatted category path (string or array)
     */
    public static function getPathName($id, $format = 'flat', $linkSelf = false)
    {
        $name = '';
        if (empty($id)) {
            return $name;
        }

        $itemObject = self::loadCategory($id);
        if (empty($itemObject) || empty($itemObject->category_id)) {
            return '';
        }

        // This relies on CategoryTable extending TableNested and getPath() working correctly.
        // If CategoryTable is not TableNested, getPath() will not exist.
        // TODO: Verify CategoryTable structure and getPath method.
        $path = [];
        if (method_exists($itemObject, 'getPath')) {
            $path = $itemObject->getPath();
        } else {
             Factory::getApplication()->enqueueMessage('CategoryTable::getPath() method not available. Category path cannot be fully resolved.', 'warning');
             // Fallback to just the item itself if path cannot be retrieved
             $path = [$itemObject]; // Treat the item as its own path for limited display
        }


        $params = ComponentHelper::getParams('com_tienda');
        $include_root = $params->get('include_root_pathway', false);

        switch ($format) {
            case "array":
                $name = [];
                foreach ($path as $cat) {
                    if (isset($cat->isroot) && !$cat->isroot || $include_root || !isset($cat->isroot)) { // Handle items if isroot is not set (e.g. if not TableNested)
                        $pathway_object = new \stdClass();
                        $pathway_object->name = $cat->category_name ?? Text::_('COM_TIENDA_UNDEFINED');
                        $slug = isset($cat->category_alias) && $cat->category_alias ? ":" . $cat->category_alias : "";
                        $link = Route::_("index.php?option=com_tienda&view=products&filter_category=" . ($cat->category_id ?? '0') . $slug);
                        $pathway_object->link = $link;
                        $pathway_object->id = $cat->category_id ?? '0';
                        $name[] = $pathway_object;
                    }
                }
                // In the original, the item itself was added again if it wasn't part of getPath().
                // Assuming getPath() includes the item itself if it's part of TableNested logic.
                // If not, the last item in $path should be $itemObject.
                break;
            case "bullet":
                foreach ($path as $cat) {
                    if (isset($cat->isroot) && !$cat->isroot || !isset($cat->isroot)) {
                        $name .= '&bull;&nbsp;&nbsp;';
                        $name .= Text::_($cat->category_name ?? Text::_('COM_TIENDA_UNDEFINED'));
                        $name .= "<br/>";
                    }
                }
                // No need to add $itemObject again if getPath includes it.
                break;
            case 'links':
                // TODO: Use new RouteHelper or direct Route::_ calls for SEF Itemids.
                // This part requires TiendaHelperRoute to be refactored or replaced.
                Factory::getApplication()->enqueueMessage('CategoryHelper::getPathName "links" format needs SEF Itemid routing replacement.', 'notice');
                $root_itemid_placeholder = null; // Placeholder for Itemid

                if ($include_root) {
                     // Assuming root category ID 1 if not dynamically found.
                    $link = Route::_("index.php?option=com_tienda&view=products&filter_category=1" . ($root_itemid_placeholder ? "&Itemid=".$root_itemid_placeholder : ''));
                    $name .= ' <a href="' . $link . '">' . Text::_('COM_TIENDA_ALL_CATEGORIES') . '</a> ';
                }

                foreach ($path as $cat) {
                    if (isset($cat->isroot) && !$cat->isroot || !isset($cat->isroot)) {
                        $slug = isset($cat->category_alias) && $cat->category_alias ? ":" . $cat->category_alias : "";
                        $link = Route::_("index.php?option=com_tienda&view=products&filter_category=" . ($cat->category_id ?? '0') . $slug . ($root_itemid_placeholder ? "&Itemid=".$root_itemid_placeholder : ''));
                        if (!empty($name)) { $name .= " &gt; "; } // Use &gt; for >
                        $name .= ' <a href="' . $link . '">' . Text::_($cat->category_name ?? Text::_('COM_TIENDA_UNDEFINED')) . '</a> ';
                    }
                }

                // Handle linkSelf for the last item if it's not already linked or path is just the item
                 if ($linkSelf && $itemObject && (!count($path) || (count($path) && end($path)->category_id != $itemObject->category_id))) {
                     if (!empty($name)) { $name .= " &gt; "; }
                     $slug = isset($itemObject->category_alias) && $itemObject->category_alias ? ":" . $itemObject->category_alias : "";
                     $link = Route::_("index.php?option=com_tienda&view=products&filter_category=" . $itemObject->category_id . $slug . ($root_itemid_placeholder ? "&Itemid=".$root_itemid_placeholder : ''));
                     $name .= ' <a href="' . $link . '">' . Text::_($itemObject->category_name) . '</a> ';
                 } elseif (!$linkSelf && $itemObject && (!count($path) || (count($path) && end($path)->category_id != $itemObject->category_id))) {
                     if (!empty($name)) { $name .= " &gt; "; }
                     $name .= Text::_($itemObject->category_name);
                 }

                break;
            default: // flat
                $nameParts = [];
                foreach ($path as $cat) {
                     if (isset($cat->isroot) && !$cat->isroot || !isset($cat->isroot)) {
                        $nameParts[] = Text::_($cat->category_name ?? Text::_('COM_TIENDA_UNDEFINED'));
                    }
                }
                // Ensure the item itself is part of the flat path if getPath() doesn't include it
                // or if path is empty (e.g. root category itself not part of its own path)
                 if ($itemObject && (!count($path) || (count($path) && end($path)->category_id != $itemObject->category_id))) {
                     // Only add if it's not already the last element of $nameParts
                     if (!count($nameParts) || (count($nameParts) && end($nameParts) != Text::_($itemObject->category_name))) {
                        $nameParts[] = Text::_($itemObject->category_name);
                     }
                 }
                $name = implode(' / ', $nameParts);
                break;
        }
        return $name;
    }

    /**
     * Gets a category's image
     *
     * @param mixed $id Category ID or image filename
     * @param string $by 'id' or 'filename' (not fully used by placeholder)
     * @param string $alt Alt text
     * @param string $type 'thumb' or 'full' (not used by placeholder)
     * @param bool $url True to return URL only
     * @return string HTML <img> tag or image URL
     */
    public static function getImage($id, $by = 'id', $alt = '', $type = 'thumb', $urlOnly = false)
    {
        $app = Factory::getApplication();
        $categoryObject = null;
        $image_ref = null;
        $effective_alt = $alt ?: Text::_('COM_TIENDA_CATEGORY_IMAGE');

        if (is_numeric($id) && $id > 0) {
            $categoryObject = self::loadCategory((int)$id);
            if (!\$categoryObject) {
                // Category not found, will use placeholder
            } else {
                // Assuming category table has 'category_full_image' and potentially 'category_thumb_image'
                // In Tienda's original CategoryTable, only 'category_image' (renamed from 'category_full_image' in some contexts) was standard.
                // Let's assume 'category_full_image' is the property name on the loaded CategoryTable object.
                $image_ref = (isset(\$categoryObject->category_full_image) ? \$categoryObject->category_full_image : null);
                // Thumbs for categories might not have a separate field and might be in a 'thumbs' subdir by convention if they exist.
                // For simplicity, this version won't distinguish between 'full' and 'thumb' for categories unless a 'category_thumb_image' field is confirmed.
                $effective_alt = \$alt ?: (isset(\$categoryObject->category_name) ? \$categoryObject->category_name : Text::_('COM_TIENDA_CATEGORY_IMAGE'));
            }
        } elseif (is_string(\$id) && strpos(\$id, '.') !== false && strtolower(\$by) === 'filename') {
            $image_ref = \$id;
        }

        $baseImagePath = JPATH_MEDIA . DIRECTORY_SEPARATOR . 'com_tienda' . DIRECTORY_SEPARATOR . 'categories' . DIRECTORY_SEPARATOR;
        // If thumbs are stored in a 'thumbs' subdirectory by convention:
        $thumbImagePath = \$baseImagePath . 'thumbs' . DIRECTORY_SEPARATOR;

        $baseImageUrl  = Uri::root(true) . 'media/com_tienda/categories/';
        $thumbBaseUrl = \$baseImageUrl . 'thumbs/';

        $placeholderSrc = Uri::root(true) . 'media/com_tienda/images/category_placeholder.png'; // Ensure this placeholder exists

        \$imageSrc = \$placeholderSrc;
        \$finalPathToCheck = '';

        if (!empty(\$image_ref)) {
            if (filter_var(\$image_ref, FILTER_VALIDATE_URL)) {
                \$imageSrc = \$image_ref;
            } else {
                if (strtolower(\$type) === 'thumb') {
                    if (File::exists(\$thumbImagePath . \$image_ref)) {
                        \$imageSrc = \$thumbBaseUrl . \$image_ref;
                        \$finalPathToCheck = \$thumbImagePath . \$image_ref;
                    } elseif (File::exists(\$baseImagePath . \$image_ref)) { // Fallback to full if thumb not found
                        \$imageSrc = \$baseImageUrl . \$image_ref;
                        \$finalPathToCheck = \$baseImagePath . \$image_ref;
                    }
                } else { // 'full' or any other type
                    if (File::exists(\$baseImagePath . \$image_ref)) {
                        \$imageSrc = \$baseImageUrl . \$image_ref;
                        \$finalPathToCheck = \$baseImagePath . \$image_ref;
                    }
                }

                if (\$imageSrc === \$placeholderSrc && \$finalPathToCheck !== '') { // Only enqueue if we attempted a file check
                     Factory::getApplication()->enqueueMessage(Text::sprintf('COM_TIENDA_CATEGORY_IMAGE_FILE_NOT_FOUND', \$image_ref, \$finalPathToCheck), 'notice');
                }
            }
        }

        if (\$urlOnly) {
            return \$imageSrc;
        }

        \$altText = htmlspecialchars(\$effective_alt, ENT_QUOTES, 'UTF-8');
        return '<img src="' . \$imageSrc . '" alt="'. \$altText .'" />';
    }

    // Stub out other public static methods
    public static function getLayouts($options = [])
    {
        Factory::getApplication()->enqueueMessage('CategoryHelper::getLayouts needs refactoring for J5 template discovery.', 'notice');
        return [];
    }

    public static function getLayout($category_id)
    {
        Factory::getApplication()->enqueueMessage('CategoryHelper::getLayout needs refactoring.', 'notice');
        return 'default'; // Default layout name
    }

    public static function getSurrounding($id)
    {
        Factory::getApplication()->enqueueMessage('CategoryHelper::getSurrounding needs refactoring using CategoryModel logic.', 'notice');
        return ['prev' => null, 'next' => null];
    }
}
