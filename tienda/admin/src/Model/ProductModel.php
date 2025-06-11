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
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Model\AdminModel;
use Joomla\CMS\Form\Form;
use Joomla\CMS\Component\ComponentHelper;
use Dioscouri\Component\Tienda\Administrator\Table\ProductTable; // Correct path to ProductTable
use Joomla\Registry\Registry; // For product_params in getItem

class ProductModel extends AdminModel
{
    /**
     * Constructor.
     *
     * @param   array  $config  An optional associative array of configuration settings.
     *
     * @see     \Joomla\CMS\MVC\Model\BaseDatabaseModel
     * @since   1.6
     */
    public function __construct($config = [])
    {
        $config['event_map'] = array_merge(
            isset($config['event_map']) ? $config['event_map'] : [],
            [
                'onPrepareTable' => 'Product', // Example event if needed
                'onBeforeSave'   => 'Product', // Example event if needed
                'onAfterSave'    => 'Product', // Example event if needed
                'onBeforeDelete' => 'Product', // Example event if needed
                'onAfterDelete'  => 'Product', // Example event if needed
            ]
        );
        parent::__construct($config);
    }

    /**
     * Method to get a table object, load it if necessary.
     *
     * @param   string  $name     The table name. Optional.
     * @param   string  $prefix   The class prefix. Optional.
     * @param   array   $options  Configuration array for model. Optional.
     *
     * @return  \Joomla\CMS\Table\Table  A \Joomla\CMS\Table\Table object
     *
     * @throws  \Exception if the table object cannot be instantiated
     */
    public function getTable($name = 'Product', $prefix = 'Administrator', $options = [])
    {
        // Ensure we are using our specific ProductTable
        if ($name === 'Product' && $prefix === 'Administrator') {
             // Make sure ProductTable is correctly namespaced and loaded
            return new ProductTable(Factory::getDbo());
        }
        // Fallback to parent if a different table is requested for some reason
        return parent::getTable($name, $prefix, $options);
    }

    /**
     * Method to get the record form.
     *
     * @param   array    $data      Data for the form.
     * @param   boolean  $loadData  True if the form is to load its own data (default case), false if not.
     *
     * @return  Form|boolean  A Form object on success, false on failure.
     * @since   1.6
     */
    public function getForm($data = array(), $loadData = true)
    {
        // Get the form.
        $options = ['control' => 'jform', 'load_data' => $loadData];
        $form = $this->loadForm('com_tienda.product', 'product', $options);

        if (empty($form)) {
            $this->setError(Text::_('COM_TIENDA_FORM_NOT_FOUND_PRODUCT'));
            return false;
        }

        // TODO: Dynamically modify form here for EAV attributes if needed.
        // Factory::getApplication()->enqueueMessage('ProductModel::getForm() EAV modification is a TODO.', 'notice');

        return $form;
    }

    /**
     * Method to get the data that should be injected in the form.
     *
     * @return  mixed  The data for the form.
     * @since   1.6
     */
    protected function loadFormData()
    {
        $app = Factory::getApplication();
        // Check the session for previously entered form data.
        $data = $app->getUserState('com_tienda.edit.product.data', array());

        if (empty($data)) {
            $data = $this->getItem(); // getItem() will load the product data

            // TODO: Pre-fill defaults for new products if not handled by getItem() or form XML defaults.
            // For example, set default enabled state, default category from params, etc.
            if (empty($data->product_id)) { // New product
                $params = ComponentHelper::getParams('com_tienda');
                // Example: $data->category_id = $params->get('default_category_for_new_products');
                // Example: $data->product_enabled = 1;
            }
        }
        return $data;
    }

    /**
     * Method to get a single record.
     *
     * @param   integer  $pk  The id of the primary key.
     *
     * @return  mixed    Object on success, false on failure.
     * @since   1.6
     */
    public function getItem($pk = null)
    {
        $item = parent::getItem($pk);

        if ($item && !empty($item->product_id)) {
            // TODO: Load EAV attributes and other related data and attach to $item.
            // Factory::getApplication()->enqueueMessage('ProductModel::getItem() EAV/related data loading is a TODO for product ' . $item->product_id, 'notice');

            // Ensure product_params is a Registry object
            if (isset($item->product_params) && is_string($item->product_params)) {
                try {
                    $item->product_params = new Registry($item->product_params);
                } catch (\Exception $e) {
                    $item->product_params = new Registry(); // Fallback to empty registry on error
                    Factory::getApplication()->enqueueMessage(Text::sprintf('COM_TIENDA_ERROR_PARSING_PRODUCT_PARAMS', $item->product_id, $e->getMessage()), 'warning');
                }
            } elseif (!isset($item->product_params) || !($item->product_params instanceof Registry)) {
                 $item->product_params = new Registry();
            }
        }
        return $item;
    }

    /**
     * Method to save the form data.
     *
     * @param   array  $data  The form data.
     *
     * @return  boolean  True on success, False on error.
     * @since   1.6
     */
    public function save($data)
    {
        $app = Factory::getApplication();
        $table = $this->getTable(); // This will use our overridden getTable()
        $pkName = $table->getKeyName();

        // AdminModel's $this->input is JInput, $app->input is also JInput. Using $app->input for consistency.
        $pk = $app->input->getInt($pkName, $data[$pkName] ?? null);

        // If $data contains the PK, use it. Otherwise, rely on input or it's a new item.
        if (isset($data[$pkName]) && !empty($data[$pkName])) {
            $pk = (int) $data[$pkName];
        }

        if ($pk > 0) {
            if (!$table->load($pk)) {
                $this->setError($table->getError());
                return false;
            }
        } elseif (isset($data[$pkName]) && empty($data[$pkName])) {
            // Ensure PK is null for new records if it's passed as empty string or 0 in data
             unset($data[$pkName]); // Let table handle auto-increment
        }


        // Bind the data.
        if (!$table->bind($data)) {
            $this->setError($table->getError());
            return false;
        }

        // Check the data.
        if (!$table->check()) {
            $this->setError($table->getError());
            return false;
        }

        // Store the data.
        if (!$table->store()) {
            $this->setError($table->getError());
            return false;
        }

        // Set the new state.
        // After store, $table will have the PK, even for new records.
        $this->setState($this->getName() . '.id', $table->{$pkName});

        // Clear the session data.
        $app->setUserState('com_tienda.edit.product.data', null);

        // TODO: Save EAV attributes.
        // Factory::getApplication()->enqueueMessage('ProductModel::save() EAV saving is a TODO for product ' . $table->{$pkName}, 'notice');

        // TODO: Save other related data (prices, quantities per attribute, multiple categories, gallery images).
        // Factory::getApplication()->enqueueMessage('ProductModel::save() Related data saving (prices, attributes, etc.) is a TODO for product ' . $table->{$pkName}, 'notice');

        return true;
    }
}
