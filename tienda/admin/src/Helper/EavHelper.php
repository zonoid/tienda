<?php
/**
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
use Joomla\CMS\Table\Table;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\Event\Dispatcher; // For J5, this is DispatcherInterface, but actual usage might be EventDispatcher
// For JPATH_ADMINISTRATOR, it's a constant, no direct use statement needed.
// JLoader is replaced by PSR-4 or direct includes/use statements for specific classes.

use Dioscouri\Component\Tienda\Administrator\Table\EavAttributeTable;
use Dioscouri\Component\Tienda\Administrator\Table\EavValueTable;
// Assuming TiendaModelEavAttributes will be refactored
// use Dioscouri\Component\Tienda\Administrator\Model\EavAttributeModel; (Example)


class EavHelper
{
    /**
     * Gets an Attribute type based on its alias
     *
     * @param string $alias
     * @return string|null The attribute type (varchar, text, int, etc.) or null if not found
     */
    public static function getType($alias): ?string
    {
        static $sets = [];
        if (isset($sets[$alias])) {
            return $sets[$alias];
        }

        // TODO: Replace with direct use of the refactored EavAttributeTable
        // Table::addIncludePath(JPATH_ADMINISTRATOR . '/components/com_tienda/tables'); // No longer needed
        // $table = Table::getInstance('EavAttributes', 'TiendaTable'); // Old way
        try {
            $table = new EavAttributeTable(Factory::getDbo());
            if ($table->load(['eavattribute_alias' => $alias])) {
                $type = '';
                switch ($table->eavattribute_type) {
                    case 'bool':
                    case 'hidden':
                        $type = 'varchar'; // As per original logic
                        break;
                    default:
                        $type = $table->eavattribute_type;
                        break;
                }
                $sets[$alias] = $type;
                return $type;
            }
        } catch (\Exception $e) {
            Factory::getApplication()->enqueueMessage('EavHelper::getType - Error loading EavAttributeTable: ' . $e->getMessage(), 'error');
        }

        $sets[$alias] = null; // Cache not found
        return null;
    }

    /**
     * Get the Eav Attributes for a particular entity
     * @param string $entity Entity type (e.g., 'products', 'users')
     * @param int $id Entity ID
     * @param boolean $only_enabled Filter by enabled attributes
     * @param string|array $editable_by Filter by editable_by status ('-1' for all, or specific group/user ID or array)
     * @return array Array of attribute objects
     */
    public static function getAttributes($entity, $id, $only_enabled = false, $editable_by = ''): array
    {
        static $sets = [];

        $editable_by_key = is_array($editable_by) ? implode(',', $editable_by) : (string)$editable_by;
        if (empty($editable_by_key)) $editable_by_key = '-1';

        if (isset($sets[$entity][$id][$editable_by_key])) {
            return $sets[$entity][$id][$editable_by_key];
        }

        // For now, query the EavAttributeTable directly.
        $attributes = [];
        try {
            $table = new EavAttributeTable(Factory::getDbo());
            $query = Factory::getDbo()->getQuery(true)
                ->select('*')
                ->from($table->getTableName())
                ->where($table->quoteName('eaventity_type') . ' = ' . $table->getDbo()->quote($entity));

            if ($only_enabled) {
                $query->where($table->quoteName('eavattribute_enabled') . ' = 1');
            }
            // TODO: The 'editable_by' logic was complex and based on numeric/array values.
            // For now, omit 'editable_by' filtering from this direct query. This needs to be handled by a proper model or more robust ACL check.
            // Factory::getApplication()->enqueueMessage('EavHelper::getAttributes() editable_by filter is not fully implemented yet.', 'notice');
            $query->order($table->quoteName('ordering') . ' ASC');
            $attributes = $table->getDbo()->setQuery($query)->loadObjectList();

        } catch (\Exception $e) {
            Factory::getApplication()->enqueueMessage('EavHelper::getAttributes - Error: ' . $e->getMessage(), 'error');
        }

        $sets[$entity][$id][$editable_by_key] = $attributes;

        // Let the plugins change the list of custom fields
        // TODO: Review event dispatching for J5. Use injected dispatcher if possible.
        // $dispatcher = JDispatcher::getInstance(); // Old J3 way
        // $dispatcher = Factory::getApplication()->getDispatcher(); // J4/J5 EventDispatcher
        // $dispatcher->trigger('onAfterGetCustomFields', array( &$sets[$entity][$id][$editable_by_key], $entity, $id ) );

        return $sets[$entity][$id][$editable_by_key];
    }

    /**
     * Get the value of an attribute
     * @param object $eav EavAttribute object (should have eavattribute_id, eavattribute_type, eavattribute_alias)
     * @param string $entity_type
     * @param int $entity_id
     * @param bool $no_post Only value from DB will be used
     * @param bool $cache_values If the values should be cached
     * @return mixed Attribute value or null
     */
    public static function getAttributeValue($eav, $entity_type, $entity_id, $no_post = false, $cache_values = true)
    {
        static $sets = []; // Cache: $sets[$type][$attr_id][$entity_type][$entity_id]

        if (!is_object($eav) || !isset($eav->eavattribute_id) || !isset($eav->eavattribute_type) || !isset($eav->eavattribute_alias)) {
            return null;
        }

        if ($cache_values && isset($sets[$eav->eavattribute_type][$eav->eavattribute_id][$entity_type][$entity_id])) {
            return $sets[$eav->eavattribute_type][$eav->eavattribute_id][$entity_type][$entity_id];
        }

        $value = null;
        // TODO: Replace with direct use of the refactored EavValueTable
        // Table::addIncludePath(JPATH_ADMINISTRATOR . '/components/com_tienda/tables'); // No longer needed
        // $table = Table::getInstance('EavValues', 'TiendaTable'); // Old way
        try {
            $table = new EavValueTable(Factory::getDbo());
            $table->setType($eav->eavattribute_type); // This correctly initializes the table name in EavValueTable

            $keynames = [
                'eavattribute_id' => $eav->eavattribute_id,
                'eaventity_id'    => $entity_id,
                'eaventity_type'  => $entity_type,
            ];

            if ($table->load($keynames)) {
                $value = $table->eavvalue_value;
            } elseif (!$no_post) {
                $appInput = Factory::getApplication()->input;
                if ($eav->eavattribute_type == 'text') {
                    $value = $appInput->get($eav->eavattribute_alias, null, 'raw'); // Using 'raw' for editor content
                } else {
                    $value = $appInput->get($eav->eavattribute_alias, null, 'string'); // Default to string, adjust filter as needed
                }
            }
        } catch (\Exception $e) {
             Factory::getApplication()->enqueueMessage('EavHelper::getAttributeValue - Error loading EavValueTable: ' . $e->getMessage(), 'error');
        }

        if ($value === null && $eav->eavattribute_type === 'datetime') {
            $value = Factory::getDbo()->getNullDate();
        }

        if ($cache_values) {
            $sets[$eav->eavattribute_type][$eav->eavattribute_id][$entity_type][$entity_id] = $value;
        }

        return $value;
    }

    /**
     * Show the correct edit field based on the eav type
     * @param object $eav EavAttribute object
     * @param mixed $value Current value
     * @return string HTML for the form field
     */
    public static function editForm($eav, $value = null): string
    {
        // TODO: This method generates form HTML directly. Consider moving to layouts or custom form fields for J5.
        Factory::getApplication()->enqueueMessage('EavHelper::editForm generates direct HTML and should be refactored to use layouts/custom form fields.', 'notice');
        $html = '';
        $fieldName = $eav->eavattribute_alias;
        $fieldId = 'eavfield_' . $eav->eavattribute_alias; // Ensure unique ID

        switch ($eav->eavattribute_type) {
            case "bool":
                // Use Joomla\CMS\HTML\HTMLHelper::_('select.booleanlist', ...)
                $options = [
                    HTMLHelper::_('select.option', '0', Text::_('JNO')),
                    HTMLHelper::_('select.option', '1', Text::_('JYES')),
                ];
                $html = HTMLHelper::_('select.radiolist', $options, $fieldName, ['class' => 'inputbox cf_'.$fieldName], 'value', 'text', (int)$value, $fieldId);
                break;
            case "datetime":
                // Use Joomla\CMS\HTML\HTMLHelper::_('calendar', ...)
                $format = !empty($eav->eavattribute_format_strftime) ? $eav->eavattribute_format_strftime : '%Y-%m-%d %H:%M:%S';
                $html = HTMLHelper::_('calendar', $value, $fieldName, $fieldId, $format, ['class' => 'input-medium cf_'.$fieldName]);
                break;
            case "text":
                // Use Joomla\CMS\Editor\Editor::getInstance()->display(...)
                $editor = Factory::getApplication()->getEditor();
                $html = $editor->display($fieldName, $value, '300', '200', '50', '20', true, $fieldId); // 7th param true for buttons
                break;
            case "hidden":
                $html = '<input type="hidden" name="'.$fieldName.'" id="'.$fieldId.'" value="'.htmlspecialchars($value, ENT_QUOTES, 'UTF-8').'"/>';
                break;
            case "decimal":
            case "int":
                $html = '<input type="number" name="'.$fieldName.'" id="'.$fieldId.'" value="'.htmlspecialchars($value, ENT_QUOTES, 'UTF-8').'" class="input-mini cf_'.$fieldName.'"/>';
                if ($eav->eavattribute_type == "decimal") $html = str_replace( 'type="number"', 'type="number" step="any"', $html );
                break;
            case "varchar":
            default:
                $html = '<input type="text" name="'.$fieldName.'" id="'.$fieldId.'" value="'.htmlspecialchars($value, ENT_QUOTES, 'UTF-8').'" class="input-medium cf_'.$fieldName.'"/>';
                break;
        }
        return $html;
    }

    /**
     * Show the field value based on the eav type
     * @param object $eav EavAttribute object
     * @param mixed $value Current value
     * @return string Formatted value
     */
    public static function showValue($eav, $value = null): string
    {
        // TODO: This method generates display HTML/formatted values. Consider moving formatting to specific helpers or layouts.
        $app = Factory::getApplication();
        $params = ComponentHelper::getParams('com_tienda');

        switch($eav->eavattribute_type) {
            case "bool":
                return $value ? Text::_('JYES') : Text::_('JNO');
            case "datetime":
                $format = !empty($eav->eavattribute_format_date) ? $eav->eavattribute_format_date : Text::_('DATE_FORMAT_LC3'); // Joomla default
                return $value ? HTMLHelper::_('date', $value, $format) : '';
            case "text":
                // TODO: Review content plugin triggering. This can be complex.
                // For now, just return the value, or basic HTML cleaning.
                // $dispatcher = Factory::getApplication()->getDispatcher();
                // $item = new \stdClass();
                // $item->text = &$value;
                // $eventParams = new Registry(); // Empty params for content plugins
                // if ($params->get('eavtext_content_plugin', 1)) {
                //     PluginHelper::importPlugin('content');
                //     $dispatcher->trigger('onContentPrepare', ['com_tienda.eav', &$item, &$eventParams, 0]);
                // }
                return $value; // Potentially $item->text if plugins modified it.
            case "decimal":
                $num_decimals = $params->get('num_decimals', 2); // Default from main config
                return BaseHelper::number($value, ['num_decimals' => $num_decimals, 'thousands' => ($params->get('eavinteger_use_thousand_separator', 0) ? $params->get('thousands_separator', ',') : '')]);
            case "int":
                return BaseHelper::number($value, ['num_decimals' => 0, 'thousands' => ($params->get('eavinteger_use_thousand_separator', 0) ? $params->get('thousands_separator', ',') : '')]);
            case "hidden":
            case "varchar":
            default:
                return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
        }
        return '';
    }

    /**
     * Show the edit form or the field value based on the eav status
     * @param object $eav EavAttribute object
     * @param mixed $value Current value
     * @return string HTML for field or formatted value
     */
    public static function showField($eav, $value = null): string
    {
        // TODO: DSCAcl::isAdmin() needs replacement with Joomla's ACL check, e.g. $user->authorise('core.manage', 'com_tienda');
        $user = Factory::getApplication()->getIdentity();
        $isAdmin = $user->authorise('core.manage', 'com_tienda'); // Example, adjust permission as needed

        switch($eav->editable_by) {
            case "0": // No one
                return self::showValue($eav, $value);
            case "1": // Admin
                return $isAdmin ? self::editForm($eav, $value) : self::showValue($eav, $value);
            case "2": // All (assuming this means any logged-in user who can edit the item)
            default:
                // This needs to be context-aware of who the current user is and if they have edit rights for the item.
                // For simplicity, if it's editable by 'all', we'll show the form.
                // In a real scenario, this would be tied to Joomla's ACL for the item.
                return self::editForm($eav, $value);
        }
    }

    /**
     * This method removes all eav values from an entity with a specified ID
     *
     * @param string $entity_type Type of the entity
     * @param int $entity_id Entity ID
     * @param string|null $entity_type_mirror Mirrored entity type (if any)
     * @param int|null $entity_id_mirror Mirrored entity ID (if any)
     * @return bool True on success (or if no values to delete), false on error
     */
    public static function deleteEavValuesFromEntity($entity_type, $entity_id, $entity_type_mirror = null, $entity_id_mirror = null): bool
    {
        if (!$entity_type_mirror) $entity_type_mirror = $entity_type;
        if (!$entity_id_mirror) $entity_id_mirror = $entity_id;

        // TODO: This method needs to use refactored EavValueTable and EavAttributeModel/Helper
        // Factory::getApplication()->enqueueMessage('EavHelper::deleteEavValuesFromEntity needs refactoring for Table/Model usage.', 'warning');

        $eavs = self::getAttributes($entity_type, $entity_id, false, '-1'); // Get all attributes for the entity type
        if (empty($eavs)) return true; // Nothing to delete based on attribute definitions

        $success = true;
        try {
            $table_eav_values = new EavValueTable(Factory::getDbo());
            foreach ($eavs as $eav) {
                $table_eav_values->setType($eav->eavattribute_type); // Set type for correct table name suffix
                // Try to load and delete if exists, though direct delete query might be more efficient for batch
                // For now, stick to table object for consistency if individual error tracking is desired.
                // A more performant way for bulk delete would be to issue DELETE queries per value type table.
                 $conditions = [
                     'eaventity_type'  => $entity_type_mirror, // Use mirror type for deletion target
                     'eaventity_id'    => $entity_id_mirror,   // Use mirror ID for deletion target
                     'eavattribute_id' => $eav->eavattribute_id
                 ];
                 // Load to get the specific eavvalue_id for deletion, or to check existence
                 // This is inefficient for mass delete. A direct query is better.
                 // However, to use $table->delete(), it usually expects a PK.
                 // Let's assume we want to delete all values for this entity_id and attribute_id for the given type.
                 // A direct query is more appropriate if $table->delete() expects a single PK.

                $db = Factory::getDbo();
                $query = $db->getQuery(true)
                    ->delete($db->quoteName($table_eav_values->getTableName())) // Get the dynamic table name
                    ->where($db->quoteName('eaventity_type') . ' = ' . $db->quote($entity_type_mirror))
                    ->where($db->quoteName('eaventity_id') . ' = ' . (int)$entity_id_mirror)
                    ->where($db->quoteName('eavattribute_id') . ' = ' . (int)$eav->eavattribute_id);
                $db->setQuery($query)->execute();
            }
        } catch (\Exception $e) {
            Factory::getApplication()->enqueueMessage('Error in deleteEavValuesFromEntity: ' . $e->getMessage(), 'error');
            $success = false;
        }
        return $success;
    }
}
