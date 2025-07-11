# Create-your-box
This can allow you to create your own customize gift box for Wordpress Woocommerce.
create page template with given file & than paste this code in your functions.php

add_action('wp_ajax_get_variation_id', 'get_variation_id');
add_action('wp_ajax_nopriv_get_variation_id', 'get_variation_id');
function get_variation_id() {
    $product_id = intval($_POST['product_id']);
    $attributes = $_POST['attributes'];
    
    // Get the product
    $product = wc_get_product($product_id);
    if (!$product || $product->get_type() !== 'variable') {
        wp_send_json_error('Invalid product');
    }
    
    // Get all available variations
    $available_variations = $product->get_available_variations();
    $variation_id = 0;
    
    // First try the WooCommerce data store method
    $data_store = WC_Data_Store::load('product');
    $variation_id = $data_store->find_matching_product_variation($product, $attributes);
    
    if ($variation_id) {
        $variation = wc_get_product($variation_id);
        
        // Create a human-readable summary of the variation attributes
        $attribute_summary = [];
        foreach ($attributes as $key => $value) {
            $taxonomy = str_replace('attribute_', '', $key);
            
            // Get readable attribute name
            if (taxonomy_exists($taxonomy)) {
                $attribute_name = wc_attribute_label($taxonomy);
                $term = get_term_by('slug', $value, $taxonomy);
                $attribute_value = $term ? $term->name : $value;
            } else {
                $attribute_name = str_replace('pa_', '', $taxonomy);
                $attribute_name = ucfirst(str_replace('-', ' ', $attribute_name));
                $attribute_value = $value;
            }
            
            $attribute_summary[] = "$attribute_value";
        }
        
        $response = array(
            'variation_id' => $variation_id,
            'price' => $variation->get_price(),
            'name' => $product->get_name(),
            'image' => wp_get_attachment_image_url($variation->get_image_id(), 'medium') ?: get_the_post_thumbnail_url($product_id, 'medium'),
            'attribute_summary' => implode(', ', $attribute_summary)
        );
        wp_send_json_success($response);
    } else {
        // If no variation found, try to provide a helpful error message
        wp_send_json_error('No matching variation found for the selected attributes');
    }
}

// Get variation details (price and image) when attributes change
add_action('wp_ajax_get_variation_details', 'get_variation_details');
add_action('wp_ajax_nopriv_get_variation_details', 'get_variation_details');
function get_variation_details() {
    $product_id = intval($_POST['product_id']);
    $attributes = $_POST['attributes'];
    
    // Get the product
    $product = wc_get_product($product_id);
    if (!$product || $product->get_type() !== 'variable') {
        wp_send_json_error('Invalid product');
    }
    
    // Find the matching variation
    $data_store = WC_Data_Store::load('product');
    $variation_id = $data_store->find_matching_product_variation($product, $attributes);
    
    if ($variation_id) {
        $variation = wc_get_product($variation_id);
        $response = array(
            'price_html' => $variation->get_price_html(),
            'image' => wp_get_attachment_image_url($variation->get_image_id(), 'medium') ?: get_the_post_thumbnail_url($product_id, 'medium')
        );
        wp_send_json_success($response);
    } else {
        wp_send_json_error('No matching variation found');
    }
}
// Add AJAX handler for adding products to cart
add_action('wp_ajax_add_mix_match_to_cart', 'add_mix_match_to_cart_ajax');
add_action('wp_ajax_nopriv_add_mix_match_to_cart', 'add_mix_match_to_cart_ajax');
function add_mix_match_to_cart_ajax() {
    // Check nonce
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'mix_match_nonce')) {
    wp_send_json_error('Invalid security token');
    }

    // Get products data
    $products_json = isset($_POST['products']) ? sanitize_text_field($_POST['products']) : '';
    $products = json_decode(stripslashes($products_json), true);

    if (empty($products) || !is_array($products)) {
    wp_send_json_error('No products selected');
    }

    // Start session if not already started
    if (!WC()->session) {
    WC()->session = new WC_Session_Handler();
    WC()->session->init();
    }

    // Get cart if not already loaded
    if (!WC()->cart) {
    WC()->cart = new WC_Cart();
    }

    $success = true;
    $messages = array();

    // Add each product to cart
    foreach ($products as $product_data) {
    $product_id = $product_data['id'];
    $variation_id = isset($product_data['variation_id']) ? $product_data['variation_id'] : 0;
    $quantity = isset($product_data['quantity']) ? absint($product_data['quantity']) : 1;
    $variation = array();

    // For variable products, we need the variation_id and attributes
    if ($variation_id) {
    $product_id = isset($product_data['parent_id']) ? $product_data['parent_id'] : $product_id;
    $variation_product = wc_get_product($variation_id);

    if ($variation_product) {
    $variation = $variation_product->get_attributes();
    }
    }

    // Add to cart
    $result = WC()->cart->add_to_cart($product_id, $quantity, $variation_id, $variation);

    if (!$result) {
    $success = false;
    $messages[] = sprintf('Failed to add product ID %d to cart', $product_id);
    }
    }

    if ($success) {
    wp_send_json_success('Products added to cart successfully');
    } else {
    wp_send_json_error(implode(', ', $messages));
    }
}
add_action('wp_ajax_get_product_variations', 'your_get_variations_function');
add_action('wp_ajax_nopriv_get_product_variations', 'your_get_variations_function');
function your_get_variations_function() {
    // Check nonce
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'mix_match_nonce')) {
        wp_send_json_error('Invalid security token');
    }

    // Get product ID
    $product_id = isset($_POST['product_id']) ? intval($_POST['product_id']) : 0;
    if (!$product_id) {
        wp_send_json_error('Invalid product ID');
    }

    // Get product
    $product = wc_get_product($product_id);
    if (!$product || !$product->is_type('variable')) {
        wp_send_json_error('Product not found or not variable');
    }

    // Generate variation form HTML
    ob_start();
    woocommerce_variable_add_to_cart();
    $form_html = ob_get_clean();

    wp_send_json_success(['html' => $form_html]);

    // Get product ID
    $product_id = isset($_POST['product_id']) ? intval($_POST['product_id']) : 0;
    
    // Debug output
    error_log('Product ID received: ' . $product_id);
    
    // Get product
    $product = wc_get_product($product_id);
    
    if (!$product) {
        error_log('Product not found: ' . $product_id);
        wp_send_json_error('Product not found');
        return;
    }
    
    if (!$product->is_type('variable')) {
        error_log('Product is not variable: ' . $product_id);
        wp_send_json_error('Product is not variable');
        return;
    }
    
    // Generate variation form HTML
    ob_start();
    woocommerce_variable_add_to_cart();
    $form_html = ob_get_clean();
    
    error_log('Form HTML length: ' . strlen($form_html));
    
    // Make sure $form_html isn't empty
    if (empty($form_html)) {
        wp_send_json_error('Empty variation form');
        return;
    }
    
    wp_send_json_success(['html' => $form_html]);
}
function mix_match_enqueue_scripts() {
    // Make sure jQuery is enqueued
    wp_enqueue_script('jquery');
    
    // Enqueue WooCommerce variation scripts
    wp_enqueue_script('wc-add-to-cart-variation');
    
    // Optional: Enqueue your custom script
    wp_enqueue_script('mix-match-custom', get_stylesheet_directory_uri() . '/js/mix-match-custom.js', array('jquery', 'wc-add-to-cart-variation'), '1.0.0', true);
    
    // Localize script with data needed for AJAX
    wp_localize_script('jquery', 'mix_match_data', array(
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('mix_match_nonce')
    ));
}
add_action('wp_enqueue_scripts', 'mix_match_enqueue_scripts');

function load_simple_product() {
    $product_id = intval($_POST['product_id']);
    $product = wc_get_product($product_id);
    if (!$product || $product->get_type() !== 'simple') {
        wp_send_json_error('Invalid simple product');
    }
    ob_start();
    ?>
    <div class="product-cards">
        <div class="quickview-gallery">
            <?php
            // Get product image with multiple fallback methods
            $main_img = '';
            
            // Try getting the post thumbnail first
            if (has_post_thumbnail($product_id)) {
                $main_img = get_the_post_thumbnail_url($product_id, 'medium');
            }
            
            // If no post thumbnail, try getting the first image from gallery
            if (empty($main_img)) {
                $attachment_ids = $product->get_gallery_image_ids();
                if (!empty($attachment_ids)) {
                    $main_img = wp_get_attachment_image_url($attachment_ids[0], 'medium');
                }
            }
            
            // If still no image, use a placeholder
            if (empty($main_img)) {
                $main_img = wc_placeholder_img_src('medium');
            }

            $attachment_ids = $product->get_gallery_image_ids();
            $all_images = array_merge([$main_img], array_map(function($id) {
                return wp_get_attachment_image_url($id, 'medium');
            }, $attachment_ids));
            ?>
            <div class="gallery-slider">
                <button class="slider-arrow slider-prev" style="<?php echo (count($all_images) > 1) ? 'display:block;' : 'display:none;'; ?>"><i class="las la-angle-left"></i></button>
                <div class="gallery-slides">
                    <?php foreach ($all_images as $index => $img_url): ?>
                        <div class="slide <?php echo ($index === 0) ? 'active' : ''; ?>">
                            <img src="<?php echo esc_url($img_url); ?>" alt="<?php echo esc_attr($product->get_name()); ?>">
                        </div>
                    <?php endforeach; ?>
                </div>
                <button class="slider-arrow slider-next" style="<?php echo (count($all_images) <= 1) ? 'display:none;' : 'display:block;'; ?>"><i class="las la-angle-right"></i></button>
            </div>
           
            <?php if (count($all_images) > 1): ?>
                <div class="thumbnails">
                    <?php foreach ($all_images as $index => $img_url): ?>
                        <div class="thumb <?php echo ($index === 0) ? 'active' : ''; ?>" data-index="<?php echo $index; ?>">
                            <img src="<?php echo esc_url(str_replace('medium', 'thumbnail', $img_url)); ?>" class="thumb-img">
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        <div class="product-info">
            <h3><?php echo esc_html($product->get_name()); ?></h3>
            <p class="price"><?php echo $product->get_price_html(); ?></p>
            <!-- Add to Box button -->
            <button class="add-to-box"
                    data-id="<?php echo esc_attr($product_id); ?>"
                    data-name="<?php echo esc_attr($product->get_name()); ?>" 
                    data-price="<?php echo esc_attr($product->get_price()); ?>"
                    data-img="<?php echo esc_attr($main_img); ?>">
                    <i class="las la-shopping-cart"></i>
            </button>
        </div>
    </div>
   
    <script>
    (function($) {
        // Debugging function
        function logDebug(message) {
            console.log('Add to Box Debug:', message);
        }

        // Add to Box functionality
        $('.add-to-box').on('click', function() {
            const productId = $(this).data('id');
            const productName = $(this).data('name');
            const productPrice = $(this).data('price');
            const productImg = $(this).data('img');

            // Extensive logging for debugging
            logDebug('Product ID: ' + productId);
            logDebug('Product Name: ' + productName);
            logDebug('Product Price: ' + productPrice);
            logDebug('Product Image: ' + productImg);

            // Create product object
            const product = {
                id: productId,
                name: productName,
                price: productPrice,
                img: productImg
            };

            // Check if localStorage is supported
            if (typeof(Storage) !== "undefined") {
                try {
                    // Retrieve existing box items or initialize empty array
                    let boxItems = JSON.parse(localStorage.getItem('boxItems') || '[]');
                    
                    // Check if product already exists in box
                    const existingProductIndex = boxItems.findIndex(item => item.id === productId);
                    
                    if (existingProductIndex !== -1) {
                        // If product exists, increase quantity
                        boxItems[existingProductIndex].quantity = (boxItems[existingProductIndex].quantity || 1) + 1;
                        logDebug('Existing product quantity updated');
                    } else {
                        // Add new product to box with quantity
                        product.quantity = 1;
                        boxItems.push(product);
                        logDebug('New product added to box');
                    }

                    // Save updated box items to localStorage
                    localStorage.setItem('boxItems', JSON.stringify(boxItems));
                    logDebug('Box items saved to localStorage');

                    // Optional: Trigger a visual feedback
                    $(this).addClass('added');
                    setTimeout(() => {
                        $(this).removeClass('added');
                    }, 1000);

                    // Trigger custom event for box update
                    $(document).trigger('boxUpdated', [boxItems]);

                    // Alert for immediate debugging
                    // alert('Product added to box: ' + productName);

                } catch (error) {
                    console.error('Error adding product to box:', error);
                    alert('Error adding product to box. Check console for details.');
                }
            } else {
                console.error('localStorage is not supported');
                alert('Local storage not supported. Cannot add to box.');
            }
        });

        // Image slider functionality (previous code remains the same)
        let currentSlide = 0;
        const totalSlides = $('.gallery-slides .slide').length;
       
        function updateSlider() {
            $('.gallery-slides .slide').removeClass('active');
            $('.gallery-slides .slide').eq(currentSlide).addClass('active');
            $('.thumbnails .thumb').removeClass('active');
            $('.thumbnails .thumb').eq(currentSlide).addClass('active');
            
            if (currentSlide === 0) {
                $('.slider-prev').hide();
            } else {
                $('.slider-prev').show();
            }
            
            if (currentSlide === totalSlides - 1) {
                $('.slider-next').hide();
            } else {
                $('.slider-next').show();
            }
        }
        
        updateSlider();
        
        $('.slider-next').on('click', function() {
            currentSlide = (currentSlide + 1) % totalSlides;
            updateSlider();
        });
       
        $('.slider-prev').on('click', function() {
            currentSlide = (currentSlide - 1 + totalSlides) % totalSlides;
            updateSlider();
        });
       
        $('.thumbnails .thumb').on('click', function() {
            currentSlide = $(this).data('index');
            updateSlider();
        });
    })(jQuery);
    </script>
    <?php
    $output = ob_get_clean();
    echo $output;
    wp_die();
}
function load_variable_product() {
    $product_id = intval($_POST['product_id']);
    $product = wc_get_product($product_id);
    
    if (!$product || $product->get_type() !== 'variable') {
        wp_send_json_error('Invalid product');
    }
    
    ob_start();
    ?>
    <div class="variable-product">
        <div class="quickview-gallery">
            <div class="gallery-slider">
                <button class="slider-arrow slider-prev"><i class="las la-angle-left"></i></button>
                <div class="gallery-slides">
                    <div class="slide active">
                        <img src="<?php echo get_the_post_thumbnail_url($product_id, 'medium'); ?>" class="main-img">
                    </div>
                    
                    <?php
                    $attachment_ids = $product->get_gallery_image_ids();
                    foreach ($attachment_ids as $index => $id) {
                        echo '<div class="slide">';
                        echo '<img src="' . wp_get_attachment_image_url($id, 'medium') . '" alt="' . esc_attr($product->get_name()) . '">';
                        echo '</div>';
                    }
                    ?>
                </div>
                <button class="slider-arrow slider-next"><i class="las la-angle-right"></i></button>
            </div>
            
            <?php if (!empty($attachment_ids)): ?>
                <div class="thumbnails">
                    <div class="thumb active" data-index="0">
                        <img src="<?php echo get_the_post_thumbnail_url($product_id, 'thumbnail'); ?>" class="thumb-img">
                    </div>
                    
                    <?php foreach ($attachment_ids as $index => $id): ?>
                        <div class="thumb" data-index="<?php echo $index + 1; ?>">
                            <img src="<?php echo wp_get_attachment_image_url($id, 'thumbnail'); ?>" class="thumb-img">
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <div class="product-info">
            <h3><?php echo esc_html($product->get_name()); ?></h3>
            <p class="price"><?php echo $product->get_price_html(); ?></p>
            
            <?php
            // Variations as buttons
            $variation_attributes = $product->get_variation_attributes();
            $available_variations = $product->get_available_variations();
            $out_of_stock_variations = array();
            
            // Get all out of stock variation IDs
            foreach ($available_variations as $variation) {
                if (!$variation['is_in_stock']) {
                    $out_of_stock_variations[$variation['variation_id']] = $variation['attributes'];
                }
            }
            
            if (!empty($variation_attributes)) {
                // Counter to track first attribute selection
                $is_first_attribute = true;
                
                foreach ($variation_attributes as $attribute_name => $options) {
                    $attribute_label = wc_attribute_label($attribute_name);
                    
                    echo '<div class="variation-row">';
                    echo '<label>' . esc_html($attribute_label) . ':</label>';
                    
                    $attribute_field_name = sanitize_title($attribute_name);
                    if (strpos($attribute_name, 'pa_') !== 0) {
                        $attribute_field_name = 'attribute_' . $attribute_field_name;
                    } else {
                        $attribute_field_name = 'attribute_' . $attribute_field_name;
                    }

                    echo '<div class="variation-buttons" data-attribute_name="' . esc_attr($attribute_name) . '">';
                    
                    $first_option_selected = false; // Track if we've already selected an option
                    
                    foreach ($options as $option) {
                        $is_disabled = false;
                        $disabled_attr = '';
                        $option_class = 'variation-btn';
                        
                        // Check if this option is out of stock
                        foreach ($out_of_stock_variations as $variation_id => $variation_attributes) {
                            $attr_key = 'attribute_' . sanitize_title($attribute_name);
                            if (isset($variation_attributes[$attr_key]) && $variation_attributes[$attr_key] === $option) {
                                // Check if this option is ONLY available in out-of-stock variations
                                $in_stock_with_option = false;
                                
                                foreach ($available_variations as $variation) {
                                    if ($variation['is_in_stock'] && $variation['attributes'][$attr_key] === $option) {
                                        $in_stock_with_option = true;
                                        break;
                                    }
                                }
                                
                                if (!$in_stock_with_option) {
                                    $is_disabled = true;
                                    $disabled_attr = ' disabled="disabled"';
                                    $option_class .= ' out-of-stock';
                                }
                            }
                        }
                        
                        if (taxonomy_exists($attribute_name)) {
                            $term = get_term_by('slug', $option, $attribute_name);
                            $option_label = $term ? $term->name : $option;
                        } else {
                            $option_label = $option;
                        }
                        
                        // Add selected class to first in-stock option if this is the first attribute
                        $selected_class = '';
                        if ($is_first_attribute && !$is_disabled && !$first_option_selected) {
                            $selected_class = ' selected';
                            $first_option_selected = true;
                        }

                        echo '<button type="button" class="' . esc_attr($option_class . $selected_class) . '" data-value="' . esc_attr($option) . '"' . $disabled_attr . '>' . esc_html($option_label) . '</button>';
                    }
                    echo '</div>';
                    echo '</div>';
                    
                    $is_first_attribute = false;
                }
            }
            ?>

            <!-- Add to Box button -->
            <div class="btn-wrappers">
                <div class="quantity-controls-wrapper" data-id="<?php echo esc_attr($product_id); ?>">
                    <div class="flex-wrapper">
                        <button class="add-to-box-variable" data-id="<?php echo esc_attr($product_id); ?>">
                             Add to Box
                        </button>
                        <div class="quantity-selector">
                            <button class="quantity-minuss" data-action="decrease"><i class="las la-minus"></i></button>
                                <span class="quantity-value">1</span>
                            <button class="quantity-pluss" data-action="increase"><i class="las la-plus"></i></button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Add this JavaScript to handle the quantity selector and add to box functionality -->
            <script>
                (function($) {
                    // Image slider functionality (keeping existing code)
                    let currentSlide = 0;
                    const totalSlides = $('.gallery-slides .slide').length;
                    
                    function updateSlider() {
                        $('.gallery-slides .slide').removeClass('active');
                        $('.gallery-slides .slide').eq(currentSlide).addClass('active');
                        $('.thumbnails .thumb').removeClass('active');
                        $('.thumbnails .thumb').eq(currentSlide).addClass('active');
                        
                        // Hide/show arrows based on current slide position
                        if (currentSlide === 0) {
                            $('.slider-prev').hide(); // Hide left arrow on first slide
                        } else {
                            $('.slider-prev').show();
                        }
                        
                        if (currentSlide === totalSlides - 1) {
                            $('.slider-next').hide(); // Optional: Hide right arrow on last slide
                        } else {
                            $('.slider-next').show();
                        }
                    }
                    
                    // Initial call to set correct arrow visibility on page load
                    updateSlider();
                    
                    $('.slider-next').on('click', function() {
                        currentSlide = (currentSlide + 1) % totalSlides;
                        updateSlider();
                    });
                    
                    $('.slider-prev').on('click', function() {
                        currentSlide = (currentSlide - 1 + totalSlides) % totalSlides;
                        updateSlider();
                    });
                    
                    $('.thumbnails .thumb').on('click', function() {
                        currentSlide = $(this).data('index');
                        updateSlider();
                    });
                    
                    // Get variations data
                    const variationsData = JSON.parse($('#variations-data').attr('data-product_variations') || '[]');
                    let selectedAttributes = {};
                    
                    // Pre-select first variation option for each attribute
                    // Auto-select first non-disabled button for each attribute
                    $('.variation-buttons').each(function() {
                        const $btnGroup = $(this);
                        const attributeName = $btnGroup.data('attribute_name');
                        
                        // Find first enabled button
                        const $firstEnabledBtn = $btnGroup.find('.variation-btn:not([disabled]):first');
                        
                        if ($firstEnabledBtn.length) {
                            const attributeValue = $firstEnabledBtn.data('value');
                            
                            // Update selectedAttributes object
                            selectedAttributes[attributeName] = attributeValue;
                            
                            // If this button is not already selected (which it might be from PHP)
                            if (!$firstEnabledBtn.hasClass('selected')) {
                                $firstEnabledBtn.addClass('selected');
                            }
                        }
                    });
                    
                    // Update display based on initial selection
                    const initialVariation = findMatchingVariation(selectedAttributes);
                    if (initialVariation && initialVariation.image && initialVariation.image.src) {
                        updateVariationImage(initialVariation.image.src, initialVariation.image.thumb_src);
                    }
                    
                    // Variation selection functionality
                    $('.variation-btn:not([disabled])').on('click', function() {
                        const $btn = $(this);
                        const $btnGroup = $btn.closest('.variation-buttons');
                        const attributeName = $btnGroup.data('attribute_name');
                        const attributeValue = $btn.data('value');
                        
                        // Update UI
                        $btnGroup.find('.variation-btn').removeClass('selected');
                        $btn.addClass('selected');
                        
                        // Update selected attributes
                        selectedAttributes[attributeName] = attributeValue;
                        
                        // Find matching variation
                        const matchedVariation = findMatchingVariation(selectedAttributes);
                        
                        // If we found a matching variation with an image
                        if (matchedVariation && matchedVariation.image && matchedVariation.image.src) {
                            // Replace main image
                            updateVariationImage(matchedVariation.image.src, matchedVariation.image.thumb_src);
                        }
                    });
                    
                    // Find matching variation based on selected attributes
                    function findMatchingVariation(selectedAttrs) {
                        // Count how many attributes we have selected
                        const selectedAttrsCount = Object.keys(selectedAttrs).length;
                        
                        // If we don't have any attributes selected, no need to continue
                        if (selectedAttrsCount === 0) {
                            return null;
                        }
                        
                        // Check each variation
                        for (let i = 0; i < variationsData.length; i++) {
                            const variation = variationsData[i];
                            let matches = 0;
                            
                            // Check if this variation matches our selected attributes
                            for (const attrName in selectedAttrs) {
                                // Convert attribute name to the format used in variations
                                let formattedAttrName = attrName;
                                if (attrName.indexOf('pa_') !== 0) {
                                    formattedAttrName = 'attribute_' + attrName;
                                } else {
                                    formattedAttrName = 'attribute_' + attrName;
                                }
                                
                                if (variation.attributes[formattedAttrName] === selectedAttrs[attrName]) {
                                    matches++;
                                }
                            }
                            
                            // If all selected attributes match this variation
                            if (matches === selectedAttrsCount) {
                                return variation;
                            }
                        }
                        
                        return null;
                    }
                    
                    // Update the image in the gallery
                    function updateVariationImage(imageSrc, thumbSrc) {
                        // Replace the main image
                        $('.gallery-slides .slide.active img').attr('src', imageSrc);
                        
                        // If we also want to add this as a new slide (optional):
                        // This part adds the variation image as a new slide if it doesn't exist yet
                        let imageExists = false;
                        
                        // Check if this image is already in the slider
                        $('.gallery-slides .slide img').each(function() {
                            if ($(this).attr('src') === imageSrc) {
                                imageExists = true;
                                return false; // break the loop
                            }
                        });
                        
                        // If the image doesn't exist in the slider already
                        if (!imageExists) {
                            // First, remove any previously added variation images (optional)
                            $('.gallery-slides .slide.variation-image').remove();
                            $('.thumbnails .thumb.variation-image').remove();
                            
                            // Add the new slide and thumbnail
                            const newSlideIndex = $('.gallery-slides .slide').length;
                            
                            // Add new slide
                            $('.gallery-slides').append(
                                `<div class="slide variation-image">
                                    <img src="${imageSrc}" alt="Variation">
                                </div>`
                            );
                            
                            // Add new thumbnail if we have thumbnails
                            if ($('.thumbnails').length) {
                                $('.thumbnails').append(
                                    `<div class="thumb variation-image" data-index="${newSlideIndex}">
                                        <img src="${thumbSrc || imageSrc}" class="thumb-img">
                                    </div>`
                                );
                                
                                // Reattach click event for the new thumbnail
                                $('.thumbnails .thumb.variation-image').on('click', function() {
                                    currentSlide = $(this).data('index');
                                    updateSlider();
                                });
                            }
                            
                            // Show the variation image
                            $('.gallery-slides .slide').removeClass('active');
                            $('.gallery-slides .slide.variation-image').addClass('active');
                            $('.thumbnails .thumb').removeClass('active');
                            $('.thumbnails .thumb.variation-image').addClass('active');
                            
                            // Update current slide index
                            currentSlide = newSlideIndex;
                            
                            // Update slider controls visibility
                            updateSlider();
                        }
                    }
                    
                    // Fix: Correct class names and use .off() before .on() to prevent duplicate handlers
                    // First, remove any existing event handlers
                    $(document).off('click', '.quantity-minuss');
                    $(document).off('click', '.quantity-pluss');
                    
                    // Now attach the event handlers with correct class names
                    $(document).on('click', '.quantity-minuss', function() {
                        const $quantityValue = $(this).siblings('.quantity-value');
                        let quantity = parseInt($quantityValue.text());
                        
                        if (quantity > 1) {
                            $quantityValue.text(quantity - 1);
                        }
                    });
                    
                    $(document).on('click', '.quantity-pluss', function() {
                        const $quantityValue = $(this).siblings('.quantity-value');
                        let quantity = parseInt($quantityValue.text());
                        
                        $quantityValue.text(quantity + 1);
                    });
                    
                    // Add CSS for out-of-stock variations
                    $('<style>')
                        .prop('type', 'text/css')
                        .html(`
                            .variation-btn.out-of-stock {
                                opacity: 0.5;
                                cursor: not-allowed;
                                text-decoration: line-through;
                                color:000;
                            }
                        `)
                        .appendTo('head');
                    
                })(jQuery);
                jQuery(document).ready(function($) {
                    const observer = new IntersectionObserver(entries => {
                        entries.forEach(entry => {
                            console.log('Observed:', entry.target); // Debug line

                            const btnWrapper = entry.target.querySelector('.quantity-controls-wrapper');

                            if (btnWrapper) {
                                console.log('Intersection:', entry.isIntersecting); // Debug line

                                if (entry.isIntersecting) {
                                    btnWrapper.classList.remove('navbar-fixed', 'visible');
                                } else {
                                    btnWrapper.classList.add('navbar-fixed', 'visible');
                                }
                            } else {
                                console.log('No .btn-wrappers found inside:', entry.target); // Debug line
                            }
                        });
                    });

                    const containers = document.querySelectorAll('.btn-wrappers');
                    console.log('Containers found:', containers.length); // Debug line

                    containers.forEach(container => observer.observe(container));
                });

            </script>
            

            <!-- Hidden field for variations data -->
            <div id="variations-data" style="display:none;" 
                 data-product_id="<?php echo esc_attr($product_id); ?>"
                 data-product_variations="<?php echo esc_attr(json_encode($product->get_available_variations())); ?>">
            </div>
        </div>
    </div>
    <?php
    $output = ob_get_clean();
    echo $output;
    wp_die();
}


// Enqueue scripts (Add this to your functions.php or plugin file)
function enqueue_quickview_scripts() {
    // Make sure jQuery and Line Awesome icons are loaded
    wp_enqueue_script('jquery');
    // If Line Awesome is not already enqueued, uncomment the line below
    // wp_enqueue_style('line-awesome', 'https://maxst.icons8.com/vue-static/landings/line-awesome/line-awesome/1.3.0/css/line-awesome.min.css');
}
add_action('wp_enqueue_scripts', 'enqueue_quickview_scripts');

// Ajax actions (Add this to your functions.php or plugin file)
function register_quickview_ajax_actions() {
    add_action('wp_ajax_load_simple_product', 'load_simple_product');
    add_action('wp_ajax_nopriv_load_simple_product', 'load_simple_product');
    
    add_action('wp_ajax_load_variable_product', 'load_variable_product');
    add_action('wp_ajax_nopriv_load_variable_product', 'load_variable_product');
}
add_action('init', 'register_quickview_ajax_actions');

// Register AJAX actions
add_action('wp_ajax_get_product_variations', 'get_product_variations_ajax');
add_action('wp_ajax_nopriv_get_product_variations', 'get_product_variations_ajax');

add_action('wp_ajax_add_mix_match_to_cart', 'add_mix_match_to_cart');
add_action('wp_ajax_nopriv_add_mix_match_to_cart', 'add_mix_match_to_cart');

function add_mix_match_to_cart() {
    check_ajax_referer('your_nonce_action', 'nonce');

    $products = json_decode(stripslashes($_POST['products']), true);
    $box_type = sanitize_text_field($_POST['box_type']);

    foreach ($products as $product) {
        $product_id = intval($product['id']);
        $quantity = intval($product['quantity']);
        $variation_id = intval($product['variation_id']) ?: 0;

        $cart_item_data = array(
            'box_type' => $box_type,
        );

        if ($variation_id) {
            WC()->cart->add_to_cart($product_id, $quantity, $variation_id, [], $cart_item_data);
        } else {
            WC()->cart->add_to_cart($product_id, $quantity, 0, [], $cart_item_data);
        }
    }

    wp_send_json_success();
}
add_filter('woocommerce_add_cart_item_data', function($cart_item_data, $product_id) {
    if (isset($_POST['box_type'])) {
        $cart_item_data['box_type'] = sanitize_text_field($_POST['box_type']);
    }
    return $cart_item_data;
}, 10, 2);
add_filter('woocommerce_get_item_data', function($item_data, $cart_item) {
    if (!empty($cart_item['box_type'])) {
        $item_data[] = array(
            'name' => 'Box Type',
            'value' => ucfirst($cart_item['box_type']),
        );
    }
    return $item_data;
}, 10, 2);
add_action('woocommerce_checkout_create_order_line_item', function($item, $cart_item_key, $values, $order) {
    if (!empty($values['box_type'])) {
        $item->add_meta_data('Box Type', ucfirst($values['box_type']));
    }
}, 10, 4);
add_filter('woocommerce_hidden_order_itemmeta', function($hidden_meta_keys) {
    return array_diff($hidden_meta_keys, array('Box Type'));
});
add_filter('woocommerce_order_item_display_meta_key', function($display_key, $meta, $item) {
    if ($meta->key === 'Box Type') {
        return 'Box Type';
    }
    return $display_key;
}, 10, 3);
function get_next_box_id() {
    if (!session_id()) session_start(); // Start PHP session if not already started

    if (!isset($_SESSION['box_counter'])) {
        $_SESSION['box_counter'] = 1;
    } else {
        $_SESSION['box_counter']++;
    }

    return 'box-' . $_SESSION['box_counter'];
}
// Register custom endpoint for Mix & Match products
add_action('init', 'register_mix_match_product_type');
function register_mix_match_product_type() {
    add_action('woocommerce_register_post_type', function() {
        register_post_type('mix_match_box', array(
            'label' => 'Mix & Match Boxes',
            'public' => true,
            'show_in_menu' => true,
            'supports' => array('title', 'thumbnail', 'excerpt')
        ));
    });
}

// Add scripts and styles
function enqueue_mix_match_scripts() {
    wp_enqueue_style('mix-match-style', get_template_directory_uri() . '/assets/css/mix-match.css');
    wp_enqueue_script('mix-match-script', get_template_directory_uri() . '/assets/js/mix-match.js', array('jquery'), '1.0', true);
    
    wp_localize_script('mix-match-script', 'mix_match_data', array(
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('mix_match_nonce')
    ));
}
add_action('wp_enqueue_scripts', 'enqueue_mix_match_scripts');


// Add custom meta box for category selection
function add_mix_match_meta_box() {
    add_meta_box(
        'mix_match_categories',
        'Select Categories to Display',
        'render_mix_match_categories',
        'page',
        'normal',
        'high'
    );
}
add_action('add_meta_boxes', 'add_mix_match_meta_box');

// Render category selection
function render_mix_match_categories($post) {
    $selected_categories = get_post_meta($post->ID, '_mix_match_categories', true);
    if (!is_array($selected_categories)) {
        $selected_categories = array();
    }
    
    // Get product categories from WooCommerce
    $product_cats = get_terms(array(
        'taxonomy' => 'product_cat',
        'hide_empty' => false
    ));
    
    wp_nonce_field('mix_match_categories', 'mix_match_categories_nonce');
    ?>
    <div style="margin: 15px 0;">
        <label style="display: block; margin-bottom: 10px;">Select categories to show in Mix & Match box:</label>
        <?php foreach ($product_cats as $cat): ?>
            <label style="display: inline-block; margin-right: 20px;">
                <input type="checkbox" 
                       name="mix_match_categories[]" 
                       value="<?php echo esc_attr($cat->term_id); ?>"
                       <?php checked(in_array($cat->term_id, $selected_categories)); ?>>
                <?php echo esc_html($cat->name); ?>
            </label>
        <?php endforeach; ?>
    </div>
    <?php
}

// Save category selection
function save_mix_match_categories($post_id) {
    if (!isset($_POST['mix_match_categories_nonce']) || 
        !wp_verify_nonce($_POST['mix_match_categories_nonce'], 'mix_match_categories')) {
        return;
    }
    
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }
    
    if (isset($_POST['mix_match_categories'])) {
        $categories = array_map('intval', $_POST['mix_match_categories']);
        update_post_meta($post_id, '_mix_match_categories', $categories);
    } else {
        delete_post_meta($post_id, '_mix_match_categories');
    }
}
add_action('save_post', 'save_mix_match_categories');



// AJAX handler for adding products to cart
add_action('wp_ajax_add_mix_match_to_cart', 'handle_mix_match_cart');
add_action('wp_ajax_nopriv_add_mix_match_to_cart', 'handle_mix_match_cart');
function handle_mix_match_cart() {
    // Verify nonce for security
    check_ajax_referer('mix_match_nonce', 'nonce');
    
    // Get and decode products array from POST data
    $products = isset($_POST['products']) ? json_decode(stripslashes($_POST['products']), true) : array();
    
    if (empty($products)) {
        wp_send_json_error(array('message' => 'No products provided'));
        return;
    }
    
    try {
        // Get current box counter from options and increment it
        $box_counter = (int)get_option('mix_match_box_counter', 0);
        $box_counter++;
        
        // Save the incremented counter back to options
        update_option('mix_match_box_counter', $box_counter);
        
        // Create the box identifier for this entire group
        $box_identifier = 'Box-' . $box_counter;
        
        foreach($products as $product) {
            if (!isset($product['id']) || !is_numeric($product['id'])) {
                throw new Exception('Invalid product ID');
            }
            
            // Add product to cart with custom meta data
            $cart_item_data = array(
                'box_identifier' => $box_identifier,  // Same identifier for all products in this group
            );
            
            $added = WC()->cart->add_to_cart(
                absint($product['id']),  // Product ID
                1,                       // Quantity
                0,                       // Variation ID (0 for simple products)
                array(),                 // Variation attributes
                $cart_item_data          // Custom data
            );
            
            if (!$added) {
                throw new Exception('Failed to add product to cart');
            }
        }
        
        wp_send_json_success(array(
            'message' => sprintf(_n('%d product added to cart', '%d products added to cart', count($products), 'your-theme-domain'), count($products)),
            'cart_total' => WC()->cart->get_cart_total(),
            'current_box' => $box_identifier // Send back the current box ID for debugging
        ));
        
    } catch (Exception $e) {
        wp_send_json_error(array(
            'message' => $e->getMessage()
        ));
    }
}

// Reset box counter when cart is emptied
add_action('woocommerce_cart_emptied', 'reset_mix_match_box_counter');
function reset_mix_match_box_counter() {
    update_option('mix_match_box_counter', 0);
}

// Display custom meta on cart and checkout pages
add_filter('woocommerce_get_item_data', 'display_box_identifier_on_cart', 10, 2);

function display_box_identifier_on_cart($item_data, $cart_item) {
    if (isset($cart_item['box_identifier'])) {
        $item_data[] = array(
            'key'   => __('Box Type', 'your-theme-domain'),
            'value' => esc_html($cart_item['box_identifier'])
        );
    }
    return $item_data;
}

// Save box identifier to order item meta
add_action('woocommerce_checkout_create_order_line_item', 'save_box_identifier_to_order_item', 10, 4);

function save_box_identifier_to_order_item($item, $cart_item_key, $values, $order) {
    if (isset($values['box_identifier'])) {
        $item->add_meta_data(__('Box Type', 'your-theme-domain'), esc_html($values['box_identifier']));
    }
}

// Group cart items by box identifier
add_filter('woocommerce_cart_item_name', 'modify_cart_item_name', 10, 3);

function modify_cart_item_name($name, $cart_item, $cart_item_key) {
    if (isset($cart_item['box_identifier'])) {
        $name = sprintf(
            '<div class="box-item-container"><span class="box-identifier">%s</span>%s</div>',
            esc_html($cart_item['box_identifier']),
            $name
        );
    }
    return $name;
}

// Add styling for box items
add_action('wp_head', 'add_box_item_styles');

function add_box_item_styles() {
    ?>
    <style>
        .box-item-container {
            padding: 10px;
            margin: 5px 0;
            border-left: 3px solid #2271b1;
            background: #f0f0f1;
        }
        .box-identifier {
            display: block;
            font-size: 0.9em;
            color: #666;
            margin-bottom: 5px;
        }
    </style>
    <?php
}


this paste & saved 

after this on that you can see your all product category . Now you need to select which product you want to show on list  
