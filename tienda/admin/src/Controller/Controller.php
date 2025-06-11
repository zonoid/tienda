<?php
/**
 * @version 0.1
 * @package Tienda
 * @author Dioscouri Design
 * @link http://www.dioscouri.com
 * @copyright Copyright (C) 2007 Dioscouri Design. All rights reserved.
 * @license http://www.gnu.org/copyleft/gpl.html GNU/GPL, see LICENSE.php
 */

namespace Dioscouri\Component\Tienda\Administrator\Controller;

defined('_JEXEC') or die('Restricted access');

use Joomla\CMS\Factory\Factory;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Table\Table;

class Controller extends BaseController
{
    /**
     * default view
     */
    public $default_view = 'dashboard';

    /**
     * @var array() instances of Models to be used by the controller
     */
    public $_models = array();

    /**
     * string url to perform a redirect with. Useful for child classes.
     */
    protected $redirect;

    public function __construct($config = array())
    {
        parent::__construct(); // Standard J5 BaseController constructor
        // $this->defines = Tienda::getInstance(); // Removed: Dependencies should be injected or accessed via services/application params
    }

    /**
     * Hides a tooltip message
     * @return void
     */
    public function pagetooltip_switch()
    {
        $msg = new \stdClass();
        $msg->type      = '';
        $msg->message   = '';
        $view = $this->input->getString('view'); // Use getString for view name
        $msg->link      = 'index.php?option=com_tienda&view=' . $view;

        $key = $this->input->getString('key'); // Use getString for key
        $constant = 'page_tooltip_' . $key;
        $config_title = $constant . "_disabled";

        $database = Factory::getDbo();
        Table::addIncludePath(JPATH_ADMINISTRATOR . '/components/com_tienda/tables/');
        $table = Table::getInstance('config', 'TiendaTable'); // This will require TiendaTableConfig to be refactored
        if ($table) {
            $table->load(['config_name' => $config_title]);
            $table->config_name = $config_title;
            $table->value = '1';

            if (!$table->save()) {
                $msg->message = Text::_('COM_TIENDA_ERROR') . ": " . $table->getError();
            }
        } else {
            $msg->message = Text::_('COM_TIENDA_ERROR_TABLE_CONFIG_NOT_FOUND');
            $msg->type = 'error';
        }

        $this->setRedirect($msg->link, $msg->message, $msg->type);
    }

    /**
     * For displaying a searchable list of products in a lightbox
     */
    public function elementProduct()
    {
        // $model = $this->getModel('elementproduct'); // Requires ElementproductModel to be J5 compatible
        // $view  = $this->getView('elementproduct', 'html'); // Requires ElementproductView to be J5 compatible
        // $view->setModel($model, true);
        // $view->display();
        // Commenting out body as models/views not yet refactored
        Factory::getApplication()->enqueueMessage('elementProduct needs refactoring of its model and view.', 'notice');
    }

    /**
     * For displaying a searchable list of images in a lightbox
     */
    public function elementImage()
    {
        // $model = $this->getModel('elementimage'); // Requires ElementimageModel to be J5 compatible
        // $view  = $this->getView('elementimage', 'html'); // Requires ElementimageView to be J5 compatible
        // $view->setModel($model, true);
        // $view->display();
        // Commenting out body as models/views not yet refactored
        Factory::getApplication()->enqueueMessage('elementImage needs refactoring of its model and view.', 'notice');
    }
}
