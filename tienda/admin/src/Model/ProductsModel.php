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

// It's good practice to also use Text if any JText calls are made, though not strictly required for it to function
use Joomla\CMS\Language\Text;

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
        $app = Factory::getApplication(); // For messages, params

        // SELECT fields from _buildQueryFields
        $query->select($this->getState('list.select', 'tbl.*'));

        // Extra fields from old _buildQueryFields
        if ($this->getState('filter.category')) {
            $query->select('c.category_name AS category_name');
        }
        $query->select('m.manufacturer_name AS manufacturer_name');

        $date = Factory::getDate()->toSql(); // Changed from toMysql()
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
        // $filter_attribute_set = $this->getState('filter_attribute_set'); // TODO: EAV attribute set filter
        if (!empty($filter_sku) /* || !empty($filter_attribute_set) */) {
            $query->join('LEFT', $db->quoteName('#__tienda_productattributes') . ' AS pa ON pa.product_id = tbl.product_id');
            if(!empty($filter_sku)){
                $query->join('LEFT', $db->quoteName('#__tienda_productattributeoptions') . ' AS pao ON pa.productattribute_id = pao.productattribute_id');
            }
        }

        // WHERE clauses from _buildQueryWhere (partial port, focusing on direct filters)
        $filter_id_from = $this->getState('filter.id_from');
        if (strlen($filter_id_from)) {
            $query->where('tbl.product_id >= ' . (int) $filter_id_from);
        }
        $filter_id_to = $this->getState('filter.id_to');
        if (strlen($filter_id_to)) {
            $query->where('tbl.product_id <= ' . (int) $filter_id_to);
        }
        // $filter_id_set = $this->getState('filter.id_set'); // TODO: Implement if needed
        // if (strlen($filter_id_set)) { $query->where('tbl.product_id IN (' . $filter_id_set . ')'); }

        $filter_name_q = $this->getState('filter.name');
        if (strlen($filter_name_q)) {
            $key = $db->quote('%' . $db->escape(trim(strtolower($filter_name_q)), true) . '%', false);
            $query->where('LOWER(tbl.product_name) LIKE ' . $key);
        }

        $filter_enabled_q = $this->getState('filter.enabled');
        if (strlen($filter_enabled_q)) {
            $query->where('tbl.product_enabled = ' . $db->quote((string)$filter_enabled_q));
        }

        // TODO: filter_quantity_from and filter_quantity_to require subqueries on productquantities table or using the alias from select.
        // For now, adding a placeholder. These are complex.
        $filter_quantity_from = $this->getState('filter.quantity_from');
         if (strlen($filter_quantity_from)) {
             // $query->having( $db->quoteName('current_stock') . ' >= ' . (int) $filter_quantity_from); // Needs to be HAVING
             $app->enqueueMessage('getListQuery: filter_quantity_from needs to use HAVING clause or a more complex subquery in WHERE.', 'notice');
         }
        $filter_quantity_to = $this->getState('filter.quantity_to');
         if (strlen($filter_quantity_to)) {
             // $query->having( $db->quoteName('current_stock') . ' <= ' . (int) $filter_quantity_to); // Needs to be HAVING
             $app->enqueueMessage('getListQuery: filter_quantity_to needs to use HAVING clause or a more complex subquery in WHERE.', 'notice');
         }


        $filter_category_q = $this->getState('filter.category');
        if ($filter_category_q === 'none') {
             $query->where("NOT EXISTS (SELECT * FROM #__tienda_productcategoryxref AS p2c_check WHERE tbl.product_id = p2c_check.product_id)");
        } elseif (strlen($filter_category_q) && $filter_category_q > 0) {
            $query->where('p2c.category_id = ' . (int) $filter_category_q);
        }
        // TODO: filter_multicategory and filter_multicategoryoperator are complex, require temp tables or advanced subqueries. Placeholder for now.

        if (strlen($filter_sku)) {
            $key = $db->quote('%' . $db->escape(trim(strtolower($filter_sku)), true) . '%', false);
            $query->where('(LOWER(tbl.product_sku) LIKE ' . $key . ' OR LOWER(pao.productattributeoption_code) LIKE ' . $key . ')');
        }

        // TODO: filter_price_from and filter_price_to require HAVING or subquery on prices. Placeholder.
        $filter_price_from = $this->getState('filter.price_from');
        if (strlen($filter_price_from)) {
            // $query->having( $db->quoteName('calculated_price') . ' >= ' . $db->quote($filter_price_from));
            $app->enqueueMessage('getListQuery: filter_price_from needs to use HAVING clause.', 'notice');
        }
        $filter_price_to = $this->getState('filter.price_to');
        if (strlen($filter_price_to)) {
            // $query->having( $db->quoteName('calculated_price') . ' <= ' . $db->quote($filter_price_to));
            $app->enqueueMessage('getListQuery: filter_price_to needs to use HAVING clause.', 'notice');
        }


        $filter_taxclass_q = $this->getState('filter.taxclass');
        if (strlen($filter_taxclass_q)) {
            $query->where('tbl.tax_class_id = ' . (int) $filter_taxclass_q);
        }
        $filter_ships_q = $this->getState('filter.ships');
        if (strlen($filter_ships_q)) {
            $query->where('tbl.product_ships = ' . (int) $filter_ships_q);
        }
        // TODO: filter_date_from, filter_date_to, filter_datetype
        // TODO: filter_published, filter_published_date
        // TODO: filter_manufacturer, filter_manufacturer_set
        // TODO: filter_description, filter_description_short, filter_namedescription
        // TODO: filter_attribute_set (EAV)
        // TODO: filter_rating
        // TODO: filter_pao_names, filter_pao_ids, filter_pao_id_groups (complex EAV)


        // GROUP BY from _buildQueryGroup
        $query->group('tbl.product_id');
        // Also group by other selected non-aggregated fields from main table if not covered by product_id (if product_id is PK, it's fine)
        // and from joined tables like c.category_name, m.manufacturer_name if they are selected.
        // This is important if multiple categories per product can cause multiple rows before grouping.
        if ($this->getState('filter.category')) {
             $query->group('c.category_name');
        }
        $query->group('m.manufacturer_name');
        // If calculated_price and current_stock are used in WHERE via subqueries, they don't need group by.
        // If they are in HAVING, they also don't need to be in GROUP BY.

        // HAVING clause for filters that operate on aggregated values (price, quantity)
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
        // TODO: Handle EAV ordering from _buildQueryOrder in TiendaModelEav (strpos($order, 'value_') === 0)
        // This would require dynamic joins for the EAV attribute being sorted.
        // For now, only allow direct columns or defined aliases.

        $query->order($db->escape($orderCol) . ' ' . $db->escape($orderDirn));

        // TODO: EAV Joins and Where clauses from TiendaModelEav::_buildQueryEav
        // This requires iterating over EAV states and dynamically adding joins and where clauses.
        // Example structure:
        // $eavStates = $this->getState('eav_filters'); // Assuming eav_filters is an array of [alias => value]
        // if (!empty($eavStates)) {
        //     foreach ($eavStates as $alias => $value) {
        //         $eav_tbl_name = 'eav_' . $alias;
        //         $value_tbl_name = 'value_' . $alias;
        //         // Determine table_type based on alias (e.g., from an EAV attribute helper)
        //         // $table_type = TiendaHelperEav::getType($alias); // Placeholder
        //         // $query->join('LEFT', $db->quoteName('#__tienda_eavattributes') . " AS {$eav_tbl_name} ON tbl.product_id = {$eav_tbl_name}.eaventity_id");
        //         // $query->join('LEFT', $db->quoteName("#__tienda_eavvalues{$table_type}") . " AS {$value_tbl_name} ON {$eav_tbl_name}.eavattribute_id = {$value_tbl_name}.eavattribute_id");
        //         // $query->where("{$eav_tbl_name}.eavattribute_alias = " . $db->quote($alias));
        //         // $query->where("{$value_tbl_name}.eavvalue_value = " . $db->quote($value));
        //     }
        // }
        $app->enqueueMessage('getListQuery in ProductsModel needs full EAV implementation for joins, where, and ordering.', 'notice');

        return $query;
    }

    /**
     * Method to auto-populate the model state.
     *
     * Note. Calling getState in this method will result in recursion.
     *
     * @param   string  \$ordering   An optional ordering field.
     * @param   string  \$direction  An optional direction (asc|desc).
     *
     * @return  void
     * @since   1.6
     */
    protected function populateState($ordering = null, $direction = null)
    {
        // Call parent populateState to set up the standard list states (like limit, start, ordering, direction)
        parent::populateState($ordering, $direction);

        $app = Factory::getApplication(); // Use $this->app if available and preferred after parent::populateState
        $input = $app->input;

        // Load the filter state.
        $this->setState('filter.id_from', $input->getInt('filter_id_from', $app->getUserState('com_tienda.products.filter_id_from', null)));
        $this->setState('filter.id_to', $input->getInt('filter_id_to', $app->getUserState('com_tienda.products.filter_id_to', null)));
        $this->setState('filter.name', $input->getString('filter_name', $app->getUserState('com_tienda.products.filter_name', '')));
        $this->setState('filter.enabled', $input->getString('filter_enabled', $app->getUserState('com_tienda.products.filter_enabled', ''))); // Consider specific values like '0', '1', or ''
        $this->setState('filter.quantity_from', $input->getInt('filter_quantity_from', $app->getUserState('com_tienda.products.filter_quantity_from', null)));
        $this->setState('filter.quantity_to', $input->getInt('filter_quantity_to', $app->getUserState('com_tienda.products.filter_quantity_to', null)));
        $this->setState('filter.category', $input->getInt('filter_category', $app->getUserState('com_tienda.products.filter_category', null)));
        $this->setState('filter.sku', $input->getString('filter_sku', $app->getUserState('com_tienda.products.filter_sku', '')));
        $this->setState('filter.price_from', $input->get('filter_price_from', $app->getUserState('com_tienda.products.filter_price_from', null), 'float')); // Use 'float' or 'double' filter
        $this->setState('filter.price_to', $input->get('filter_price_to', $app->getUserState('com_tienda.products.filter_price_to', null), 'float'));
        $this->setState('filter.taxclass', $input->getInt('filter_taxclass', $app->getUserState('com_tienda.products.filter_taxclass', null)));
        $this->setState('filter.ships', $input->getString('filter_ships', $app->getUserState('com_tienda.products.filter_ships', ''))); // Consider specific values

        $compParams = ComponentHelper::getParams('com_tienda');
        $this->setState('filter.group', $compParams->get('default_user_group', '1')); // Default from component params

        // TODO: Port EAV filters from TiendaModelEav and store them in state.
        // Example: $this->setState('filter_eav_manufacturer', $input->getString('filter_eav_manufacturer', $app->getUserState('com_tienda.products.filter_eav_manufacturer', '')));
        Factory::getApplication()->enqueueMessage('populateState in ProductsModel needs EAV filter states to be added.', 'notice');

        // List state variables
        $this->setState('list.ordering', $ordering);
        $this->setState('list.direction', $direction);
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
}
