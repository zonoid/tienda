<?php
/**
 * @version 0.1
 * @package Tienda
 * @author Dioscouri Design
 * @link http://www.dioscouri.com
 * @copyright Copyright (C) 2007 Dioscouri Design. All rights reserved.
 * @license http://www.gnu.org/copyleft/gpl.html GNU/GPL, see LICENSE.php
 */

namespace Dioscouri\Component\Tienda\Administrator\View\Product; // Singular 'Product'

defined('_JEXEC') or die('Restricted access');

use Joomla\CMS\MVC\View\FormView;
use Joomla\CMS\Factory\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Toolbar\Toolbar;
use Joomla\CMS\Toolbar\ToolbarHelper;
use Dioscouri\Component\Tienda\Administrator\Helper\TiendaAdminHelper; // Placeholder
use Joomla\CMS\Component\ComponentHelper; // Added as it's used in addToolbar
use Joomla\CMS\Layout\LayoutHelper; // For potential sidebar rendering

class HtmlView extends FormView
{
    protected $form;
    protected $item;
    protected $state;
    // Optional: For Joomla 4/5 sidebar if you define one
    // protected $sidebar;

    /**
     * Display the view
     *
     * @param   string  $tpl  The name of the template file to parse; automatically searches through the template paths.
     * @return  void
     */
    public function display($tpl = null)
    {
        $this->form  = $this->get('Form');
        $this->item  = $this->get('Item');
        $this->state = $this->get('State'); // Get the model state

        // Check for errors.
        if (count($errors = $this->get('Errors'))) {
            Factory::getApplication()->enqueueMessage(implode("\n", $errors), 'error');
            return false;
        }

        $this->addToolbar();

        // Placeholder for admin submenu/sidebar
        // Ensure TiendaAdminHelper class exists or this will cause an error.
        // if (class_exists(TiendaAdminHelper::class)) {
        //     TiendaAdminHelper::addSubmenu(Factory::getApplication()->input->getCmd('view', 'product'));
        //     // $this->sidebar = TiendaAdminHelper::getSidebar(); // Or however sidebar is rendered
        // } else {
        //     Factory::getApplication()->enqueueMessage('TiendaAdminHelper not found for submenu in ProductView.', 'notice');
        // }

        // For Joomla 4/5 standard sidebar (if you have one defined for the view in a layout like `joomla.sidebars.standard`)
        // Example: Check if a sidebar layout exists for this component for this view
        // if (LayoutHelper::exists('joomla.sidebars.standard', JPATH_ADMINISTRATOR . '/components/com_tienda/layouts')) {
        //    $this->sidebar = LayoutHelper::render('joomla.sidebars.standard', ['view' => $this]);
        // }


        parent::display($tpl);
    }

    /**
     * Add the page title and toolbar.
     *
     * @return  void
     * @since   1.6
     */
    protected function addToolbar()
    {
        $app = Factory::getApplication();
        $app->input->set('hidemainmenu', true); // Standard for form views
        $user = $app->getIdentity();
        $isNew = ($this->item->product_id == 0); // Assuming product_id is the primary key

        // ComponentHelper requires 'use Joomla\CMS\Component\ComponentHelper;'
        // Corrected to use $this->item->product_id for existing item checks.
        $canDo = ComponentHelper::getActions('com_tienda', 'product', $isNew ? 0 : $this->item->product_id);

        ToolbarHelper::title($isNew ? Text::_('COM_TIENDA_PRODUCT_NEW') : Text::_('COM_TIENDA_PRODUCT_EDIT'), 'tags'); // 'tags' or other relevant icon

        // Save actions
        // If can create and new, or can edit and existing
        if (($isNew && $user->authorise('core.create', 'com_tienda')) || (!$isNew && $user->authorise('core.edit', 'com_tienda.product.' . $this->item->product_id))) {
            ToolbarHelper::apply('product.apply'); // Assumes controller is ProductController, task is apply
            ToolbarHelper::save('product.save');
        }

        // Save as new (if user has create permission)
        if ($user->authorise('core.create', 'com_tienda')) {
             // ToolbarHelper::custom('product.savenew', 'save-new.png', 'save-new_f2.png', 'JTOOLBAR_SAVE_AND_NEW', false);
             ToolbarHelper::save2new('product.save2new'); // J5 standard
        }

        // TODO: Add Save as Copy if relevant and user has create permission
        // if (!$isNew && $user->authorise('core.create', 'com_tienda')) {
        //     ToolbarHelper::custom('product.savecopy', 'copy', 'copy', 'JTOOLBAR_SAVE_AS_COPY', false); // Updated icon names
        // }

        if (empty($this->item->product_id)) {
            ToolbarHelper::cancel('product.cancel', 'JTOOLBAR_CANCEL');
        } else {
            ToolbarHelper::cancel('product.cancel', 'JTOOLBAR_CLOSE');
        }

        // Add versioning button if available and configured
        // if (isset($this->state) && $this->state->get('params') && $this->state->get('params')->get('save_history', 0) && $user->authorise('core.edit')) {
        //    ToolbarHelper::versions('com_tienda.product', $this->item->product_id);
        // }
    }
}
