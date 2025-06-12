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

use Joomla\CMS\MVC\Controller\FormController;
use Joomla\CMS\Factory\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Joomla\CMS\MVC\Model\BaseDatabaseModel; // For postSaveHook type hinting
use Joomla\CMS\Session\Session;
use Joomla\CMS\Filesystem\File;
use Dioscouri\Component\Tienda\Administrator\Helper\ProductHelper;

class ProductController extends FormController
{
    /**
     * Constructor.
     *
     * @param   array  $config  An optional associative array of configuration settings.
     *
     * @since   1.6
     */
    public function __construct($config = [])
    {
        // Assuming 'product' is the view name for the form
        $this->view_item = 'product';
        // Assuming 'products' is the view name for the list
        $this->view_list = 'products';
        parent::__construct($config);
    }

    /**
     * Method to run after saving the data.
     *
     * @param   BaseDatabaseModel  $model      The model object.
     * @param   array              $validData  The validated data.
     *
     * @return  void
     * @since   1.6
     */
    protected function postSaveHook(BaseDatabaseModel $model, $validData = array()): void
    {
        parent::postSaveHook($model, $validData);

        // $item = $model->getItem(); // Get the saved item
        // if ($item) {
        //    Factory::getApplication()->enqueueMessage('postSaveHook called for product ID: ' . $item->product_id . '. TODO: Implement EAV and related data saving here or in model.', 'notice');
        // }
        // Note: EAV and related data (prices, quantities, etc.) should ideally be handled within the ProductModel's save method
        // or via events triggered from there, to keep controller cleaner and model responsible for its data integrity.
    }

    /**
     * Method to get a model object, loading it if required.
     *
     * @param   string  $name    The model name. Optional.
     * @param   string  $prefix  The class prefix. Optional.
     * @param   array   $config  Configuration array for model. Optional.
     *
     * @return  \Joomla\CMS\MVC\Model\BaseDatabaseModel|\Joomla\CMS\MVC\Model\FormModel|false  Model object or false on failure.
     *
     * @since   1.6
     */
    public function getModel($name = 'Product', $prefix = 'Administrator', $config = array('ignore_request' => true))
    {
        // Ensure we get our ProductModel.
        // FormController by default tries to load a model named 'Form' if $name is not 'Form'.
        // If our view_item is 'product', it will try to load 'ProductModel'.
        // This override just makes it explicit if we want to ensure our naming convention.
        if ($name === 'Product' && $prefix === 'Administrator') {
            $name = 'Product'; // This should match the class name ProductModel
        } elseif ($name === 'Form' && $prefix === $this->model_prefix && $this->view_item) {
            // If FormController is asking for its default 'Form' model, give it the ProductModel.
             $name = 'Product';
             $prefix = 'Administrator';
        }

        return parent::getModel($name, $prefix, $config);
    }

    public function ajaxDeleteGalleryImage()
    {
        $app = Factory::getApplication();
        $response = ['success' => false, 'message' => ''];

        if (!Session::checkToken('post')) { // Assuming POST request for AJAX delete and token is passed in POST data
            $response['message'] = Text::_('JINVALID_TOKEN');
            $app->setHeader('Content-Type', 'application/json');
            echo json_encode($response);
            $app->close();
            return;
        }

        $productId = $app->input->getInt('product_id', 0);
        // Permission check
        if (!Factory::getApplication()->getIdentity()->authorise('core.edit', 'com_tienda.product.' . $productId) &&
            !Factory::getApplication()->getIdentity()->authorise('core.create', 'com_tienda')) { // core.create for new products not yet saved but might have draft gallery
            $response['message'] = Text::_('JLIB_RULES_NOT_ALLOWED');
            $app->setHeader('Content-Type', 'application/json', true);
            echo json_encode($response);
            $app->close();
        }

        $filename  = $app->input->getString('filename', '');

        if (!$productId || empty($filename)) {
            $response['message'] = Text::_('COM_TIENDA_ERROR_MISSING_PRODUCT_OR_FILENAME');
            $app->setHeader('Content-Type', 'application/json', true);
            echo json_encode($response);
            $app->close();
        }

        try {
            if (ProductHelper::deleteGalleryImage($productId, $filename)) {
                $response['success'] = true;
                $response['message'] = Text::sprintf('COM_TIENDA_GALLERY_IMAGE_DELETED_SUCCESS', $filename);
            } else {
                $response['message'] = Text::sprintf('COM_TIENDA_GALLERY_IMAGE_DELETED_ERROR', $filename);
                // Check for more specific messages enqueued by the helper
                $messages = $app->getMessageQueue();
                if (!empty($messages)) {
                    foreach($messages as $msg) {
                        if($msg['type'] === 'error' || $msg['type'] === 'warning') { // Concatenate error/warning messages
                           $response['message'] .= ' - ' . $msg['message'];
                        }
                    }
                }
            }
        } catch (\Exception $e) {
            // Log the exception $e->getMessage() for debugging
            $response['message'] = Text::sprintf('COM_TIENDA_GALLERY_IMAGE_DELETED_ERROR_EXCEPTION', $filename);
        }

        $app->setHeader('Content-Type', 'application/json', true);
        echo json_encode($response);
        $app->close(); // Outputs JSON and exits
    }
}
