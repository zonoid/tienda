<?php
/**
 * @package		Tienda
 * @copyright	Copyright (C) 2009 DT Design Inc. All rights reserved.
 * @license		GNU GPLv2 <http://www.gnu.org/licenses/old-licenses/gpl-2.0.html>
 * @link 		http://www.dioscouri.com
 */

// no direct access
defined('_JEXEC') or die('Restricted access');

class Tienda
{
	protected $_name 			= 'tienda';	
	protected $_version 		= '0.10.1';
	protected $_build          = null;
	protected $_versiontype    = 'community';
	protected $_copyrightyear 	= '2012';
	protected $_min_php		= '5.3';
	static $_guestIdStart = -10;
	
	public static function getGuestIdStart()
	{
		return self::$_guestIdStart;
	}
	
	/**
	 * Get the URL to the folder containing all media assets
	 *
	 * @param string	$type	The type of URL to return, default 'media'
	 * @return 	string	URL
	 */
	public static function getURL($type = 'media')
	{
		$url = \Joomla\CMS\Uri\Uri::root(true);

		switch($type)
		{
			case 'media' :
				$url .= '/media/com_tienda/';
				break;
			case 'css' :
				$url .= '/media/com_tienda/css/';
				break;
			case 'images' :
				$url .= '/media/com_tienda/images/';
				break;
			case 'ratings' :
				$url .= '/media/com_tienda/images/ratings/';
				break;
			case 'js' :
				$url .= '/media/com_tienda/js/';
				break;
			case 'categories_images' :
				$url .= '/images/com_tienda/categories/';
				break;
			case 'categories_thumbs' :
				$url .= '/images/com_tienda/categories/thumbs/';
				break;
			case 'products_images' :
				$url .= '/images/com_tienda/products/';
				break;
			case 'products_thumbs' :
				$url .= '/images/com_tienda/products/thumbs/';
				break;
			case 'products_files' :
				$url .= '/images/com_tienda/files/';
				break;
			case 'order_files' :
				$url .= '/images/com_tienda/orders/';
				break;
			case 'manufacturers_images' :
				$url .= '/images/com_tienda/manufacturers/';
				break;
			case 'manufacturers_thumbs' :
				$url .= '/images/com_tienda/manufacturers/thumbs/';
				break;
			case 'cartitems_files':
				$url .= '/images/com_tienda/cartitems/';
				break;
			case 'orderitems_files':
				$url .= '/images/com_tienda/orderitems/';
				break;
		}

		return $url;
	}

	/**
	 * Get component config
	 *
	 * @acces	public
	 * @return	object
	 */
	 public static function getInstance()
	{
		static $instance;

		if (!is_object($instance))
		{
			$instance = new Tienda();
		}

		return $instance;
		
	}
	
	/**
	 * Get the path to the folder containing all media assets
	 *
	 * @param 	string	$type	The type of path to return, default 'media'
	 * @return 	string	Path
	 */
	public static function getPath($type = 'media')
	{
		$path = JPATH_SITE;

		switch($type)
		{
			case 'media' :
				$path .= '/media/com_tienda';
				break;
			case 'css' :
				$path .= '/media/com_tienda/css';
				break;
			case 'images' :
				$path .= '/media/com_tienda/images';
				break;
			case 'ratings' :
				$path .= '/media/com_tienda/images/ratings';
				break;
			case 'js' :
				$path .= '/media/com_tienda/js';
				break;
			case 'products_templates' :
				$path .= '/media/com_tienda/templates/site/products';
				break;
            case 'product_buy_templates' :
                $path .= '/media/com_tienda/templates/site/product_buy';
                break;
			case 'categories_templates' :
				$path .= '/media/com_tienda/templates/site/categories';
				break;
			case 'categories_images' :
				$path .= '/images/com_tienda/categories';
				break;
			case 'categories_thumbs' :
				$path .= '/images/com_tienda/categories/thumbs';
				break;
			case 'products_images' :
				$path .= '/images/com_tienda/products';
				break;
			case 'products_thumbs' :
				$path .= '/images/com_tienda/products/thumbs';
				break;
			case 'products_files' :
				$path .= '/images/com_tienda/files';
				break;
			case 'manufacturers_images' :
				$path .= '/images/com_tienda/manufacturers';
				break;
			case 'manufacturers_thumbs' :
				$path .= '/images/com_tienda/manufacturers/thumbs';
				break;
			case 'order_files' :
				$path .= '/images/com_tienda/orders';
				break;
			case 'cartitems_files':
				$path .= '/images/com_tienda/cartitems';
				break;
			case 'orderitems_files':
				$path .= '/images/com_tienda/orderitems';
				break;
		}

		return $path;
	}
    
	/**
	 * Copy of Joomla method to fix problem with JS when SSL is turned on
	 */
	public static function getUriRoot()
	{
		return \Joomla\CMS\Uri\Uri::root();
	}
	
	/**
	 * Returns the result of the named function if it exists,
	 * otherwise returns a property of the object or the default value if the property is not set.
	 *
	 * @param   string  $property  The name of the property.
	 * @param   mixed   $default   The default value.
	 *
	 * @return  mixed    The value of the property.
	 *
	 * @since   11.1
	 *
	 * @see     getProperties()
	 */
	public function get($property, $default = null)
	{
	    if (method_exists($this, 'get'.$property)) 
	    {
	        $method_name = 'get'.$property;
	        return $this->{$method_name}($default);
	    }
	        
	    return parent::get($property, $default);
	}
} 


// keeping for compatibility
class TiendaConfig extends Tienda {}

?>
