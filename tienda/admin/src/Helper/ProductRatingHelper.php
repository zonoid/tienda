<?php
namespace Dioscouri\Component\Tienda\Administrator\Helper;

defined('_JEXEC') or die('Restricted access');

use Joomla\CMS\Factory\Factory;
use Joomla\CMS\Language\Text;
// ProductCommentsModel is not directly used by this helper method, direct DB query is used.
// use Dioscouri\Component\Tienda\Administrator\Model\ProductCommentsModel;
   // use Dioscouri\Component\Tienda\Administrator\Table\ProductTable; // No longer using ProductTable directly here

class ProductRatingHelper
{
    /**
     * Updates the overall rating and comment count for a specific product.
     *
     * @param   int   $productId  The ID of the product to update.
     * @return  bool  True on success, false on failure.
     */
    public static function updateProductOverallRating(int $productId)
    {
        if ($productId <= 0) {
            return false;
        }

        $app = Factory::getApplication();
        $db = Factory::getDbo();
        $success = true;

        // Get count and sum of ratings for enabled comments of this product
        $query = $db->getQuery(true)
            ->select('COUNT(*) AS comments_count, SUM(tbl.productcomment_rating) AS ratings_sum')
            ->from($db->quoteName('#__tienda_productcomments') . ' AS tbl')
            ->where($db->quoteName('tbl.product_id') . ' = ' . $productId)
            ->where($db->quoteName('tbl.productcomment_enabled') . ' = 1');
        $db->setQuery($query);

        try {
            $result = $db->loadObject();
        } catch (\Exception $e) {
            $app->enqueueMessage(Text::sprintf('COM_TIENDA_DATABASE_ERROR_QUERYING_RATINGS', $productId, $e->getMessage()), 'error');
            return false; // Cannot proceed if DB query fails
        }

        $commentsCount = 0;
        $ratingsSum = 0;
        if ($result) {
            $commentsCount = (int)$result->comments_count;
            $ratingsSum = (float)$result->ratings_sum;
        }

        $averageRating = ($commentsCount > 0) ? ($ratingsSum / $commentsCount) : 0;

        // Update the product table directly
        try {
             $updateQuery = $db->getQuery(true)
                 ->update($db->quoteName('#__tienda_products'))
                 ->set($db->quoteName('product_rating') . ' = ' . $db->quote($averageRating))
                 ->set($db->quoteName('product_comments') . ' = ' . (int)$commentsCount)
                 ->where($db->quoteName('product_id') . ' = ' . $productId);
             $db->setQuery($updateQuery);
             if (!$db->execute()) { // Check if execute() returned false (Joomla 4/5 execute often returns void or throws exception on error)
                 // This path might not be hit if execute() throws exception on failure.
                 $app->enqueueMessage(Text::sprintf('COM_TIENDA_ERROR_UPDATING_PRODUCT_RATING_DIRECT_DB_EXECUTE_FAILED', $productId), 'error');
                 $success = false;
             }
        } catch (\Exception $e) {
             $app->enqueueMessage(Text::sprintf('COM_TIENDA_ERROR_UPDATING_PRODUCT_RATING_DIRECT_DB', $productId, $e->getMessage()), 'error');
             $success = false;
        }
        return $success;
    }
}
?>
