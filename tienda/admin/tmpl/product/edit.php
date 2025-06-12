<?php
defined('_JEXEC') or die('Restricted access');

use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Layout\LayoutHelper;

// The JLayout for the controls is not standard, assuming a placeholder or future creation.
// For now, standard Joomla form rendering will be used.

// Display form validation messages if any
// echo LayoutHelper::render('joomla.edit.params', $this); // This is for module params, not ideal here.
// Instead, manually display messages or use a different layout for form messages if available.
if (!empty($this->form->getErrors())) :
    ?>
    <div class="alert alert-danger">
        <p><?php echo Text::_('JERROR_FORM_INVALID'); ?></p>
        <ul>
            <?php foreach ($this->form->getErrors() as $error) : ?>
                <li><?php echo $error; ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<form action="<?php echo Route::_('index.php?option=com_tienda&view=product&layout=edit&id=' . (int) ($this->item->product_id ?? 0)); ?>"
    method="post" name="adminForm" id="item-form" class="form-validate" enctype="multipart/form-data">

    <div class="main-card">
        <?php echo LayoutHelper::render('joomla.edit.title_alias', $this); ?>

        <div class="form-horizontal">
            <?php echo $this->form->renderFieldset('basic_info'); ?>
            <?php echo $this->form->renderFieldset('description'); ?>
            <?php echo $this->form->renderFieldset('pricing'); ?>
            <?php echo $this->form->renderFieldset('inventory_shipping'); ?>
            <?php echo $this->form->renderFieldset('display_options'); ?>
            <?php echo $this->form->renderFieldset('metadata'); ?>
            <?php echo $this->form->renderFieldset('eav_attributes'); ?>
        </div>
    </div>

    <?php echo $this->form->renderField('product_id'); // Hidden field for ID ?>
    <?php echo $this->form->renderField('created_date'); // Hidden field for created_date ?>
    <?php echo $this->form->renderField('modified_date'); // Hidden field for modified_date ?>

    <input type="hidden" name="task" value="" />
    <?php echo HTMLHelper::_('form.token'); ?>
</form>
