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
use Dioscouri\Component\Tienda\Administrator\Helper\ProductHelper;
use Joomla\CMS\Filesystem\File;
use Joomla\CMS\Filesystem\Folder;
use Joomla\CMS\Filesystem\Path; // Path is used in the new block
use Joomla\CMS\Utilities\ArrayHelper;
use Dioscouri\Component\Tienda\Administrator\Table\ProductCategoryXrefTable;
use Dioscouri\Component\Tienda\Administrator\Table\ProductPriceTable; // For saving prices
use Dioscouri\Component\Tienda\Administrator\Table\ProductQuantityTable; // For saving quantities
use Joomla\CMS\Date\Date; // For product price date conversion
use Dioscouri\Component\Tienda\Administrator\Table\EavAttributeTable;
use Dioscouri\Component\Tienda\Administrator\Table\EavAttributeOptionTable;

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
                        case 'checkboxes':
                        case 'checkboxgroup':
                        case 'multiselect': // Assuming this implies checkboxes for multiple selection
                            $fieldType = 'checkboxes'; // Joomla JForm field type
                            $fieldOptionsXml = '';
                            // Fetch options for this attribute (similar to list/radio)
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
                            $currentFieldXml = '<field name="' . $fieldName . '[]" type="' . $fieldType . '"'. // Note: name appended with [] for multiple values
                                             ' label="' . $fieldLabel . '"'.
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
            // Load selected category IDs
            $db = Factory::getDbo();
            $query = $db->getQuery(true)
                ->select($db->quoteName('category_id'))
                ->from($db->quoteName('#__tienda_productcategoryxref'))
                ->where($db->quoteName('product_id') . ' = ' . (int)$item->product_id);
            $db->setQuery($query);
            $item->category_ids = $db->loadColumn(); // Load as an array of category IDs

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

                            $multiSelectTypes = ['checkboxes', 'checkboxgroup', 'multiselect']; // Match types used in save()
                            if (in_array(strtolower($eavAttribute->eavattribute_type), $multiSelectTypes)) {
                                if (is_string($value)) {
                                    $decodedValue = json_decode($value, true); // true for associative array
                                    if (json_last_error() === JSON_ERROR_NONE) {
                                        $item->{$alias} = $decodedValue; // Assign the array
                                    } else {
                                        // Value might be a legacy non-JSON value, or empty/corrupt JSON.
                                        // Assign empty array for the JForm checkboxes field to handle it gracefully.
                                        $item->{$alias} = [];
                                        if (!empty($value) && $value !== '[]') { // Avoid warning for empty valid JSON array string
                                             Factory::getApplication()->enqueueMessage(Text::sprintf('COM_TIENDA_EAV_MULTISELECT_JSON_DECODE_ERROR', $alias, $value), 'warning');
                                        }
                                    }
                                } elseif (empty($value)) {
                                     $item->{$alias} = []; // Ensure it's an array for the form field if value is null/empty from DB
                                } else {
                                    // It's already an array or some other type, assign as is (though should be string from DB)
                                    $item->{$alias} = $value;
                                }
                            } else {
                                $item->{$alias} = $value; // For non-multiselect types, assign as is
                            }
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

        // Load Product Prices for the subform
        if ($item && isset($item->product_id) && $item->product_id > 0) {
            $db = Factory::getDbo();
            $pricesQuery = $db->getQuery(true)
                ->select('*') // Select all fields from productprices table
                ->from($db->quoteName('#__tienda_productprices'))
                ->where($db->quoteName('product_id') . ' = ' . (int)$item->product_id)
                ->order($db->quoteName('price_quantity_start') . ' ASC'); // Optional: order them

            $db->setQuery($pricesQuery);
            try {
                $item->product_prices = $db->loadObjectList();
                // Dates from DB are GMT. Convert to user's timezone for calendar fields in subform.
                if (!empty($item->product_prices)) {
                    $app = Factory::getApplication();
                    // Ensure 'offset' is correctly retrieved; it might be like $app->get('offset') or $app->getIdentity()->getParam('timezone', $app->get('offset'));
                    $userTimezoneOffset = $app->get('offset');
                    if (strpos($userTimezoneOffset, '.') !== false) { // Handle cases like 'UTC+5.5'
                        // DateTimeZone does not like decimal offsets directly. Convert to HH:MM or a valid named timezone.
                        // This is a simplification. Joomla's User object has a getTimezone method that might be more robust.
                        // For now, assuming a simple offset string that DateTimeZone might handle or defaulting to UTC if complex.
                        // A better way: $userTimeZone = new \DateTimeZone($app->getUser()->getTimezone()->getName());
                        // However, $app->getUser() is J4/5. $app->getIdentity() is for current user.
                        try {
                             $userTimeZone = new \DateTimeZone($userTimezoneOffset);
                        } catch (\Exception $e) {
                             // If offset string is not a valid timezone name (e.g. 'UTC+5.5')
                             // Fallback to UTC or try to construct from offset hours/minutes if possible.
                             // For simplicity, fallback to application's timezone (often UTC if not user-specific)
                             $userTimeZone = new \DateTimeZone(Factory::getConfig()->get('offset'));
                        }

                    } else {
                         $userTimeZone = new \DateTimeZone($userTimezoneOffset);
                    }
                    $dbTimeZone   = new \DateTimeZone('UTC');

                    foreach ($item->product_prices as &$priceRow) { // Use reference to modify directly
                        if (!empty($priceRow->product_price_startdate) && $priceRow->product_price_startdate !== $db->getNullDate()) {
                            $dateObj = new Date($priceRow->product_price_startdate, $dbTimeZone);
                            $dateObj->setTimezone($userTimeZone);
                            $priceRow->product_price_startdate = $dateObj->format('Y-m-d H:i:s');
                        }
                        if (!empty($priceRow->product_price_enddate) && $priceRow->product_price_enddate !== $db->getNullDate()) {
                            $dateObj = new Date($priceRow->product_price_enddate, $dbTimeZone);
                            $dateObj->setTimezone($userTimeZone);
                            $priceRow->product_price_enddate = $dateObj->format('Y-m-d H:i:s');
                        }
                    }
                }
            } catch (\Exception $e) {
                Factory::getApplication()->enqueueMessage(Text::sprintf('COM_TIENDA_ERROR_LOADING_PRODUCT_PRICES', $item->product_id) . ': ' . $e->getMessage(), 'error');
                $item->product_prices = []; // Ensure it's an array even on error
            }
        } elseif ($item) {
            $item->product_prices = []; // For new items, initialize as empty array for the subform
        }

        // Load Product Quantities for the subform
        if ($item && isset($item->product_id) && $item->product_id > 0) {
            $db = Factory::getDbo();
            $quantitiesQuery = $db->getQuery(true)
                ->select('*') // Select all fields from productquantities table
                ->from($db->quoteName('#__tienda_productquantities'))
                ->where($db->quoteName('product_id') . ' = ' . (int)$item->product_id)
                ->order($db->quoteName('product_attributes') . ' ASC'); // Order for consistency

            $db->setQuery($quantitiesQuery);
            try {
                $item->product_quantities = $db->loadObjectList();

                if (!empty($item->product_quantities)) {
                    $eavAttrTable = new EavAttributeTable($db);
                    $eavAttrOptionTable = new EavAttributeOptionTable($db);

                    foreach ($item->product_quantities as &$qtyRow) { // Use reference
                        $qtyRow->attributes_display_text = ''; // Initialize as product_attributes_display for form
                        if (!empty($qtyRow->product_attributes)) {
                            $optionIds = explode(',', $qtyRow->product_attributes);
                            $displayTextParts = [];
                            foreach ($optionIds as $optionId) {
                                if ($eavAttrOptionTable->load((int)$optionId)) {
                                    $optionName = $eavAttrOptionTable->eavattributeoption_name;
                                    // Try to get the parent attribute's name for context
                                    if ($eavAttrTable->load((int)$eavAttrOptionTable->eavattribute_id)) {
                                        $displayTextParts[] = Text::_($eavAttrTable->eavattribute_label) . ': ' . Text::_($optionName);
                                    } else {
                                        $displayTextParts[] = Text::_($optionName);
                                    }
                                } else {
                                    $displayTextParts[] = Text::sprintf('COM_TIENDA_UNKNOWN_ATTRIBUTE_OPTION_ID', $optionId);
                                }
                            }
                            $qtyRow->attributes_display_text = implode(' | ', $displayTextParts); // Changed separator
                        }
                    }
                }
            } catch (\Exception $e) {
                Factory::getApplication()->enqueueMessage(Text::sprintf('COM_TIENDA_ERROR_LOADING_PRODUCT_QUANTITIES', $item->product_id) . ': ' . $e->getMessage(), 'error');
                $item->product_quantities = [];
            }
        } elseif ($item) {
            $item->product_quantities = []; // For new items
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

                        // Check if this EAV attribute type is a multi-select type like checkboxes
                        $multiSelectTypes = ['checkboxes', 'checkboxgroup', 'multiselect']; // Add any other type aliases used for this
                        if (in_array(strtolower($eavAttribute->eavattribute_type), $multiSelectTypes)) {
                            if (is_array($submitted_value)) {
                                // For multi-select types, store as JSON or a specific separator if preferred.
                                // JSON is generally safer if option values might contain commas.
                                $valueToStore = json_encode($submitted_value);
                            } elseif (empty($submitted_value)) {
                                $valueToStore = json_encode([]); // Store empty array as JSON for consistency
                            } else {
                                // If it's not an array but should be (e.g., single checkbox submitted not as array),
                                // wrap it in an array before encoding. Or, this might indicate an issue.
                                // For now, assume it's either an array or empty for multi-selects.
                                $valueToStore = json_encode([$submitted_value]);
                                Factory::getApplication()->enqueueMessage(Text::sprintf('COM_TIENDA_EAV_MULTISELECT_UNEXPECTED_SCALAR', $alias), 'warning');
                            }
                        } else {
                            $valueToStore = $submitted_value;
                        }

                        $eavValueTable->eavvalue_value = $valueToStore; // Use $valueToStore now

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

        // Process uploaded gallery images
        $productId = $table->{$pkName}; // Already available as $entity_id
        // $app = Factory::getApplication(); // Already available
        $jform_request = $app->input->files->get('jform', [], 'array');
        $galleryImages = isset($jform_request['gallery_images']) ? $jform_request['gallery_images'] : [];

        if ($productId && !empty($galleryImages) && isset($galleryImages['name']) && is_array($galleryImages['name']) && !empty($galleryImages['name'][0])) {
            $galleryPath = ProductHelper::getGalleryPath($productId); // This should use ProductTable now, via ProductHelper

            // Ensure gallery folder exists (ProductHelper::getGalleryPath should ideally handle this based on ProductTable::getImagePath)
            // However, ProductTable::getImagePath's $check=true creates the final product-specific folder, not necessarily the 'gallery' subfolder.
            // So, we might need to ensure the 'gallery' subfolder itself exists.
            // The ProductHelper::getGalleryPath was modified to use $product->getImagePath(true) where true indicates gallery.
            // And ProductTable::getImagePath($gallery=true, $check=true) will create $baseDir/id/gallery if $check is true.
            // So, $galleryPath should already be created if ProductHelper::getGalleryPath was called with intent to create.
            // For safety, an explicit check/create here for $galleryPath and $galleryPath/thumbs might be redundant if helpers do it.
            // Let's assume ProductHelper::getGalleryPath($productId) already ensures $galleryPath exists.

            if (!Folder::exists($galleryPath)) {
                if (!Folder::create($galleryPath)) {
                    $app->enqueueMessage(Text::sprintf('COM_TIENDA_ERROR_CREATING_GALLERY_FOLDER', $galleryPath), 'error');
                    // Decide if this is a fatal error for the gallery upload portion
                }
            }

            if (Folder::exists($galleryPath)) { // Proceed only if gallery path exists or was created
                $thumbsPath = Path::clean($galleryPath . DIRECTORY_SEPARATOR . 'thumbs');
                if (!Folder::exists($thumbsPath)) {
                    if (!Folder::create($thumbsPath)) {
                         $app->enqueueMessage(Text::sprintf('COM_TIENDA_ERROR_CREATING_GALLERY_THUMBS_FOLDER', $thumbsPath), 'error');
                         // Non-fatal for main image upload, but thumbnails will fail.
                    }
                }

                $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif'];
                $maxSize = 2 * 1024 * 1024; // 2MB

                foreach ($galleryImages['name'] as $key => $name) {
                    if (empty($name) || !isset($galleryImages['error'][$key]) || $galleryImages['error'][$key] !== UPLOAD_ERR_OK) {
                        continue;
                    }

                    $tmp_name = $galleryImages['tmp_name'][$key];
                    $filename = File::makeSafe($name);
                    $extension = strtolower(File::getExt($filename));
                    $filesize = $galleryImages['size'][$key];

                    if (!in_array($extension, $allowedExtensions)) {
                        $app->enqueueMessage(Text::sprintf('COM_TIENDA_UPLOAD_ERROR_INVALID_EXTENSION', $filename), 'warning');
                        continue;
                    }
                    if ($filesize > $maxSize) {
                        $app->enqueueMessage(Text::sprintf('COM_TIENDA_UPLOAD_ERROR_FILE_TOO_LARGE', $filename), 'warning');
                        continue;
                    }

                    $targetPath = Path::clean($galleryPath . DIRECTORY_SEPARATOR . $filename);
                    if (File::upload($tmp_name, $targetPath)) {
                        $app->enqueueMessage(Text::sprintf('COM_TIENDA_FILE_UPLOAD_SUCCESS', $filename), 'message');

                        // Create thumbnail
                        $thumbPath = Path::clean($thumbsPath . DIRECTORY_SEPARATOR . $filename);
                        // Get thumbnail dimensions from config (add these to config.xml later)
                        $params = ComponentHelper::getParams('com_tienda');
                        $thumbWidth = $params->get('gallery_thumb_width', 150); // Example default
                        $thumbHeight = $params->get('gallery_thumb_height', 150); // Example default

                        if (ProductHelper::createThumbnail($targetPath, $thumbPath, $thumbWidth, $thumbHeight)) {
                            // $app->enqueueMessage(Text::sprintf('COM_TIENDA_THUMBNAIL_SUCCESS', $filename), 'message'); // Optional success message
                        } else {
                            // Error message is enqueued by createThumbnail itself
                        }

                        if (empty($table->product_full_image)) {
                            $table->product_full_image = $filename; // Store filename relative to its specific product image folder
                            if (!$table->store()) {
                                $app->enqueueMessage(Text::sprintf('COM_TIENDA_ERROR_UPDATING_MAIN_IMAGE', $table->getError()), 'error');
                            }
                        }
                    } else {
                        $app->enqueueMessage(Text::sprintf('COM_TIENDA_FILE_UPLOAD_ERROR', $filename), 'error');
                    }
                }
            }
        }

        // TODO: Save other related data (prices, quantities per attribute, multiple categories).
        // Gallery images (basic upload) now handled.

        // Save Product Prices from subform
        if (isset($data['product_prices']) && $entity_id > 0) {
            $productPricesData = $data['product_prices'];
            if (!is_array($productPricesData)) { // Should be an array from subform
                $productPricesData = [];
            }

            $db = Factory::getDbo();
            $originalErrors = $this->getErrors();
            // We should not clear _errors here if EAV or Gallery saving might have populated it.
            // Instead, accumulate errors. Let's create a temporary array for price errors.
            $priceSavingErrors = [];

            // 1. Delete existing price records for this product
            try {
                $deleteQuery = $db->getQuery(true)
                    ->delete($db->quoteName('#__tienda_productprices'))
                    ->where($db->quoteName('product_id') . ' = ' . (int)$entity_id);
                $db->setQuery($deleteQuery)->execute();
            } catch (\Exception $e) {
                $this->setError(Text::sprintf('COM_TIENDA_ERROR_DELETING_OLD_PRODUCT_PRICES', $entity_id) . ': ' . $e->getMessage());
                // Restore original errors and return false, as this is critical before adding new ones
                // $this->_errors = array_merge($originalErrors, $this->getErrors()); // Merge current error
                return false;
            }

            // 2. Insert new price records
            foreach ($productPricesData as $priceData) {
                if (empty($priceData)) continue; // Skip if an empty row was submitted by subform

                $priceTable = new ProductPriceTable($db);
                $priceData['product_id'] = $entity_id; // Ensure product_id is set

                // Convert dates from user's local timezone to GMT for DB storage
                // $app is already defined in the save method
                $userOffset = $app->get('offset'); // User's Joomla timezone offset string
                $dbNullDate = $db->getNullDate();

                if (!empty($priceData['product_price_startdate'])) {
                    try {
                        // Date constructor expects UTC if no timezone provided, or use user's TZ if date string implies it
                        // Assuming calendar field provides date string in user's local time.
                        $localDate = new Date($priceData['product_price_startdate'], $userOffset);
                        $priceData['product_price_startdate'] = $localDate->toSql(false); // toSql(false) gives GMT
                    } catch (\Exception $e) {
                        $this->setError(Text::sprintf('COM_TIENDA_ERROR_CONVERTING_START_DATE_TO_GMT', $priceData['product_price_startdate']));
                        $priceData['product_price_startdate'] = $dbNullDate;
                    }
                } else {
                    $priceData['product_price_startdate'] = $dbNullDate;
                }

                if (!empty($priceData['product_price_enddate'])) {
                    try {
                        $localDate = new Date($priceData['product_price_enddate'], $userOffset);
                        $priceData['product_price_enddate'] = $localDate->toSql(false); // toSql(false) gives GMT
                    } catch (\Exception $e) {
                        $this->setError(Text::sprintf('COM_TIENDA_ERROR_CONVERTING_END_DATE_TO_GMT', $priceData['product_price_enddate']));
                        $priceData['product_price_enddate'] = $dbNullDate;
                    }
                } else {
                    $priceData['product_price_enddate'] = $dbNullDate;
                }

                // Unset product_price_id if it's empty to allow auto-increment
                if (isset($priceData['product_price_id']) && empty($priceData['product_price_id'])) {
                    unset($priceData['product_price_id']);
                }

                if (!$priceTable->bind($priceData)) {
                    $this->setError($priceTable->getError());
                    continue;
                }
                if (!$priceTable->check()) { // check() now handles created/modified dates
                    $this->setError($priceTable->getError());
                    continue;
                }
                if (!$priceTable->store()) {
                    $this->setError($priceTable->getError());
                }
            }
            // Merge price errors with any previous errors from main save or EAV/Gallery
            // The current $this->getErrors() will have price errors. We need to merge them back.
            // $this->_errors was not cleared at the start of this price block, so errors are cumulative.
        }

        // Save Product Quantities from subform
        if (isset($data['product_quantities']) && $entity_id > 0) {
            $productQuantitiesData = $data['product_quantities'];
            if (!is_array($productQuantitiesData)) {
                $productQuantitiesData = [];
            }

            // $db = Factory::getDbo(); // Already available
            $originalErrors = $this->getErrors(); // Preserve errors from main save, EAV, gallery, prices
            // Create a temporary array for quantity errors to avoid losing earlier ones if we return false.
            $quantitySavingErrors = [];

            // 1. Delete existing quantity records for this product
            try {
                $deleteQuery = $db->getQuery(true)
                    ->delete($db->quoteName('#__tienda_productquantities'))
                    ->where($db->quoteName('product_id') . ' = ' . (int)$entity_id);
                $db->setQuery($deleteQuery)->execute();
            } catch (\Exception $e) {
                $this->setError(Text::sprintf('COM_TIENDA_ERROR_DELETING_OLD_PRODUCT_QUANTITIES', $entity_id) . ': ' . $e->getMessage());
                // This is a critical error, merge and return.
                // $this->_errors = array_merge($originalErrors, $this->getErrors()); No, just return false after setting error.
                return false;
            }

            // 2. Insert new quantity records
            foreach ($productQuantitiesData as $qtyData) {
                if (empty($qtyData) || !isset($qtyData['quantity'])) { // Ensure quantity is set to avoid saving empty/incomplete rows
                    continue;
                }

                $qtyTable = new ProductQuantityTable($db);
                $qtyData['product_id'] = $entity_id;

                // product_attributes should be submitted as a pre-sorted CSV string from the hidden field.
                // ProductQuantityTable::check() handles sorting if it's an array, or keeps string as is.
                // If it's from our hidden field, it should already be sorted CSV.

                if (isset($qtyData['productquantity_id']) && empty($qtyData['productquantity_id'])) {
                    unset($qtyData['productquantity_id']);
                }

                if (!$qtyTable->bind($qtyData)) {
                    $this->setError($qtyTable->getError()); // Use $this->setError to accumulate
                    continue;
                }
                if (!$qtyTable->check()) {
                    $this->setError($qtyTable->getError());
                    continue;
                }
                if (!$qtyTable->store()) {
                    $this->setError($qtyTable->getError());
                }
            }
            // $this->_errors now contains any errors from this quantity saving batch plus any previous ones.
            // No need to explicitly merge $originalErrors back if we didn't clear $this->_errors for this batch.
            // The provided code sample for price saving did:
            // $originalErrors = $this->getErrors(); $this->_errors = []; (for batch) then $this->_errors = array_merge($originalErrors, $priceErrors);
            // Let's follow that pattern for consistency.
            // $currentBatchErrors = $this->_errors; // Assuming _errors was cleared for this batch (it wasn't in my adaptation)
            // $this->_errors = array_merge($originalErrors, $currentBatchErrors);
            // Actually, the current structure where $this->setError() just adds to $this->_errors is fine.
            // The critical part is returning false on the delete failure.
        }

        // Save category relationships
        if (isset($data['category_ids'])) {
            $category_ids = (array) $data['category_ids']; // Ensure it's an array
            ArrayHelper::toInteger($category_ids); // Sanitize

            // $entity_id is already set from $table->{$pkName} earlier in this method.
            $xrefTable = new ProductCategoryXrefTable(Factory::getDbo());

            // 1. Delete existing xrefs for this product
            try {
                $deleteQuery = Factory::getDbo()->getQuery(true)
                    ->delete($xrefTable->getTableName())
                    ->where(Factory::getDbo()->quoteName('product_id') . ' = ' . (int)$entity_id);
                Factory::getDbo()->setQuery($deleteQuery)->execute();
            } catch (\Exception $e) {
                $this->setError(Text::sprintf('COM_TIENDA_ERROR_DELETING_OLD_CATEGORY_XREFS', $entity_id) . ': ' . $e->getMessage());
                // Decide if this should make the whole save fail. For now, it adds to _errors.
            }

            // 2. Insert new xrefs
            if (empty($this->getErrors())) { // Proceed only if deletion didn't cause critical errors (optional check)
                foreach ($category_ids as $category_id) {
                    if ($category_id > 0) {
                        $xrefData = ['product_id' => $entity_id, 'category_id' => $category_id];
                        try {
                            // Reset table before each insert attempt with new composite key
                            $xrefTable->reset();
                            if (!$xrefTable->bind($xrefData) || !$xrefTable->store()) {
                                // JTable::store will attempt INSERT. If PK violation, it might fail silently or throw error depending on DB.
                                // A more robust way for composite keys without AI is direct insert and catch exception,
                                // or a specific saveXref method in the table that handles it.
                                // Given JTable's store with composite keys can be tricky for insert, let's use direct insert:
                                // Factory::getDbo()->insertObject($xrefTable->getTableName(), (object)$xrefData);
                                // However, to use Table class benefits (like events if any), let's try bind/store.
                                // JTable's store() uses INSERT IGNORE or REPLACE INTO based on table properties or db driver,
                                // or simple INSERT. For composite keys, it might try to update if load() by keys succeeds.
                                // Since we deleted all, it should always be an INSERT.
                                $this->setError(Text::sprintf('COM_TIENDA_ERROR_SAVING_CATEGORY_XREF_BIND_STORE', $category_id, $entity_id) . ': ' . $xrefTable->getError());
                            }
                        } catch (\Exception $e) { // Catch potential DB exceptions if store doesn't and direct insert used.
                            $this->setError(Text::sprintf('COM_TIENDA_ERROR_SAVING_CATEGORY_XREF', $category_id, $entity_id) . ': ' . $e->getMessage());
                        }
                    }
                }
            }
        }
        // Factory::getApplication()->enqueueMessage('ProductModel::save() Related data saving (prices, attributes, etc.) is a TODO for product ' . \$table->{\$pkName}, 'notice');

        return empty(\$this->_errors); // Return true if no errors (including EAV and gallery ones)
    }
}
