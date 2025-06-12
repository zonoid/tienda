<?php
/**
 * @version 0.1
 * @package Tienda
 * @author Dioscouri Design
 * @link http://www.dioscouri.com
 * @copyright Copyright (C) 2007 Dioscouri Design. All rights reserved.
 * @license http://www.gnu.org/copyleft/gpl.html GNU/GPL, see LICENSE.php
 */

namespace Dioscouri\Component\Tienda\Administrator\Model;

defined('_JEXEC') or die('Restricted access');

use Joomla\CMS\Factory\Factory;
use Joomla\CMS\MVC\Model\ListModel;
use Joomla\Database\DatabaseQuery;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\Registry\Registry; // For product_parameters
use Joomla\CMS\Language\Text;
use Joomla\CMS\Table\Table; // Added
use Joomla\Utilities\ArrayHelper; // Added
use Joomla\CMS\Filesystem\File;
use Dioscouri\Component\Tienda\Administrator\Table\ProductFileTable;
use Dioscouri\Component\Tienda\Administrator\Table\ProductRelationTable;

class ProductsModel extends ListModel
{
    /**
     * Method to build an SQL query to load the list data.
     *
     * @return      DatabaseQuery
     * @since       1.6
     */
    protected function getListQuery()
    {
        $db = $this->getDbo();
        $query = $db->getQuery(true);
        $app = $this->getApplication(); // J5: $this->app is available

        // SELECT fields
        $query->select($this->getState('list.select', 'tbl.*'));
        $query->select('c.category_name AS category_name');
        $query->select('m.manufacturer_name AS manufacturer_name');

        $date = Factory::getDate()->toSql();
        $compParams = ComponentHelper::getParams('com_tienda');
        $default_group = $compParams->get('default_user_group', 1); // Sensible default for J5
        $filter_group = (int) $this->getState('filter.group', $default_group);
        if (empty($filter_group)) {
            $filter_group = $default_group;
        }

        $query->select(
            $db->quoteName('price_subquery.product_price') . ' AS price'
        );
        $priceSubquery = $db->getQuery(true)
            ->select('prices.product_price')
            ->from($db->quoteName('#__tienda_productprices') . ' AS prices')
            ->where('prices.product_id = tbl.product_id')
            ->where('prices.group_id = ' . $db->quote($filter_group))
            ->where('prices.product_price_startdate <= ' . $db->quote($date))
            ->where('(prices.product_price_enddate >= ' . $db->quote($date) . ' OR prices.product_price_enddate = ' . $db->quote($db->getNullDate()) . ')')
            ->order('prices.price_quantity_start ASC')
            ->setLimit(1);
        $query->select('(' . $priceSubquery . ') AS calculated_price'); // To avoid alias conflict if 'price' is a real column

        $query->select(
            $db->quoteName('quantity_subquery.quantity') . ' AS product_quantity'
        );
        $quantitySubquery = $db->getQuery(true)
            ->select('SUM(quantities.quantity)')
            ->from($db->quoteName('#__tienda_productquantities') . ' AS quantities')
            ->where('quantities.product_id = tbl.product_id')
            ->where('quantities.vendor_id = 0'); // Assuming vendor_id 0 is the main stock
        $query->select('(' . $quantitySubquery . ') AS current_stock');


        // FROM
        $query->from($db->quoteName('#__tienda_products') . ' AS tbl');

        // JOINs from _buildQueryJoins
        $query->join('LEFT', $db->quoteName('#__tienda_productcategoryxref') . ' AS p2c ON p2c.product_id = tbl.product_id');
        $query->join('LEFT', $db->quoteName('#__tienda_categories') . ' AS c ON p2c.category_id = c.category_id');
        $query->join('LEFT', $db->quoteName('#__tienda_manufacturers') . ' AS m ON m.manufacturer_id = tbl.manufacturer_id');

        // Conditional JOINs for SKU/Attribute filtering (simplified for now)
        $filter_sku = $this->getState('filter.sku');
        // Conditional JOINs for product attributes/options if SKU is part of general search or specific EAV filters active
        $filter_search_text = $this->getState('filter.search');
        $joinProductAttributes = false; // Flag to check if we need to join productattributes related tables

        // Check if SKU related search might need these tables
        // This is a simplification; a more robust solution might involve analyzing search query for SKU-like patterns
        // or having a dedicated SKU filter in XML that sets a specific state.
        if (!empty($filter_search_text) && (strpos(strtolower($filter_search_text), 'sku') !== false || preg_match('/[\w-]+-[\w-]+/', $filter_search_text))) {
            // $joinProductAttributes = true; // Assuming SKU search might involve pao.productattributeoption_code
        }
        // TODO: Add more conditions to set $joinProductAttributes = true if other EAV filters are active and need these tables.

        if ($joinProductAttributes) {
            $query->join('LEFT', $db->quoteName('#__tienda_productattributes') . ' AS pa ON pa.product_id = tbl.product_id');
            $query->join('LEFT', $db->quoteName('#__tienda_productattributeoptions') . ' AS pao ON pa.productattribute_id = pao.productattribute_id');
        }

        // Apply Filters
        if (!empty($filter_search_text)) {
            $search_quoted = $db->quote('%' . $db->escape(strtolower($filter_search_text), true) . '%', false);
            $search_conditions = [
                'LOWER(tbl.product_name) LIKE ' . $search_quoted,
                'LOWER(tbl.product_sku) LIKE ' . $search_quoted,
                'LOWER(tbl.product_description) LIKE ' . $search_quoted,
                'LOWER(m.manufacturer_name) LIKE ' . $search_quoted,
                'LOWER(c.category_name) LIKE ' . $search_quoted,
            ];
            // if ($joinProductAttributes) { // Only add this condition if tables are joined
            //    $search_conditions[] = 'LOWER(pao.productattributeoption_code) LIKE ' . $search_quoted;
            // }
            $query->where('(' . implode(' OR ', $search_conditions) . ')');
        }

        $filter_id_from = $this->getState('filter.product_id_from');
        if (is_numeric($filter_id_from)) {
            $query->where('tbl.product_id >= ' . (int) $filter_id_from);
        }
        $filter_id_to = $this->getState('filter.product_id_to');
        if (is_numeric($filter_id_to)) {
            $query->where('tbl.product_id <= ' . (int) $filter_id_to);
        }

        $filter_category_id = $this->getState('filter.category_id');
        if (is_numeric($filter_category_id) && $filter_category_id > 0) {
            $query->where('p2c.category_id = ' . (int) $filter_category_id);
        }

        $filter_manufacturer_id = $this->getState('filter.manufacturer_id');
        if (is_numeric($filter_manufacturer_id) && $filter_manufacturer_id > 0) {
            $query->where('tbl.manufacturer_id = ' . (int) $filter_manufacturer_id);
        }

        $filter_product_enabled = $this->getState('filter.product_enabled');
        if ($filter_product_enabled !== '' && $filter_product_enabled !== null) {
            $query->where('tbl.product_enabled = ' . $db->quote((string)$filter_product_enabled));
        }

        $filter_product_ships = $this->getState('filter.product_ships');
         if ($filter_product_ships !== '' && $filter_product_ships !== null) {
            $query->where('tbl.product_ships = ' . (int) $filter_product_ships);
        }

        $filter_tax_class_id = $this->getState('filter.tax_class_id');
        if (is_numeric($filter_tax_class_id) && $filter_tax_class_id > 0) {
            $query->where('tbl.tax_class_id = ' . (int) $filter_tax_class_id);
        }

        // GROUP BY
        $query->group('tbl.product_id');
        // Required for MySQL 5.7+ if other selected fields are not functionally dependent on product_id
        $query->group('c.category_name');
        $query->group('m.manufacturer_name');
        // The calculated price and stock are from subqueries that return a single value per product_id,
        // so they don't strictly need to be in GROUP BY for most DBs if product_id is PK.
        // However, to be safe or if issues arise, they can be added:
        // $query->group('calculated_price');
        // $query->group('current_stock');


        // HAVING clause (for filters on aggregated/calculated values)
        // This is where filter_price_from, filter_price_to, filter_quantity_from, filter_quantity_to should be implemented.
        // Example for price:
        if (strlen($filter_price_from)) {
             $query->having($db->quoteName('calculated_price') . ' >= ' . $db->quote((float)$filter_price_from));
        }
        if (strlen($filter_price_to)) {
             $query->having($db->quoteName('calculated_price') . ' <= ' . $db->quote((float)$filter_price_to));
        }
        if (strlen($filter_quantity_from)) {
            $query->having($db->quoteName('current_stock') . ' >= ' . (int) $filter_quantity_from);
        }
        if (strlen($filter_quantity_to)) {
            $query->having($db->quoteName('current_stock') . ' <= ' . (int) $filter_quantity_to);
        }


        // ORDERING
        // list.ordering and list.direction are set in populateState by parent::populateState
        $orderCol = $this->state->get('list.ordering', 'tbl.product_name'); // Changed default from product_id
        $orderDirn = $this->state->get('list.direction', 'ASC');

        // Handle special ordering cases like 'price' or 'product_quantity' which are aliases
        if ($orderCol === 'price') {
            $orderCol = 'calculated_price';
        } elseif ($orderCol === 'product_quantity') {
            $orderCol = 'current_stock';
        }
        $orderColFromState = $this->state->get('list.ordering', 'tbl.product_name');
        $orderDirnFromState = $this->state->get('list.direction', 'ASC');

        // Translate aliases from XML sort options to actual query columns/aliases
        if ($orderColFromState === 'calculated_price') {
            $orderCol = 'calculated_price';
        } elseif ($orderColFromState === 'current_stock') {
            $orderCol = 'current_stock';
        } else {
            // Ensure it's a valid column from tbl or joined tables to prevent SQL injection
            // For simplicity, assuming $orderColFromState is one of the direct table columns like tbl.product_name, tbl.product_id etc.
            // More robust validation might be needed here if $orderColFromState can be arbitrary.
            $orderCol = $orderColFromState;
        }

        $query->order($db->escape($orderCol) . ' ' . $db->escape($orderDirnFromState));

        // Example EAV Filtering (simplified - assumes EAV aliases match filter names like 'eav_color')
        $eavFilters = [
            'eav_color' => $this->getState('filter.eav_color'),
            'eav_material' => $this->getState('filter.eav_material')
        ];

        $joinIndex = 0;
        foreach ($eavFilters as $filterKey => $filterValue) {
            if (!empty($filterValue)) {
                $eavAlias = str_replace('eav_', '', $filterKey); // e.g., 'color' or 'material'

                // Need to get attribute_id and type for this alias
                // This is a simplified lookup. A robust solution might cache attribute details.
                $attrQuery = $db->getQuery(true)
                    ->select([$db->quoteName('eavattribute_id'), $db->quoteName('eavattribute_type')])
                    ->from($db->quoteName('#__tienda_eavattributes'))
                    ->where($db->quoteName('eavattribute_alias') . ' = ' . $db->quote($eavAlias))
                    ->where($db->quoteName('eaventity_type') . ' = ' . $db->quote('products')); // Assuming 'products'
                $db->setQuery($attrQuery, 0, 1);
                $eavAttributeDetails = $db->loadObject();

                if ($eavAttributeDetails) {
                    $joinIndex++;
                    $valTableAlias = 'eav_val_' . $joinIndex;
                    $attrTableAlias = 'eav_attr_' . $joinIndex;
                    // Construct value table name carefully. Ensure eavattribute_type is safe.
                    $eavAttributeTypeClean = preg_replace('/[^a-zA-Z0-9_]/', '', strtolower($eavAttributeDetails->eavattribute_type));
                    if (empty($eavAttributeTypeClean)) {
                        // Skip if type is somehow empty or invalid after cleaning
                        // Factory::getApplication()->enqueueMessage(Text::sprintf('COM_TIENDA_EAV_FILTER_ATTR_INVALID_TYPE', $eavAlias, $eavAttributeDetails->eavattribute_type), 'warning');
                        continue;
                    }
                    $valueTable = '#__tienda_eavvalues' . $eavAttributeTypeClean;


                    $query->join('INNER', $db->quoteName($valueTable) . ' AS ' . $db->quoteName($valTableAlias)
                        . ' ON ' . $db->quoteName($valTableAlias . '.eaventity_id') . ' = tbl.product_id');
                    $query->join('INNER', $db->quoteName('#__tienda_eavattributes') . ' AS ' . $db->quoteName($attrTableAlias)
                        . ' ON ' . $db->quoteName($valTableAlias . '.eavattribute_id') . ' = ' . $db->quoteName($attrTableAlias . '.eavattribute_id'));

                    $query->where($db->quoteName($attrTableAlias . '.eavattribute_alias') . ' = ' . $db->quote($eavAlias));

                    // For list types, use exact match. For text types, use LIKE.
                    if (in_array(strtolower($eavAttributeDetails->eavattribute_type), ['list', 'select', 'radio', 'boolean', 'bool'])) {
                         $query->where($db->quoteName($valTableAlias . '.eavvalue_value') . ' = ' . $db->quote($filterValue));
                    } else {
                         $query->where($db->quoteName($valTableAlias . '.eavvalue_value') . ' LIKE ' . $db->quote('%' . $db->escape($filterValue, true) . '%', false));
                    }
                } else {
                    // Enqueue message using Text::_ for translation
                    Factory::getApplication()->enqueueMessage(Text::sprintf('COM_TIENDA_EAV_FILTER_ATTR_NOT_FOUND', $eavAlias), 'warning');
                }
            }
        }
        // Ensure GROUP BY is still valid after potential new JOINs. If EAV values cause multiple rows per product,
        // this might need adjustment, or the main GROUP BY tbl.product_id should be sufficient if other selects are aggregates or functionally dependent.
        // The current GROUP BY on tbl.product_id should be okay.

        return $query;
    }

    /**
    protected function populateState($ordering = null, $direction = null)
    {
        // Call parent populateState to set up the standard list states (like limit, start, ordering, direction)
        parent::populateState($ordering, $direction);

        $app = $this->getApplication();
        $input = $app->input;

        // Load the filter state from the input aplication.
        // These filter names should match the 'name' attribute in the filter_products.xml fields.
        // The input object automatically handles the 'filter[name]' array structure from the XML.

        $searchValue = $input->getString('search', $app->getUserState('com_tienda.products.filter.search', ''));
        $this->setState('filter.search', $searchValue);
        $app->setUserState('com_tienda.products.filter.search', $searchValue);

        $productIdFrom = $input->getInt('product_id_from', $app->getUserState('com_tienda.products.filter.product_id_from', null));
        $this->setState('filter.product_id_from', $productIdFrom);
        $app->setUserState('com_tienda.products.filter.product_id_from', $productIdFrom);

        $productIdTo = $input->getInt('product_id_to', $app->getUserState('com_tienda.products.filter.product_id_to', null));
        $this->setState('filter.product_id_to', $productIdTo);
        $app->setUserState('com_tienda.products.filter.product_id_to', $productIdTo);

        $categoryId = $input->getInt('category_id', $app->getUserState('com_tienda.products.filter.category_id', null));
        $this->setState('filter.category_id', $categoryId);
        $app->setUserState('com_tienda.products.filter.category_id', $categoryId);

        $manufacturerId = $input->getInt('manufacturer_id', $app->getUserState('com_tienda.products.filter.manufacturer_id', null));
        $this->setState('filter.manufacturer_id', $manufacturerId);
        $app->setUserState('com_tienda.products.filter.manufacturer_id', $manufacturerId);

        $productEnabled = $input->getString('product_enabled', $app->getUserState('com_tienda.products.filter.product_enabled', ''));
        $this->setState('filter.product_enabled', $productEnabled);
        $app->setUserState('com_tienda.products.filter.product_enabled', $productEnabled);

        $productShips = $input->getString('product_ships', $app->getUserState('com_tienda.products.filter.product_ships', ''));
        $this->setState('filter.product_ships', $productShips);
        $app->setUserState('com_tienda.products.filter.product_ships', $productShips);

        $taxClassId = $input->getInt('tax_class_id', $app->getUserState('com_tienda.products.filter.tax_class_id', null));
        $this->setState('filter.tax_class_id', $taxClassId);
        $app->setUserState('com_tienda.products.filter.tax_class_id', $taxClassId);

        // Component parameters (not user filters, but affect query)
        $compParams = ComponentHelper::getParams('com_tienda');
        $this->setState('filter.group', $compParams->get('default_user_group', '1'));

        // Placeholder for EAV filters (from old logic, not yet in XML)
        // $this->setState('filter.sku', $input->getString('sku', $app->getUserState('com_tienda.products.filter.sku', '')));
        // $app->setUserState('com_tienda.products.filter.sku', $this->getState('filter.sku'));
        // $this->setState('filter.price_from', $input->get('price_from', $app->getUserState('com_tienda.products.filter.price_from', null), 'float'));
        // $app->setUserState('com_tienda.products.filter.price_from', $this->getState('filter.price_from'));
        // $this->setState('filter.price_to', $input->get('price_to', $app->getUserState('com_tienda.products.filter.price_to', null), 'float'));
        // $app->setUserState('com_tienda.products.filter.price_to', $this->getState('filter.price_to'));
        // $this->setState('filter.quantity_from', $input->getInt('quantity_from', $app->getUserState('com_tienda.products.filter.quantity_from', null)));
        // $app->setUserState('com_tienda.products.filter.quantity_from', $this->getState('filter.quantity_from'));
        // $this->setState('filter.quantity_to', $input->getInt('quantity_to', $app->getUserState('com_tienda.products.filter.quantity_to', null)));
        // $app->setUserState('com_tienda.products.filter.quantity_to', $this->getState('filter.quantity_to'));

        // EAV Filters from filter_products.xml
        // Example for eav_color
        $eavColorValue = $input->getString('filter_eav_color', $app->getUserState('com_tienda.products.filter.eav_color', ''));
        $this->setState('filter.eav_color', $eavColorValue);
        $app->setUserState('com_tienda.products.filter.eav_color', $eavColorValue);

        // Example for eav_material
        $eavMaterialValue = $input->getString('filter_eav_material', $app->getUserState('com_tienda.products.filter.eav_material', ''));
        $this->setState('filter.eav_material', $eavMaterialValue);
        $app->setUserState('com_tienda.products.filter.eav_material', $eavMaterialValue);

        // TODO: Port EAV filters from TiendaModelEav and store them in state.
        // Example: $eavValue = $input->getString('eav_someattribute', $app->getUserState('com_tienda.products.filter.eav_someattribute', ''));
        // $this->setState('filter.eav_someattribute', $eavValue);
        // $app->setUserState('com_tienda.products.filter.eav_someattribute', $eavValue);
        // if ($this->getState('filter.eav_someattribute')) { // Example check
        // Factory::getApplication()->enqueueMessage('populateState in ProductsModel needs EAV filter states to be added.', 'notice');
        // }
    }

    /**
     * Prepare and sanitise the item prior to display.
     *
     * @param   object  \$item  The item to prepare.
     *
     * @return  void
     * @since   4.0.0
     */
    protected function prepareItem(&$item) // Note: parameter is by reference
    {
        if (isset($item->product_params) && is_string($item->product_params)) {
            $item->product_parameters = new Registry($item->product_params);
        } elseif (!isset($item->product_parameters)) {
            $item->product_parameters = new Registry(); // Ensure it's always a Registry object
        }

        // TODO: Port EAV data loading and other item preparations from TiendaModelEav::prepareItem and TiendaModelProducts::prepareItem.
        Factory::getApplication()->enqueueMessage('prepareItem in ProductsModel needs EAV and other data preparations.', 'notice');
    }

    // Stub out other public methods from TiendaModelProducts
    public function getItemid()
    {
        Factory::getApplication()->enqueueMessage('Method getItemid needs refactoring.', 'notice');
        return null;
    }

    public function getAlias()
    {
        Factory::getApplication()->enqueueMessage('Method getAlias needs refactoring.', 'notice');
        return null;
    }

    public function getCategories()
    {
        Factory::getApplication()->enqueueMessage('Method getCategories needs refactoring.', 'notice');
        return [];
    }

    // Add more stubs as identified from the old model...
    public function getSurrounding($id, $refresh = false)
    {
        Factory::getApplication()->enqueueMessage('Method getSurrounding needs refactoring.', 'notice');
        return ['prev' => '', 'next' => ''];
    }

    public function getPAOCategories($category_ids = array())
    {
        Factory::getApplication()->enqueueMessage('Method getPAOCategories needs refactoring for EAV logic.', 'notice');
        return [];
    }

    public function getPAOCategoryOptions($pa_ids)
    {
        Factory::getApplication()->enqueueMessage('Method getPAOCategoryOptions needs refactoring for EAV logic.', 'notice');
        return [];
    }

    public function isInWishlist($product_id, $xref_id, $xref_type = 'user', $attributes = '')
    {
        Factory::getApplication()->enqueueMessage('Method isInWishlist needs refactoring.', 'notice');
        return false;
    }

    // clearCache method was present in TiendaModelProducts, let's ensure it's here or in a base if used.
    // Joomla's BaseDatabaseModel (parent of ListModel) has a clearCache method,
    // so specific clearing logic might be needed if it did more than just parent::clearCache().
    // The old clearCache also called clearCacheAuxiliary.
    public function clearCache($group = null, $client_id = 0) // J5 BaseDatabaseModel signature
    {
         parent::clearCache($group, $client_id);
         $this->clearCacheAuxiliary(); // Call the auxiliary cache clearing
    }

    public function clearCacheAuxiliary()
    {
        // TODO: Port logic from TiendaModelProducts::clearCacheAuxiliary
        // This involved clearing caches for related models like ProductCategories, ProductAttributeOptions, etc.
        // For J5, this might involve getting model instances via MVCFactory and calling their clearCache methods.
        Factory::getApplication()->enqueueMessage('Method clearCacheAuxiliary needs refactoring for J5 model dependencies.', 'notice');
    }

    /**
     * Method to publish/unpublish a list of items
     *
     * @param   array  $pks    An array of primary S.
     * @param   bool   $state  The publishing state. True for published, false for unpublished.
     *
     * @return  bool   True on success, false on failure.
     * @since   1.7.0
     */
    public function publish(array $pks, bool $state): bool
    {
        ArrayHelper::toInteger($pks);
        // Ensure we are using the correct table class, including its namespace
        $table = $this->getTable('Product', 'Dioscouri\\Component\\Tienda\\Administrator\\Table\\');
        $success = true;

        if (empty($pks)) {
            return false;
        }

        foreach ($pks as $pk) {
            if ($table->load($pk)) {
                // The publish method in JTable/NestedTable expects an array of PKs and the state.
                // However, since we are in a loop, we can call it for each item,
                // or call a modified version if our table class has one.
                // The standard JTable::publish expects $pks as array, $state (0/1), $userId (optional)
                // It internally sets ->product_enabled = $state and calls store().
                // For simplicity here, directly setting and saving.
                // Or, if ProductTable has a JTable-compatible publish method:
                // if (!$table->publish([$pk], $state ? 1 : 0)) {
                // For direct manipulation if `publish` method is not suitable or not yet J5 compatible in Table Class:
                $table->product_enabled = $state ? 1 : 0;
                if (!$table->store()) {
                    $this->setError($table->getError());
                    $success = false;
                }
            } else {
                $this->setError(Text::sprintf('COM_TIENDA_ITEM_LOAD_FAILED', $pk));
                $success = false;
            }
        }
        return $success;
    }

    /**
     * Method to delete a list of items
     *
     * @param   array  $pks  An array of primary keys.
     *
     * @return  bool   True on success, false on failure.
     */
    public function delete(array $pks): bool
    {
        ArrayHelper::toInteger($pks);
        $table = $this->getTable('Product', 'Dioscouri\\Component\\Tienda\\Administrator\\Table\\');
        $success = true;

        if (empty($pks)) {
            return false;
        }

        foreach ($pks as $pk) {
            if ($table->load($pk)) {
                // Delete Category Xrefs
                try {
                    $query = $this->getDbo()->getQuery(true)
                        ->delete($this->getDbo()->quoteName('#__tienda_productcategoryxref'))
                        ->where($this->getDbo()->quoteName('product_id') . ' = ' . (int)$pk);
                    $this->getDbo()->setQuery($query)->execute();
                } catch (\Exception $e) {
                    $this->setError(Text::sprintf('COM_TIENDA_ERROR_DELETING_CATEGORY_XREF', $pk) . ': ' . $e->getMessage());
                    $success = false; // Mark as not fully successful
                    // Continue to delete the main product if desired, or return false here
                }

                // Delete Coupon Xrefs
                try {
                    $query = $this->getDbo()->getQuery(true)
                        ->delete($this->getDbo()->quoteName('#__tienda_productcouponxref'))
                        ->where($this->getDbo()->quoteName('product_id') . ' = ' . (int)$pk);
                    $this->getDbo()->setQuery($query)->execute();
                } catch (\Exception $e) {
                    $this->setError(Text::sprintf('COM_TIENDA_ERROR_DELETING_COUPON_XREF', $pk) . ': ' . $e->getMessage());
                    $success = false;
                }

                // Delete Product Prices
                try {
                    $query = $this->getDbo()->getQuery(true)
                        ->delete($this->getDbo()->quoteName('#__tienda_productprices'))
                        ->where($this->getDbo()->quoteName('product_id') . ' = ' . (int)$pk);
                    $this->getDbo()->setQuery($query)->execute();
                } catch (\Exception $e) {
                    $this->setError(Text::sprintf('COM_TIENDA_ERROR_DELETING_PRODUCT_PRICES', $pk) . ': ' . $e->getMessage());
                    $success = false;
                }

                // Delete Product Quantities
                try {
                    $query = $this->getDbo()->getQuery(true)
                        ->delete($this->getDbo()->quoteName('#__tienda_productquantities'))
                        ->where($this->getDbo()->quoteName('product_id') . ' = ' . (int)$pk);
                    $this->getDbo()->setQuery($query)->execute();
                } catch (\Exception $e) {
                    $this->setError(Text::sprintf('COM_TIENDA_ERROR_DELETING_PRODUCT_QUANTITIES', $pk) . ': ' . $e->getMessage());
                    $success = false;
                }

                // Delete EAV Values for this product
                try {
                    // Assuming 'products' is the eaventity_type for products.
                    // EavHelper::deleteEavValuesFromEntity was already refactored to use new tables.
                    EavHelper::deleteEavValuesFromEntity('products', (int)$pk);
                    // Factory::getApplication()->enqueueMessage(Text::sprintf('COM_TIENDA_DEBUG_DELETED_EAV_FOR_PRODUCT', $pk), 'message'); // Optional debug
                } catch (\Exception $e) {
                    $this->setError(Text::sprintf('COM_TIENDA_ERROR_DELETING_EAV_VALUES', $pk) . ': ' . $e->getMessage());
                    $success = false; // Mark as not fully successful
                }

                // Delete Physical Product Files and their DB records including related logs/downloads
                $dbHost = $this->getDbo(); // Renamed to avoid conflict with $db from the loop for product files query
                $queryFiles = $dbHost->getQuery(true)
                    ->select([$dbHost->quoteName('productfile_id'), $dbHost->quoteName('productfile_path')])
                    ->from($dbHost->quoteName('#__tienda_productfiles'))
                    ->where($dbHost->quoteName('product_id') . ' = ' . (int)$pk);
                $dbHost->setQuery($queryFiles);
                $productFileRecords = $dbHost->loadObjectList();

                if (!empty($productFileRecords)) {
                    $productFileTable = new ProductFileTable($dbHost);
                    foreach ($productFileRecords as $fileRecord) {
                        if ($productFileTable->load($fileRecord->productfile_id)) {
                            if (!empty($productFileTable->absolute_productfile_path) && File::exists($productFileTable->absolute_productfile_path)) {
                                if (File::delete($productFileTable->absolute_productfile_path)) {
                                    Factory::getApplication()->enqueueMessage(Text::sprintf('COM_TIENDA_PHYSICAL_FILE_DELETE_SUCCESS', $productFileTable->absolute_productfile_path), 'message');
                                } else {
                                    Factory::getApplication()->enqueueMessage(Text::sprintf('COM_TIENDA_PHYSICAL_FILE_DELETE_ERROR', $productFileTable->absolute_productfile_path), 'error');
                                    // $success = false; // Optionally make overall delete fail
                                }
                            } elseif (!empty($productFileTable->productfile_path) && empty($productFileTable->absolute_productfile_path)) {
                                 Factory::getApplication()->enqueueMessage(Text::sprintf('COM_TIENDA_PHYSICAL_FILE_PATH_INVALID_OR_NOT_ABSOLUTE', $productFileTable->productfile_path), 'warning');
                            } else if (!empty($productFileTable->productfile_path)) {
                                // Path was set, but file didn't exist at absolute_productfile_path
                                // Factory::getApplication()->enqueueMessage(Text::sprintf('COM_TIENDA_PHYSICAL_FILE_NOT_FOUND', $productFileTable->absolute_productfile_path), 'notice');
                            }
                        }

                        // Now delete the DB record for this productfile using ProductFileTable::delete(),
                        // which also handles its related productdownloads and productdownloadlogs records.
                        $productFileTableForDelete = new ProductFileTable($dbHost); // New instance to avoid issues with loaded state if any
                        if (!$productFileTableForDelete->delete($fileRecord->productfile_id)) {
                             $this->setError(Text::sprintf('COM_TIENDA_ERROR_DELETING_PRODUCTFILE_DB_RECORD', $fileRecord->productfile_id) . ': ' . $productFileTableForDelete->getError());
                             $success = false;
                        }
                    }
                }

                // Delete Product Relations (where this product is either product_id_from or product_id_to)
                try {
                    $db = $this->getDbo();
                    $relationTable = new ProductRelationTable($db);

                    // Delete where product_id_from = $pk
                    $queryDeleteFrom = $db->getQuery(true)
                        ->delete($relationTable->getTableName())
                        ->where($db->quoteName('product_id_from') . ' = ' . (int)$pk);
                    $db->setQuery($queryDeleteFrom)->execute();

                    // Delete where product_id_to = $pk
                    $queryDeleteTo = $db->getQuery(true)
                        ->delete($relationTable->getTableName())
                        ->where($db->quoteName('product_id_to') . ' = ' . (int)$pk);
                    $db->setQuery($queryDeleteTo)->execute();

                } catch (\Exception $e) {
                    $this->setError(Text::sprintf('COM_TIENDA_ERROR_DELETING_PRODUCT_RELATIONS', $pk) . ': ' . $e->getMessage());
                    $success = false; // Mark as not fully successful
                }

                // Delete Product Comments for this product
                try {
                    $db = $this->getDbo();
                    $commentsQuery = $db->getQuery(true)
                        ->select($db->quoteName('productcomment_id'))
                        ->from($db->quoteName('#__tienda_productcomments'))
                        ->where($db->quoteName('product_id') . ' = ' . (int)$pk);
                    $db->setQuery($commentsQuery);
                    $commentIds = $db->loadColumn();

                    if (!empty($commentIds)) {
                        // Use application's MVC factory to get model to avoid direct instantiation issues
                        // Ensure the model name 'ProductComments' matches the class ProductCommentsModel
                        $commentModel = Factory::getApplication()->bootComponent('com_tienda')->getMVCFactory()->createModel('ProductComments', 'Administrator', ['ignore_request' => true]);
                        if ($commentModel instanceof \Dioscouri\Component\Tienda\Administrator\Model\ProductCommentsModel) {
                            if (!$commentModel->delete($commentIds)) { // Pass by value, model expects array
                                // Collect errors from comment model if any
                                foreach($commentModel->getErrors() as $errMsg) { $this->setError($errMsg); }
                                $success = false; // Mark overall product delete as not fully successful
                                Factory::getApplication()->enqueueMessage(Text::sprintf('COM_TIENDA_ERROR_DELETING_PRODUCT_COMMENTS_FOR_PRODUCT', $pk), 'error');
                            }
                        } else {
                             $this->setError(Text::sprintf('COM_TIENDA_ERROR_LOADING_PRODUCTCOMMENTS_MODEL_FOR_PRODUCT', $pk));
                             $success = false;
                        }
                    }
                } catch (\Exception $e) {
                    $this->setError(Text::sprintf('COM_TIENDA_ERROR_DELETING_PRODUCT_COMMENTS_FOR_PRODUCT', $pk) . ': ' . $e->getMessage());
                    $success = false;
                }

                // TODO: Implement cascading delete for other complex related data.
                // Basic xrefs, prices, quantities, EAV values, product files (DB & physical), product relations, and product comments are now handled.
                // (Note: comment helpfulness & product rating updates are TODOs within ProductCommentsModel::delete)

                if (!$table->delete($pk)) {
                    $this->setError($table->getError());
                    $success = false;
                }
            } else {
                $this->setError(Text::sprintf('COM_TIENDA_ITEM_LOAD_FAILED_FOR_DELETE', $pk));
                $success = false;
            }
        }
        return $success;
    }
}
