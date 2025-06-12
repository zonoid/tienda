<?php
/**
 * @package     Tienda
 * @subpackage  Administrator
 * @author      Dioscouri Design
 * @link        http://www.dioscouri.com
 * @copyright   (C) 2024 Dioscouri Design. All rights reserved.
 * @license     GNU/GPL V2 or later
 */

namespace Dioscouri\Component\Tienda\Administrator\Model;

defined('_JEXEC') or die('Restricted access');

use Joomla\CMS\Factory\Factory;
use Joomla\CMS\MVC\Model\ListModel;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route; // For generating links
use Joomla\CMS\Utilities\ArrayHelper;
use Dioscouri\Component\Tienda\Administrator\Table\ProductCommentTable; // Though getTable() is overridden, good for clarity if used elsewhere
use Dioscouri\Component\Tienda\Administrator\Helper\ProductRatingHelper;

class ProductCommentsModel extends ListModel
{
    /**
     * Constructor.
     *
     * @param   array  $config  An optional associative array of configuration settings.
     */
    public function __construct($config = [])
    {
        if (empty($config['filter_fields'])) {
            $config['filter_fields'] = [
                'productcomment_id', 'tbl.productcomment_id',
                'product_id', 'tbl.product_id',
                'user_id', 'tbl.user_id',
                'user_name', 'tbl.user_name', // Direct column in productcomments
                'username', 'm.name', // From joined users table
                'product_name', 'p.product_name',
                'productcomment_enabled', 'tbl.productcomment_enabled',
                'reported_count', 'tbl.reported_count',
                'created_date', 'tbl.created_date',
            ];
        }
        parent::__construct($config);
    }

    /**
     * Method to auto-populate the model state.
     *
     * @param   string  $ordering   The ordering field.
     * @param   string  $direction  The ordering direction.
     *
     * @return  void
     */
    protected function populateState($ordering = 'tbl.created_date', $direction = 'DESC')
    {
        parent::populateState($ordering, $direction);
        $app = Factory::getApplication();

        // Filter for Product ID (can be from 'filter_productid' or 'filter_product')
        $productId = $this->getUserStateFromRequest($this->context . '.filter.product_id', 'filter_productid', '');
        if (empty($productId)) {
             $productId = $this->getUserStateFromRequest($this->context . '.filter.product_id', 'filter_product', '');
        }
        $this->setState('filter.product_id', $productId);

        // Filter for Enabled state
        $enabled = $this->getUserStateFromRequest($this->context . '.filter.enabled', 'filter_enabled', '');
        $this->setState('filter.enabled', $enabled);

        // Filter for Reported status
        $reported = $this->getUserStateFromRequest($this->context . '.filter.reported', 'filter_reported', '');
        $this->setState('filter.reported', $reported);

        // Filter by product name (from search)
        $search = $this->getUserStateFromRequest($this->context . '.filter.search', 'filter_search', '');
        $this->setState('filter.search', $search);
    }

    /**
     * Method to build an SQL query to load the list data.
     *
     * @return      \Joomla\Database\DatabaseQuery  A \Joomla\Database\DatabaseQuery object.
     */
    protected function getListQuery()
    {
        $db    = $this->getDbo();
        $query = $db->getQuery(true);

        // Select fields
        $query->select($this->getState('list.select', 'tbl.*'));
        $query->select('p.product_name AS product_name');
        $query->select('m.name AS username'); // User's full name from #__users
        // tbl.user_name is the name entered by guest or if user changed it

        // From the productcomments table
        $query->from($this->getTable()->getTableName() . ' AS tbl');

        // Join with products table
        $query->join('LEFT', $db->quoteName('#__tienda_products') . ' AS p ON p.product_id = tbl.product_id');
        // Join with users table
        $query->join('LEFT', $db->quoteName('#__users') . ' AS m ON m.id = tbl.user_id');

        // Apply filters
        // Product ID
        $productId = $this->getState('filter.product_id');
        if (is_numeric($productId) && $productId > 0) {
            $query->where('tbl.product_id = ' . (int) $productId);
        }

        // Enabled state
        $enabled = $this->getState('filter.enabled');
        if ($enabled !== '' && $enabled !== null) {
            $query->where('tbl.productcomment_enabled = ' . (int) $enabled);
        }

        // Reported status
        $reported = $this->getState('filter.reported');
        if ($reported !== '' && $reported !== null) {
            if ((int)$reported > 0) {
                $query->where('tbl.reported_count > 0');
            } else {
                $query->where('tbl.reported_count = 0');
            }
        }

        // Search filter (product name, user name, user email, comment text)
        $search = $this->getState('filter.search');
        if (!empty($search)) {
            $searchQuoted = $db->quote('%' . $db->escape($search, true) . '%', false);
            $searchConditions = [
                'LOWER(p.product_name) LIKE ' . $searchQuoted,
                'LOWER(tbl.user_name) LIKE ' . $searchQuoted, // Name provided at comment time
                'LOWER(m.name) LIKE ' . $searchQuoted,       // Registered user's name
                'LOWER(m.username) LIKE ' . $searchQuoted,   // Registered user's username
                'LOWER(tbl.user_email) LIKE ' . $searchQuoted,
                'LOWER(tbl.comment_text) LIKE ' . $searchQuoted,
            ];
            $query->where('(' . implode(' OR ', $searchConditions) . ')');
        }

        // Add the ordering clause
        $orderCol  = $this->state->get('list.ordering', 'tbl.created_date');
        $orderDirn = $this->state->get('list.direction', 'DESC');
        $query->order($db->escape($orderCol) . ' ' . $db->escape($orderDirn));

        return $query;
    }

    /**
     * Gets an array of data items.
     * Overrides parent getItems to add a link property.
     *
     * @return  array  An array of data items.
     */
    public function getItems()
    {
        $items = parent::getItems();

        if (!empty($items)) {
            foreach ($items as &$item) { // Use reference to modify item directly
                $item->link = Route::_('index.php?option=com_tienda&view=productcomment&layout=edit&id=' . (int) $item->productcomment_id);
            }
        }
        return $items;
    }

    /**
     * Method to get a table object, load it if necessary.
     *
     * @param   string  $name     The table name. Optional.
     * @param   string  $prefix   The class prefix. Optional.
     * @param   array   $options  Configuration array for model. Optional.
     *
     * @return  \Joomla\CMS\Table\Table  A \Joomla\CMS\Table\Table object
     */
    public function getTable($name = 'ProductComment', $prefix = 'Administrator', $options = [])
    {
        if ($name === 'ProductComment' && $prefix === 'Administrator') {
             // Ensure Administrator is the actual prefix expected by Table::getInstance for Administrator tables
             // Or use the full class name if Table::getInstance supports it in J5
            return Table::getInstance('ProductComment', 'Dioscouri\\Component\\Tienda\\Administrator\\Table', $options);
        }
        return parent::getTable($name, $prefix, $options);
    }

    /**
     * Deletes a list of product comments.
     *
     * @param   array  &$pks  An array of primary key values. Passed by reference.
     *
     * @return  bool   True on success, false on failure.
     */
    public function delete(&$pks): bool
    {
        if (empty($pks)) {
            return true;
        }
        // Ensure $pks is an array of integers.
        ArrayHelper::toInteger($pks);

        $success = true;
        $commentTable = $this->getTable();
        $affectedProductIds = [];

        foreach ($pks as $pk) {
            if ($pk <= 0) {
                $this->setError(Text::_('COM_TIENDA_INVALID_COMMENT_ID_FOR_DELETE'));
                $success = false;
                continue;
            }

            if ($commentTable->load($pk)) {
                $productIdForRatingUpdate = $commentTable->product_id;
                if ($productIdForRatingUpdate && !in_array($productIdForRatingUpdate, $affectedProductIds)) {
                    $affectedProductIds[] = $productIdForRatingUpdate;
                }

                // Delete related helpfulness records
                if ($pk > 0) {
                    try {
                        $dbHelpfulness = $this->getDbo();
                        $queryHelpfulness = $dbHelpfulness->getQuery(true)
                            ->delete($dbHelpfulness->quoteName('#__tienda_productcommentshelpfulness'))
                            ->where($dbHelpfulness->quoteName('productcomment_id') . ' = ' . (int)$pk);
                        $dbHelpfulness->setQuery($queryHelpfulness)->execute();
                    } catch (\Exception $e) {
                        $this->setError(Text::sprintf('COM_TIENDA_ERROR_DELETING_COMMENT_HELPFULNESS', $pk) . ': ' . $e->getMessage());
                        // Log error but continue, as main comment deletion is more critical.
                        // $success = false; // Optionally make it critical
                    }
                }

                if (!$commentTable->delete($pk)) {
                    $this->setError($commentTable->getError());
                    $success = false;
                    // If comment deletion fails, remove its product_id from affected list if it was added
                    if (($key = array_search($productIdForRatingUpdate, $affectedProductIds)) !== false) {
                        unset($affectedProductIds[$key]);
                    }
                }
                // No specific per-comment rating update TODO here anymore, will be handled in bulk
            } else {
                $this->setError(Text::sprintf('COM_TIENDA_ITEM_LOAD_FAILED_FOR_DELETE', $pk));
                $success = false;
            }
        }

        if ($success && !empty($affectedProductIds)) {
            // Remove duplicate product IDs just in case
            $uniqueProductIds = array_unique($affectedProductIds);
            foreach ($uniqueProductIds as $prodId) {
                if ($prodId > 0) {
                    if (!ProductRatingHelper::updateProductOverallRating($prodId)) {
                        Factory::getApplication()->enqueueMessage(Text::sprintf('COM_TIENDA_RATING_UPDATE_FAILED_FOR_PRODUCT_AFTER_COMMENT_DELETE', $prodId), 'warning');
                        // This failure doesn't make the whole delete operation fail, but logs a warning.
                    }
                }
            }
        }

        $this->clearCache();
        return $success;
    }
}
?>
