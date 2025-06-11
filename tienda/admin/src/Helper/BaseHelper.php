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
use Joomla\CMS\WebAsset\WebAssetManager;
use Joomla\CMS\Date\Date; // For date conversions
use Joomla\CMS\Uri\Uri;   // For URL generation

class BaseHelper
{
    static $added_strings = [];

    /**
     * Formats and converts a number according to currency rules
     *
     * @param float $amount
     * @param string|null $currency_code
     * @param array $options
     * @return string
     */
    public static function currency($amount, $currency_code = null, $options = [])
    {
        return CurrencyHelper::format($amount, $currency_code, $options);
    }

    /**
     * Nicely format a number
     *
     * @param float $number
     * @param array $options
     * @return string
     */
    public static function number($number, $options = [])
    {
        $params = ComponentHelper::getParams('com_tienda');

        $cfg_thousands = $params->get('thousands_separator', ',');
        $cfg_decimal = $params->get('decimal_separator', '.');
        $cfg_num_decimals = $params->get('num_decimals', 2);

        $thousands = isset($options['thousands']) ? $options['thousands'] : $cfg_thousands;
        $decimal = isset($options['decimal']) ? $options['decimal'] : $cfg_decimal;
        $num_decimals = isset($options['num_decimals']) ? $options['num_decimals'] : $cfg_num_decimals;

        return number_format((float)$number, (int)$num_decimals, $decimal, $thousands);
    }

    /**
     * Method to add translation strings to JS translation object
     *
     * @param array $strings List of strings to translate (keys for JText)
     */
    public static function addJsTranslationStrings(array $strings)
    {
        if (self::$added_strings === null) { // Should be initialized as array as per class property
            self::$added_strings = [];
        }

        $wa = Factory::getApplication()->getDocument()->getWebAssetManager();
        // Ensure com_tienda.language or a global language asset is defined in joomla.asset.json
        // and that it handles Joomla.JText.load setup.
        // If not, direct script declaration might be needed for the initial setup of Joomla.JText.
        // $wa->useScript('com_tienda.language');
        // For now, we assume a global Joomla.JText is available.

        $js_strings = [];
        foreach ($strings as $string_key) {
            if (!in_array(strtoupper($string_key), self::$added_strings)) {
                // Ensure the key passed is what JText expects (e.g., "COM_TIENDA_MY_STRING")
                $js_strings[] = '"' . strtoupper($string_key) . '":"' . Text::_($string_key) . '"';
                self::$added_strings[] = strtoupper($string_key);
            }
        }

        if (count($js_strings)) {
            $script = 'Joomla.JText.load({' . implode(',', $js_strings) . '});';
            $wa->addInlineScript($script, ['name' => 'tienda-lang-'.md5($script)]); // Add a unique name for the inline script
        }
    }

    /**
     * convert Local data to GMT data
     * @param string $local_data Date string in local timezone
     * @return string Date string in GMT
     */
    public static function local_to_GMT_data($local_data)
    {
        if (empty($local_data) || $local_data === Factory::getDbo()->getNullDate()) {
            return $local_data;
        }
        $date = new Date($local_data, Factory::getApplication()->get('offset')); // Assume local timezone
        return $date->toSql(false); // toSql(false) gives GMT
    }

    /**
     * convert GMT data to Local data
     * @param string $GMT_data Date string in GMT
     * @return string Date string in local timezone
     */
    public static function GMT_to_local_data($GMT_data)
    {
        if (empty($GMT_data) || $GMT_data === Factory::getDbo()->getNullDate()) {
            return $GMT_data;
        }
        $date = new Date($GMT_data, 'UTC'); // Assume GMT/UTC
        $date->setTimezone(new \DateTimeZone(Factory::getApplication()->get('offset')));
        return $date->toSql(true); // toSql(true) gives local time based on offset
    }

    /**
     * Generates a validation message (HTML)
     *
     * @param string $text
     * @param string $type 'success' or 'fail'
     * @return string HTML message
     */
    public static function validationMessage($text, $type = 'fail')
    {
        // TODO: This method outputs direct HTML. Consider returning data for view layer or using system messages.
        // For now, porting as closely as possible.
        // Uri::root() might be needed if Tienda::getUrl('images') was pointing to a specific media path.
        // Assuming a placeholder path for now.
        $imgPath = Uri::root(true) . '/media/com_tienda/images/'; // Adjust if icons are elsewhere

        switch (strtolower($type)) {
            case "success":
                $src = $imgPath . 'accept_16.png'; // Ensure these images exist or use FontAwesome/Joomla icons
                $html = "<div class='tienda_validation alert alert-success'><span class='icon-ok'></span> <span class='validation-success'>" . Text::_($text) . "</span></div>";
                break;
            default:
                $src = $imgPath . 'remove_16.png';
                $html = "<div class='tienda_validation alert alert-danger'><span class='icon-remove'></span> <span class='validation-fail'>" . Text::_($text) . "</span></div>";
                break;
        }
        return $html;
    }

    /**
     * Generates a new secret key for Tienda
     *
     * @param int $length Length of the word
     * @return string Secret key as a string
     */
    public static function generateSecretWord($length = 32)
    {
        $salt = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*()_+}{|:<>?,. ";
        $len = strlen($salt);
        $sw = '';
        mt_srand((float)microtime() * 1000000); // Seed for mt_rand
        for ($i = 0; $i < $length; $i++) {
            $sw .= $salt[mt_rand(0, $len - 1)];
        }
        return $sw;
    }

    /**
     * Method which gets a correct time of beginning of a day with respect to the current time zone
     *
     * @param Date|string $date Joomla Date object or date string
     * @return string Correct Datetime (YYYY-MM-DD 00:00:00) in GMT
     */
    public static function getCorrectBeginDayTime($date)
    {
        if (!$date instanceof Date) {
            $date = new Date($date); // If string, assume it's in server's local (from config offset)
        }
        // Set time to 00:00:00 in its current timezone
        $date->setTime(0, 0, 0);
        return $date->toSql(false); // Convert to GMT for storage/comparison
    }

    // Methods to remove or comment out:
    // - getInstance(): Replaced by static calls.
    // - addIncludePath(): PSR-4 autoloading replaces this.
    // - checkDirectory(): Should be handled by Joomla\CMS\Filesystem\Folder or similar.
    // - canView(): Permissions should use Joomla's ACL.
    // - measure(): Specific to dimensions/weights, might be better in a product helper.
    // - getColumn(): Joomla\Utilities\ArrayHelper::getColumn().
    // - elementsToArray(): Specific to old form handling.
    // - setDateVariables(): Custom date logic, review if needed or use Date class.
    // - getToday(): Use new Date('now', Factory::getApplication()->get('offset'))->toSql(false) for GMT start of day.
    // - getOffsetDate(): Use Date object manipulations.
    // - getPeriodData(): Complex reporting logic, needs significant refactor if kept.
    // - includeJQueryUI, includeJQuery, includeMultiFile: Use Web Asset Manager.
    // - generateMessage(): Use $app->enqueueMessage().
    // - setSessionVariable, getSessionVariable: Use $app->getSession()->set/get().
    // - setFormat(): Use $app->getDocument()->setType().
}
