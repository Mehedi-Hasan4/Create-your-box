<?php
/*
Template Name: Custom Box Products
*/

get_header();

// Get selected categories
$selected_categories = get_post_meta(get_the_ID(), '_mix_match_categories', true);
if (!is_array($selected_categories)) {
    $selected_categories = array();
}

// Get products from selected categories and show only in-stock products
$args = array(
    'post_type' => 'product',
    'posts_per_page' => -1,
    'tax_query' => array(
        array(
            'taxonomy' => 'product_cat',
            'field' => 'term_id',
            'terms' => $selected_categories,
            'operator' => 'IN'
        )
    ),
    'meta_query' => array(
        array(
            'key' => '_stock_status',
            'value' => 'instock',
            'compare' => '='
        )
    )
);

// Define upsell product IDs based on categories instead of hard-coded IDs
$upsell_category_slug = 'box'; // The slug of the box category
$upsell_category = get_term_by('slug', $upsell_category_slug, 'product_cat');


// Get products
$products_query = new WP_Query($args);
$products = $products_query->posts;

// Get only selected categories
$categories = array();
foreach($selected_categories as $cat_id) {
    $term = get_term($cat_id, 'product_cat');
    if ($term && !is_wp_error($term)) {
        $categories[$term->term_id] = $term->name;
        
        // Check if this is a food or card category (you need to set these IDs)
        if ($term->slug == 'foods' || $term->parent == 'foods') {
            $upsell_categories['foods'][] = $cat_id;
        } elseif ($term->slug == 'cards' || $term->parent == 'cards') {
            $upsell_categories['cards'][] = $cat_id;
        }
    }
}

// Get upsell products by categories

$upsell_products = array();

if ($upsell_category && !is_wp_error($upsell_category)) {
    $upsell_args = array(
        'post_type' => 'product',
        'posts_per_page' => 10,
        'tax_query' => array(
            array(
                'taxonomy' => 'product_cat',
                'field' => 'term_id',
                'terms' => array($upsell_category->term_id),
                'operator' => 'IN'
            )
        ),
        'meta_query' => array(
            array(
                'key' => '_stock_status',
                'value' => 'instock',
                'compare' => '='
            )
        )
    );

    $upsell_query = new WP_Query($upsell_args);

    foreach ($upsell_query->posts as $upsell_post) {
        $wc_product = wc_get_product($upsell_post->ID);
        if ($wc_product && $wc_product->is_in_stock()) {
            $upsell_products[$upsell_post->ID] = $wc_product;
        }
    }
}

?>

<div class="multi-steps">
    <div class="multi-steps-box">
        <ul class="mix-steps">
            <li class="mix-step" data-step="2"><span class="stepd">1</span>Choose Products</li>
            <li class="mix-step" data-step="3"><span class="stepd">2</span>Choose Gift Box</li>
            <li class="mix-step" data-step="4"><span class="stepd">3</span>Confirm Box</li>
        </ul>
        <div class="progress-bar-wrap">
            <div class="progress-bar"></div>
        </div>
    </div>
</div>
<div id="mix-step-content">
    <div id="step-2" class="mix-step-content">
        <div class="products-section">
            <h1>Create your customize box</h1>
            
            <!-- Category Filter -->
            <?php if (!empty($categories)): ?>
            <div class="category-filter-wrapper">
                <button class="scroll-btn left-btn"><i class="fa-solid fa-chevron-left"></i></button>
                <div class="category-filter">
                    <button class="category-btn active" data-category="all">All</button>
                    <?php foreach($categories as $id => $name): ?>
                        <button class="category-btn" data-category="<?php echo esc_attr($id); ?>">
                            <?php echo esc_html($name); ?>
                        </button>
                    <?php endforeach; ?>
                </div>
                <button class="scroll-btn right-btn"><i class="fa-solid fa-chevron-right"></i></button>
            </div>
            <?php endif; ?>

            <!-- Enhanced Search and Sorting Section -->
            <div class="product-search-sorting">
                <div class="mis-match-search-box">
                    <input type="text" id="product-search" placeholder="Search products...">
                </div>
                <div class="sorting-box">
                    <select id="product-sorting">
                        <option value="default">Default sorting</option>
                        <option value="price-low">Sort by price: low to high</option>
                        <option value="price-high">Sort by price: high to low</option>
                    </select>
                </div>
            </div>
            
            <!-- Products Grid -->
            <div class="products-grid">
                <?php 
                    if (!empty($products)):
                        foreach($products as $product):
                            $wc_product = wc_get_product($product->ID);
                            
                            // Skip if product is not in stock
                            if (!$wc_product->is_in_stock()) continue;
                            
                            $product_cats = wp_get_post_terms($product->ID, 'product_cat');
                            $category_ids = array_map(function($cat) { return $cat->term_id; }, $product_cats);
                            $product_type = $wc_product->get_type();
                            $product_price = $wc_product->get_price();
                            
                            // Additional classes based on product type
                            $product_class = 'product-card';
                            $product_class .= ' product-type-' . $product_type;
                ?>
                    <div class="<?php echo esc_attr($product_class); ?>" 
                         data-categories="<?php echo esc_attr(implode(',', $category_ids)); ?>"
                         data-price="<?php echo esc_attr($product_price); ?>"
                         data-type="<?php echo esc_attr($product_type); ?>"
                         data-id="<?php echo esc_attr($product->ID); ?>">
                         <div class="popup-click-wrapper  open-quickview" data-type="<?php echo esc_attr($product_type); ?>"
                         data-id="<?php echo esc_attr($product->ID); ?>">
                            <div class="product-img-wrapper">
                                <img src="<?php echo get_the_post_thumbnail_url($product->ID, 'medium'); ?>" 
                                    alt="<?php echo esc_attr($product->post_title); ?>">
                            </div>
                            <h3><?php echo esc_html($product->post_title); ?></h3>
                         </div>
                        <div class="btn-wrapper">
                            <?php if ($product_type === 'simple'): ?>
                                <button class="add-to-box" 
                                        data-id="<?php echo esc_attr($product->ID); ?>"
                                        data-name="<?php echo esc_attr($product->post_title); ?>"
                                        data-price="<?php echo esc_attr($product_price); ?>"
                                        data-type="simple">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 14 14" fill="none">
                                            <path d="M0.5 7.5V6.5H6.5V0.5H7.5V6.5H13.5V7.5H7.5V13.5H6.5V7.5H0.5Z" fill="black"/>
                                        </svg>
                                </button>
                                <?php else: ?>
                                <button class="select-variation" 
                                        data-id="<?php echo esc_attr($product->ID); ?>"
                                        data-name="<?php echo esc_attr($product->post_title); ?>"
                                        data-type="variable">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 14 14" fill="none">
                                            <path d="M0.5 7.5V6.5H6.5V0.5H7.5V6.5H13.5V7.5H7.5V13.5H6.5V7.5H0.5Z" fill="black"/>
                                        </svg>
                                </button>
                            <?php endif; ?>
                        </div>
                        <div class="product-card-content">
                            <p class="price"><?php echo $wc_product->get_price_html(); ?></p>
                        </div>
                    </div>
                <?php 
                    endforeach;
                else:
                ?>
                    <p>No products found in the selected categories.</p>
                <?php endif; ?>
            </div>
        </div>
        <div class="right-side-wrapper">
            <div class="selected-products">
                <h2 class="your-box-title">Your Box (<span class="selected-count">0</span> item)</h2>
                <div class="selected-itemss"></div>
                <div class="total-section">
                    <span class="total"><span>Total:</span> <span>৳0.00</span></span>
                    <button type="button" class="button nexts-steps" data-next-step="#step-3">Next<svg xmlns="http://www.w3.org/2000/svg" width="21" height="21" viewBox="0 0 21 21" fill="none">
                        <path fill-rule="evenodd" clip-rule="evenodd" d="M11.2752 5.01094L15.478 9.74141L3 9.80106L3.02659 11.1989L15.5305 11.2288L11.3011 15.9883L12.1943 17L18 10.5145L12.1684 4L11.2752 5.01094Z" fill="white"/>
                    </svg></button>
                </div>
            </div>

            <div class="reviews-wrapper">
                <?php echo do_shortcode('[custom_reviews]'); ?>
            </div>
        </div>
    </div>
    <div id="step-3" class="mix-step-content">
        <div class="choose-premium-box">
            <h3>Choose Your Premium Box</h3>
        </div>
        <div class="selected-products">
            <h2 class="your-box-title">Your Box (<span class="selected-count">0</span> item)</h2>
            <div class="selected-itemss"></div>
            <div class="total-section">
                <span class="total"><span>Total:</span> <span>৳0.00</span></span>
                <button class="add-to-cart-btn" disabled>Add to Cart</button>
            </div>
            <div class="sticky-nav-btn-section">
                <!-- Before Button to Go to Payment Step -->
                <button type="button" class="button prevs-steps" data-next-step="#step-2">Back</button>
                <!-- Next Button to Go to Payment Step -->
                <button type="button" class="button nexts-steps" data-next-step="#step-4">Next <svg xmlns="http://www.w3.org/2000/svg" width="21" height="21" viewBox="0 0 21 21" fill="none">
                    <path fill-rule="evenodd" clip-rule="evenodd" d="M11.2752 5.01094L15.478 9.74141L3 9.80106L3.02659 11.1989L15.5305 11.2288L11.3011 15.9883L12.1943 17L18 10.5145L12.1684 4L11.2752 5.01094Z" fill="white"/>
                </svg></button>
            </div>
        </div>
        
    </div>
    <div id="step-4" class="mix-step-content">
        <div class="selected-products-img">
            <h3>Order Summary</h3>
            <div class="listed-products-box">
                <div class="selected-items"></div>
                <div class="total-section">
                    <span class="total"><span>Total:</span> <span>৳0.00</span></span>
                    <button class="add-to-cart-btn last-stages" disabled>Confirm</button>
                </div>
            </div>
        </div>
        <div class="selected-products confirmed-steps">
            <h2 class="your-box-title">Your Box (<span class="selected-count">0</span> item)</h2>
            <div class="selected-itemss"></div>
            <div class="total-section">
                <span class="total"><span>Total:</span> <span>৳0.00</span></span>
            </div>
            <div class="sticky-nav-btn-section">
                <!-- Before Button to Go to Payment Step -->
                <button type="button" class="button prevs-steps last-steps-btn" data-next-step="#step-3">Back</button>
                <button class="add-to-cart-btn" disabled>Confirm</button>
            </div>
        </div>
    </div>
</div>


<div id="quickview-modal" class="quickview-modal" style="display: none;">
    <div class="quickview-content">
        <button class="quickview-close"><i class="las la-times"></i></button>
        <div class="quickview-loader">Loading...</div>
        <div class="quickview-data" style="display: none;"></div>
    </div>
</div>

<script>
   document.addEventListener("DOMContentLoaded", function () {
  const selected = document.querySelector('.selected-products');
  const reviews = document.querySelector('.reviews-wrapper');

  function updateStickyOffset() {
    if (!selected || !reviews) return;

    const selectedHeight = selected.getBoundingClientRect().height;
    const selectedTop = parseInt(window.getComputedStyle(selected).top, 10) || 0;

    const totalOffset = selectedHeight + selectedTop + 20;
    reviews.style.top = totalOffset + 'px';
  }

  // Update on scroll (once, before sticky activates)
  let offsetSet = false;
  window.addEventListener('scroll', function () {
    if (!offsetSet) {
      updateStickyOffset();
      offsetSet = true;
    }
  });

  // Also recalculate on resize in case layout changes
  window.addEventListener('resize', function () {
    offsetSet = false;
    updateStickyOffset();
  });
});
    jQuery(document).ready(function($) {
		const menuContainer = $('.category-filter'); // Category filter container
		const menuItems = $('.category-btn'); // All category buttons
		const menuScroll = $('.category-filter'); // Scrollable element

		// Add 'active' class to the first item on page load
		menuItems.first().addClass('active');

		// Handle category button click
		menuItems.on('click', function() {
			const $this = $(this);

			// Remove active class from all items and add to clicked one
			menuItems.removeClass('active');
			$this.addClass('active');

			// Ensure the clicked item smoothly scrolls into view
			$this[0].scrollIntoView({ 
				behavior: 'smooth', 
				block: 'center',
				inline: 'center' 
			});
		});
	});
	document.addEventListener("DOMContentLoaded", function () {
		const categoryFilter = document.querySelector(".category-filter");
		const leftBtn = document.querySelector(".left-btn");
		const rightBtn = document.querySelector(".right-btn");
		const scrollAmount = 100; // Adjust scroll distance

		rightBtn.addEventListener("click", () => {
			categoryFilter.scrollBy({ left: scrollAmount, behavior: "smooth" });
		});

		leftBtn.addEventListener("click", () => {
			categoryFilter.scrollBy({ left: -scrollAmount, behavior: "smooth" });
		});
	});
    jQuery(document).ready(function($) {
        $('.select-variation').on('click', function() {
            const productId = $(this).data('id');

            $('#quickview-modal').fadeIn();
            $('.quickview-loader').show();
            $('.quickview-data').hide().html('');

            $.ajax({
                url: '<?php echo admin_url("admin-ajax.php"); ?>',
                type: 'POST',
                data: {
                    action: 'load_variable_product',
                    product_id: productId
                },
                success: function(response) {
                    $('.quickview-loader').hide();
                    $('.quickview-data').html(response).fadeIn();
                }
            });
        });

        $('.quickview-close').on('click', function() {
            $('#quickview-modal').fadeOut();
        });
    });
jQuery(document).ready(function($) {
    function openQuickview(productId, productType) {
        let actionName = (productType === 'variable') ? 'load_variable_product' : 'load_simple_product';

        $('#quickview-modal').fadeIn();
        $('.quickview-loader').show();
        $('.quickview-data').hide().html('');

        $.ajax({
            url: mix_match_data.ajax_url,
            type: 'POST',
            data: {
                action: actionName,
                product_id: productId,
                nonce: mix_match_data.nonce
            },
            success: function(response) {
                $('.quickview-loader').hide();
                if (response.success !== false) {
                    $('.quickview-data').html(response).fadeIn();
                } else {
                    $('.quickview-data').html('<p>Product could not be loaded.</p>').fadeIn();
                }
            }
        });
    }

    $('.products-grid').on('click', '.open-quickview', function() {
        const productId = $(this).data('id');
        const productType = $(this).data('type'); // 'simple' or 'variable'

        openQuickview(productId, productType);
    });

    $('.quickview-close').on('click', function() {
        $('#quickview-modal').fadeOut();
    });
});

    // Pass PHP data to JavaScript
    var upsellProducts = {
        <?php foreach ($upsell_products as $id => $product) : if ($product) : ?>
        "<?php echo $id; ?>": {
            id: "<?php echo $id; ?>",
            name: "<?php echo esc_js($product->get_name()); ?>",
            price: "<?php echo esc_js($product->get_price()); ?>",
            image: "<?php echo esc_url(get_the_post_thumbnail_url($id, 'medium')); ?>"
        },
        <?php endif; endforeach; ?>
    };


    jQuery(document).ready(function($) {
       // Initialize selection from localStorage
        let selectedProducts = JSON.parse(localStorage.getItem('selectedProducts')) || [];
        let selectedProductsImg = JSON.parse(localStorage.getItem('selectedProductsImg')) || [];
        // Category filter
        $('.category-btn').click(function() {
			$('.category-btn').removeClass('active');
			$(this).addClass('active');

			const category = $(this).data('category');
			if(category === 'all') {
				$('.product-card').show();
			} else {
				$('.product-card').each(function() {
					const categories = $(this).data('categories').toString().split(',');
					if(categories.includes(category.toString())) {
						$(this).show();
					} else {
						$(this).hide();
					}
				});
			}
		});
		// Function to save selected products to localStorage
		function saveSelectedProductsToLocalStorage() {
			localStorage.setItem('selectedProducts', JSON.stringify(selectedProducts));
			localStorage.setItem('selectedProductsImg', JSON.stringify(selectedProductsImg));
		}
		// Search functionality
		$('#product-search').on('input', function() {
			const searchTerm = $(this).val().toLowerCase();
			$('.product-card').each(function() {
				const productName = $(this).find('h3').text().toLowerCase();
				if (productName.includes(searchTerm)) {
					$(this).show();
				} else {
					$(this).hide();
				}
			});
		});
		// Sorting functionality
		$('#product-sorting').change(function() {
			const sortType = $(this).val();
			const $products = $('.product-card').toArray();

			$products.sort((a, b) => {
				const $a = $(a);
				const $b = $(b);

				switch(sortType) {
					case 'price-low':
						return parseFloat($a.find('.add-to-box').data('price')) - 
							   parseFloat($b.find('.add-to-box').data('price'));
					case 'price-high':
						return parseFloat($b.find('.add-to-box').data('price')) - 
							   parseFloat($a.find('.add-to-box').data('price'));
					// Add other sorting cases as needed
					default:
						return 0;
				}
			});

			const $grid = $('.products-grid');
			$grid.empty();
			$products.forEach(product => $grid.append(product));
		});
        // Helper functions
        function saveSelectedProductsToLocalStorage() {
            localStorage.setItem('selectedProducts', JSON.stringify(selectedProducts));
            localStorage.setItem('selectedProductsImg', JSON.stringify(selectedProductsImg));
        }

        function findProductIndex(products, id) {
            return products.findIndex(item => item.id == id || item.variation_id == id);
        }
        function updateQuantityUI(productId, quantity) {
            $(`.product-card[data-id="${productId}"] .quantity-value`).text(quantity);
        }
        function replaceWithAddButton($element, product) {
            $element.replaceWith(`
                <button class="add-to-box" 
                        data-id="${product.id}"
                        data-name="${product.name}"
                        data-price="${product.price}"
                        data-type="simple">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 14 14" fill="none">
                                            <path d="M0.5 7.5V6.5H6.5V0.5H7.5V6.5H13.5V7.5H7.5V13.5H6.5V7.5H0.5Z" fill="black"/>
                                        </svg>
                </button>
            `);
        }

        function replaceWithQuantityControls($element, productId, quantity = 1, isVariation = false) {
            const dataAttr = isVariation ? 'data-variation-id' : 'data-id';
            
            $element.replaceWith(`
                <div class="quantity-controls" ${dataAttr}="${productId}">
                    <button class="quantity-minus" data-id="${productId}">-</button>
                    <span class="quantity-value">${quantity}</span>
                    <button class="quantity-plus" data-id="${productId}">+</button>
                </div>
            `);
        }

        // Event handlers
        $(document).ready(function() {
            updateSelectedProducts();
            
           // Replace the findProductIndex function with this more precise version
        function findProductIndex(productsArray, productId) {
            return productsArray.findIndex(product => 
                product.id == productId || (product.variation_id && product.variation_id == productId)
            );
        }


        // Updated handler for the item-quantity-plus and item-quantity-minus buttons in the cart view
        $(document).on('click', '.item-quantity-plus, .item-quantity-minus', function() {
            const index = $(this).data('index');
            const isPlus = $(this).hasClass('item-quantity-plus');
            
            if (!selectedProducts[index]) return;
            
            const product = selectedProducts[index];
            const isVariation = product.variation_id !== undefined;
            const productId = isVariation ? product.variation_id : product.id;
            
            if (isPlus) {
                // Add a new separate item with the same properties
                const newProduct = JSON.parse(JSON.stringify(product));
                newProduct.quantity = 1; // Each item has quantity of 1
                selectedProducts.push(newProduct);
                
                // Also add to image array
                const newProductImg = JSON.parse(JSON.stringify(selectedProductsImg[index]));
                newProductImg.quantity = 1;
                selectedProductsImg.push(newProductImg);
            } else {
                // Remove this specific item
                selectedProducts.splice(index, 1);
                selectedProductsImg.splice(index, 1);
            }
            
            // Update product counters in the product grid
            updateProductCountersInUI();
            
            // Save to localStorage and update UI
            saveSelectedProductsToLocalStorage();
            updateSelectedProducts();
        });

        // New helper function to update all product counters in the UI
        function updateProductCountersInUI() {
            // Create a counter for each product/variation
            const productCounts = {};
            
            // Count all products and variations
            selectedProducts.forEach(product => {
                const key = product.variation_id ? 
                    `variation-${product.variation_id}` : 
                    `product-${product.id}`;
                    
                if (!productCounts[key]) {
                    productCounts[key] = 0;
                }
                
                productCounts[key]++;
            });
            
            // Update UI counters for single products
            $('.product-card').each(function() {
                const productId = $(this).data('id');
                const counterKey = `product-${productId}`;
                const count = productCounts[counterKey] || 0;
                
                const $controls = $(this).find('.quantity-controls');
                if ($controls.length && count > 0) {
                    $controls.find('.quantity-value').text(count);
                }
            });
            
            // Update UI counters for variable products
            $('.quantity-controls[data-variation-id]').each(function() {
                const variationId = $(this).data('variation-id');
                const counterKey = `variation-${variationId}`;
                const count = productCounts[counterKey] || 0;
                
                if (count > 0) {
                    $(this).find('.quantity-value').text(count);
                }
            });
            
            // Update the total item count in the box header
            const totalItems = selectedProducts.length;
            if ($('.your-box-title').length) {
                $('.your-box-title').text(`YOUR BOX (${totalItems} ITEM${totalItems !== 1 ? 'S' : ''})`);
            }
        }

        // Update the selected products function to also call the counter update
        const originalUpdateSelectedProducts = updateSelectedProducts;
        updateSelectedProducts = function() {
            originalUpdateSelectedProducts();
            updateProductCountersInUI();
        };

        $(document).on('click', '.variation-btn', function () {
            const $button = $(this);
            const $buttonGroup = $button.closest('.variation-buttons');

            // Remove active class from all buttons in the same group
            $buttonGroup.find('.variation-btn').removeClass('selected');

            // Add active class to clicked button
            $button.addClass('selected');
        });

        // Handle variable product add to box - using proper AJAX handling
        $(document).on('click', '.add-to-box-variable', function() {
            const $btn = $(this);
            const parentProductId = $btn.data('id');
            // Find the quantity value in the closest wrapper
            const $wrapper = $btn.closest('.quantity-controls-wrapper');
            const quantity = parseInt($wrapper.find('.quantity-value').text());
            
            console.log('Selected quantity:', quantity); // Debug log
            
            // Get all selected variation attributes
            const attributes = {};
            let allSelected = true;
            
            // Collect all selected attributes
            $('.variation-buttons').each(function() {
                const $group = $(this);
                const attributeName = $group.data('attribute_name');
                const $selectedBtn = $group.find('.variation-btn.selected');
                
                if ($selectedBtn.length === 0) {
                    if (typeof showNotification === 'function') {
                        showNotification('Please select all options before adding to box');
                    } else {
                        alert('Please select all options before adding to box');
                    }
                    allSelected = false;
                    return false; // break loop
                }
                
                // Format the attribute name properly
                const formattedAttrName = attributeName.indexOf('pa_') === 0 ? 
                    `attribute_${attributeName}` : `attribute_${attributeName}`;
                
                attributes[formattedAttrName] = $selectedBtn.data('value');
            });
            
            if (!allSelected) return;
            
            // Find the matching variation ID via AJAX
            $.ajax({
                url: mix_match_data.ajax_url,
                type: 'POST',
                data: {
                    action: 'get_variation_id',
                    product_id: parentProductId,
                    attributes: attributes
                },
                success: function(response) {
                    if (response.success && response.data.variation_id) {
                        const variationId = response.data.variation_id;
                        
                        const baseProduct = {
                            id: variationId,
                            parent_id: parentProductId,
                            name: `${response.data.name} - ${response.data.attribute_summary}`,
                            price: parseFloat(response.data.price),
                            image: response.data.image,
                            variation_id: variationId,
                            attributes: attributes,
                            isUpsell: false,
                            quantity: 1
                        };

                        for (let i = 0; i < quantity; i++) {
                            const product = {...baseProduct};
                            selectedProducts.push(product);
                            selectedProductsImg.push({...product});
                        }

                        saveSelectedProductsToLocalStorage();
                        updateSelectedProducts();

                        // Reset quantity to 1 after adding
                        $wrapper.find('.quantity-value').text('1');

                        // --- Success Feedback ---
                        if (typeof showNotification === 'function') {
                            showNotification('Product added to box!');
                        }

                        // Change button text to "Added" for 2 seconds
                        const originalText = $btn.text();
                        $btn.text('Added').prop('disabled', true);

                        setTimeout(() => {
                            $btn.text(originalText).prop('disabled', false);
                        }, 2000);

                    } else {
                        if (typeof showNotification === 'function') {
                            showNotification(response.data || 'This variation is not available. Please choose another combination.');
                        } else {
                            alert(response.data || 'This variation is not available. Please choose another combination.');
                        }
                    }
                },

                error: function() {
                    if (typeof showNotification === 'function') {
                        showNotification('Error adding product. Please try again.');
                    } else {
                        alert('Error adding product. Please try again.');
                    }
                }
            });
        });

        // Fix for simple product handling - always add as separate items
        $(document).on('click', '.add-to-box', function() {
            const $productCard = $(this).closest('.product-card, .product-cards');
            
            // Attempt to find the image with multiple selectors
            let productImage = $productCard.find('img.primary-image, .gallery-slides .slide.active img, .product-image img, img.attachment-woocommerce_thumbnail').first().attr('src');
            
            // Fallback image selection
            if (!productImage) {
                // Try to find any image in the product card
                productImage = $productCard.find('img').first().attr('src');
            }
            
            // If still no image, use a placeholder
            if (!productImage) {
                productImage = '/wp-content/plugins/woocommerce/assets/images/placeholder.png';
            }
            
            const product = {
                id: $(this).data('id'),
                name: $(this).data('name'),
                price: parseFloat($(this).data('price')),
                image: productImage,
                isUpsell: false,
                quantity: 1
            };
            
            console.log('Adding product:', product); // Debug logging
        
            // Always add as a new item
            selectedProducts.push(product);
            selectedProductsImg.push(product);
        
            saveSelectedProductsToLocalStorage();
            updateSelectedProducts();
            replaceWithQuantityControls($(this), product.id);
            // showNotification('Product added to your box!');
        });

        // Optional: Enhanced product image selection function
        function findProductImage($productCard) {
            const imageCandidates = [
                'img.primary-image',           // Custom class for primary image
                '.gallery-slides .slide.active img', // Active slide in gallery
                '.product-image img',           // Generic product image class
                'img.attachment-woocommerce_thumbnail', // WooCommerce thumbnail
                '.quickview-gallery img',       // Quickview gallery image
                'img.wp-post-image',            // WordPress post image class
                'img'                           // Fallback to first image found
            ];
            
            for (let selector of imageCandidates) {
                const $image = $productCard.find(selector).first();
                if ($image.length && $image.attr('src')) {
                    return $image.attr('src');
                }
            }
            
            // Final fallback to WooCommerce placeholder
            return '/wp-content/plugins/woocommerce/assets/images/placeholder.png';
        }



            jQuery(document).ready(function($) {
                // Initialize the multi-step form
                function initMultiStepForm() {
                    // Hide all steps except the first one
                    $('.mix-step-content').hide();
                    $('#step-2').css('display', 'flex').hide().fadeIn();
                    // Mark the first step as active and completed
                    $('.mix-step[data-step="2"]').addClass('active completed');

                    // Update progress bar initially
                    updateProgressBar();

                    // Handle next button clicks
                    $('.nexts-steps').on('click', function() {
                        let nextStepId = $(this).data('next-step');
                        goToStep(nextStepId.replace('#step-', ''));
                    });
                    // Handle back button clicks
                    $('.prevs-steps').on('click', function() {
                        let prevStepId = $(this).data('next-step');
                        goToStep(prevStepId.replace('#step-', ''));
                    });

                    // Handle step header clicks
                    $('.mix-step').on('click', function() {
                        let currentActiveStep = $('.mix-step.active').data('step');
                        let clickedStep = $(this).data('step');

                        // Only allow going back to previous steps
                        if (clickedStep < currentActiveStep) {
                            goToStep(clickedStep);
                        }
                    });
                }
                // Function to navigate to a specific step
                function goToStep(stepNumber) {
                    // Hide all step content
                    $('.mix-step-content').hide();

                    // Show the target step content
                    $('#step-' + stepNumber).css('display', 'flex').hide().fadeIn(function() {
                        $(window).scrollTop(0);// Ensure it starts from the top
                    });

                    // Update step indicators
                    $('.mix-step').removeClass('active');
                    $('.mix-step[data-step="' + stepNumber + '"]').addClass('active');

                    // Mark all previous steps as completed
                    $('.mix-step').each(function() {
                        let step = $(this).data('step');
                        if (step < stepNumber) {
                            $(this).addClass('completed');
                        } else if (step > stepNumber) {
                            $(this).removeClass('completed');
                        }
                    });

                    // Update the progress bar
                    updateProgressBar();
                }
                // Function to update the progress bar
                function updateProgressBar() {
                    // We have 3 steps: 2, 3, and 4
                    const totalSteps = 3;

                    // Get the current active step number
                    const currentStep = parseInt($('.mix-step.active').data('step'));

                    // Calculate the current step index (0-based)
                    // Step 2 = index 0, Step 3 = index 1, Step 4 = index 2
                    const currentStepIndex = currentStep - 2;

                    // Calculate progress percentage
                    // For step 2: (0/2)*100 = 0%
                    // For step 3: (1/2)*100 = 50%
                    // For step 4: (2/2)*100 = 100%
                    let progressPercentage = (currentStepIndex / (totalSteps - 1)) * 100;

                    // Ensure we don't have negative progress
                    progressPercentage = Math.max(0, progressPercentage);

                    // Animate the progress bar
                    $('.progress-bar').css('width', progressPercentage + '%');

                    // Optional: Update text percentage if you have it
                    // $('.progress-percentage').text(Math.round(progressPercentage) + '%');
                }
                // Initialize the form
                initMultiStepForm();
            }); 

            
            // Handle gallery thumbnails
            $(document).on('click', '.thumb-img', function() {
                const imgSrc = $(this).attr('src');
                $(this).closest('.quickview-gallery').find('.main-img').attr('src', imgSrc.replace('thumbnail', 'medium'));
            });
            
         // Modify the add-upsell click handler to replace the button with quantity controls
            $(document).on('click', '.add-upsell', function() {
                const $button = $(this);
                const upsellId = $button.data('id');
                const upsellProduct = {
                    id: upsellId,
                    name: $button.data('name'),
                    price: parseFloat($button.data('price')),
                    image: $button.data('image'),
                    isUpsell: true,
                    quantity: 1
                };
                
                // Add product to selections
                selectedProducts.push(upsellProduct);
                selectedProductsImg.push(upsellProduct);
                
                // Replace the add button with quantity controls
                $button.replaceWith(`
                    <div class="quantity-controls upsell-controls" data-upsell-id="${upsellId}">
                        <button class="quantity-minus" data-id="${upsellId}">-</button>
                        <span class="quantity-value">1</span>
                        <button class="quantity-plus" data-id="${upsellId}">+</button>
                    </div>
                `);
                
                saveSelectedProductsToLocalStorage();
                updateSelectedProducts();
                showNotification('Premium box added!');
            });

            // Update the quantity-plus and quantity-minus event handler to handle upsell products
            $(document).on('click', '.quantity-plus, .quantity-minus', function() {
                const productId = $(this).data('id');
                const isPlus = $(this).hasClass('quantity-plus');
                const $controls = $(this).closest('.quantity-controls');
                const $quantityValue = $controls.find('.quantity-value');
                
                // Determine if this is a variable product, upsell product, or simple product
                const isVariation = $controls.data('variation-id') !== undefined;
                const isUpsell = $controls.data('upsell-id') !== undefined;
                
                // Find product based on appropriate identifier
                const productToFind = isVariation ? 
                    product => product.variation_id == productId : 
                    (isUpsell ? 
                        product => product.id == productId && product.isUpsell : 
                        product => product.id == productId && !product.isUpsell);
                        
                if (isPlus) {
                    // Add a new separate item
                    const existingIndex = selectedProducts.findIndex(productToFind);
                    
                    if (existingIndex !== -1) {
                        // Clone the existing product
                        const newProduct = JSON.parse(JSON.stringify(selectedProducts[existingIndex]));
                        newProduct.quantity = 1; // Set quantity to 1 for the new item
                        selectedProducts.push(newProduct);
                        
                        // Also clone for the image array
                        const newProductImg = JSON.parse(JSON.stringify(selectedProductsImg[existingIndex]));
                        newProductImg.quantity = 1;
                        selectedProductsImg.push(newProductImg);
                        
                        // Calculate total items of this product
                        const totalItems = selectedProducts.filter(productToFind).length;
                        
                        // Update the counter in the UI for this specific product
                        $quantityValue.text(totalItems);
                    }
                } else {
                    // Find the last occurrence of this product to remove it
                    const indices = [];
                    selectedProducts.forEach((product, index) => {
                        if (productToFind(product)) {
                            indices.push(index);
                        }
                    });
                    
                    if (indices.length > 0) {
                        // Remove the last occurrence
                        const lastIndex = indices[indices.length - 1];
                        selectedProducts.splice(lastIndex, 1);
                        selectedProductsImg.splice(lastIndex, 1);
                        
                        // Calculate remaining items of this product
                        const remainingCount = selectedProducts.filter(productToFind).length;
                        
                        if (remainingCount === 0) {
                            // Restore the add button if no items remain
                            if (isVariation) {
                                // For variable products, get parent ID
                                const parentId = selectedProducts[indices[0]]?.parent_id || productId;
                                
                                $controls.replaceWith(`
                                    <button class="add-to-box-variable" 
                                            data-id="${parentId}">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 14 14" fill="none">
                                            <path d="M0.5 7.5V6.5H6.5V0.5H7.5V6.5H13.5V7.5H7.5V13.5H6.5V7.5H0.5Z" fill="black"/>
                                        </svg>
                                    </button>
                                `);
                            } else if (isUpsell) {
                                // For upsell products, restore add-upsell button
                                const upsellProduct = window.upsellProducts[productId];
                                if (upsellProduct) {
                                    $controls.replaceWith(`
                                        <button class="add-upsell" 
                                                data-id="${upsellProduct.id}" 
                                                data-name="${upsellProduct.name}" 
                                                data-price="${upsellProduct.price}" 
                                                data-image="${upsellProduct.image}">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 14 14" fill="none">
                                                <path d="M0.5 7.5V6.5H6.5V0.5H7.5V6.5H13.5V7.5H7.5V13.5H6.5V7.5H0.5Z" fill="black"/>
                                            </svg>
                                        </button>
                                    `);
                                } else {
                                    // If upsell product not found in the window object, use a generic approach
                                    $controls.replaceWith(`
                                        <button class="add-upsell" 
                                                data-id="${productId}">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 14 14" fill="none">
                                                <path d="M0.5 7.5V6.5H6.5V0.5H7.5V6.5H13.5V7.5H7.5V13.5H6.5V7.5H0.5Z" fill="black"/>
                                            </svg>
                                        </button>
                                    `);
                                }
                            } else {
                                // For simple products, restore original add-to-box button
                                const $productCard = $controls.closest('.product-card');
                                const productInfo = {
                                    id: productId,
                                    name: $productCard.find('h3 a').text() || 'Product',
                                    price: parseFloat($productCard.data('price') || 0)
                                };
                                
                                $controls.replaceWith(`
                                    <button class="add-to-box" 
                                            data-id="${productInfo.id}"
                                            data-name="${productInfo.name}"
                                            data-price="${productInfo.price}"
                                            data-type="simple">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 14 14" fill="none">
                                            <path d="M0.5 7.5V6.5H6.5V0.5H7.5V6.5H13.5V7.5H7.5V13.5H6.5V7.5H0.5Z" fill="black"/>
                                        </svg>
                                    </button>
                                `);
                            }
                        } else {
                            // Update the counter in the UI to show remaining items
                            $quantityValue.text(remainingCount);
                        }
                    }
                }
                
                // Save to localStorage
                saveSelectedProductsToLocalStorage();
                updateSelectedProducts();
            });

            // Update the remove-item handler to also handle upsell items
            $(document).on('click', '.remove-item', function() {
                const index = $(this).data('index');
                const type = $(this).data('type') || 'regular';
                
                if (index !== undefined && selectedProducts[index]) {
                    const product = selectedProducts[index];
                    const isVariation = product.variation_id !== undefined;
                    const isUpsell = product.isUpsell;
                    const productId = isVariation ? product.variation_id : product.id;
                    
                    // Remove the product
                    selectedProducts.splice(index, 1);
                    selectedProductsImg.splice(index, 1);
                    
                    // Find and restore the appropriate button
                    let selector;
                    
                    if (isVariation) {
                        selector = `.quantity-controls[data-variation-id="${productId}"]`;
                    } else if (isUpsell) {
                        selector = `.quantity-controls[data-upsell-id="${productId}"]`;
                    } else {
                        selector = `.product-card[data-id="${productId}"] .quantity-controls`;
                    }
                    
                    const $control = $(selector);
                    
                    if ($control.length) {
                        if (isVariation) {
                            // For variable products, restore add-to-box-variable button
                            $control.replaceWith(`
                                <button class="add-to-box-variable" 
                                        data-id="${product.parent_id}">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 14 14" fill="none">
                                            <path d="M0.5 7.5V6.5H6.5V0.5H7.5V6.5H13.5V7.5H7.5V13.5H6.5V7.5H0.5Z" fill="black"/>
                                        </svg>
                                </button>
                            `);
                        } else if (isUpsell) {
                            // For upsell products, restore add-upsell button
                            const upsellData = window.upsellProducts[productId];
                            if (upsellData) {
                                $control.replaceWith(`
                                    <button class="add-upsell" 
                                            data-id="${upsellData.id}" 
                                            data-name="${upsellData.name}" 
                                            data-price="${upsellData.price}" 
                                            data-image="${upsellData.image}">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 14 14" fill="none">
                                            <path d="M0.5 7.5V6.5H6.5V0.5H7.5V6.5H13.5V7.5H7.5V13.5H6.5V7.5H0.5Z" fill="black"/>
                                        </svg>
                                    </button>
                                `);
                            } else {
                                // Fallback if upsell data not found
                                $control.replaceWith(`
                                    <button class="add-upsell" 
                                            data-id="${productId}">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 14 14" fill="none">
                                            <path d="M0.5 7.5V6.5H6.5V0.5H7.5V6.5H13.5V7.5H7.5V13.5H6.5V7.5H0.5Z" fill="black"/>
                                        </svg>
                                    </button>
                                `);
                            }
                        } else {
                            // For simple products, restore add-to-box button
                            const $productCard = $control.closest('.product-card');
                            const simpleProduct = {
                                id: productId,
                                name: $productCard.find('h3 a').text() || 'Product',
                                price: parseFloat($productCard.data('price') || 0)
                            };
                            
                            $control.replaceWith(`
                                <button class="add-to-box" 
                                        data-id="${simpleProduct.id}"
                                        data-name="${simpleProduct.name}"
                                        data-price="${simpleProduct.price}"
                                        data-type="simple">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 14 14" fill="none">
                                            <path d="M0.5 7.5V6.5H6.5V0.5H7.5V6.5H13.5V7.5H7.5V13.5H6.5V7.5H0.5Z" fill="black"/>
                                        </svg>
                                </button>
                            `);
                        }
                    }
                    
                    saveSelectedProductsToLocalStorage();
                    updateSelectedProducts();
                }
            });

            // Update the updateSelectedProducts function to handle upsell quantity controls
            function updateSelectedProducts() {
                const $container = $('.selected-items');
                const $containerr = $('.selected-itemss');
                const $premiumBoxContainer = $('.choose-premium-box');
                $container.empty();
                $containerr.empty();
                $premiumBoxContainer.html('<h3>Choose Your Premium Box</h3>');

                let total = 0;
                let regularProducts = selectedProducts.filter(product => !product.isUpsell);
                let upsellProducts = selectedProducts.filter(product => product.isUpsell);

                // Update the count to include all products (regular + upsell)
                $('.selected-count').text(selectedProducts.length);

                // Update product quantity indicators in the grid
                $('.product-card').each(function() {
                    const productId = $(this).data('id');
                    const existingProductIndex = findProductIndex(selectedProducts, productId);

                    if (existingProductIndex !== -1 && !selectedProducts[existingProductIndex].isUpsell) {
                        const quantity = selectedProducts[existingProductIndex].quantity;

                        // Check if we need to add quantity controls
                        if (!$(this).find('.quantity-controls').length) {
                            $(this).find('.add-to-box').replaceWith(`
                                <div class="quantity-controls">
                                    <button class="quantity-minus" data-id="${productId}">-</button>
                                    <span class="quantity-value">${quantity}</span>
                                    <button class="quantity-plus" data-id="${productId}">+</button>
                                </div>
                            `);
                        } else {
                            // Just update the quantity
                            $(this).find('.quantity-value').text(quantity);
                        }
                    }
                });

                // Loop through regular products and create HTML with correct indices
                regularProducts.forEach((product, index) => {
                    total += product.price * (product.quantity || 1);

                    // Create HTML for first container with correct index
                    $container.append(`
                        <div class="selected-item">
                            <div class="selected-item-left">
                                <img src="${product.image}" alt="${product.name}" class="selected-item-image">
                                <span class="selected-item-name">${product.name}</span>
                            </div>
                            <div class="selected-item-right">
                                <span class="selected-item-price"><span></span><span>৳${(product.price * (product.quantity || 1)).toFixed(2)}</span></span>
                                
                            </div>
                        </div>
                    `);

                    // Create HTML for second container with correct index
                    $containerr.append(`
                        <div class="selected-item">
                            <div class="selected-item-left">
                                <img src="${product.image}" alt="${product.name}" class="selected-item-image">
                            </div>
                            <div class="selected-item-right">
                                <button class="remove-item" data-index="${index}" data-type="regular"><i class="las la-times"></i></button>
                            </div>
                        </div>
                    `);
                });

                // Now add upsell products to the display and total
                upsellProducts.forEach((product, index) => {
                    total += product.price * (product.quantity || 1);

                    // Calculate the actual index in the selectedProducts array
                    const actualIndex = regularProducts.length + index;

                    // Add upsell products to both containers
                    $container.append(`
                        <div class="selected-item upsell-item">
                            <div class="selected-item-left">
                                <img src="${product.image}" alt="${product.name}" class="selected-item-image">
                                <span class="selected-item-name">${product.name} (Premium)</span>
                            </div>
                            <div class="selected-item-right">
                                <span class="selected-item-price"><span></span><span>৳${(product.price * (product.quantity || 1)).toFixed(2)}</span></span>
                            </div>
                        </div>
                    `);

                    $containerr.append(`
                        <div class="selected-item upsell-item">
                            <div class="selected-item-left">
                                <img src="${product.image}" alt="${product.name}" class="selected-item-image">
                            </div>
                            <div class="selected-item-right">
                                <button class="remove-item" data-index="${actualIndex}" data-type="upsell"><i class="las la-times"></i></button>
                            </div>
                        </div>
                    `);
                });

                // Always show upsell products
                const filteredUpsellProducts = [];

                // Filter upsell products if needed (example: by category)
                for (const id in window.upsellProducts) {
                    if (window.upsellProducts.hasOwnProperty(id)) {
                        const product = window.upsellProducts[id];
                        filteredUpsellProducts.push(product);
                    }
                }

                let upsellHTML = "";

                filteredUpsellProducts.forEach(upsellProduct => {
                    // Check if this upsell product is selected
                    const selectedUpsells = upsellProducts.filter(p => p.id == upsellProduct.id);
                    const isSelected = selectedUpsells.length > 0;
                    const upsellCount = isSelected ? selectedUpsells.length : 0;

                    upsellHTML += `
                        <div class="selected-item upsell-product ${isSelected ? 'selected' : ''}">
                            <div class="selected-item-left">
                                <img src="${upsellProduct.image}" alt="${upsellProduct.name}" class="selected-item-image">
                                <span class="selected-item-name">${upsellProduct.name}</span>
                            </div>
                            <div class="selected-item-right">
                                <span class="selected-item-price">৳${parseFloat(upsellProduct.price).toFixed(2)}</span>
                                ${isSelected 
                                    ? `<div class="quantity-controls upsell-controls" data-upsell-id="${upsellProduct.id}">
                                        <button class="quantity-minus" data-id="${upsellProduct.id}">-</button>
                                        <span class="quantity-value">${upsellCount}</span>
                                        <button class="quantity-plus" data-id="${upsellProduct.id}">+</button>
                                    </div>`
                                    : `<button class="add-upsell" 
                                            data-id="${upsellProduct.id}" 
                                            data-name="${upsellProduct.name}" 
                                            data-price="${upsellProduct.price}" 
                                            data-image="${upsellProduct.image}">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 14 14" fill="none">
                                            <path d="M0.5 7.5V6.5H6.5V0.5H7.5V6.5H13.5V7.5H7.5V13.5H6.5V7.5H0.5Z" fill="black"/>
                                        </svg>
                                    </button>`
                                }
                            </div>
                        </div>
                    `;
                });

                $premiumBoxContainer.html(`
                    <h3>Choose Your Premium Box</h3>
                    ${upsellHTML}
                `);

                // Update total price
                $('.total').html(`<span>Total:</span><span>৳${total.toFixed(2)}</span>`);

                // Enable/disable add to cart button based on selections
                $('.add-to-cart-btn').prop('disabled', selectedProducts.length === 0);
                
                // Call validation for next buttons
                validateNextButtons();
            }
            
            // Navigation between steps
            $('.nexts-steps, .prevs-steps').click(function() {
                const nextStep = $(this).data('next-step');
                
                // Hide all steps
                $('.mix-step-content').hide();
                
                // Show the target step
                $(nextStep).show();
                
                // Update progress bar
                const stepNumber = $(nextStep).attr('id').replace('step-', '');
                $('.mix-step').removeClass('active');
                $(`.mix-step[data-step="${stepNumber}"]`).addClass('active');
                
                // Update progress bar width
                const totalSteps = 3; // Total number of steps (excluding step-1)
                const currentStep = parseInt(stepNumber) - 2; // Adjust for the step numbering
                const progressPercentage = (currentStep / totalSteps) * 100;
                $('.progress-bar').css('width', `${progressPercentage}%`);
                
                // Scroll to top
                $('html, body').animate({
                    scrollTop: $('#mix-step-content').offset().top - 100
                }, 500);
            });
            

            
            // Add to cart functionality
            $('.add-to-cart-btn').click(function() {
                // Prevent multiple clicks
                $(this).prop('disabled', true);
                if ($('.xoo-wsc-loading').length) $('.xoo-wsc-loading').show();
                
                // Prepare products data
                const productsData = selectedProducts.map(product => ({
                    id: product.id,
                    parent_id: product.parent_id || null,
                    quantity: product.quantity || 1,
                    variation_id: product.variation_id || null,
                    isUpsell: product.isUpsell || false
                }));
                
                // Get the current box type if available, otherwise use default
                const boxType = $('#box-type-selector').val() || 'mix-match';
                
                $.ajax({
                    url: mix_match_data.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'add_mix_match_to_cart',
                        nonce: mix_match_data.nonce,
                        products: JSON.stringify(productsData),
                        box_type: boxType // Pass the box type to the server
                    },
                    success: function(response) {
                        if (response.success) {
                            // Display the box identifier in the success message
                            const boxIdentifier = response.data.current_box || 'Box';
                            
                            // Clear selections
                            localStorage.removeItem('selectedProducts');
                            localStorage.removeItem('selectedProductsImg');
                            selectedProducts = [];
                            selectedProductsImg = [];
                            
                            // Reset UI
                            updateSelectedProducts();
                            $('.quantity-controls').each(function() {
                                const $productCard = $(this).closest('.product-card');
                                const product = {
                                    id: $productCard.data('id'),
                                    name: $productCard.find('h3 a').text(),
                                    price: parseFloat($productCard.data('price'))
                                };
                                replaceWithAddButton($(this), product);
                            });
                            
                            // Update cart fragments and open cart
                            $.ajax({
                                url: wc_add_to_cart_params.wc_ajax_url.toString().replace('%%endpoint%%', 'get_refreshed_fragments'),
                                type: 'POST',
                                data: { time: new Date().getTime() },
                                success: function(data) {
                                    if (data && data.fragments) {
                                        // Update fragments
                                        $.each(data.fragments, function(key, value) {
                                            $(key).replaceWith(value);
                                        });
                                        
                                        // Open side cart - handle different versions
                                        if (typeof Xoo !== 'undefined' && Xoo.hasOwnProperty('Wsc')) {
                                            if (data.fragments['.xoo-wsc-container']) {
                                                $('.xoo-wsc-container').replaceWith(data.fragments['.xoo-wsc-container']);
                                            }
                                            if (typeof Xoo.Wsc.openCart === 'function') {
                                                Xoo.Wsc.openCart();
                                            } else {
                                                $('.xoo-wsc-basket').trigger('click');
                                            }
                                        } else if (typeof XooWsc !== 'undefined') {
                                            if (typeof XooWsc.openCart === 'function') {
                                                XooWsc.openCart();
                                            } else {
                                                $('.xoo-wsc-basket').trigger('click');
                                            }
                                        } else {
                                            $('.xoo-wsc-basket, .xoo-wsc-icon-basket').trigger('click');
                                        }
                                        
                                        // Hide loading indicator
                                        if ($('.xoo-wsc-loading').length) $('.xoo-wsc-loading').hide();
                                        
                                        // Trigger events and update UI
                                        $(document.body).trigger('added_to_cart', [data.fragments, data.cart_hash]);
                                        showNotification(`Products added to cart successfully in ${boxIdentifier}!`);
                                        $('.add-to-cart-btn').prop('disabled', false);
                                        
                                        // Return to step 2
                                        $('.mix-step-content').hide();
                                        $('#step-2').show();
                                        $('.mix-step').removeClass('active');
                                        $('.mix-step[data-step="2"]').addClass('active');
                                        $('.progress-bar').css('width', '0%');
                                    }
                                },
                                error: function() {
                                    $('.add-to-cart-btn').prop('disabled', false);
                                    if ($('.xoo-wsc-loading').length) $('.xoo-wsc-loading').hide();
                                    showNotification('Error updating cart. Please try again.');
                                }
                            });
                            window.location.href = wc_add_to_cart_params.checkout_url || '/checkout/';
                        } else {
                            $('.add-to-cart-btn').prop('disabled', false);
                            if ($('.xoo-wsc-loading').length) $('.xoo-wsc-loading').hide();
                            showNotification(response.data.message || 'Error adding products to cart');
                        }
                    },
                    error: function() {
                        $('.add-to-cart-btn').prop('disabled', false);
                        if ($('.xoo-wsc-loading').length) $('.xoo-wsc-loading').hide();
                        showNotification('Server error. Please try again.');
                    }
                });
            });
        });

        // Function to save selected products to localStorage
        function saveSelectedProductsToLocalStorage() {
            localStorage.setItem('selectedProducts', JSON.stringify(selectedProducts));
            localStorage.setItem('selectedProductsImg', JSON.stringify(selectedProductsImg));
        }

        // Function to validate and update next buttons based on current selections
        function validateNextButtons() {
            let regularProducts = selectedProducts.filter(product => !product.isUpsell);
            let upsellProducts = selectedProducts.filter(product => product.isUpsell);

            // Enable/disable step 3 button based on regular products
            $('.nexts-steps[data-next-step="#step-3"]').prop('disabled', regularProducts.length === 0);

            // Enable/disable step 4 button based on upsell products
            $('.nexts-steps[data-next-step="#step-4"]').prop('disabled', upsellProducts.length === 0);
        }



        // Improved function to validate and update next buttons based on current selections
        function validateNextButtons() {
            let regularProducts = selectedProducts.filter(product => !product.isUpsell);
            let upsellProducts = selectedProducts.filter(product => product.isUpsell);

            // Step 3 button - Enable only if there's at least one regular product
            $('.nexts-steps[data-next-step="#step-3"]').prop('disabled', regularProducts.length === 0);

            // Step 4 button - Enable only if there's at least one regular product AND one upsell product
            $('.nexts-steps[data-next-step="#step-4"]').prop('disabled', regularProducts.length === 0 || upsellProducts.length === 0);
            
            // Update Last Confirm button status too
            $('.add-to-cart-btn.last-stages').prop('disabled', regularProducts.length === 0);
        }


        // Initialize - show the first step
        $('.mix-step-content').hide();
        $('#step-2').show();
    });
    // Add event listeners for step navigation
    $(document).ready(function() {
        // When clicking on step navigation
        $('.nexts-steps').on('click', function(e) {
            if ($(this).prop('disabled')) {
                e.preventDefault();
                if ($(this).data('next-step') === '#step-4' && selectedProducts.filter(product => !product.isUpsell).length > 0) {
                    showNotification('Please select a premium box to continue.');
                } else if ($(this).data('next-step') === '#step-3' && selectedProducts.filter(product => !product.isUpsell).length === 0) {
                    showNotification('Please select at least one product to continue.');
                }
                return false;
            }
            
            const nextStep = $(this).data('next-step');
            $('.mix-step-content').hide();
            $(nextStep).show();
            
            // Update progress bar and active step
            const stepNumber = parseInt(nextStep.replace('#step-', ''));
            $('.mix-step').removeClass('active');
            $(`.mix-step[data-step="${stepNumber}"]`).addClass('active');
            
            // Calculate progress percentage
            const progressPercentage = ((stepNumber - 2) / 2) * 100;
            $('.progress-bar').css('width', `${progressPercentage}%`);
        });
        
        // Call validation on initial load
        validateNextButtons();
    });

</script>
<style>
    .quickview-content .add-to-box {
    opacity: 1;
    }
    .quickview-gallery .swiper-slide img {
        width: 100%;
        height: auto;
        display: block;
    }

</style>