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
use Dioscouri\Component\Tienda\Administrator\Helper\EavHelper;

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

        // Dynamically add EAV fields
        $app = Factory::getApplication();
        $item_id = $this->getState($this->getName() . '.id'); // Get current product ID from model state if available
        if (empty($item_id) && isset($data['product_id'])) { // Fallback for new items if data is pre-filled
            $item_id = (int) $data['product_id'];
        }
        if (empty($item_id)) { // Try to get it from input if it's an edit operation
             $item_id = $app->input->getInt('product_id', 0);
        }

        $entityType = 'products'; // Assuming 'products' entity type for now
        // Only try to add EAV fields if we have an item context or for a new product generally
        // For a new product ($item_id = 0), we might load all 'products' EAVs.
        // For an existing product, we load its specific EAVs (getAttributes might handle this distinction).

        $eavAttributes = EavHelper::getAttributes($entityType, $item_id ?: 0, true); // Get enabled attributes for 'products'

        // $eavFieldsXmlString will accumulate the XML for each EAV field.
        $eavFieldsXmlString = '';

        if (!empty($eavAttributes)) {
            foreach ($eavAttributes as $eavAttribute) {
                if (!empty($eavAttribute->eavattribute_alias) && !empty($eavAttribute->eavattribute_label)) {
                    $fieldName = htmlspecialchars($eavAttribute->eavattribute_alias, ENT_QUOTES, 'UTF-8');
                    $fieldLabel = htmlspecialchars(Text::_($eavAttribute->eavattribute_label), ENT_QUOTES, 'UTF-8');
                    $fieldDesc = htmlspecialchars(Text::_('COM_TIENDA_EAV_FIELD_DESC'), ENT_QUOTES, 'UTF-8');
                    $currentFieldXml = '';

                    switch (strtolower($eavAttribute->eavattribute_type)) {
                        case 'text':
                        case 'textarea':
                            $currentFieldXml = '<field name="' . $fieldName . '" type="textarea" rows="3"'.
                                             ' label="' . $fieldLabel . '"'.
                                             ' description="' . $fieldDesc . '" />';
                            break;
                        case 'select':
                        case 'list':
                        case 'radio':
                            $fieldType = (strtolower($eavAttribute->eavattribute_type) === 'radio') ? 'radio' : 'list';
                            $fieldOptionsXml = '';
                            $db = Factory::getDbo();
                            $optionsQuery = $db->getQuery(true)
                                ->select([$db->quoteName('eavattributeoption_value'), $db->quoteName('eavattributeoption_name')])
                                ->from($db->quoteName('#__tienda_eavattributeoptions'))
                                ->where($db->quoteName('eavattribute_id') . ' = ' . (int)$eavAttribute->eavattribute_id)
                                ->order($db->quoteName('ordering') . ' ASC');
                            $db->setQuery($optionsQuery);
                            try {
                                $eavOptions = $db->loadObjectList();
                                if (!empty($eavOptions)) {
                                    foreach ($eavOptions as $opt) {
                                        $optionValue = htmlspecialchars($opt->eavattributeoption_value ?? $opt->eavattributeoption_name, ENT_QUOTES, 'UTF-8');
                                        $optionText = htmlspecialchars(Text::_($opt->eavattributeoption_name), ENT_QUOTES, 'UTF-8');
                                        $fieldOptionsXml .= '<option value="' . $optionValue . '">' . $optionText . '</option>';
                                    }
                                }
                            } catch (\Exception $e) {
                                Factory::getApplication()->enqueueMessage(Text::sprintf('COM_TIENDA_ERROR_LOADING_EAV_OPTIONS', $eavAttribute->eavattribute_alias) . ': ' . $e->getMessage(), 'error');
                            }
                            $currentFieldXml = '<field name="' . $fieldName . '" type="' . $fieldType . '"'.
                                             ' default="" label="' . $fieldLabel . '"'.
                                             ' description="' . $fieldDesc . '">' . $fieldOptionsXml . '</field>';
                            break;
                        case 'boolean':
                        case 'bool':
                             $currentFieldXml = '<field name="' . $fieldName . '" type="radio" class="btn-group btn-group-yesno" default="0" '.
                                          ' label="' . $fieldLabel . '"'.
                                          ' description="' . $fieldDesc . '">'.
                                          '<option value="1">JYES</option><option value="0">JNO</option></field>';
                            break;
                        case 'date':
                        case 'datetime':
                            $fieldFormat = (!empty($eavAttribute->eavattribute_format_date)) ? $eavAttribute->eavattribute_format_date : '%Y-%m-%d %H:%M:%S';
                            $currentFieldXml = '<field name="' . $fieldName . '" type="calendar"'.
                                          ' label="' . $fieldLabel . '"'.
                                          ' description="' . $fieldDesc . '" format="' . htmlspecialchars($fieldFormat, ENT_QUOTES, 'UTF-8') . '" />';
                            break;
                        default:
                            $currentFieldXml = '<field name="' . $fieldName . '" type="text"'.
                                          ' label="' . $fieldLabel . '"'.
                                          ' description="' . $fieldDesc . '" />';
                            break;
                    }
                    $eavFieldsXmlString .= $currentFieldXml;
                } else {
                     Factory::getApplication()->enqueueMessage(Text::sprintf('COM_TIENDA_DEBUG_EAV_ATTRIBUTE_SKIPPED_FORM', $eavAttribute->eavattribute_id ?? 'UNKNOWN'), 'warning');
                }
            } // End foreach

            // After the loop, load the accumulated XML string into the 'eav_attributes' fieldset defined in product.xml
            if (!empty($eavFieldsXmlString) && $form->getFieldset('eav_attributes')) {
                $finalXmlToLoad = '<form><fieldset name="eav_attributes">' . $eavFieldsXmlString . '</fieldset></form>';
                if (!$form->loadString($finalXmlToLoad, true)) { // true to merge with existing form data/structure
                    Factory::getApplication()->enqueueMessage(Text::_('COM_TIENDA_ERROR_LOADING_EAV_XML_FORM_MERGE'), 'error');
                }
                // Use the new language string for processed fields
                Factory::getApplication()->enqueueMessage(Text::sprintf('COM_TIENDA_DEBUG_PROCESSED_N_EAV_FIELDS_FORM', count($eavAttributes)), 'message');
            } elseif (!empty($eavFieldsXmlString) && !$form->getFieldset('eav_attributes')) {
                Factory::getApplication()->enqueueMessage(Text::_('COM_TIENDA_EAV_FIELDSET_NOT_FOUND_IN_FORM'), 'error');
            }
        }

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
            // Load EAV attributes and other related data and attach to $item.
            if ($item && isset($item->product_id) && $item->product_id > 0) { // Ensure item is loaded and has an ID
                $entityType = 'products'; // Assuming 'products' is the eaventity_type for products
                // In Tienda, eaventity_type was often the table suffix, e.g., 'products', 'users'
                // It might also be 'com_tienda.product' if more namespaced. This needs verification against #__tienda_eavattributes data.
                // For now, let's assume 'products'.

                $eavAttributes = EavHelper::getAttributes($entityType, $item->product_id, true); // true for only_enabled

                if (!empty($eavAttributes)) {
                    Factory::getApplication()->enqueueMessage(Text::sprintf('COM_TIENDA_DEBUG_LOADED_N_EAV_ATTRIBUTES', count($eavAttributes), $entityType, $item->product_id), 'message'); // Debug
                    foreach ($eavAttributes as $eavAttribute) {
                        if (isset($eavAttribute->eavattribute_alias) && !empty($eavAttribute->eavattribute_alias)) {
                            $value = EavHelper::getAttributeValue($eavAttribute, $entityType, $item->product_id);
                            $alias = $eavAttribute->eavattribute_alias;
                            $item->{$alias} = $value;
                        } else {
                            Factory::getApplication()->enqueueMessage(Text::sprintf('COM_TIENDA_DEBUG_EAV_ATTRIBUTE_MISSING_ALIAS', $eavAttribute->eavattribute_id ?? 'UNKNOWN'), 'warning'); // Debug
                        }
                    }
                } else {
                     // Factory::getApplication()->enqueueMessage(Text::sprintf('COM_TIENDA_DEBUG_NO_EAV_ATTRIBUTES_FOUND', \$entityType, \$item->product_id), 'message'); // Debug
                }
            }

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

        // Save EAV attributes
        $entityType = 'products'; // Assuming 'products' entity type
        $entity_id = $table->{$pkName}; // This is the product_id of the saved product

        if ($entity_id > 0) {
            $eavAttributes = EavHelper::getAttributes($entityType, $entity_id, false); // Get all attributes, not just enabled, as some might have data

            if (!empty($eavAttributes)) {
                $eavValueTable = new \Dioscouri\Component\Tienda\Administrator\Table\EavValueTable(Factory::getDbo());
                $originalErrors = $this->getErrors(); // Preserve existing errors
                $this->_errors = []; // Clear errors for this batch of EAV saves

                foreach ($eavAttributes as $eavAttribute) {
                    if (isset($eavAttribute->eavattribute_alias) && array_key_exists($eavAttribute->eavattribute_alias, $data)) {
                        $alias = $eavAttribute->eavattribute_alias;
                        $submitted_value = $data[$alias];

                        $eavValueTable->setType($eavAttribute->eavattribute_type);
                        // Attempt to load existing value
                        $loaded = $eavValueTable->load([
                            'eavattribute_id' => $eavAttribute->eavattribute_id,
                            'eaventity_id'    => $entity_id,
                            'eaventity_type'  => $entityType
                            // Note: EavValueTable does not have eaventity_type as a key, but it's good for filtering if multiple entities share value tables
                            // The load by array of keys on JTable usually expects primary keys or unique composite keys.
                            // We might need a custom load method in EavValueTable if it doesn't support this well.
                            // For now, assuming load by eavattribute_id + eaventity_id is what we need.
                            // A more robust load would be: load by PK if known, or by attribute+entity if new.
                            // For simplicity, let's assume we always try to load and then set keys for store.
                        ]);

                        // If no value loaded, or if value is different, or if it's a new attribute linkage
                        // We need to ensure primary key `eavvalue_id` is reset if we intend to insert a new value row vs update an old one.
                        // For this EAV structure, we usually have one row per attribute-entity pair.
                        if(!\$loaded){
                            $eavValueTable->eavvalue_id = 0; // Ensure it's a new record if load failed
                            $eavValueTable->eavattribute_id = $eavAttribute->eavattribute_id;
                            $eavValueTable->eaventity_id    = $entity_id;
                            $eavValueTable->eaventity_type  = $entityType;
                        } else {
                            // If loaded, and value is empty string and original wasn't (or vice versa), we might want to delete or save empty.
                            // For now, always update.
                        }

                        $eavValueTable->eavvalue_value = $submitted_value;

                        if (!\$eavValueTable->check()) {
                            $this->setError(Text::sprintf('COM_TIENDA_EAV_VALUE_CHECK_FAILED', \$alias) . ': ' . \$eavValueTable->getError());
                            continue; // Skip storing this attribute
                        }
                        if (!\$eavValueTable->store()) {
                            $this->setError(Text::sprintf('COM_TIENDA_EAV_VALUE_STORE_FAILED', \$alias) . ': ' . \$eavValueTable->getError());
                        }
                    }
                }
                // Merge EAV errors with any previous errors
                \$eavErrors = \$this->getErrors();
                \$this->_errors = array_merge(\$originalErrors, \$eavErrors);
            }
        }

        // TODO: Save other related data (prices, quantities per attribute, multiple categories, gallery images).
        // Factory::getApplication()->enqueueMessage('ProductModel::save() Related data saving (prices, attributes, etc.) is a TODO for product ' . \$table->{\$pkName}, 'notice');

        return empty(\$this->_errors); // Return true if no errors (including EAV ones)
    }
}
