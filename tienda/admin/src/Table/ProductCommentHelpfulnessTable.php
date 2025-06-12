<?php
/**
 * @package     Tienda
 * @subpackage  Administrator
 * @author      Dioscouri Design
 * @link        http://www.dioscouri.com
 * @copyright   (C) 2024 Dioscouri Design. All rights reserved.
 * @license     GNU/GPL V2 or later
 */

namespace Dioscouri\Component\Tienda\Administrator\Table;

defined('_JEXEC') or die('Restricted access');

use Joomla\CMS\Factory\Factory;
use Joomla\CMS\Date\Date;
use Joomla\CMS\Table\Table;
use Joomla\Database\DatabaseDriver;

class ProductCommentHelpfulnessTable extends Table
{
    /** @var int Primary key */
    public $productcommentshelpfulness_id = null;
    /** @var int Foreign key to #__tienda_productcomments table */
    public $productcomment_id = null;
    /** @var int Foreign key to #__users table (the voter) */
    public $user_id = null;
    /** @var int Rating of helpfulness (e.g., 1 for helpful, -1 for unhelpful) */
    public $helpfulness_rating = null;
    /** @var string Date this record was created */
    public $created_date = null;
    // Optional: Add IP address if it was tracked
    // public $ip_address = null;

    /**
     * Constructor
     *
     * @param   DatabaseDriver  $db  A database connector object
     */
    public function __construct(DatabaseDriver $db)
    {
        parent::__construct('#__tienda_productcommentshelpfulness', 'productcommentshelpfulness_id', $db);
    }

    /**
     * Overloaded check function to ensure data integrity, especially for dates.
     *
     * @return  bool  True if the record is valid, false otherwise.
     */
    public function check()
    {
        $db = $this->getDbo();
        $nullDate = $db->getNullDate();

        // Set created_date if it's a new record and date is not set
        if (empty($this->productcommentshelpfulness_id) && (empty($this->created_date) || $this->created_date == $nullDate)) {
            // Using UTC for created_date as it's a server-generated timestamp generally
            $this->created_date = Factory::getDate('now', 'UTC')->toSql(true);
        }

        // Ensure helpfulness_rating is within expected values if necessary
        // For example, if only 1 and -1 are allowed:
        // if ($this->helpfulness_rating !== null && !in_array((int)$this->helpfulness_rating, [1, -1])) {
        //     $this->setError(Text::_('COM_TIENDA_INVALID_HELPFULNESS_RATING'));
        //     return false;
        // }

        return parent::check();
    }
}
?>
