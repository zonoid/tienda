<?php
/**
 * @version 0.1
 * @package Tienda
 * @author Dioscouri Design
 * @link http://www.dioscouri.com
 * @copyright Copyright (C) 2007 Dioscouri Design. All rights reserved.
 * @license http://www.gnu.org/copyleft/gpl.html GNU/GPL, see LICENSE.php
 */

namespace Dioscouri\Component\Tienda\Administrator\Table;

defined('_JEXEC') or die('Restricted access');

use Joomla\CMS\Factory\Factory;
use Joomla\CMS\Date\Date;
use Joomla\CMS\Table\Table;
use Joomla\Database\DatabaseDriver;

class CurrencyTable extends Table
{
    public $currency_id = null;
    public $currency_name = null;
    public $currency_code = null; // e.g., USD, EUR
    public $symbol_left = null;   // Symbol displayed to the left of the amount
    public $symbol_right = null;  // Symbol displayed to the right of the amount
    public $decimal_separator = '.';
    public $thousands_separator = ',';
    public $currency_decimals = 2;  // Number of decimal places
    public $exchange_rate = null;   // Exchange rate against a base currency (e.g., USD)
    public $updated_date = null;  // Last time exchange_rate was updated
    public $created_date = null;
    public $modified_date = null;
    public $enabled = null;       // Is the currency enabled for use?

    /**
     * Constructor
     *
     * @param   DatabaseDriver  &$db  A database connector object
     */
    public function __construct(DatabaseDriver $db)
    {
        parent::__construct('#__tienda_currencies', 'currency_id', $db);
    }

    /**
     * Overloaded check function to ensure data integrity.
     *
     * @return  boolean  True if the instance is sane and able to be stored according to the business rules.
     */
    public function check()
    {
        $db = $this->getDbo();
        $nullDate = $db->getNullDate();

        if (empty($this->created_date) || $this->created_date == $nullDate)
        {
            // Use Factory::getDate() with current timezone for created_date if new
            // Assuming 'now' with site's offset for creation time
            $this->created_date = Factory::getDate('now', Factory::getApplication()->get('offset'))->toSql();
        }

        // Always update modified_date to GMT
        $this->modified_date = Factory::getDate('now')->toSql(); // toSql() defaults to GMT

        // Ensure currency_code is uppercase
        if (!empty($this->currency_code)) {
            $this->currency_code = strtoupper($this->currency_code);
        }

        // Basic validation for required fields
        if (trim($this->currency_name ?? '') === '') {
            $this->setError(\Joomla\CMS\Language\Text::_('COM_TIENDA_ERROR_CURRENCY_NAME_REQUIRED'));
            return false;
        }
        if (trim($this->currency_code ?? '') === '') {
            $this->setError(\Joomla\CMS\Language\Text::_('COM_TIENDA_ERROR_CURRENCY_CODE_REQUIRED'));
            return false;
        }
        if (strlen($this->currency_code ?? '') !== 3) {
             $this->setError(\Joomla\CMS\Language\Text::_('COM_TIENDA_ERROR_CURRENCY_CODE_MUST_BE_3_CHARS'));
            return false;
        }


        return true;
    }
}
