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
use Joomla\CMS\Table\Table; // Keep for Table::addIncludePath if still needed, though direct instantiation of CurrencyTable is preferred
use Joomla\CMS\Date\Date;
use Dioscouri\Component\Tienda\Administrator\Table\CurrencyTable;

class CurrencyHelper
{
    private static $currenciesById = [];
    private static $currenciesByCode = [];
    private static $exchangeRates = []; // Cache for [from_to] => rate

    /**
     * Gets the current default currency ID from component parameters.
     *
     * @return int
     */
    public static function getCurrentCurrencyId(): int
    {
        $params = ComponentHelper::getParams('com_tienda');
        return (int) $params->get('default_currencyid', 1); // Assuming 1 is a fallback default
    }

    /**
     * Loads currency data by ID or code, using cache if available.
     *
     * @param mixed $currencyInput Currency ID (int) or Currency Code (string)
     * @return object|null Currency object or null if not found
     */
    private static function loadCurrency($currencyInput)
    {
        $isNumeric = is_numeric($currencyInput);
        $cacheKey = $isNumeric ? (int) $currencyInput : strtoupper((string) $currencyInput);

        if ($isNumeric && isset(self::$currenciesById[$cacheKey])) {
            return self::$currenciesById[$cacheKey];
        }
        if (!$isNumeric && isset(self::$currenciesByCode[$cacheKey])) {
            return self::$currenciesByCode[$cacheKey];
        }

        try {
            $table = new CurrencyTable(Factory::getDbo());
            $success = false;

            if ($isNumeric) {
                $success = $table->load((int)$cacheKey);
            } else {
                $success = $table->load(['currency_code' => (string)$cacheKey]);
            }

            if ($success && $table->currency_id) {
                self::$currenciesById[$table->currency_id] = $table;
                self::$currenciesByCode[strtoupper($table->currency_code)] = $table; // Ensure key is uppercase
                return $table;
            }
        } catch (\Exception $e) {
            Factory::getApplication()->enqueueMessage('Error loading currency: ' . $e->getMessage(), 'error');
            // Fallthrough to return null below
        }

        // Factory::getApplication()->enqueueMessage(Text::sprintf('COM_TIENDA_CURRENCY_NOT_FOUND', $currencyInput), 'warning'); // Removed as per instruction to return null/false
        return null; // Or false, depending on how failures should be handled by callers
    }

    /**
     * Fetches live exchange rate from Yahoo (Placeholder - needs reliable alternative).
     *
     * @param string $currencyFrom
     * @param string $currencyTo
     * @return float|null
     */
    private static function fetchLiveExchangeRateYahoo($currencyFrom, $currencyTo)
    {
        // TODO: Replace Yahoo API with a reliable alternative or make manual override primary.
        // This is a placeholder and likely will not work as Yahoo Finance API has changed.
        Factory::getApplication()->enqueueMessage('fetchLiveExchangeRateYahoo is a placeholder and needs a new reliable API or removal.', 'warning');

        // Example of old logic structure (would need modern HTTP client)
        // $url = "http://download.finance.yahoo.com/d/quotes.csv?s={$currencyFrom}{$currencyTo}=X&f=sl1d1t1ba&e=.csv";
        // Use Joomla\CMS\Http\HttpFactory::getHttp();
        // $http = \Joomla\CMS\Http\HttpFactory::getHttp();
        // try {
        //     $response = $http->get($url);
        //     if ($response->code == 200) {
        //         $data = explode(',', $response->body);
        //         if (isset($data[1]) && is_numeric($data[1]) && $data[1] > 0) {
        //             return (float) $data[1];
        //         }
        //     }
        // } catch (\Exception $e) {
        //     // Log error
        // }
        return null; // Indicate failure or no rate found
    }

    /**
     * Gets the exchange rate between two currencies.
     *
     * @param string $fromCurrencyCode
     * @param string $toCurrencyCode
     * @param bool $refresh
     * @return float
     */
    private static function getExchangeRate($fromCurrencyCode, $toCurrencyCode, $refresh = false): float
    {
        $fromCurrencyCode = strtoupper($fromCurrencyCode);
        $toCurrencyCode = strtoupper($toCurrencyCode);

        if ($fromCurrencyCode === $toCurrencyCode) {
            return 1.0;
        }

        $cacheKey = $fromCurrencyCode . '_' . $toCurrencyCode;
        if (!$refresh && isset(self::$exchangeRates[$cacheKey])) {
            return self::$exchangeRates[$cacheKey];
        }

        $fromCurrency = self::loadCurrency($fromCurrencyCode);
        $toCurrency = self::loadCurrency($toCurrencyCode);

        if (!$fromCurrency || !$toCurrency) {
            Factory::getApplication()->enqueueMessage(Text::_('COM_TIENDA_CURRENCY_CONVERSION_ERROR_MISSING_CURRENCY_DATA'), 'error');
            return 1.0; // Fallback to 1.0 if currency data is missing
        }

        $params = ComponentHelper::getParams('com_tienda');
        $autoUpdate = $params->get('currency_exchange_autoupdate', 0);
        $baseCurrencyForRates = 'USD'; // Assuming all stored exchange_rate values are against USD.

        // TODO: Implement logic for checking 'updated_date' and triggering live fetch if autoUpdate is on.
        // This requires the currency table to have an 'updated_date' and 'exchange_rate' (vs USD) field.
        // For now, we will simulate based on stored rates if auto-update is off or fails.

        if ($autoUpdate) {
            Factory::getApplication()->enqueueMessage('Live exchange rate fetching part of getExchangeRate needs review and a working API.', 'notice');
            // $liveRate = self::fetchLiveExchangeRateYahoo($fromCurrencyCode, $toCurrencyCode);
            // if ($liveRate !== null) {
            //     // TODO: Update the database table for $fromCurrencyCode or $toCurrencyCode with the new rate vs USD and new updated_date
            //     // This is complex as it depends on which currency is the base for the fetched rate.
            //     // For example, if fetched $from/$to, and our base is USD, we might need $from/USD and $to/USD to update.
            //     // After updating DB, reload currency objects or update them directly.
            //     // $fromCurrency = self::loadCurrency($fromCurrencyCode, true); // Force reload
            //     // $toCurrency = self::loadCurrency($toCurrencyCode, true); // Force reload
            //     self::$exchangeRates[$cacheKey] = $liveRate; // This might be direct $from/$to or needs calculation via USD
            //     return $liveRate;
            // }
        }

        // Fallback to calculating from stored USD-based rates
        $rate = 1.0;
        if (isset($fromCurrency->exchange_rate) && isset($toCurrency->exchange_rate)) {
             if ($fromCurrency->exchange_rate == 0) { // Avoid division by zero
                Factory::getApplication()->enqueueMessage(Text::sprintf('COM_TIENDA_CURRENCY_CONVERSION_ERROR_ZERO_RATE', $fromCurrencyCode), 'error');
                return 1.0;
            }
            if ($toCurrency->exchange_rate == 0 && $fromCurrencyCode !== $baseCurrencyForRates) {
                 // If toCurrency rate is 0 and fromCurrency is not the base, this would also lead to issues.
                 // However, the formula below handles toCurrency being base (USD) correctly.
            }


            if ($fromCurrencyCode === $baseCurrencyForRates) { // From USD to Other
                $rate = (float) $toCurrency->exchange_rate; // This should be Other per USD. If it's USD per Other, then 1.0 / exchange_rate
                                                        // Assuming exchange_rate in DB is "1 USD = X OTHER"
                                                        // So if from USD to OTHER, rate is X.
                                                        // If the DB stores "1 OTHER = X USD", then this should be 1.0 / $toCurrency->exchange_rate
                // Let's assume DB stores "1 USD = X OTHER_CURRENCY"
                // If converting USD to EUR, and 1 USD = 0.9 EUR, rate is 0.9. $toCurrency (EUR) exchange_rate = 0.9
                $rate = (float) $toCurrency->exchange_rate;
            } elseif ($toCurrencyCode === $baseCurrencyForRates) { // From Other to USD
                 // If converting EUR to USD, and 1 USD = 0.9 EUR (so EUR's exchange_rate is 0.9)
                 // Then 1 EUR = 1/0.9 USD. Rate is 1 / $fromCurrency->exchange_rate
                $rate = 1.0 / (float) $fromCurrency->exchange_rate;
            } else { // From Other to Other (via USD)
                 // EUR to GBP: (EUR to USD) * (USD to GBP)
                 // (1 / fromCurrency.rate_vs_usd) * (toCurrency.rate_vs_usd)
                $rate = (1.0 / (float) $fromCurrency->exchange_rate) * (float) $toCurrency->exchange_rate;
            }
        } else {
            Factory::getApplication()->enqueueMessage(Text::_('COM_TIENDA_CURRENCY_CONVERSION_ERROR_RATES_NOT_SET'), 'error');
        }

        self::$exchangeRates[$cacheKey] = $rate;
        return $rate;
    }

    /**
     * Converts an amount from one currency to another.
     *
     * @param float $amount
     * @param string $fromCurrencyCode
     * @param string $toCurrencyCode
     * @param bool $refresh Force refresh of exchange rate
     * @return float
     */
    private static function convert($amount, $fromCurrencyCode, $toCurrencyCode, $refresh = false): float
    {
        $exchangeRate = self::getExchangeRate($fromCurrencyCode, $toCurrencyCode, $refresh);
        return (float)$amount * $exchangeRate;
    }

    /**
     * Formats an amount in a given currency.
     *
     * @param float $amount The amount to format.
     * @param mixed $targetCurrencyInput Currency ID (int), Currency Code (string), or null for default.
     * @param array $options Formatting options (override component params).
     *                       'num_decimals', 'decimal_separator', 'thousands_separator',
     *                       'currency_symbol_pre', 'currency_symbol_post', 'currency_code_display'
     * @return string Formatted currency string.
     */
    public static function format($amount, $targetCurrencyInput = null, array $options = []): string
    {
        $app = Factory::getApplication();
        $params = ComponentHelper::getParams('com_tienda');

        $defaultCurrencyId = (int) $params->get('default_currencyid', self::getCurrentCurrencyId()); // Fallback to getCurrent if param missing
        $defaultCurrency = self::loadCurrency($defaultCurrencyId);

        if (!$defaultCurrency) {
            // Major issue if default currency cannot be loaded, fallback to basic number_format
            Factory::getApplication()->enqueueMessage(Text::_('COM_TIENDA_ERROR_DEFAULT_CURRENCY_LOAD_FAILED'), 'error');
            return number_format((float)$amount, 2);
        }

        $targetCurrency = null;
        if ($targetCurrencyInput === null) {
            $targetCurrency = $defaultCurrency;
        } else {
            $targetCurrency = self::loadCurrency($targetCurrencyInput);
            if (!$targetCurrency) {
                Factory::getApplication()->enqueueMessage(Text::sprintf('COM_TIENDA_WARNING_TARGET_CURRENCY_LOAD_FAILED_USING_DEFAULT', $targetCurrencyInput), 'warning');
                $targetCurrency = $defaultCurrency;
            }
        }

        // Convert amount if target currency is different from the input amount's assumed currency (default)
        // This assumes the input $amount is always in the shop's $defaultCurrency.
        if ($targetCurrency->currency_id !== $defaultCurrency->currency_id) {
            // Check if conversion is enabled or desired through an option perhaps
            // For now, assume conversion is always done if target is different
            $amount = self::convert((float)$amount, $defaultCurrency->currency_code, $targetCurrency->currency_code);
        } else {
            $amount = (float)$amount; // Ensure it's a float
        }

        // Get formatting details from the target currency object
        // These property names are assumed based on typical Tienda structure.
        // They might need adjustment if TiendaTableCurrencies uses different names.
        $num_decimals = $options['num_decimals'] ?? (isset($targetCurrency->currency_decimals) ? (int)$targetCurrency->currency_decimals : (int)$params->get('num_decimals', 2));
        $decimal_sep = $options['decimal_separator'] ?? ($targetCurrency->decimal_separator ?? $params->get('decimal_separator', '.'));
        $thousands_sep = $options['thousands_separator'] ?? ($targetCurrency->thousands_separator ?? $params->get('thousands_separator', ','));

        $symbol_pre = $options['currency_symbol_pre'] ?? ($targetCurrency->symbol_left ?? $params->get('currency_symbol_pre', '$'));
        $symbol_post = $options['currency_symbol_post'] ?? ($targetCurrency->symbol_right ?? $params->get('currency_symbol_post', ''));

        // Display currency code (e.g., USD, EUR)
        $code_display = $options['currency_code_display'] ?? $params->get('currency_code_display', ''); // e.g., 'ISO', 'Symbol', 'None'

        $formatted_number = number_format($amount, $num_decimals, $decimal_sep, $thousands_sep);

        $returnValue = $symbol_pre . $formatted_number . $symbol_post;

        if ($code_display === 'ISO' && isset($targetCurrency->currency_code)) {
            $returnValue .= ' ' . $targetCurrency->currency_code;
        }
        // Add more conditions for other $code_display options if needed

        return $returnValue;
    }
}
