<?php defined('_JEXEC') or die('Restricted access'); ?>
<?php
use Joomla\CMS\Router\Route;
use Joomla\CMS\Layout\LayoutHelper;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Uri\Uri;
?>

<form action="<?php echo Route::_('index.php?option=com_tienda&view=products'); ?>" method="post" name="adminForm" id="adminForm" enctype="multipart/form-data">

    <?php echo LayoutHelper::render('joomla.searchtools.default', ['view' => $this, 'options' => ['filtersHidden' => false]]); ?>

    <table class="table table-striped table-bordered" style="clear: both;">
        <thead>
            <tr>
                <th style="width: 5px;">
                    <?php echo Text::_('COM_TIENDA_NUM'); ?>
                </th>
                <th style="width: 20px;">
                    <?php echo HTMLHelper::_('grid.checkall'); ?>
                </th>
                <th style="width: 50px;">
                    <?php echo HTMLHelper::_('grid.sort', Text::_('COM_TIENDA_ID'), 'tbl.product_id', $this->state->get('list.direction'), $this->state->get('list.ordering')); ?>
                </th>
                <th style="text-align: left;" colspan="2">
                    <?php echo HTMLHelper::_('grid.sort', Text::_('COM_TIENDA_NAME'), 'tbl.product_name', $this->state->get('list.direction'), $this->state->get('list.ordering')); ?>
                    <!-- TODO: Add separate sortable headers for Rating and Reviews if needed, or remove them if not primary sort fields -->
                    <!-- <?php echo HTMLHelper::_('grid.sort', Text::_('COM_TIENDA_RATING'), 'tbl.product_rating', $this->state->get('list.direction'), $this->state->get('list.ordering')); ?> -->
                    <!-- <?php echo HTMLHelper::_('grid.sort', Text::_('COM_TIENDA_REVIEWS'), 'tbl.product_comments', $this->state->get('list.direction'), $this->state->get('list.ordering')); ?> -->
                </th>
                <th style="width: 70px;">
                    <?php echo HTMLHelper::_('grid.sort', Text::_('COM_TIENDA_SKU'), 'tbl.product_sku', $this->state->get('list.direction'), $this->state->get('list.ordering')); ?>
                </th>
                <th style="width: 50px;">
                    <?php echo HTMLHelper::_('grid.sort', Text::_('COM_TIENDA_PRICE'), 'price', $this->state->get('list.direction'), $this->state->get('list.ordering')); /* 'price' is an alias from model */ ?>
                </th>
                <th style="width: 100px;">
                    <?php echo HTMLHelper::_('grid.sort', Text::_('COM_TIENDA_QUANTITY'), 'product_quantity', $this->state->get('list.direction'), $this->state->get('list.ordering')); /* 'product_quantity' is an alias from model */?>
                </th>
                <th style="width: 100px;">
                    <?php echo HTMLHelper::_('grid.sort', Text::_('COM_TIENDA_ORDER'), 'tbl.ordering', $this->state->get('list.direction'), $this->state->get('list.ordering')); ?>
                    <!-- TODO: Implement J5 ordering controls if needed, JHTML::_('grid.order', @$items) is old. -->
                    <!-- For now, manual ordering might require a separate view or UI element. -->
                </th>
                <th style="width: 100px;">
                    <?php echo HTMLHelper::_('grid.sort', Text::_('COM_TIENDA_ENABLED'), 'tbl.product_enabled', $this->state->get('list.direction'), $this->state->get('list.ordering')); ?>
                </th>
            </tr>
            <!-- Remove old filterline row, it's handled by joomla.searchtools.default -->
            <tr>
                <th colspan="20" style="font-weight: normal;">
                    <div style="float: right; padding: 5px;"><?php echo $this->pagination->getResultsCounter(); ?></div>
                    <div style="float: left;"><?php echo $this->pagination->getListFooter(); ?></div>
                </th>
            </tr>
        </thead>

        <tbody>
        <?php if (!empty($this->items)) : ?>
            <?php foreach ($this->items as $i => $item) : ?>
            <tr class='row<?php echo $i % 2; ?>'>
                <td align="center">
                    <?php echo $this->pagination->getRowOffset($i); ?>
                </td>
                <td style="text-align: center;">
                    <?php echo HTMLHelper::_('grid.id', $i, $item->product_id); ?>
                </td>
                <td style="text-align: center;">
                    <a href="<?php echo Route::_('index.php?option=com_tienda&task=product.edit&product_id=' . (int) $item->product_id); ?>">
                        <?php echo $this->escape($item->product_id); ?>
                    </a>
                </td>
                <td style="text-align: center; width: 50px;">
                    <?php echo \Dioscouri\Component\Tienda\Administrator\Helper\ProductHelper::getImage($item->product_id, 'id', $item->product_name, 'thumb', false, false, ['width'=>48, 'height'=>48]); ?>
                </td>
                <td style="text-align: left;">
                    <a href="<?php echo Route::_('index.php?option=com_tienda&task=product.edit&product_id=' . (int) $item->product_id); ?>">
                        <?php echo $this->escape($item->product_name); ?>
                    </a>

                    <div class="product_rating">
                        <?php $ratingData = \Dioscouri\Component\Tienda\Administrator\Helper\ProductHelper::getRatingImage($item->product_rating); ?>
                        <?php if (isset($item->product_rating) && $item->product_rating > 0 && isset($ratingData->starValue)) : ?>
                            <?php
                            // Construct image name, e.g., stars_3_5.gif or stars_4_0.gif
                            // Ensure that starValue like '3' becomes '3_0' for consistency if your images are named that way.
                            // Or, if your images are just 'stars_3.gif', adjust accordingly.
                            $starValueForImage = str_replace('.', '_', $ratingData->starValue);
                            if (strpos($starValueForImage, '_') === false) { // If it's a whole number like '3', make it '3_0'
                                $starValueForImage .= '_0';
                            }
                            $starImageFile = 'stars_' . $starValueForImage . '.gif'; // Assuming .gif as per original Tienda
                            $ratingImageSrc = Uri::root(true) . '/media/com_tienda/images/ratings/' . $starImageFile;
                            ?>
                            <img src="<?php echo $ratingImageSrc; ?>" alt="<?php echo $this->escape(sprintf(Text::_('COM_TIENDA_RATING_TEXT'), $ratingData->originalValue, $ratingData->totalStars)); ?>" />
                            <?php if (!empty($item->product_comments)) : ?>
                                <span>(<?php echo (int)$item->product_comments; ?>)</span>
                            <?php endif; ?>
                        <?php else: ?>
                            <span><?php echo Text::_('COM_TIENDA_NO_RATING'); ?></span>
                        <?php endif; ?>
                    </div>

                    <div class="product_categories">
                        <?php
                        $category_ids = \Dioscouri\Component\Tienda\Administrator\Helper\ProductHelper::getCategories($item->product_id);
                        if (!empty($category_ids)) {
                            $first_category_id = $category_ids[0];
                            // TODO: CategoryTable needs to be refactored for getPathName to work reliably
                            // For now, if CategoryHelper is not fully functional due to table dependencies, this might output notices or empty strings.
                            $category_path = \Dioscouri\Component\Tienda\Administrator\Helper\CategoryHelper::getPathName($first_category_id);
                            echo $this->escape($category_path);
                             if (count($category_ids) > 1) {
                                echo ' ' . Text::sprintf('COM_TIENDA_AND_X_MORE', count($category_ids) - 1);
                            }
                        } else {
                            echo Text::_('COM_TIENDA_NO_CATEGORY_ASSIGNED');
                        }
                        ?>
                        <!-- TODO: Implement Categories Popup Link if still needed -->
                        <!-- <span style="float: right;">[...]</span> -->
                    </div>

                    <!-- TODO: Implement Image Gallery Path Display -->
                    <!-- <div class="product_images_path"> -->
                        <!-- <b><?php // echo Text::_('COM_TIENDA_IMAGE_GALLERY_PATH'); ?>:</b> <?php // echo str_replace( JPATH_SITE, '', $helper_product->getGalleryPath( $item->product_id ) ); ?> -->
                    <!-- </div> -->

                    <!-- TODO: Implement Layout Override Display -->
                    <!-- <?php  // $layout = $helper_product->getLayout( $item->product_id );
                    // if ($layout != 'view')
                    // {
                    // echo "<b>".Text::_('COM_TIENDA_LAYOUT_OVERRIDE')."</b>: ".$layout;
                    // }
                    ?> -->
                </td>

                <td style="text-align: center;">
                    <?php echo $this->escape($item->product_sku); ?>
                </td>
                <td style="text-align: right;">
                    <?php echo $this->escape(isset($item->calculated_price) ? TiendaHelperBase::currency($item->calculated_price) : Text::_('COM_TIENDA_N_A')); // Placeholder for currency formatting ?>
                    <!-- TODO: Implement Set Prices Popup Link -->
                    <!-- <br/> -->
                    <!-- [<?php // echo TiendaUrl::popup( "index.php?option=com_tienda&controller=products&task=setprices&id=".$item->product_id."&tmpl=component", Text::_('COM_TIENDA_SET_PRICES'), array('update' => true) ); ?>] -->
                </td>
                <td style="text-align: center;">
                    <?php echo $this->escape(isset($item->current_stock) ? (int) $item->current_stock : Text::_('COM_TIENDA_N_A')); ?>
                    <!-- TODO: Implement Set Quantities Popup Link -->
                    <!-- <br/> -->
                    <!-- [<?php // echo TiendaUrl::popup( "index.php?option=com_tienda&controller=products&task=setquantities&id=".$item->product_id."&tmpl=component", Text::_('COM_TIENDA_SET_QUANTITIES'), array('update' => true) ); ?>] -->
                </td>
                <td style="text-align: center;">
                    <!-- TODO: Implement J5 Ordering Controls -->
                    <!-- <?php // echo TiendaGrid::order($item->product_id); ?> -->
                    <!-- <?php // echo TiendaGrid::ordering($item->product_id, $item->ordering ); ?> -->
                    <?php echo (int) $item->ordering; ?>
                </td>
                <td style="text-align: center;">
                    <?php echo HTMLHelper::_('jgrid.published', $item->product_enabled, $i, 'products.'); ?>
                </td>
            </tr>
            <?php endforeach; ?>
        <?php else : ?>
            <tr>
                <td colspan="10" align="center">
                    <?php echo Text::_('COM_TIENDA_NO_ITEMS_FOUND'); ?>
                </td>
            </tr>
        <?php endif; ?>
        </tbody>
            <tfoot>
            <tr>
                <td colspan="20">
                     <?php echo $this->pagination->getListFooter(); ?>
                     <?php // echo $this->pagination->getLimitBox(); // Usually part of searchtools layout or handled by template if needed separately ?>
                </td>
            </tr>
        </tfoot>
    </table>

    <input type="hidden" name="task" value="" />
    <input type="hidden" name="boxchecked" value="0" />
    <input type="hidden" name="filter_order" value="<?php echo $this->state->get('list.ordering'); ?>" />
    <input type="hidden" name="filter_direction" value="<?php echo $this->state->get('list.direction'); ?>" />
    <?php echo HTMLHelper::_('form.token'); ?>
</form>
