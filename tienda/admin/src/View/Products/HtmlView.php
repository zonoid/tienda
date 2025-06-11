<?php
/**
 * @version 0.1
 * @package Tienda
 * @author Dioscouri Design
 * @link http://www.dioscouri.com
 * @copyright Copyright (C) 2007 Dioscouri Design. All rights reserved.
 * @license http://www.gnu.org/copyleft/gpl.html GNU/GPL, see LICENSE.php
 */

namespace Dioscouri\Component\Tienda\Administrator\View\Products;

defined('_JEXEC') or die('Restricted access');

use Joomla\CMS\Factory\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Toolbar\Toolbar;
use Joomla\CMS\Toolbar\ToolbarHelper;
use Joomla\CMS\Helper\ContentHelper;
use Dioscouri\Component\Tienda\Administrator\Helper\TiendaAdminHelper; // Assuming future helper
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView; // Alias to avoid naming conflict if HtmlView is used directly

class HtmlView extends BaseHtmlView
{
    protected $items;
    protected $pagination;
    protected $state;
    protected $filterForm;
    protected $activeFilters;

    /**
     * Display the view
     *
     * @param   string  $tpl  The name of the template file to parse; automatically searches through the template paths.
     * @return  void
     */
    public function display($tpl = null)
    {
        $this->items         = $this->get('Items');
        $this->pagination    = $this->get('Pagination');
        $this->state         = $this->get('State');
        $this->filterForm    = $this->get('FilterForm');
        $this->activeFilters = $this->get('ActiveFilters');

        // Check for errors.
        if (count($errors = $this->get('Errors'))) {
            Factory::getApplication()->enqueueMessage(implode("\n", $errors), 'error');
            return false;
        }

        $this->addToolbar();

        // TODO: TiendaAdminHelper::addSubmenu will need to be implemented or replaced
        // For now, we can check if the class exists to prevent fatal errors if it's not yet created.
        if (class_exists(TiendaAdminHelper::class)) {
            TiendaAdminHelper::addSubmenu('products');
        } else {
            Factory::getApplication()->enqueueMessage('TiendaAdminHelper not found. Submenu not added.', 'notice');
        }

        // Add this line to make the sidebar work in J4/J5 if you are using it
        $this->sidebar = \Joomla\CMS\HTML\Helpers\Sidebar::render();


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
        // $canDo = ContentHelper::getActions('com_tienda', 'category', $this->state->get('filter.category_id'));
        // For component-level actions, typically you check 'core.manage' or specific component permissions.
        // For 'com_tienda', let's assume a general manage permission for now.
        // The 'category' part in getActions is usually for a specific item, which might not be relevant for a list view toolbar.
        // We'll use 'core.manage' on the component for overall access control.
        $canDo = ContentHelper::getActions('com_tienda'); // Gets component actions
        $user  = Factory::getApplication()->getIdentity();

        ToolbarHelper::title(Text::_('COM_TIENDA_PRODUCTS_MANAGER'), 'search'); // Added an icon

        // Check for 'core.create' permission for the component.
        if ($user->authorise('core.create', 'com_tienda')) {
            ToolbarHelper::addNew('product.add'); // Task: controller.method (ProductsController::add())
        }

        // Check for 'core.edit' permission for the component.
        // Note: editList typically also checks for 'core.edit.state' if items can be checked out.
        if ($user->authorise('core.edit', 'com_tienda') || $user->authorise('core.edit.own', 'com_tienda')) {
            ToolbarHelper::editList('product.edit'); // Task: ProductsController::edit()
        }

        // Publish and Unpublish based on 'core.edit.state'
        if ($user->authorise('core.edit.state', 'com_tienda')) {
            ToolbarHelper::publish('products.publish', 'JTOOLBAR_PUBLISH', true);
            ToolbarHelper::unpublish('products.unpublish', 'JTOOLBAR_UNPUBLISH', true);
        }

        // Check for 'core.delete' permission for the component.
        if ($user->authorise('core.delete', 'com_tienda')) {
            ToolbarHelper::deleteList(Text::_('COM_TIENDA_CONFIRM_DELETE'), 'products.delete', 'JTOOLBAR_DELETE');
        }

        if ($user->authorise('core.admin', 'com_tienda') || $user->authorise('core.options', 'com_tienda')) {
            ToolbarHelper::preferences('com_tienda');
        }
    }
}
