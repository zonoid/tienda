<?php
namespace Dioscouri\Component\Tienda\Administrator\Controller;

defined('_JEXEC') or die('Restricted access');

use Joomla\CMS\Factory\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Session\Session;
use Joomla\CMS\Table\Table;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Joomla\Event\DispatcherInterface;

// Assuming the new base controller is Dioscouri\Component\Tienda\Administrator\Controller\Controller
class ProductsController extends Controller
{
    public function __construct($config = [], MVCFactoryInterface $factory = null, $input = null, DispatcherInterface $dispatcher = null)
    {
        parent::__construct($config, $factory, $input, $dispatcher);
        // Remove: $this->set('suffix', 'products');
        $this->registerTask('product_enabled.enable', 'booleanTask');
        $this->registerTask('product_enabled.disable', 'booleanTask');
        $this->registerTask('selected_enable', 'selected_switch');
        $this->registerTask('selected_disable', 'selected_switch');
        // Keep other task registrations for now, will need individual review
        $this->registerTask('saveprev', 'save');
        $this->registerTask('savenext', 'save');
        $this->registerTask('prev', 'jump');
        $this->registerTask('next', 'jump');
    }

    // Implement booleanTask as a placeholder for product_enabled.enable/disable tasks
    public function booleanTask()
    {
        Factory::getApplication()->enqueueMessage('booleanTask needs to be implemented.', 'notice');
        $this->setRedirect(Route::_('index.php?option=com_tienda&view=products', false));
    }

    protected function _setModelState()
    {
        $state = parent::_setModelState(); // Assuming BaseController has a _setModelState or this is custom
        $app = Factory::getApplication();
        $model = $this->getModel('Products'); // Changed from 'Product' to 'Products' to match old controller's $this->get('suffix')
        // $ns = $this->getNamespace(); // Old J1.5 pattern, not directly applicable

        // TODO: All these need to be verified for J5. Using $app->input directly.
        // User state handling needs to be component-specific, e.g., com_tienda.products.filter_id_from
        $state['filter_id_from']    = $app->input->getString('filter_id_from', $app->getUserState('com_tienda.products.filter_id_from', ''));
        $app->setUserState('com_tienda.products.filter_id_from', $state['filter_id_from']);
        $state['filter_id_to']      = $app->input->getString('filter_id_to', $app->getUserState('com_tienda.products.filter_id_to', ''));
        $app->setUserState('com_tienda.products.filter_id_to', $state['filter_id_to']);
        $state['filter_name']       = $app->input->getString('filter_name', $app->getUserState('com_tienda.products.filter_name', ''));
        $app->setUserState('com_tienda.products.filter_name', $state['filter_name']);
        $state['filter_enabled']    = $app->input->getString('filter_enabled', $app->getUserState('com_tienda.products.filter_enabled', '')); // TODO: Check input type, might be int
        $app->setUserState('com_tienda.products.filter_enabled', $state['filter_enabled']);
        $state['filter_quantity_from'] = $app->input->getString('filter_quantity_from', $app->getUserState('com_tienda.products.filter_quantity_from', '')); // TODO: Check input type, might be int
        $app->setUserState('com_tienda.products.filter_quantity_from', $state['filter_quantity_from']);
        $state['filter_quantity_to']    = $app->input->getString('filter_quantity_to', $app->getUserState('com_tienda.products.filter_quantity_to', '')); // TODO: Check input type, might be int
        $app->setUserState('com_tienda.products.filter_quantity_to', $state['filter_quantity_to']);
        $state['filter_category']       = $app->input->getString('filter_category', $app->getUserState('com_tienda.products.filter_category', ''));// TODO: Check input type, might be int
        $app->setUserState('com_tienda.products.filter_category', $state['filter_category']);
        $state['filter_sku']        = $app->input->getString('filter_sku', $app->getUserState('com_tienda.products.filter_sku', ''));
        $app->setUserState('com_tienda.products.filter_sku', $state['filter_sku']);
        $state['filter_price_from']     = $app->input->getString('filter_price_from', $app->getUserState('com_tienda.products.filter_price_from', '')); // TODO: Check input type, might be float/decimal
        $app->setUserState('com_tienda.products.filter_price_from', $state['filter_price_from']);
        $state['filter_price_to']       = $app->input->getString('filter_price_to', $app->getUserState('com_tienda.products.filter_price_to', '')); // TODO: Check input type, might be float/decimal
        $app->setUserState('com_tienda.products.filter_price_to', $state['filter_price_to']);
        $state['filter_taxclass']   = $app->input->getString('filter_taxclass', $app->getUserState('com_tienda.products.filter_taxclass', ''));// TODO: Check input type, might be int
        $app->setUserState('com_tienda.products.filter_taxclass', $state['filter_taxclass']);
        $state['filter_ships']   = $app->input->getString('filter_ships', $app->getUserState('com_tienda.products.filter_ships', ''));// TODO: Check input type, might be int
        $app->setUserState('com_tienda.products.filter_ships', $state['filter_ships']);

        // $state['filter_group']   = Tienda::getInstance()->get('default_user_group', '1'); // TODO: Replace Tienda::getInstance()
        $compParams = ComponentHelper::getParams('com_tienda');
        $state['filter_group']   = $compParams->get('default_user_group', '1');

        $state['order']     = $app->input->getCmd('filter_order', $app->getUserState('com_tienda.products.filter_order', 'tbl.ordering')); // Changed from $ns.'.filter_order'
        $app->setUserState('com_tienda.products.filter_order', $state['order']);

        // TODO: The following loop might not be needed if model state is set directly via accessors or if model pulls from app input/user state.
        // foreach (@$state as $key=>$value)
        // {
        //     if ($model) $model->setState( $key, $value );
        // }
        Factory::getApplication()->enqueueMessage('_setModelState partially updated. Full review of user state and model interaction needed.', 'notice');
        return $state;
    }

    public function edit($cachable = false, $urlparams = [])
    {
        // $view   = $this->getView('Product', 'html'); // Assuming ProductView, suffix removed
        // $model  = $this->getModel('Product'); // Assuming ProductModel
        // $view->set( 'hidemenu', false);
        // $view->assign( 'product_relations', $this->getRelationshipsHtml($view, $model->getId()) ); // This needs major refactor
        // $view->setLayout( 'form' );
        // $view->setTask(true);
        // parent::edit(); // BaseController doesn't have this. FormController does.
        Factory::getApplication()->enqueueMessage('Edit method needs complete refactoring for J5 FormController or custom implementation.', 'notice');
        $this->setRedirect(Route::_('index.php?option=com_tienda&view=products', false));
        // For now, redirect or display a message as the view/model layer is not ready
        // $this->display(); // This would be typical if the view was ready
    }

    public function save($key = null, $urlVar = null)
    {
        // TODO: Complete refactor of save method needed.
        // Major changes for $this->input, JTable, TiendaHelperProduct, JDispatcher, etc.
        // For now, just redirect to prevent errors.
        Factory::getApplication()->enqueueMessage('Save method needs complete refactoring.', 'notice');
        $this->setRedirect(Route::_('index.php?option=com_tienda&view=products', false));
    }

    // Add other methods from TiendaControllerProducts (e.g., jump, addimage, selectcategories, viewGallery, selected_switch, setquantities, savequantities, etc.)
    // For each method:
    // 1. Update method signature if needed.
    // 2. Replace JRequest with $this->input.
    // 3. Replace JFactory with Factory::.
    // 4. Replace JRoute with Route::.
    // 5. Replace JText with Text::.
    // 6. Comment out complex logic that depends on unrefactored models, tables, or helpers, adding a TODO and enqueueing a notice.
    // 7. Ensure it redirects or handles flow appropriately to avoid fatal errors.
    // Example for 'jump':
    public function jump()
    {
        // $model  = $this->getModel( $this->get('suffix') ); // Suffix removed, getModel('Products')
        // $row = $model->getTable();
        // $row->load( $model->getId() );
        // if (isset($row->checked_out) && !Table::isCheckedOut( Factory::getUser()->id, $row->checked_out) ) // JTable -> Table
        // {
        //     $row->checkin();
        // }
        // $task = $this->input->getCmd( "task" );
        // $redirect = "index.php?option=com_tienda&view=products";
        // // Tienda::load( "TiendaHelperProduct", 'helpers.product' ); // TODO: Replace helper loading
        // // $surrounding = TiendaHelperProduct::getSurrounding( $model->getId() );
        // $surrounding = ['prev' => null, 'next' => null]; // Placeholder
        // switch ($task)
        // {
        //     case "prev":
        //         if (!empty($surrounding['prev']))
        //         {
        //             $redirect .= "&task=view&id=".$surrounding['prev'];
        //         }
        //         break;
        //     case "next":
        //         if (!empty($surrounding['next']))
        //         {
        //             $redirect .= "&task=view&id=".$surrounding['next'];
        //         }
        //         break;
        // }
        // $this->setRedirect( Route::_( $redirect, false ), $this->message, $this->messagetype );
        Factory::getApplication()->enqueueMessage('Jump method needs complete refactoring for model, table, and helper interaction.', 'notice');
        $this->setRedirect(Route::_('index.php?option=com_tienda&view=products', false));
    }

    /**
     * Adds a thumbnail image to item
     * @return unknown_type
     */
    public function addimage( $fieldname = 'product_full_image_new', $num = 0, $path = 'products_images' )
    {
        // Tienda::load( 'TiendaImage', 'library.image' ); // TODO: Replace library loading
        // $upload = new TiendaImage();
        // // handle upload creates upload object properties
        // $upload->handleMultipleUpload( $fieldname, $num );
        // // then save image to appropriate folder
        // if ($path == 'products_images') { $path = Tienda::getPath( 'products_images' ); } // TODO: Replace Tienda::getPath
        // $upload->setDirectory( $path );

        // // Do the real upload!
        // $upload->upload();

        // Tienda::load( 'TiendaHelperImage', 'helpers.image' ); // TODO: Replace helper loading
        // $imgHelper = TiendaHelperBase::getInstance('Image', 'TiendaHelper'); // TODO: Replace helper loading
        // if (!$imgHelper->resizeImage( $upload, 'product'))
        // {
        //     Factory::getApplication()->enqueueMessage( $imgHelper->getError(), 'notice' );
        // }
        // return $upload;
        Factory::getApplication()->enqueueMessage('addimage method needs complete refactoring for library and helper loading.', 'notice');
        return null; // Placeholder
    }

    /**
     * Loads view for assigning product to categories
     *
     * @return unknown_type
     */
    public function selectcategories()
    {
        // $this->set('suffix', 'categories'); // Suffix removed
        // $state = parent::_setModelState(); // Assuming BaseController has this
        // $app = Factory::getApplication();
        // $model = $this->getModel( 'categories' ); // Assuming CategoriesModel
        // // $ns = $this->getNamespace(); // Old pattern

        // // $state['filter_parentid']    = $app->getUserStateFromRequest($ns.'parentid', 'filter_parentid', '', '');
        // $state['filter_parentid']   = $app->input->getString('filter_parentid', $app->getUserState('com_tienda.categories.filter_parentid', ''));
        // $app->setUserState('com_tienda.categories.filter_parentid', $state['filter_parentid']);
        // // $state['order']     = $app->getUserStateFromRequest($ns.'.filter_order', 'filter_order', 'tbl.lft', 'cmd');
        // $state['order']     = $app->input->getCmd('filter_order', $app->getUserState('com_tienda.categories.filter_order', 'tbl.lft'));
        // $app->setUserState('com_tienda.categories.filter_order', $state['order']);


        // foreach (@$state as $key=>$value)
        // {
        //     if ($model) $model->setState( $key, $value );
        // }

        // $id = $this->input->getInt('id', $this->input->getInt('id_post_alias', 0)); // Reading from input directly, assuming 'id' is the primary var
        // $row = $model->getTable( 'products' ); // This seems incorrect, should be Products table for product row
        // $row->load( $id );

        // $view   = $this->getView( 'products', 'html' ); // View name should be 'Categories' for categories list
        // $view->set( '_controller', 'products' );
        // $view->set( '_view', 'products' ); // This should also be 'categories'
        // $view->set( '_action', "index.php?option=com_tienda&view=products&task=selectcategories&tmpl=component&id=".$id ); // $model->getId() might be product id
        // $view->setModel( $model, true );
        // $view->assign( 'state', $model->getState() );
        // $view->assign( 'row', $row ); // This is product row, view is for categories
        // $view->setLayout( 'selectcategories' );
        // $view->setTask(true);
        // $view->display();
        Factory::getApplication()->enqueueMessage('selectcategories method needs complete refactoring for model, view, and user state.', 'notice');
        $this->setRedirect(Route::_('index.php?option=com_tienda&view=products', false));
    }

    /**
     * Loads view to show the gallery
     *
     * @return unknown_type
     */
    public function viewGallery()
    {
        // $id = $this->input->getInt('id', $this->input->getInt('id_post_alias', 0));
        // $row = Table::getInstance('Products', 'TiendaTable'); // TODO: Ensure TiendaTableProducts is J5 compatible
        // $row->load( $id );

        // // Tienda::load( "TiendaHelperProduct", 'helpers.product' ); // TODO: Replace helper
        // // $helper = TiendaHelperBase::getInstance('Product', 'TiendaHelper');
        // // $gallery_path = $helper->getGalleryPath($row->product_id);
        // // $gallery_url = $helper->getGalleryUrl($row->product_id);
        // // $images = $helper->getGalleryImages($gallery_path);
        // $gallery_path = ''; $gallery_url = ''; $images = []; // Placeholders

        // $view   = $this->getView( 'products', 'html' ); // Assuming ProductsView
        // $model = $this->getModel('Products'); // Assuming ProductsModel

        // $view->setModel($model, true);
        // $view->set( '_controller', 'products' );
        // $view->set( '_view', 'products' );
        // $view->set( '_action', "index.php?option=com_tienda&view=products&task=viewGallery&tmpl=component&id=".$id);
        // $view->assign( 'row', $row );
        // $view->assign( 'images', $images );
        // $view->assign( 'url', $gallery_url );
        // $view->setLayout( 'gallery' );
        // $view->setTask(true);
        // $view->display();
        Factory::getApplication()->enqueueMessage('viewGallery method needs complete refactoring for helpers, table, model, and view.', 'notice');
        $this->setRedirect(Route::_('index.php?option=com_tienda&view=products', false));
    }

    /**
     *
     * @return unknown_type
     */
    public function selected_switch()
    {
        // $app = Factory::getApplication();
        // $error = false;
        // $this->messagetype  = ''; // Use $app->enqueueMessage type
        // $this->message      = ''; // Use $app->enqueueMessage message

        // $model = $this->getModel('Products'); // Assuming ProductsModel
        // $row = $model->getTable(); // Which table? ProductCategories?

        // $id = $this->input->getInt('id', $this->input->getInt('id_post_alias', 0));
        // $cids = $this->input->get('cid', array (0), 'array'); // Filter input
        // $task = $this->input->getCmd( 'task' );
        // $vals = explode('_', $task);

        // $field = $vals[0]; // Unused
        // $action = $vals[1];

        // $enable = null; // Initialize
        // $switch = 0; // Initialize

        // switch (strtolower($action))
        // {
        //     case "switch":
        //         $switch = '1';
        //         break;
        //     case "disable":
        //         $enable = '0';
        //         $switch = '0';
        //         break;
        //     case "enable":
        //         $enable = '1';
        //         $switch = '0';
        //         break;
        //     default:
        //         $app->enqueueMessage(Text::_('COM_TIENDA_INVALID_TASK'), 'notice');
        //         $this->setRedirect( Route::_( "index.php?option=com_tienda&view=products&task=selectcategories&tmpl=component&id=".$id, false ) );
        //         return;
        // }

        // $keynames = array();
        // foreach (@$cids as $cid_val) // Renamed $cid to $cid_val to avoid conflict
        // {
        //     $table = Table::getInstance('ProductCategories', 'TiendaTable'); // TODO: Ensure TiendaTableProductCategories is J5 compatible
        //     $keynames["product_id"] = $id;
        //     $keynames["category_id"] = $cid_val;
        //     $table->load( $keynames );
        //     if ($switch)
        //     {
        //         if (isset($table->product_id))
        //         {
        //             if (!$table->delete())
        //             {
        //                 $app->enqueueMessage($cid_val.': '.$table->getError(), 'notice');
        //                 $error = true;
        //             }
        //         }
        //         else
        //         {
        //             $table->product_id = $id;
        //             $table->category_id = $cid_val;
        //             if (!$table->save())
        //             {
        //                 $app->enqueueMessage($cid_val.': '.$table->getError(), 'notice');
        //                 $error = true;
        //             }
        //         }
        //     }
        //     else
        //     {
        //         switch ($enable)
        //         {
        //             case "1":
        //                 $table->product_id = $id;
        //                 $table->category_id = $cid_val;
        //                 if (!$table->save())
        //                 {
        //                     $app->enqueueMessage($cid_val.': '.$table->getError(), 'notice');
        //                     $error = true;
        //                 }
        //                 break;
        //             case "0":
        //             default:
        //                 if (isset($table->product_id)) { // Only delete if it exists
        //                     if (!$table->delete())
        //                     {
        //                         $app->enqueueMessage($cid_val.': '.$table->getError(), 'notice');
        //                         $error = true;
        //                     }
        //                 }
        //                 break;
        //         }
        //     }
        // }

        // if ($model) $model->clearCache(); // TODO: Check model caching in J5

        // if ($error)
        // {
        //     $app->enqueueMessage(Text::_('COM_TIENDA_ERROR') . ": " . implode('<br/>', $app->getMessageQueue()), 'error'); // Consolidate messages
        // }
        // else
        // {
        //     // $app->enqueueMessage(Text::_('COM_TIENDA_SUCCESS'), 'message'); // Or specific success message
        // }

        // $returnUrl = $this->input->getBase64('return'); // TODO: Check if this input var exists
        // $redirect = $returnUrl ? base64_decode( $returnUrl ) : "index.php?option=com_tienda&view=products&task=selectcategories&tmpl=component&id=".$id;
        // $this->setRedirect( Route::_( $redirect, false ) );
        Factory::getApplication()->enqueueMessage('selected_switch method needs complete refactoring for table interaction and error handling.', 'notice');
        $this->setRedirect(Route::_('index.php?option=com_tienda&view=products', false));
    }

    /*
     * Creates a popup where quantities can be set
     */
    public function setquantities()
    {
        // $app = Factory::getApplication();
        // // $this->set('suffix', 'productquantities'); // suffix removed
        // $model = $this->getModel( 'Productquantities' ); // Assuming ProductquantitiesModel
        // $productId = $this->input->getInt('id'); // Assuming id is product id
        // $model->setState('filter_productid', $productId);
        // $model->setState('filter_vendorid', '0'); // Example, may need config
        // // $items = $model->getAll(); // This method might not exist or work the same in J5 model

        // $productTable = Table::getInstance('Products', 'TiendaTable'); // TODO: Ensure TiendaTableProducts is J5 compatible
        // $productTable->load($productId);

        // // Tienda::load( "TiendaHelperProduct", 'helpers.product' ); // TODO: Replace helper
        // // TiendaHelperProduct::doProductQuantitiesReconciliation( $productTable->product_id ); // Static call to helper

        // // $state = parent::_setModelState(); // Call to parent
        // // $ns = $this->getNamespace(); // Old pattern

        // // foreach (@$state as $key=>$value)
        // // {
        // //     if($model) $model->setState( $key, $value );
        // // }

        // $view   = $this->getView( 'products', 'html' ); // Should be Productquantities view?
        // $view->set( '_controller', 'products' );
        // $view->set( '_view', 'products' ); // Should be Productquantities view?
        // $view->set( '_action', "index.php?option=com_tienda&view=products&task=setquantities&id={$productId}&tmpl=component" );
        // $view->setModel( $model, true );
        // $view->assign( 'state', $model->getState() );
        // $view->assign( 'row', $productTable ); // This is product row
        // $view->assign( 'items', $model->getList() ); // List of quantities
        // $view->setLayout( 'setquantities' );
        // $view->setTask(true);
        // $view->display();
        Factory::getApplication()->enqueueMessage('setquantities method needs complete refactoring for model, table, helper, and view interaction.', 'notice');
        $this->setRedirect(Route::_('index.php?option=com_tienda&view=products', false));
    }

    /**
     * Saves the quantities for all product attributes in list
     *
     * @return unknown_type
     */
    public function savequantities()
    {
        // $app = Factory::getApplication();
        // $error = false;
        // $productId = 0; // Initialize

        // $model = $this->getModel('Productquantities'); // Assuming ProductquantitiesModel
        // $row = $model->getTable(); // Productquantities table

        // $cids = $this->input->get('cid', array(0), 'array'); // Filter input
        // $quantities = $this->input->get('quantity', array(0), 'array'); // Filter input

        // foreach (@$cids as $cid_val) // Renamed $cid to $cid_val
        // {
        //     $row->load( $cid_val );
        //     if (!$productId && isset($row->product_id)) $productId = $row->product_id; // Capture product_id from the first valid row
        //     $row->quantity = $quantities[$cid_val];

        //     if (!$row->save())
        //     {
        //         $app->enqueueMessage($row->getError(), 'error');
        //         $error = true;
        //     }
        // }

        // $productModel = $this->getModel('Products'); // Assuming ProductsModel
        // if($productModel) $productModel->clearCache(); // TODO: Check J5 caching

        // if ($error)
        // {
        //     // Error messages already enqueued
        // }
        // else
        // {
        //      $app->enqueueMessage(Text::_('COM_TIENDA_SAVED'), 'message');
        // }

        // $redirect = "index.php?option=com_tienda&view=products&task=setquantities&id={$productId}&tmpl=component";
        // $this->setRedirect( Route::_( $redirect, false ) );
        Factory::getApplication()->enqueueMessage('savequantities method needs complete refactoring for model and table interaction.', 'notice');
        $this->setRedirect(Route::_('index.php?option=com_tienda&view=products', false));
    }

    /*
     * Creates a popup where prices can be edited & created
     */
    public function setprices()
    {
        // $app = Factory::getApplication();
        // // $this->set('suffix', 'productprices'); // suffix removed
        // $model = $this->getModel( 'Productprices' ); // Assuming ProductpricesModel
        // $productId = $this->input->getInt('id');
        // // $state = parent::_setModelState(); // Call to parent
        // // $ns = $this->getNamespace(); // Old pattern
        // // foreach (@$state as $key=>$value)
        // // {
        // //    if($model) $model->setState( $key, $value );
        // // }

        // $productTable = Table::getInstance('Products', 'TiendaTable'); // TODO: Ensure TiendaTableProducts is J5 compatible
        // $productTable->load($productId);

        // $model->setState('filter_id', $productId); // This seems to be filtering prices for this product_id

        // $view   = $this->getView( 'productprices', 'html' ); // Assuming ProductpricesView
        // $view->set( '_controller', 'products' );
        // $view->set( '_view', 'products' ); // Should be 'productprices'
        // $view->set( '_action', "index.php?option=com_tienda&view=products&task=setprices&id={$productId}&tmpl=component" );
        // $view->setModel( $model, true );
        // $view->assign( 'state', $model->getState() );
        // $view->assign( 'row', $productTable ); // This is product row
        // $view->setLayout( 'default' ); // View's default layout
        // $view->setTask(true);
        // $view->display();
        Factory::getApplication()->enqueueMessage('setprices method needs complete refactoring for model, table, and view interaction.', 'notice');
        $this->setRedirect(Route::_('index.php?option=com_tienda&view=products', false));
    }

    /**
     * Creates a price and redirects
     *
     * @return unknown_type
     */
    public function createprice()
    {
        // $app = Factory::getApplication();
        // // $this->set('suffix', 'productprices'); // suffix removed
        // $model  = $this->getModel( 'Productprices' ); // Assuming ProductpricesModel

        // $row = $model->getTable(); // Productprices table
        // $row->product_id = $this->input->getInt( 'id' );
        // $row->product_price = $this->input->getString( 'createprice_price' ); // Consider filtering as float
        // $row->product_price_startdate = $this->input->getString( 'createprice_date_start' ); // Consider date filtering
        // $row->product_price_enddate = $this->input->getString( 'createprice_date_end' ); // Consider date filtering
        // $row->price_quantity_start = $this->input->getInt( 'createprice_quantity_start' );
        // $row->price_quantity_end = $this->input->getInt( 'createprice_quantity_end' );
        // $row->group_id = $this->input->getInt( 'createprice_group_id' );

        // if ( $row->save() )
        // {
        //     if($model) $model->clearCache(); // TODO: Check J5 caching

        //     // $dispatcher = Factory::getApplication()->getDispatcher(); // TODO: Use injected dispatcher
        //     // $dispatcher->trigger( 'onAfterSaveProductprices', array( $row ) ); // Event name might change
        //     $app->enqueueMessage(Text::_('COM_TIENDA_SAVED'), 'message');
        // }
        // else
        // {
        //     $app->enqueueMessage(Text::_('COM_TIENDA_SAVE_FAILED')." - ".$row->getError(), 'error');
        // }

        // $redirect = "index.php?option=com_tienda&view=products&task=setprices&id={$row->product_id}&tmpl=component";
        // $this->setRedirect( Route::_( $redirect, false ) );
        Factory::getApplication()->enqueueMessage('createprice method needs complete refactoring for model and table interaction.', 'notice');
        $this->setRedirect(Route::_('index.php?option=com_tienda&view=products', false));
    }

    /**
     * Saves the properties for all prices in list
     *
     * @return unknown_type
     */
    public function saveprices()
    {
        // $app = Factory::getApplication();
        // $error = false;
        // $productId = 0;

        // $model = $this->getModel('Productprices'); // Assuming ProductpricesModel
        // $row = $model->getTable(); // Productprices table

        // $cids = $this->input->get('cid', array(0), 'array'); // Filter input
        // $prices = $this->input->get('price', array(0), 'array'); // Filter input
        // $date_starts = $this->input->get('date_start', array(0), 'array'); // Filter input
        // $date_ends = $this->input->get('date_end', array(0), 'array'); // Filter input
        // $quantity_starts = $this->input->get('quantity_start', array(0), 'array'); // Filter input
        // $quantity_ends = $this->input->get('quantity_end', array(0), 'array'); // Filter input
        // $user_groups = $this->input->get('price_group_id', array(0), 'array'); // Filter input

        // foreach (@$cids as $cid_val) // Renamed $cid to $cid_val
        // {
        //     $row->load( $cid_val );
        //     if (!$productId && isset($row->product_id)) $productId = $row->product_id;

        //     $row->product_price = $prices[$cid_val]; // Consider float filtering
        //     $row->product_price_startdate = $date_starts[$cid_val]; // Consider date filtering
        //     $row->product_price_enddate = $date_ends[$cid_val]; // Consider date filtering
        //     $row->price_quantity_start = $quantity_starts[$cid_val];
        //     $row->price_quantity_end = $quantity_ends[$cid_val];
        //     $row->group_id = $user_groups[$cid_val];

        //     if (!$row->save())
        //     {
        //         $app->enqueueMessage($row->getError(), 'error');
        //         $error = true;
        //     }
        // }

        // $productModel = $this->getModel('Products'); // Assuming ProductsModel
        // if($productModel) $productModel->clearCache(); // TODO: Check J5 caching

        // if ($error)
        // {
        //     // Errors already enqueued
        // }
        // else
        // {
        //     $app->enqueueMessage(Text::_('COM_TIENDA_SAVED'), 'message');
        // }

        // $redirect = "index.php?option=com_tienda&view=products&task=setprices&id={$productId}&tmpl=component";
        // $this->setRedirect( Route::_( $redirect, false ) );
        Factory::getApplication()->enqueueMessage('saveprices method needs complete refactoring for model and table interaction.', 'notice');
        $this->setRedirect(Route::_('index.php?option=com_tienda&view=products', false));
    }

    /*
     * Creates a popup where issues can be edited & created
     */
    public function setissues()
    {
        // $app = Factory::getApplication();
        // // $this->set('suffix', 'productissues'); // suffix removed
        // $ns = 'com_tienda.productissues'; // Example namespace for user state

        // $app->setUserState( $ns.'.filter_order', $app->input->getCmd('filter_order', $app->getUserState($ns.'.filter_order', 'tbl.publishing_date')) );
        // $app->setUserState( $ns.'.filter_direction', $app->input->getWord('filter_direction', $app->getUserState($ns.'.filter_direction', 'DESC')) );

        // $state = parent::_setModelState(); // Call to parent
        // $model = $this->getModel( 'Productissues' ); // Assuming ProductissuesModel
        // // foreach (@$state as $key=>$value)
        // // {
        // //    if($model) $model->setState( $key, $value );
        // // }
        // $productId = $this->input->getInt('id');
        // $productTable = Table::getInstance('Products', 'TiendaTable'); // TODO: Ensure TiendaTableProducts is J5 compatible
        // $productTable->load( $productId );
        // $model->setState('filter_product_id', $productId);

        // $view   = $this->getView( 'productissues', 'html' ); // Assuming ProductissuesView
        // $view->set( '_controller', 'products' );
        // $view->set( '_view', 'products' ); // Should be 'productissues'
        // $view->set( '_action', "index.php?option=com_tienda&view=products&task=setissues&id={$productId}&tmpl=component" );
        // $view->setModel( $model, true );
        // $view->assign( 'state', $model->getState() );
        // $view->assign( 'row', $productTable ); // This is product row
        // $view->setLayout( 'default' );
        // $view->setTask(true);
        // $view->display();
        Factory::getApplication()->enqueueMessage('setissues method needs complete refactoring for model, table, and view interaction.', 'notice');
        $this->setRedirect(Route::_('index.php?option=com_tienda&view=products', false));
    }

    /**
     * Creates an issue and redirects
     *
     * @return unknown_type
     */
    public function createissue()
    {
        // $app = Factory::getApplication();
        // // $this->set('suffix', 'productissues'); // suffix removed
        // $model  = $this->getModel( 'Productissues' ); // Assuming ProductissuesModel

        // $row = $model->getTable(); // Productissues table
        // $row->product_id = $this->input->getInt( 'id' );
        // $row->issue_num = $this->input->getString( 'issue_num' );
        // $row->volume_num = $this->input->getString( 'volume_num' );
        // $row->publishing_date = $this->input->getString( 'publishing_date'  ); // Consider date filtering

        // if ( $row->save() )
        // {
        //     if ($model) $model->clearCache(); // TODO: Check J5 caching

        //     // $dispatcher = Factory::getApplication()->getDispatcher(); // TODO: Use injected dispatcher
        //     // $dispatcher->trigger( 'onAfterSaveProductissues', array( $row ) ); // Event name might change
        //      $app->enqueueMessage(Text::_('COM_TIENDA_SAVED'), 'message');
        // }
        // else
        // {
        //     $app->enqueueMessage(Text::_('COM_TIENDA_SAVE_FAILED')." - ".$row->getError(), 'error');
        // }

        // $redirect = "index.php?option=com_tienda&view=products&task=setissues&id={$row->product_id}&tmpl=component";
        // $this->setRedirect( Route::_( $redirect, false ) );
        Factory::getApplication()->enqueueMessage('createissue method needs complete refactoring for model and table interaction.', 'notice');
        $this->setRedirect(Route::_('index.php?option=com_tienda&view=products', false));
    }

    /**
     * Saves the properties for all issues in list
     *
     * @return unknown_type
     */
    public function saveissues()
    {
        // $app = Factory::getApplication();
        // $error = false;
        // $productId = 0;

        // $model = $this->getModel('Productissues'); // Assuming ProductissuesModel
        // $row = $model->getTable(); // Productissues table

        // $cids = $this->input->get('cid', array(0), 'array'); // Filter input
        // $issues_num = $this->input->get( 'issues_num', array(0), 'array' ); // Filter input
        // $volumes_num = $this->input->get( 'volumes_num', array(0), 'array' ); // Filter input
        // $publishing_dates = $this->input->get( 'publishing_dates', array(0), 'array' ); // Filter input

        // foreach (@$cids as $cid_val) // Renamed $cid to $cid_val
        // {
        //     $row->load( $cid_val );
        //     if (!$productId && isset($row->product_id)) $productId = $row->product_id;

        //     $row->issue_num = $issues_num[$cid_val];
        //     $row->volume_num = $volumes_num[$cid_val];
        //     $row->publishing_date = $publishing_dates[$cid_val]; // Consider date filtering

        //     if (!$row->save())
        //     {
        //         $app->enqueueMessage($row->getError(),'error');
        //         $error = true;
        //     }
        // }

        // $productModel = $this->getModel('Products'); // Assuming ProductsModel
        // if($productModel) $productModel->clearCache(); // TODO: Check J5 caching

        // if ($error)
        // {
        //     // Errors already enqueued
        // }
        // else
        // {
        //     $app->enqueueMessage(Text::_('COM_TIENDA_SAVED'), 'message');
        // }

        // $redirect = "index.php?option=com_tienda&view=products&task=setissues&id={$productId}&tmpl=component";
        // $this->setRedirect( Route::_( $redirect, false ) );
        Factory::getApplication()->enqueueMessage('saveissues method needs complete refactoring for model and table interaction.', 'notice');
        $this->setRedirect(Route::_('index.php?option=com_tienda&view=products', false));
    }

    /**
     * Loads view for assigning product attributes
     *
     * @return unknown_type
     */
    public function setattributes()
    {
        // $app = Factory::getApplication();
        // // $this->set('suffix', 'productattributes'); // suffix removed
        // $model = $this->getModel( 'Productattributes' ); // Assuming ProductattributesModel
        // $productId = $this->input->getInt('id');

        // // $state = parent::_setModelState(); // Call to parent
        // // $ns = $this->getNamespace(); // Old pattern

        // $state['filter_product'] = $productId;
        // // $state['order'] = $app->getUserStateFromRequest($ns.'.filter_order', 'filter_order', 'tbl.ordering', 'cmd');
        // $state['order'] = $app->input->getCmd('filter_order', $app->getUserState('com_tienda.productattributes.filter_order', 'tbl.ordering'));
        // $app->setUserState('com_tienda.productattributes.filter_order', $state['order']);


        // // foreach (@$state as $key=>$value)
        // // {
        // //     if($model) $model->setState( $key, $value );
        // // }
        // if($model) { // Ensure model exists before setting state on it
        //    $model->setState('filter_product', $productId);
        //    $model->setState('order', $state['order']);
        // }


        // $productTable = Table::getInstance('Products', 'TiendaTable'); // TODO: Ensure TiendaTableProducts is J5 compatible
        // $productTable->load($productId);

        // $view   = $this->getView( 'productattributes', 'html' ); // Assuming ProductattributesView
        // $view->set( '_controller', 'products' );
        // $view->set( '_view', 'products' ); // Should be 'productattributes'
        // $view->set( '_action', "index.php?option=com_tienda&view=products&task=setattributes&tmpl=component&id=".$productId );
        // $view->setModel( $model, true );
        // $view->assign( 'state', $model->getState() );
        // $view->assign( 'row', $productTable ); // This is product row
        // $view->setLayout( 'default' );
        // $view->setTask(true);
        // $view->display();
        Factory::getApplication()->enqueueMessage('setattributes method needs complete refactoring for model, table, and view interaction.', 'notice');
        $this->setRedirect(Route::_('index.php?option=com_tienda&view=products', false));
    }

    /**
     * Creates an attribute and redirects
     *
     * @return unknown_type
     */
    public function createattribute()
    {
        // $app = Factory::getApplication();
        // // $this->set('suffix', 'productattributes'); // suffix removed
        // $model  = $this->getModel( 'Productattributes' ); // Assuming ProductattributesModel

        // $row = $model->getTable(); // Productattributes table
        // $row->product_id = $this->input->getInt( 'id' );
        // $row->productattribute_name = $this->input->getString( 'createproductattribute_name' );
        // $row->ordering = '99'; // Default ordering

        // if ( $row->save() )
        // {
        //     if($model) $model->clearCache(); // TODO: Check J5 caching

        //     // $dispatcher = Factory::getApplication()->getDispatcher(); // TODO: Use injected dispatcher
        //     // $dispatcher->trigger( 'onAfterSaveProductattributes', array( $row ) ); // Event name might change
        //     $app->enqueueMessage(Text::_('COM_TIENDA_SAVED'), 'message');
        // }
        // else
        // {
        //     $app->enqueueMessage(Text::_('COM_TIENDA_SAVE_FAILED')." - ".$row->getError(), 'error');
        // }

        // $redirect = "index.php?option=com_tienda&view=products&task=setattributes&id={$row->product_id}&tmpl=component";
        // $this->setRedirect( Route::_( $redirect, false ) );
        Factory::getApplication()->enqueueMessage('createattribute method needs complete refactoring for model and table interaction.', 'notice');
        $this->setRedirect(Route::_('index.php?option=com_tienda&view=products', false));
    }

    /**
     * Saves the properties for all attributes in list
     *
     * @return unknown_type
     */
    public function saveattributes()
    {
        // $app = Factory::getApplication();
        // $error = false;
        // $productId = 0;

        // $model = $this->getModel('Productattributes'); // Assuming ProductattributesModel
        // $row = $model->getTable(); // Productattributes table

        // $cids = $this->input->get('cid', array(0), 'array'); // Filter input
        // $name = $this->input->get('name', array(0), 'array'); // Filter input
        // $parent = $this->input->get('attribute_parent', array(0), 'array'); // Filter input
        // $ordering = $this->input->get('ordering', array(0), 'array'); // Filter input

        // foreach (@$cids as $cid_val) // Renamed $cid to $cid_val
        // {
        //     $row->load( $cid_val );
        //     if (!$productId && isset($row->product_id)) $productId = $row->product_id;

        //     $row->productattribute_name = $name[$cid_val];
        //     $row->parent_productattributeoption_id = $parent[$cid_val];
        //     $row->ordering = $ordering[$cid_val];

        //     if (!$row->check() || !$row->store()) // store() is JTable method, save() is more common in J5
        //     {
        //         $app->enqueueMessage($row->getError(), 'error');
        //         $error = true;
        //     }
        // }
        // if (method_exists($row, 'reorder')) { // Check if reorder method exists
        //     $row->reorder();
        // }

        // $productModel = $this->getModel('Products'); // Assuming ProductsModel
        // if($productModel) $productModel->clearCache(); // TODO: Check J5 caching

        // if ($error)
        // {
        //     // Error messages already enqueued
        // }
        // else
        // {
        //     $app->enqueueMessage(Text::_('COM_TIENDA_SAVED'), 'message');
        // }

        // $redirect = "index.php?option=com_tienda&view=products&task=setattributes&id={$productId}&tmpl=component";
        // $this->setRedirect( Route::_( $redirect, false ) );
        Factory::getApplication()->enqueueMessage('saveattributes method needs complete refactoring for model and table interaction.', 'notice');
        $this->setRedirect(Route::_('index.php?option=com_tienda&view=products', false));
    }

    /**
     * Loads view for assigning product attribute options
     *
     * @return unknown_type
     */
    public function setattributeoptions()
    {
        // $app = Factory::getApplication();
        // // $this->set('suffix', 'productattributeoptions'); // suffix removed
        // $model = $this->getModel( 'Productattributeoptions' ); // Assuming ProductattributeoptionsModel
        // $attributeId = $this->input->getInt('id');

        // // $state = parent::_setModelState(); // Call to parent
        // // $ns = $this->getNamespace(); // Old pattern

        // $state['filter_attribute']   = $attributeId;
        // // $state['order'] = $app->getUserStateFromRequest($ns.'.filter_order', 'filter_order', 'tbl.ordering', 'cmd');
        // $state['order'] = $app->input->getCmd('filter_order', $app->getUserState('com_tienda.productattributeoptions.filter_order', 'tbl.ordering'));
        // $app->setUserState('com_tienda.productattributeoptions.filter_order', $state['order']);

        // // foreach (@$state as $key=>$value)
        // // {
        // //     if($model) $model->setState( $key, $value );
        // // }
        // if($model) {
        //    $model->setState('filter_attribute', $attributeId);
        //    $model->setState('order', $state['order']);
        // }


        // $attributeTable = Table::getInstance('ProductAttributes', 'TiendaTable'); // TODO: Ensure TiendaTableProductAttributes is J5
        // $attributeTable->load($attributeId);

        // $view   = $this->getView( 'productattributeoptions', 'html' ); // Assuming ProductattributeoptionsView
        // $view->set( '_controller', 'products' );
        // $view->set( '_view', 'products' ); // Should be 'productattributeoptions'
        // $view->set( '_action', "index.php?option=com_tienda&view=products&task=setattributeoptions&tmpl=component&id=".$attributeId );
        // $view->setModel( $model, true );
        // $view->assign( 'state', $model->getState() );
        // $view->assign( 'row', $attributeTable ); // This is attribute row
        // $view->setLayout( 'default' );
        // $view->setTask(true);
        // $view->display();
        Factory::getApplication()->enqueueMessage('setattributeoptions method needs complete refactoring for model, table, and view interaction.', 'notice');
        $this->setRedirect(Route::_('index.php?option=com_tienda&view=products', false));
    }

    /**
     * Loads view for assigning product attribute option values
     *
     * @return unknown_type
     */
    public function setattributeoptionvalues()
    {
        // $app = Factory::getApplication();
        // // $this->set('suffix', 'productattributeoptionvalues'); // suffix removed
        // $model = $this->getModel( 'Productattributeoptionvalues' ); // Assuming ProductattributeoptionvaluesModel
        // $optionId = $this->input->getInt('id');

        // // $state = parent::_setModelState(); // Call to parent
        // // $ns = $this->getNamespace(); // Old pattern

        // $state['filter_option']   = $optionId;

        // // foreach (@$state as $key=>$value)
        // // {
        // //    if($model) $model->setState( $key, $value );
        // // }
        // if($model) $model->setState('filter_option', $optionId);

        // $optionTable = Table::getInstance('ProductAttributeOptions', 'TiendaTable'); // TODO: Ensure TiendaTableProductAttributeOptions is J5
        // $optionTable->load($optionId);

        // $view   = $this->getView( 'productattributeoptionvalues', 'html' ); // Assuming ProductattributeoptionvaluesView
        // $view->set( '_controller', 'products' );
        // $view->set( '_view', 'products' ); // Should be 'productattributeoptionvalues'
        // $view->set( '_action', "index.php?option=com_tienda&view=products&task=setattributeoptionvalues&tmpl=component&id=".$optionId );
        // $view->setModel( $model, true );
        // $view->assign( 'state', $model->getState() );
        // $view->assign( 'row', $optionTable ); // This is option row
        // $view->setLayout( 'default' );
        // $view->setTask(true);
        // $view->display();
        Factory::getApplication()->enqueueMessage('setattributeoptionvalues method needs complete refactoring for model, table, and view interaction.', 'notice');
        $this->setRedirect(Route::_('index.php?option=com_tienda&view=products', false));
    }

    /**
     * Creates an option and redirects
     *
     * @return unknown_type
     */
    public function createattributeoption()
    {
        // $app = Factory::getApplication();
        // // $this->set('suffix', 'productattributeoptions'); // suffix removed
        // $model  = $this->getModel( 'Productattributeoptions' ); // Assuming ProductattributeoptionsModel

        // $row = $model->getTable(); // Productattributeoptions table
        // $row->productattribute_id = $this->input->getInt( 'id' );
        // $row->productattributeoption_name = $this->input->getString( 'createproductattributeoption_name' );
        // $row->productattributeoption_price = $this->input->getString( 'createproductattributeoption_price' ); // Consider float
        // $row->productattributeoption_code = $this->input->getString( 'createproductattributeoption_code' );
        // $row->productattributeoption_prefix = $this->input->getString( 'createproductattributeoption_prefix' );
        // $row->productattributeoption_weight = $this->input->getString( 'createproductattributeoption_weight' ); // Consider float
        // $row->productattributeoption_prefix_weight = $this->input->getString( 'createproductattributeoption_prefix_weight' );
        // $row->is_blank = $this->input->getString( 'createproductattributeoption_blank' ); // Consider int/bool
        // $row->ordering = '99';

        // if ( $row->save() )
        // {
        //     if($model) $model->clearCache(); // TODO: J5 Caching

        //     // $dispatcher = Factory::getApplication()->getDispatcher(); // TODO: Use injected dispatcher
        //     // $dispatcher->trigger( 'onAfterSaveProductattributeoptions', array( $row ) ); // Event name
        //      $app->enqueueMessage(Text::_('COM_TIENDA_SAVED'), 'message');
        // }
        // else
        // {
        //     $app->enqueueMessage(Text::_('COM_TIENDA_SAVE_FAILED')." - ".$row->getError(), 'error');
        // }

        // $redirect = "index.php?option=com_tienda&view=products&task=setattributeoptions&id={$row->productattribute_id}&tmpl=component";
        // $this->setRedirect( Route::_( $redirect, false ) );
        Factory::getApplication()->enqueueMessage('createattributeoption method needs complete refactoring for model and table interaction.', 'notice');
        $this->setRedirect(Route::_('index.php?option=com_tienda&view=products', false));
    }

    /**
     * Creates an option value and redirects
     *
     * @return unknown_type
     */
    public function createattributeoptionvalue()
    {
        // $app = Factory::getApplication();
        // // $this->set('suffix', 'productattributeoptionvalues'); // suffix removed
        // $model  = $this->getModel( 'Productattributeoptionvalues' ); // Assuming ProductattributeoptionvaluesModel

        // $row = $model->getTable(); // Productattributeoptionvalues table
        // $row->productattributeoption_id = $this->input->getInt( 'id' );
        // $row->productattributeoptionvalue_field = $this->input->getString( 'createproductattributeoptionvalue_field' );
        // $row->productattributeoptionvalue_operator = $this->input->getString( 'createproductattributeoptionvalue_operator' );
        // $row->productattributeoptionvalue_value = $this->input->getString( 'createproductattributeoptionvalue_value' );

        // if ( $row->save() )
        // {
        //     if($model) $model->clearCache(); // TODO: J5 Caching

        //     // $dispatcher = Factory::getApplication()->getDispatcher(); // TODO: Use injected dispatcher
        //     // $dispatcher->trigger( 'onAfterSaveProductattributeoptionvalues', array( $row ) ); // Event name
        //     $app->enqueueMessage(Text::_('COM_TIENDA_SAVED'), 'message');
        // }
        // else
        // {
        //     $app->enqueueMessage(Text::_('COM_TIENDA_SAVE_FAILED')." - ".$row->getError(), 'error');
        // }

        // $redirect = "index.php?option=com_tienda&view=products&task=setattributeoptionvalues&id={$row->productattributeoption_id}&tmpl=component";
        // $this->setRedirect( Route::_( $redirect, false ) );
        Factory::getApplication()->enqueueMessage('createattributeoptionvalue method needs complete refactoring for model and table interaction.', 'notice');
        $this->setRedirect(Route::_('index.php?option=com_tienda&view=products', false));
    }

    /**
     * Saves the properties for all attribute options in list
     *
     * @return unknown_type
     */
    public function saveattributeoptions()
    {
        // $app = Factory::getApplication();
        // $error = false;
        // $attributeId = 0;

        // $model = $this->getModel('Productattributeoptions'); // Assuming ProductattributeoptionsModel
        // $row = $model->getTable(); // Productattributeoptions table

        // $cids = $this->input->get('cid', array(0), 'array'); // Filter input
        // $name = $this->input->get('name', array(0), 'array'); // Filter input
        // $prefix = $this->input->get('prefix', array(0), 'array'); // Filter input
        // $price = $this->input->get('price', array(0), 'array'); // Filter input
        // $prefix_weight = $this->input->get('prefix_weight', array(0), 'array'); // Filter input
        // $weight = $this->input->get('weight', array(0), 'array'); // Filter input
        // $code = $this->input->get('code', array(0), 'array'); // Filter input
        // $parent = $this->input->get('attribute_parent', array(0), 'array'); // Filter input - this seems wrong for options, parent is for attributes
        // $ordering = $this->input->get('ordering', array(0), 'array'); // Filter input
        // $blank = $this->input->get( 'blank', array( 0 ), 'array' ); // Filter input

        // foreach (@$cids as $cid_val) // Renamed $cid to $cid_val
        // {
        //     $row->load( $cid_val );
        //     if (!$attributeId && isset($row->productattribute_id)) $attributeId = $row->productattribute_id;

        //     $row->productattributeoption_name = $name[$cid_val];
        //     $row->productattributeoption_prefix = $prefix[$cid_val];
        //     $row->productattributeoption_price = $price[$cid_val]; // Consider float
        //     $row->productattributeoption_prefix_weight = $prefix_weight[$cid_val];
        //     $row->productattributeoption_weight = $weight[$cid_val]; // Consider float
        //     $row->productattributeoption_code = @$code[$cid_val];
        //     // $row->parent_productattributeoption_id = $parent[$cid_val]; // This seems incorrect for options
        //     $row->ordering = $ordering[$cid_val];
        //     $row->is_blank = $blank[$cid_val]; // Consider int/bool

        //     if (!$row->check() || !$row->store()) // store() is JTable method, save() is more common in J5
        //     {
        //         $app->enqueueMessage($row->getError(), 'error');
        //         $error = true;
        //     }
        // }
        // if(method_exists($row, 'reorder')) $row->reorder(); // Check if reorder exists

        // $productModel = $this->getModel('Products'); // Assuming ProductsModel for general product cache
        // if($productModel) $productModel->clearCache(); // TODO: J5 Caching

        // if ($error)
        // {
        //     // Error messages already enqueued
        // }
        // else
        // {
        //     $app->enqueueMessage(Text::_('COM_TIENDA_SAVED'), 'message');
        // }

        // $redirect = "index.php?option=com_tienda&view=products&task=setattributeoptions&id={$attributeId}&tmpl=component";
        // $this->setRedirect( Route::_( $redirect, false ) );
        Factory::getApplication()->enqueueMessage('saveattributeoptions method needs complete refactoring for model and table interaction.', 'notice');
        $this->setRedirect(Route::_('index.php?option=com_tienda&view=products', false));
    }

    /**
     * Saves the properties for all attribute option values in list
     *
     * @return unknown_type
     */
    public function saveattributeoptionvalues()
    {
        // $app = Factory::getApplication();
        // $error = false;

        // $model = $this->getModel('Productattributeoptionvalues'); // Assuming ProductattributeoptionvaluesModel
        // $row = $model->getTable(); // Productattributeoptionvalues table

        // $optionId = $this->input->getInt('id', 0 ); // ID of the parent option
        // $cids = $this->input->get('cid', array(0), 'array'); // Filter input
        // $field = $this->input->get('field', array(0), 'array'); // Filter input
        // $operator = $this->input->get('operator', array(0), 'array'); // Filter input
        // $value = $this->input->get('value', array(0), 'array'); // Filter input

        // foreach (@$cids as $cid_val) // Renamed $cid to $cid_val
        // {
        //     $row->load( $cid_val );
        //     $row->productattributeoptionvalue_field = $field[$cid_val];
        //     $row->productattributeoptionvalue_operator = $operator[$cid_val];
        //     $row->productattributeoptionvalue_value = $value[$cid_val];
        //     // Tienda::dump( $row ); // Debugging line, remove

        //     if (!$row->check() || !$row->store()) // store() is JTable, save() in J5
        //     {
        //         $app->enqueueMessage($row->getError(), 'error');
        //         $error = true;
        //     }
        // }
        // if(method_exists($row, 'reorder')) $row->reorder(); // Check if reorder exists

        // $productModel = $this->getModel('Products'); // Assuming ProductsModel
        // if($productModel) $productModel->clearCache(); // TODO: J5 Caching

        // if ($error)
        // {
        //     // Errors already enqueued
        // }
        // else
        // {
        //     $app->enqueueMessage(Text::_('COM_TIENDA_SAVED'), 'message');
        // }

        // $redirect = "index.php?option=com_tienda&view=products&task=setattributeoptionvalues&id={$optionId}&tmpl=component";
        // $this->setRedirect( Route::_( $redirect, false ) );
        Factory::getApplication()->enqueueMessage('saveattributeoptionvalues method needs complete refactoring for model and table interaction.', 'notice');
        $this->setRedirect(Route::_('index.php?option=com_tienda&view=products', false));
    }

    /**
     * Loads view for managing product files
     *
     * @return unknown_type
     */
    public function setfiles()
    {
        // $app = Factory::getApplication();
        // // $this->set('suffix', 'productfiles'); // suffix removed
        // $model = $this->getModel( 'Productfiles' ); // Assuming ProductfilesModel
        // $productId = $this->input->getInt('id');

        // // $state = parent::_setModelState(); // Call to parent
        // // $ns = $this->getNamespace(); // Old pattern

        // $state['filter_product'] = $productId;
        // // $state['order'] = $app->getUserStateFromRequest($ns.'.filter_order', 'filter_order', 'tbl.ordering', 'cmd');
        // // For product files, ordering might be different or not needed in the same way for user state.
        // // If needed, use:
        // // $state['order'] = $app->input->getCmd('filter_order', $app->getUserState('com_tienda.productfiles.filter_order', 'tbl.ordering'));
        // // $app->setUserState('com_tienda.productfiles.filter_order', $state['order']);


        // // foreach (@$state as $key=>$value)
        // // {
        // //     if($model) $model->setState( $key, $value );
        // // }
        // if($model) $model->setState('filter_product', $productId);


        // $productTable = Table::getInstance('Products', 'TiendaTable'); // TODO: Ensure TiendaTableProducts is J5 compatible
        // $productTable->load($productId);

        // $view   = $this->getView( 'productfiles', 'html' ); // Assuming ProductfilesView
        // $view->set( '_controller', 'products' );
        // $view->set( '_view', 'products' ); // Should be 'productfiles'
        // $view->set( '_action', "index.php?option=com_tienda&view=products&task=setfiles&tmpl=component&id=".$productId );
        // $view->setModel( $model, true );
        // $view->assign( 'state', $model->getState() );
        // $view->assign( 'row', $productTable ); // This is product row
        // $view->setLayout( 'default' );
        // $view->setTask(true);
        // $view->display();
        Factory::getApplication()->enqueueMessage('setfiles method needs complete refactoring for model, table, and view interaction.', 'notice');
        $this->setRedirect(Route::_('index.php?option=com_tienda&view=products', false));
    }

    /**
     * Creates a file and redirects
     *
     * @return unknown_type
     */
    public function createfile()
    {
        // $app = Factory::getApplication();
        // // $this->set('suffix', 'productfiles'); // suffix removed
        // $model  = $this->getModel( 'Productfiles' ); // Assuming ProductfilesModel

        // $row = $model->getTable(); // Productfiles table
        // $row->product_id = $this->input->getInt( 'id' );
        // $row->productfile_name = $this->input->getString( 'createproductfile_name' );
        // $row->productfile_enabled = $this->input->getInt( 'createproductfile_enabled' ); // Assuming 0 or 1
        // $row->purchase_required = $this->input->getInt( 'createproductfile_purchaserequired' ); // Assuming 0 or 1
        // $row->max_download = $this->input->getInt( 'createproductfile_max_download', -1 );

        // $fieldname = 'createproductfile_file';
        // // Tienda::load( "TiendaHelperProduct", 'helpers.product' ); // TODO: Replace helper
        // // $path = TiendaHelperProduct::getFilePath( $row->product_id ); // Static call to helper
        // $path = ''; // Placeholder for path
        // $userfile = $this->input->files->get($fieldname); // Get file data

        // if (!empty($userfile['size']))
        // {
        //     // $upload = $this->addfile( $fieldname, $path ); // This calls another method in this controller
        //     // if ($upload) // Assuming addfile returns an object with properties or false
        //     // {
        //     //     if (empty($row->productfile_name)) { $row->productfile_name = $upload->proper_name; } // Assuming $upload has proper_name
        //     //     $row->productfile_extension = $upload->getExtension(); // Assuming $upload has getExtension
        //     //     $row->productfile_path = $upload->full_path; // Assuming $upload has full_path
        //     // }
        //     // else
        //     // {
        //     //     // Error already handled in addfile or needs to be set here
        //     //     $app->enqueueMessage(Text::_('COM_TIENDA_FILE_UPLOAD_FAILED'), 'error'); // Example error
        //     // }
        //     Factory::getApplication()->enqueueMessage('File upload part of createfile needs refactoring (addfile call).', 'notice');
        // }
        // // TODO Enable remotely-stored files with file_url

        // if ( $row->save() )
        // {
        //     if($model) $model->clearCache(); // TODO: J5 Caching

        //     // $dispatcher = Factory::getApplication()->getDispatcher(); // TODO: Use injected dispatcher
        //     // $dispatcher->trigger( 'onAfterSaveProductfiles', array( $row ) ); // Event name might change
        //     $app->enqueueMessage(Text::_('COM_TIENDA_UPLOAD_WAS_SUCCESSFULL'), 'message'); // This message might be premature if save fails later
        // }
        // else
        // {
        //     $app->enqueueMessage(Text::_('COM_TIENDA_SAVE_FAILED')." - ".$row->getError(), 'error');
        // }

        // $redirect = "index.php?option=com_tienda&view=products&task=setfiles&id={$row->product_id}&tmpl=component";
        // $this->setRedirect( Route::_( $redirect, false ) );
        Factory::getApplication()->enqueueMessage('createfile method needs complete refactoring for model, table, helper, and file upload (addfile) interaction.', 'notice');
        $this->setRedirect(Route::_('index.php?option=com_tienda&view=products', false));
    }

    /**
     * Creates a file from disk and redirects
     *
     * @return unknown_type
     */
    public function createfilefromdisk()
    {
        // $app = Factory::getApplication();
        // // $this->set('suffix', 'productfiles'); // suffix removed
        // $model  = $this->getModel( 'Productfiles' ); // Assuming ProductfilesModel

        // $file = $this->input->getString( 'createproductfileserver_file' );

        // $row = $model->getTable(); // Productfiles table
        // $row->product_id = $this->input->getInt( 'id' );
        // $row->productfile_name = $this->input->getString( 'createproductfileserver_name' );
        // $row->productfile_enabled = $this->input->getInt( 'createproductfileserver_enabled' ); // Assuming 0 or 1
        // $row->purchase_required = $this->input->getInt( 'createproductfileserver_purchaserequired' ); // Assuming 0 or 1
        // $row->max_download = $this->input->getInt( 'createproductfileserver_max_download', -1 );

        // if(empty($row->productfile_name))
        // $row->productfile_name = $file;

        // // Tienda::load( "TiendaHelperProduct", 'helpers.product' ); // TODO: Replace helper
        // // $path = TiendaHelperProduct::getFilePath( $row->product_id ) . DIRECTORY_SEPARATOR . $file; // Static call to helper
        // $path = ''; // Placeholder
        // $namebits = explode('.', $file);
        // $extension = $namebits[count($namebits)-1];

        // $row->productfile_extension = $extension;
        // $row->productfile_path = $path;

        // if ( $row->save() )
        // {
        //     if($model) $model->clearCache(); // TODO: J5 Caching

        //     // $dispatcher = Factory::getApplication()->getDispatcher(); // TODO: Use injected dispatcher
        //     // $dispatcher->trigger( 'onAfterSaveProductfiles', array( $row ) ); // Event name
        //     $app->enqueueMessage(Text::_('COM_TIENDA_UPLOAD_WAS_SUCCESSFULL'), 'message');
        // }
        // else
        // {
        //     $app->enqueueMessage(Text::_('COM_TIENDA_SAVE_FAILED')." - ".$row->getError(), 'error');
        // }

        // $redirect = "index.php?option=com_tienda&view=products&task=setfiles&id={$row->product_id}&tmpl=component";
        // $this->setRedirect( Route::_( $redirect, false ) );
        Factory::getApplication()->enqueueMessage('createfilefromdisk method needs complete refactoring for model, table, and helper interaction.', 'notice');
        $this->setRedirect(Route::_('index.php?option=com_tienda&view=products', false));
    }


    /**
     * Uploads a file to associate to an item
     *
     * @return unknown_type
     */
    public function addfile( $fieldname = 'createproductfile_file', $path = 'products_files' )
    {
        // $app = Factory::getApplication();
        // // Tienda::load( 'TiendaFile', 'library.file' ); // TODO: Replace library loading
        // // $upload = new TiendaFile(); // This class needs to be J5 compatible or replaced
        // // // handle upload creates upload object properties
        // // $upload->handleUpload( $fieldname );
        // // // then save image to appropriate folder
        // // if ($path == 'products_files') { $path = Tienda::getPath( 'products_files' ); } // TODO: Replace Tienda::getPath
        // // $upload->setDirectory( $path );
        // // $dest = $upload->getDirectory(). DIRECTORY_SEPARATOR .$upload->getPhysicalName();
        // // // delete the file if dest exists
        // // if (File::exists( $dest )) // Use Joomla\CMS\Filesystem\File
        // // {
        // //     File::delete($dest);
        // // }
        // // // save path and filename or just filename
        // // if (!File::upload($upload->file_path, $dest)) // Use Joomla\CMS\Filesystem\File
        // // {
        // //     $this->setError( sprintf( Text::_('COM_TIENDA_MOVE_FAILED_FROM'), $upload->file_path, $dest) ); // setError is not standard J5 BaseController
        // //     $app->enqueueMessage(sprintf( Text::_('COM_TIENDA_MOVE_FAILED_FROM'), $upload->file_path, $dest), 'error');
        // //     return false;
        // // }

        // // $upload->full_path = $dest;
        // // return $upload;
        Factory::getApplication()->enqueueMessage('addfile method needs complete refactoring for library loading and file system operations.', 'notice');
        return null; // Placeholder
    }

    /**
     * Saves the properties for all files in list
     *
     * @return unknown_type
     */
    public function savefiles()
    {
        // $app = Factory::getApplication();
        // $error = false;
        // $productId = 0;

        // $model = $this->getModel('Productfiles'); // Assuming ProductfilesModel
        // $row = $model->getTable(); // Productfiles table

        // $cids = $this->input->get('cid', array(0), 'array'); // Filter input
        // $name = $this->input->get('name', array(0), 'array'); // Filter input
        // $ordering = $this->input->get('ordering', array(0), 'array'); // Filter input
        // $enabled = $this->input->get('enabled', array(0), 'array'); // Filter input
        // $purchaserequired = $this->input->get('purchaserequired', array(0), 'array'); // Filter input
        // $max_download = $this->input->get('max_download', array(0), 'array'); // Filter input

        // foreach (@$cids as $cid_val) // Renamed $cid to $cid_val
        // {
        //     $row->load( $cid_val );
        //     if (!$productId && isset($row->product_id)) $productId = $row->product_id;

        //     $row->productfile_name = $name[$cid_val];
        //     $row->ordering = $ordering[$cid_val];
        //     $row->productfile_enabled = $enabled[$cid_val]; // Assuming 0 or 1
        //     $row->purchase_required = $purchaserequired[$cid_val]; // Assuming 0 or 1
        //     $row->max_download = $max_download[$cid_val];
        //     if (!$row->check() || !$row->store()) // store() is JTable, save() in J5
        //     {
        //         $app->enqueueMessage($row->getError(), 'error');
        //         $error = true;
        //     }
        // }
        // if(method_exists($row, 'reorder')) $row->reorder();

        // $productModel = $this->getModel('Products'); // Assuming ProductsModel
        // if($productModel) $productModel->clearCache(); // TODO: J5 Caching

        // if ($error)
        // {
        //     // Errors already enqueued
        // }
        // else
        // {
        //     $app->enqueueMessage(Text::_('COM_TIENDA_SAVED'), 'message');
        // }

        // $redirect = "index.php?option=com_tienda&view=products&task=setfiles&id={$productId}&tmpl=component";
        // $this->setRedirect( Route::_( $redirect, false ) );
        Factory::getApplication()->enqueueMessage('savefiles method needs complete refactoring for model and table interaction.', 'notice');
        $this->setRedirect(Route::_('index.php?option=com_tienda&view=products', false));
    }

    /**
     * Delete a product Image.
     * Expected to be called via Ajax
     */
    public function deleteImage()
    {
        // $app = Factory::getApplication();
        // $format = $this->input->getCmd('format');

        // // Tienda::load( "TiendaHelperProduct", 'helpers.product' ); // TODO: Replace helper
        // // $helper = TiendaHelperBase::getInstance('Product', 'TiendaHelper'); // TODO: Replace helper

        // $product_id = $this->input->getInt( 'product_id', 0);
        // $image_name = $this->input->getString('image', ''); // Renamed to avoid conflict with $image object if any
        // $image_name = html_entity_decode($image_name);

        // // $path = $helper->getGalleryPath($product_id); // Placeholder
        // $path = ''; // Placeholder

        // $redirect = $this->input->getBase64( 'return');
        // $redirect = $redirect ? base64_decode( $redirect ) : "index.php?option=com_tienda&view=products&task=viewGallery&id={$product_id}&tmpl=component";

        // $msg = '';

        // // Check if the data is ok
        // if (empty($product_id) || empty($image_name))
        // {
        //     $msg = Text::_('COM_TIENDA_INPUT_DATA_NOT_VALID');
        //     $redirect = "index.php?option=com_tienda&view=products"; // General redirect on bad data
        //     if ($format == 'raw') { echo $msg; return; } // Echo for raw format
        //     $this->setRedirect( Route::_( $redirect, false ), $msg, 'notice' );
        //     return;
        // }

        // // Delete the image if it exists
        // // Use Joomla\CMS\Filesystem\File;
        // if(File::exists($path.$image_name)){
        //     $success = File::delete($path.$image_name);

        //     if ($success)
        //     {
        //         if (File::exists($path.'thumbs/'.$image_name))
        //         {
        //             File::delete($path.'thumbs/'.$image_name);
        //             $msg = Text::_('COM_TIENDA_IMAGE_DELETED');
        //         }
        //         else
        //         {
        //             $msg = Text::_("COM_TIENDA_CANNOT_DELETE_IMAGE_THUMBNAIL").$path.'thumbs/'.$image_name;
        //         }

        //         $model = $this->getModel('Products'); // Assuming ProductsModel
        //         $row = $model->getTable(); // ProductsTable
        //         $row->load($product_id);

        //         if ($row->product_full_image == $image_name)
        //         {
        //             $row->product_full_image = '';
        //         }
        //         $row->store(); // store() is JTable, save() in J5
        //     }
        //     else
        //     {
        //         $msg = Text::_('COM_TIENDA_CANNOT_DELETE_IMAGE').$path.$image_name;
        //     }
        // }
        // else
        // {
        //     $msg = Text::_("COM_TIENDA_CANNOT_DELETE_IMAGE".$path.$image_name);
        // }

        // if ($format == 'raw') { echo $msg; return; }

        // $this->setRedirect( Route::_( $redirect, false ), $msg, 'notice' );
        Factory::getApplication()->enqueueMessage('deleteImage method needs complete refactoring for helper and file system interaction.', 'notice');
        $this->setRedirect(Route::_('index.php?option=com_tienda&view=products', false));
    }

    public function setDefaultImage()
    {
        // $app = Factory::getApplication();
        // // Tienda::load( "TiendaHelperProduct", 'helpers.product' ); // TODO: Replace helper

        // $product_id = $this->input->getInt( 'product_id', 0);
        // $image_name = $this->input->getString('image', ''); // Renamed
        // $image_name = html_entity_decode($image_name);

        // // $helper = TiendaHelperBase::getInstance('Product'); // TODO: Replace helper
        // // $path = $helper->getGalleryPath($product_id); // Placeholder
        // // $gallery_url = $helper->getGalleryUrl($product_id); // Placeholder
        // $path = ''; $gallery_url = ''; // Placeholders

        // $message = ''; // Initialize message
        // $messageType = 'notice'; // Default message type

        // // Check if the data is ok
        // if (empty($product_id) || empty($image_name))
        // {
        //     $message = Text::_('COM_TIENDA_INPUT_DATA_NOT_VALID');
        //     $redirect = "index.php?option=com_tienda&view=products&task=viewGallery&id={$product_id}&tmpl=component";
        //     $this->setRedirect( Route::_( $redirect, false ), $message, $messageType );
        //     return;
        // }

        // // Check if the image exists
        // // Use Joomla\CMS\Filesystem\File;
        // if (File::exists($path.$image_name) || File::exists($path. DIRECTORY_SEPARATOR .$image_name))
        // {
        //     $model = $this->getModel('Products'); // Assuming ProductsModel
        //     $row = $model->getTable(); // ProductsTable
        //     $row->load( $product_id ); // load by primary key
        //     $row->product_full_image = $image_name;
        //     if (!$row->store()) // store() is JTable, save() in J5
        //     {
        //         $app->enqueueMessage( $row->getError(), 'notice' ); // Changed from JFactory to $app
        //     }

        //     if($model) $model->clearCache(); // TODO: J5 Caching

        //     $message = Text::_('COM_TIENDA_UPDATE_SUCCESSFUL');
        //     $messageType = 'message';
        // }
        // else
        // {
        //     $message = Text::_("COM_TIENDA_IMAGE_DOES_NOT_EXIST").$path.$image_name;
        //     $messageType = 'notice';
        // }

        // $format = $this->input->getCmd('format');
        // if ($format == 'raw')
        // {
        //     $html = '<img src="'.$gallery_url.'thumbs/'.$image_name.'" class="img-polaroid" />'; // Ensure $gallery_url is set
        //     $response = new \stdClass();
        //     $response->html = $html;
        //     echo json_encode($response);
        //     return;
        // }

        // $redirect = "index.php?option=com_tienda&view=products&task=viewGallery&id={$product_id}&tmpl=component&update_parent=1";
        // $this->setRedirect( Route::_( $redirect, false ), $message, $messageType );
        Factory::getApplication()->enqueueMessage('setDefaultImage method needs complete refactoring for helper, table, and file system interaction.', 'notice');
        $this->setRedirect(Route::_('index.php?option=com_tienda&view=products', false));
    }

    /**
     * Batch resize of thumbs
     * @author Skullbock
     */
    public function recreateThumbs()
    {
        // $app = Factory::getApplication();
        // $per_step = 100;
        // $from_id = $this->input->getInt('from_id', 0);
        // $to_id =  $from_id + $per_step; // Not directly used like this
        // $done = $this->input->getInt('done', 0);

        // // Tienda::load( "TiendaHelperProduct", 'helpers.product' ); // TODO: Replace helper
        // // Tienda::load( 'TiendaImage', 'library.image' ); // TODO: Replace library
        // // $compParams = ComponentHelper::getParams('com_tienda');
        // // $width = $compParams->get('product_img_width', '0');
        // // $height = $compParams->get('product_img_height', '0');

        // // $helper = TiendaHelperBase::getInstance('Product', 'TiendaHelper'); // TODO: Replace helper

        // $model = $this->getModel('Products', 'TiendaModel'); // Assuming TiendaModelProducts exists or use MVCFactory
        // $total_products = $model->getTotal(); // Need to ensure getTotal works without old state
        // $model->setState('limitstart', $done); // Process 'done' count as offset
        // $model->setState('limit', $per_step); // Process 'per_step' products

        // $products = $model->getList(); // List of products for current step

        // $i = 0; // Processed images in this step
        // $k = 0; // Processed products in this step
        // $last_id = $from_id; // This logic might need rethink for pagination

        // if (!empty($products)) {
        //     foreach ($products as $p)
        //     {
        //         $k++;
        //         // $path = $helper->getGalleryPath($p->product_id); // Placeholder
        //         // $images = $helper->getGalleryImages($path); // Placeholder
        //         $path = ''; $images = []; // Placeholders

        //         foreach ($images as $image_name)
        //         {
        //             $i++;
        //             if ($image_name != '')
        //             {
        //                 // $img = new TiendaImage($path.$image_name); // TODO: Replace TiendaImage
        //                 // $img->setDirectory( $path );
        //                 // // Thumb
        //                 // // Tienda::load( 'TiendaHelperImage', 'helpers.image' ); // TODO: Replace helper
        //                 // $imgHelper = TiendaHelperBase::getInstance('Image', 'TiendaHelper'); // TODO: Replace helper
        //                 // $imgHelper->resizeImage( $img ); // This needs TiendaImage and TiendaHelperImage
        //             }
        //         }
        //         $last_id = $p->product_id; // Keep track of last processed product ID
        //         if ($i >= $per_step) break; // Safety break if many images for one product
        //     }
        // }

        // $done += $k; // Total products processed so far

        // if ($done < $total_products) {
        //     // $redirect = "index.php?option=com_tienda&view=products&task=recreateThumbs&from_id=".($last_id+1)."&done=".$done; // Old from_id logic might be problematic
        //     $redirect = "index.php?option=com_tienda&view=products&task=recreateThumbs&done=".$done; // Simpler, rely on limitstart ($done)
        // } else {
        //     $redirect = "index.php?option=com_tienda&view=config";
        // }

        // $this->setRedirect( Route::_( $redirect, false ), Text::_('COM_TIENDA_DONE'), 'notice' ); // DONE might be premature if loop continues
        Factory::getApplication()->enqueueMessage('recreateThumbs method needs complete refactoring for helpers, models, and image library.', 'notice');
        $this->setRedirect(Route::_('index.php?option=com_tienda&view=products', false));
    }

    /**
     * Gets an address formatted for display
     *
     * @param int $address_id
     * @return string html
     */
    public function getRelationshipsHtml( $view, $product_id )
    {
        // $html = '';
        // $model = $this->getFactory()->createModel('ProductRelations', 'Administrator'); // Example using MVCFactory
        // // JModel::getInstance( 'ProductRelations', 'TiendaModel' ); // Old way
        // $model->setState('filter_product', $product_id);

        // if ($items = $model->getList())
        // {
        //     // if( $view === null ) // View is passed as param, but might not be J5 compatible
        //     // {
        //     //     $view   = $this->getView( 'products', 'html' ); // Assuming ProductsView
        //     //     $view->set( '_controller', 'products' );
        //     //     $view->set( '_view', 'products' );
        //     //     $view->set( '_doTask', true);
        //     //     $view->set( 'hidemenu', true);
        //     //     $view->setModel( $model, true );
        //     // }
        //     // $view->setLayout( 'form_relations' );
        //     // $view->set('items', $items);
        //     // $view->set('product_id', $product_id);

        //     // ob_start();
        //     // echo $view->loadTemplate( null ); // This needs a J5 compatible view
        //     // $html = ob_get_contents();
        //     // ob_end_clean();
        //     Factory::getApplication()->enqueueMessage('getRelationshipsHtml view rendering part needs J5 view.', 'notice');
        // }
        // return $html;
        Factory::getApplication()->enqueueMessage('getRelationshipsHtml method needs complete refactoring for model and view.', 'notice');
        return ''; // Placeholder
    }

    public function refreshProductGallery()
    {
        // $html = '';
        // $app = Factory::getApplication();

        // $product_id = $this->input->getInt('product_id');
        // $model = $this->getModel( 'Products' ); // Assuming ProductsModel
        // $model->setId($product_id); // This method might not exist, usually done via setState
        // if ($item = $model->getItem())
        // {
        //     // $view   = $this->getView( 'Products', 'html' ); // Assuming ProductsView
        //     // $view->set( '_doTask', true);
        //     // $view->setModel( $model, true );
        //     // $view->setLayout( 'form_gallery' );
        //     // $view->set('row', $item); // Assign item to view
        //     // $html = $view->loadTemplate(); // This needs J5 view
        //     Factory::getApplication()->enqueueMessage('refreshProductGallery view rendering part needs J5 view.', 'notice');
        // }

        // $response = new \stdClass();
        // $response->html = $html;
        // // Joomla\CMS\Response\Json::getInstance()->setData($response)->send(); // J5 JSON response
        // echo json_encode($response); // Old way
        // $app->close(); // Terminate script for AJAX
        Factory::getApplication()->enqueueMessage('refreshProductGallery method needs complete refactoring for model, view, and AJAX response.', 'notice');
        echo json_encode(['html' => '']); // Basic JSON response
        Factory::getApplication()->close();
    }

    /**
     * Upload via ajax through Uploadify
     * It's here because when the swf connects to the admin side, it would need to login.
     */
    public function uploadifyImage( )
    {
        // $app = Factory::getApplication();
        // Session::checkToken() or die(Text::_('JINVALID_TOKEN')); // Use die instead of jexit

        // $product_id = $this->input->getInt( 'product_id', 0 );

        // if ( $product_id )
        // {
        //     // Tienda::load( 'TiendaImage', 'library.image' ); // TODO: Replace library
        //     // $upload = new TiendaImage( ); // TiendaImage needs refactor
        //     // // handle upload creates upload object properties
        //     // $upload->handleUpload( 'Filedata' ); // Filedata is specific to Uploadify

        //     // $productTable = Table::getInstance( 'Products', 'TiendaTable' ); // TODO: Ensure TiendaTableProducts is J5
        //     // $productTable->load( $product_id );
        //     // $path = $productTable->getImagePath( ); // This method needs to exist in the J5 table or helper

        //     // $upload->setDirectory( $path );

        //     // $success = $upload->upload( ); // Upload process

        //     // // Tienda::load( 'TiendaHelperImage', 'helpers.image' ); // TODO: Replace helper
        //     // // $imgHelper = TiendaHelperBase::getInstance( 'Image', 'TiendaHelper' ); // TODO: Replace helper
        //     // // if ( !$imgHelper->resizeImage( $upload, 'product' ) )
        //     // // {
        //     // //     $success = false;
        //     // // }

        //     // if ( $success )
        //     // {
        //     //     if ( empty( $productTable->product_full_image ) )
        //     //     {
        //     //         $productTable->product_full_image = $upload->getPhysicalName( );
        //     //         $productTable->save( ); // save() is J5 Table method
        //     //     }
        //     //     echo Text::_('COM_TIENDA_IMAGE_UPLOADED_CORRECTLY');
        //     // }
        //     // else
        //     // {
        //     //     echo 'Error: ' . $upload->getError( );
        //     // }
        //     Factory::getApplication()->enqueueMessage('uploadifyImage image processing part needs refactoring.', 'notice');
        // } else {
        //     echo 'Error: Missing Product ID';
        // }
        // $app->close(); // Terminate
        Factory::getApplication()->enqueueMessage('uploadifyImage method needs complete refactoring for session, library, table, helper, and file upload.', 'notice');
        echo 'Error: Method not fully implemented.';
        Factory::getApplication()->close();
    }

    // Placeholder for other methods - the subtask should attempt to convert them based on the above guidance.
    // It is understood that many will be heavily commented out initially.
}
