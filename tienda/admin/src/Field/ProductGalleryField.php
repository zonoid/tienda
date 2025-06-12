<?php
namespace Dioscouri\Component\Tienda\Administrator\Field;

defined('_JEXEC') or die('Restricted access');

use Joomla\CMS\Form\FormField;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Factory\Factory;
use Joomla\CMS\Uri\Uri;
use Dioscouri\Component\Tienda\Administrator\Helper\ProductHelper; // Assuming ProductHelper is where getGalleryImages/Path/Url are
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Session\Session;

class ProductGalleryField extends FormField
{
    protected $type = 'ProductGallery';

    protected function getInput()
    {
        // Get current product ID from the form's data
        $productId = (int) $this->form->getValue('product_id');
        $html = [];

        $html[] = '<div id="product_gallery_container_' . $this->id . '">';

        // Display existing images
        $html[] = '<h4>' . Text::_('COM_TIENDA_CURRENT_GALLERY_IMAGES') . '</h4>';
        $html[] = '<div class="current-gallery-images" style="display: flex; flex-wrap: wrap; gap: 10px;">';
        if ($productId) {
            $galleryPath = ProductHelper::getGalleryPath($productId);
            $galleryUrl = ProductHelper::getGalleryUrl($productId);
            // ProductHelper::getGalleryImages expects full path, and returns array of basenames or fullpaths depending on its internal logic
            // For display, we need basenames if galleryUrl is the base URL for all images in that gallery.
            // Let's assume getGalleryImages returns basenames for now, or adjust if it returns full paths.
            // If ProductHelper::getGalleryImages returns full paths, we might need to adjust how image src is constructed or modify getGalleryImages.
            // Based on previous refactor of getGalleryImages, it returns full paths if $fullpath=true (default).
            // So, we need to derive the relative path from the galleryPath for the src.

            $images = ProductHelper::getGalleryImages($galleryPath, ['fullpath' => true]); // Ensure we get full paths to work with

            if (!empty($images)) {
                foreach ($images as $imageFullPath) {
                    $imageFilename = basename($imageFullPath); // Get just the filename for display and data attributes
                    $imageSrc = rtrim($galleryUrl, '/') . '/' . htmlspecialchars($imageFilename, ENT_QUOTES, 'UTF-8');

                    $html[] = '<div class="gallery-image-item" style="border:1px solid #ddd; padding:5px; text-align:center;">';
                    $html[] = '  <img src="' . $imageSrc . '" alt="' . htmlspecialchars($imageFilename, ENT_QUOTES, 'UTF-8') . '" style="max-width:100px; max-height:100px; display:block;" />';
                    $html[] = '  <input type="text" readonly value="' . htmlspecialchars($imageFilename, ENT_QUOTES, 'UTF-8') . '" size="15" /><br/>';
                    $html[] = '  <button type="button" class="btn btn-mini btn-danger delete-gallery-image" data-productid="'. $productId .'" data-filename="'. htmlspecialchars($imageFilename, ENT_QUOTES, 'UTF-8') .'">' . Text::_('JACTION_DELETE') . '</button>';
                    // TODO: Add ordering controls (up/down arrows) if reordering is needed
                    $html[] = '</div>';
                }
            } else {
                $html[] = '<p>' . Text::_('COM_TIENDA_NO_IMAGES_IN_GALLERY') . '</p>';
            }
        } else {
            $html[] = '<p>' . Text::_('COM_TIENDA_SAVE_PRODUCT_TO_ADD_IMAGES') . '</p>';
        }
        $html[] = '</div>'; // end current-gallery-images

        // Upload new images
        $html[] = '<hr/><h4>' . Text::_('COM_TIENDA_UPLOAD_NEW_IMAGES') . '</h4>';
        if ($productId) {
             $html[] = '<input type="file" name="gallery_images[]" multiple />';
             // TODO: For AJAX upload, this input might be different or handled by JS.
             // For non-AJAX, the main form needs enctype="multipart/form-data".
             // The ProductController::save() or a dedicated upload task would handle these.
        } else {
            $html[] = '<p>' . Text::_('COM_TIENDA_SAVE_PRODUCT_TO_UPLOAD_IMAGES') . '</p>';
        }
        $html[] = '</div>'; // end product_gallery_container

        // TODO: Add JavaScript for AJAX delete and upload/reorder.
        // For now, delete buttons are illustrative and would need JS to submit to a controller task.

        $jsContent = "
        document.addEventListener('DOMContentLoaded', function() {
            const galleryContainer = document.getElementById('product_gallery_container_" . $this->id . "');
            if (!galleryContainer) return;

            galleryContainer.addEventListener('click', function(event) {
                if (event.target.classList.contains('delete-gallery-image')) {
                    event.preventDefault();
                    const button = event.target;
                    const productId = button.dataset.productid;
                    const filename = button.dataset.filename;
                    const galleryItem = button.closest('.gallery-image-item');

                    if (confirm(" . json_encode(Text::_('COM_TIENDA_CONFIRM_DELETE_GALLERY_IMAGE')) . ".replace('%s', filename))) {
                        const tokenName = '" . Session::getFormToken(true) . "';
                        const url = '" . Uri::root() . "index.php?option=com_tienda&task=product.ajaxDeleteGalleryImage&format=json&" . Session::getFormToken() . "=1';

                        const formData = new FormData();
                        formData.append('product_id', productId);
                        formData.append('filename', filename);
                        formData.append(tokenName, '1'); // Add CSRF token to POST data

                        fetch(url, {
                            method: 'POST',
                            body: formData
                            // Headers not strictly needed for FormData POST unless specific like X-CSRF-Token
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                if (galleryItem) galleryItem.remove();
                                Joomla.renderMessages({'message': [data.message]});
                            } else {
                                Joomla.renderMessages({'error': [data.message || " . json_encode(Text::_('COM_TIENDA_AJAX_ERROR')) . "]});
                            }
                        })
                        .catch(error => {
                            Joomla.renderMessages({'error': [" . json_encode(Text::_('COM_TIENDA_AJAX_REQUEST_FAILED')) . " + ': ' + error]});
                            console.error('Error:', error);
                        });
                    }
                }
            });
        });";

        HTMLHelper::_('script.inline', ['script' => $jsContent, 'version' => 'auto']);

        return implode("\n", $html);
    }

    protected function getLabel()
    {
        // This field type might not need a traditional label if it's a self-contained block
        return ''; // Or parent::getLabel(); if you want the XML label to render
    }
}
?>
