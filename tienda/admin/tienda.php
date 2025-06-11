<?php
/**
 * @version	0.1
 * @package	Tienda
 * @author 	Dioscouri Design
 * @link 	http://www.dioscouri.com
 * @copyright Copyright (C) 2007 Dioscouri Design. All rights reserved.
 * @license http://www.gnu.org/copyleft/gpl.html GNU/GPL, see LICENSE.php
*/

/** ensure this file is being included by a parent file */
defined('_JEXEC') or die('Restricted access');

use Joomla\CMS\Factory\Factory;
use Joomla\CMS\Uri\Uri;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\CMS\Dispatcher\ComponentDispatcherFactoryInterface;
use Joomla\CMS\Filesystem\File;

// Check the registry to see if our Tienda class has been overridden
if ( !class_exists('Tienda') ) 
    JLoader::register( "Tienda", JPATH_ADMINISTRATOR."/components/com_tienda/defines.php" );

// load the config class
// Tienda::load( 'Tienda', 'defines' );

// Load Custom Language File if needed (com_tienda_custom)
// if(Tienda::getInstance()->get('custom_language_file', '0'))
// {
// 	$lang = Factory::getLanguage();
// 	$extension = 'com_tienda_custom';
// 	$base_dir = JPATH_ADMINISTRATOR;
// 	$lang->load($extension, $base_dir, null, true);
// }

// before executing any tasks, check the integrity of the installation
// Tienda::getClass( 'TiendaHelperDiagnostics', 'helpers.diagnostics' )->checkInstallation();

$app = Factory::getApplication();

// Web Asset Manager for tienda_admin.js
// IMPORTANT: Adjust the path to tienda_admin.js if it's different
// Common locations: JPATH_ROOT . '/media/com_tienda/js/admin/tienda_admin.js' or JPATH_ADMINISTRATOR . '/components/com_tienda/assets/js/tienda_admin.js'
// For this example, I'm assuming it's in media/com_tienda/js/ (relative to web root)
// and JPATH_ROOT for the File::exists check.
$tiendaAdminJsPath = JPATH_ROOT . '/media/com_tienda/js/tienda_admin.js';
if (File::exists($tiendaAdminJsPath)) {
    $wa = $app->getDocument()->getWebAssetManager();
    // The path for registerScript is relative to the web root's media folder or a fully qualified URL.
    // If tienda_admin.js is in administrator/components/com_tienda/js, this might need adjustment.
    // Using 'media/com_tienda/js/tienda_admin.js' as a common pattern for Joomla 4/5 assets.
    $wa->registerScript('com_tienda.admin', 'media/com_tienda/js/tienda_admin.js', [], ['defer' => true]);
    $wa->useScript('com_tienda.admin');
}

$parentPath = JPATH_ADMINISTRATOR . '/components/com_tienda/helpers';
// DSCLoader::discover('TiendaHelper', $parentPath, true);

$parentPath = JPATH_ADMINISTRATOR . '/components/com_tienda/library';
// DSCLoader::discover('Tienda', $parentPath, true);

// JHTML::_('script', 'common.js', 'media/dioscouri/js/');
// JHTML::_('stylesheet', 'common.css', 'media/dioscouri/css/');

// load the plugins
// JPluginHelper::importPlugin( 'tienda' );

// Check Json Class Existance
//if ( !function_exists('json_decode') ) 
//{
	// This should load not only the class, but also json_encode / json_decode
//	Tienda::load('Services_JSON', 'library.json');
//}

try {
    // Get the dispatcher from the component dispatcher factory.
    $dispatcher = $app->getContainer()->get(Joomla\CMS\Dispatcher\ComponentDispatcherFactoryInterface::class)->createDispatcher($app);
    $dispatcher->dispatch();
} catch (Throwable $e) {
    if ($app->get('debug_exceptions', false) || ($app->get('debug') && defined('JDEBUG') && JDEBUG)) {
        // Log the full error for debugging if possible, or at least output more details.
        // Using error_log for server-side logging if configured, and enqueueMessage for visible feedback.
        error_log('Tienda Admin Dispatch Error: ' . $e->getMessage() . ' in ' . $e->getFile() . ' on line ' . $e->getLine());
        Factory::getApplication()->enqueueMessage('Tienda Dispatch Error: ' . $e->getMessage() . ' in ' . $e->getFile() . ' on line ' . $e->getLine(), 'error');
        if ($app->get('debug')) { // Rethrow only if Joomla debug mode is explicitly on
             throw $e;
        }
    } else {
        $app->enqueueMessage(Text::_('JERROR_AN_ERROR_HAS_OCCURRED') . ' (Tienda Entry Point)', 'error');
    }
    // Depending on the application's error handling strategy, you might redirect to an error page here.
}
?>
