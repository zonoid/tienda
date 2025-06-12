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
use Joomla\CMS\Language\Text;
use Joomla\CMS\Date\Date;
use Joomla\CMS\Table\Table;
use Joomla\Database\DatabaseDriver;

class ProductCommentTable extends Table
{
    /** @var int Primary key */
    public $productcomment_id = null;
    /** @var int Foreign key to #__tienda_products table */
    public $product_id = null;
    /** @var int Foreign key to #__users table */
    public $user_id = null;
    /** @var string Name of the user who submitted the comment */
    public $user_name = null;
    /** @var string Email of the user who submitted the comment */
    public $user_email = null;
    /** @var int Rating given by the user (e.g., 1-5) */
    public $productcomment_rating = null;
    /** @var string The text of the comment */
    public $comment_text = null; // Changed from productcomment_text to common 'comment_text'
    /** @var int Number of times this comment has been reported */
    public $reported_count = 0;
    /** @var int Whether the comment is published/enabled */
    public $productcomment_enabled = 0;
    /** @var string Date this comment was created */
    public $created_date = null;
    /** @var string Date this comment was last modified */
    public $modified_date = null;
    /** @var int Flag indicating if the product's overall rating has been updated with this comment */
    public $rating_updated = 0;

    /**
     * Constructor
     *
     * @param   DatabaseDriver  $db  A database connector object
     */
    public function __construct(DatabaseDriver $db)
    {
        parent::__construct('#__tienda_productcomments', 'productcomment_id', $db);
        $this->setColumnAlias('published', 'productcomment_enabled');
    }

    /**
     * Overloaded check function to ensure data integrity.
     *
     * @return  bool  True if the record is valid, false otherwise.
     */
    public function check()
    {
        $db = $this->getDbo();
        $nullDate = $db->getNullDate();

        if (empty($this->product_id)) {
            $this->setError(Text::_('COM_TIENDA_PRODUCT_ID_REQUIRED'));
            return false;
        }

        if (empty($this->created_date) || $this->created_date == $nullDate) {
            $this->created_date = Factory::getDate('now', 'UTC')->toSql(true);
        }

        $this->modified_date = Factory::getDate('now', 'UTC')->toSql(true);

        // Ensure text fields are not null if DB doesn't allow it (or has defaults)
        $this->user_name = (string) $this->user_name;
        $this->user_email = (string) $this->user_email;
        $this->comment_text = (string) $this->comment_text;


        return parent::check();
    }

    /**
     * Overloaded store function
     *
     * @param   boolean  $updateNulls  True to update fields even if they are null.
     * @return  boolean  True on success.
     */
    public function store($updateNulls = false)
    {
        $this->modified_date = Factory::getDate('now', 'UTC')->toSql(true);

        // TODO: Logic for updating product overall rating upon comment save/state change
        // needs to be moved to ProductCommentModel::save() or a helper.
        // This includes handling the 'rating_updated' flag.
        // Example from old table:
        // if ($this->productcomment_enabled && empty($this->rating_updated)) { ... update product rating ... $this->rating_updated = 1; }
        // elseif (!$this->productcomment_enabled && !empty($this->rating_updated)) { ... update product rating ... $this->rating_updated = 0; }

        return parent::store($updateNulls);
    }

    /**
     * Overloaded delete method.
     *
     * @param   mixed  $pk  An optional primary key value to delete. If not set the instance property value is used.
     * @return  bool   True on success.
     */
    public function delete($pk = null)
    {
        // TODO: Logic for deleting helpfulness records (from #__tienda_productcommentshelpfulness)
        // and updating product overall rating needs to be moved to ProductCommentModel::delete() or a helper.

        return parent::delete($pk);
    }
}
?>
