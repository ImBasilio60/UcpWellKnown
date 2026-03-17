<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

class UcpItemConverter
{
    private $context;
    private $default_language_id;

    public function __construct()
    {
        $this->context = Context::getContext();
        $this->default_language_id = (int) Configuration::get('PS_LANG_DEFAULT');
    }

    /**
     * Convert a PrestaShop product to UCP Item format
     *
     * @param int $product_id PrestaShop product ID
     * @param int $language_id Language ID (optional, uses default if not provided)
     * @param bool $include_combinations Whether to include combinations as separate items
     * @return array UCP Item structure
     */
    public function convertProductToUcpItem($product_id, $language_id = null, $include_combinations = true)
    {
        $language_id = $language_id ?: $this->default_language_id;

        // Load product data
        $product = new Product($product_id, false, $language_id);

        if (!Validate::isLoadedObject($product)) {
            throw new Exception("Product with ID $product_id not found");
        }

        // Get product images
        $images = $this->getProductImages($product_id, $language_id);

        // Get currency information
        $currency = $this->context->currency;

        // Build base UCP Item
        $ucp_item = [
            'id' => (string) $product_id,
            'title' => $product->name,
            'description' => $product->description ?: $product->description_short ?: '',
            'price' => $this->formatPrice($product->price, $currency),
            'currency' => $currency->iso_code,
            'availability' => $this->getAvailabilityStatus($product),
            'images' => $images,
            'metadata' => [
                'prestashop_id' => (int) $product_id,
                'reference' => $product->reference ?: '',
                'ean13' => $product->ean13 ?: '',
                'upc' => $product->upc ?: '',
                'width' => $product->width,
                'height' => $product->height,
                'depth' => $product->depth,
                'weight' => $product->weight,
                'condition' => $this->getConditionLabel($product->condition),
                'categories' => $this->getProductCategories($product_id, $language_id),
                'tags' => $this->getProductTags($product_id, $language_id),
                'manufacturer' => $this->getManufacturerInfo($product->id_manufacturer),
                'created_at' => $product->date_add,
                'updated_at' => $product->date_upd
            ]
        ];

        // Handle combinations if requested and product has them
        if ($include_combinations && $product->hasAttributes()) {
            $combinations = $this->getProductCombinations($product_id, $language_id, $currency);

            if (!empty($combinations)) {
                // Option 1: Return as separate items (uncomment if preferred)
                // return array_merge([$ucp_item], $combinations);

                // Option 2: Embed combinations in the main item
                $ucp_item['variants'] = $combinations;
                $ucp_item['has_variants'] = true;
            }
        }

        return $ucp_item;
    }

    /**
     * Get product images with URLs
     */
    private function getProductImages($product_id, $language_id)
    {
        $images = [];
        $product_images = Image::getImages($language_id, $product_id);

        foreach ($product_images as $image) {
            $image_obj = new Image($image['id_image']);
            if (Validate::isLoadedObject($image_obj)) {
                $images[] = [
                    'id' => (string) $image_obj->id,
                    'url' => $this->context->link->getImageLink($product_id, $image_obj->id_image, 'large_default'),
                    'thumbnail' => $this->context->link->getImageLink($product_id, $image_obj->id_image, 'small_default'),
                    'alt_text' => $image_obj->legend ?: '',
                    'position' => $image_obj->position,
                    'cover' => $image_obj->cover
                ];
            }
        }

        return $images;
    }

    /**
     * Get product combinations as UCP items
     */
    private function getProductCombinations($product_id, $language_id, $currency)
    {
        $combinations = [];
        $product = new Product($product_id);

        // Get all combinations
        $combination_ids = $product->getAttributeCombinations($language_id);
        $grouped_combinations = [];

        // Group by combination ID
        foreach ($combination_ids as $combination) {
            $id_combination = $combination['id_product_attribute'];
            if (!isset($grouped_combinations[$id_combination])) {
                $grouped_combinations[$id_combination] = [];
            }
            $grouped_combinations[$id_combination][] = $combination;
        }

        // Convert each combination to UCP variant
        foreach ($grouped_combinations as $id_combination => $attributes) {
            $combination_obj = new Combination($id_combination);

            if (Validate::isLoadedObject($combination_obj)) {
                $variant = [
                    'id' => (string) $id_combination,
                    'title' => $this->buildCombinationTitle($attributes),
                    'price' => $this->formatPrice($product->getPrice(true, $id_combination), $currency),
                    'currency' => $currency->iso_code,
                    'availability' => $this->getCombinationAvailability($id_combination),
                    'attributes' => $this->formatCombinationAttributes($attributes),
                    'images' => $this->getCombinationImages($product_id, $id_combination, $language_id),
                    'metadata' => [
                        'prestashop_id' => (int) $id_combination,
                        'reference' => $combination_obj->reference ?: '',
                        'ean13' => $combination_obj->ean13 ?: '',
                        'upc' => $combination_obj->upc ?: '',
                        'weight' => $combination_obj->weight,
                        'default_on' => $combination_obj->default_on,
                        'minimal_quantity' => $combination_obj->minimal_quantity
                    ]
                ];

                $combinations[] = $variant;
            }
        }

        return $combinations;
    }

    /**
     * Format price according to UCP specification
     */
    private function formatPrice($price, $currency)
    {
        // Simple and reliable price formatting
        $formatted_price = number_format($price, 2, '.', ',') . ' ' . $currency->iso_code;

        return [
            'amount' => (float) $price,
            'currency' => $currency->iso_code,
            'formatted' => $formatted_price
        ];
    }

    /**
     * Get product availability status
     */
    private function getAvailabilityStatus($product)
    {
        if ($product->available_for_order) {
            if ($product->quantity > 0 || $product->out_of_stock == 2) {
                return [
                    'status' => 'in_stock',
                    'quantity' => (int) $product->quantity
                ];
            } else {
                return [
                    'status' => 'out_of_stock',
                    'quantity' => 0
                ];
            }
        } else {
            return [
                'status' => 'not_available',
                'quantity' => 0
            ];
        }
    }

    /**
     * Get combination availability status
     */
    private function getCombinationAvailability($id_combination)
    {
        $stock = StockAvailable::getQuantityAvailableByProduct(null, $id_combination);

        if ($stock > 0) {
            return [
                'status' => 'in_stock',
                'quantity' => (int) $stock
            ];
        } else {
            return [
                'status' => 'out_of_stock',
                'quantity' => 0
            ];
        }
    }

    /**
     * Build combination title from attributes
     */
    private function buildCombinationTitle($attributes)
    {
        $attribute_names = [];
        foreach ($attributes as $attribute) {
            $attribute_names[] = $attribute['attribute_name'];
        }
        return implode(', ', $attribute_names);
    }

    /**
     * Format combination attributes for UCP
     */
    private function formatCombinationAttributes($attributes)
    {
        $formatted = [];
        foreach ($attributes as $attribute) {
            $formatted[] = [
                'group' => $attribute['group_name'],
                'name' => $attribute['attribute_name'],
                'group_id' => (string) $attribute['id_attribute_group'],
                'attribute_id' => (string) $attribute['id_attribute']
            ];
        }
        return $formatted;
    }

    /**
     * Get combination-specific images
     */
    private function getCombinationImages($product_id, $id_combination, $language_id)
    {
        $images = [];

        // In PrestaShop, images are not directly linked to combinations
        // We'll return the main product images, but you could customize this
        // to return specific images if your setup uses a different approach

        // Get all product images (combinations typically use the same images as the main product)
        $sql = new DbQuery();
        $sql->select('id_image');
        $sql->from('image');
        $sql->where('id_product = ' . (int)$product_id);
        $sql->orderBy('position', 'ASC');

        $product_images = Db::getInstance()->executeS($sql);

        foreach ($product_images as $image_row) {
            $image_obj = new Image($image_row['id_image']);
            if (Validate::isLoadedObject($image_obj)) {
                $images[] = [
                    'id' => (string) $image_obj->id,
                    'url' => $this->context->link->getImageLink($product_id, $image_obj->id_image, 'large_default'),
                    'thumbnail' => $this->context->link->getImageLink($product_id, $image_obj->id_image, 'small_default'),
                    'alt_text' => $image_obj->legend ?: ''
                ];
            }
        }

        return $images;
    }

    /**
     * Get product condition label
     */
    private function getConditionLabel($condition)
    {
        $conditions = [
            'new' => 'New',
            'used' => 'Used',
            'refurbished' => 'Refurbished'
        ];

        return $conditions[$condition] ?? 'Unknown';
    }

    /**
     * Get product categories
     */
    private function getProductCategories($product_id, $language_id)
    {
        $categories = [];
        $product_categories = Product::getProductCategories($product_id);

        foreach ($product_categories as $category_id) {
            $category = new Category($category_id, $language_id);
            if (Validate::isLoadedObject($category)) {
                $categories[] = [
                    'id' => (string) $category_id,
                    'name' => $category->name,
                    'depth_level' => $category->level_depth,
                    'active' => $category->active
                ];
            }
        }

        return $categories;
    }

    /**
     * Get product tags
     */
    private function getProductTags($product_id, $language_id)
    {
        $tags = [];
        $product_tags = Tag::getProductTags($product_id);

        if (isset($product_tags[$language_id])) {
            foreach ($product_tags[$language_id] as $tag) {
                $tags[] = $tag;
            }
        }

        return $tags;
    }

    /**
     * Get manufacturer information
     */
    private function getManufacturerInfo($manufacturer_id)
    {
        if (!$manufacturer_id) {
            return null;
        }

        $manufacturer = new Manufacturer($manufacturer_id, $this->default_language_id);

        if (Validate::isLoadedObject($manufacturer)) {
            return [
                'id' => (string) $manufacturer_id,
                'name' => $manufacturer->name
            ];
        }

        return null;
    }

    /**
     * Convert multiple products to UCP items
     */
    public function convertMultipleProducts($product_ids, $language_id = null, $include_combinations = true)
    {
        $ucp_items = [];

        foreach ($product_ids as $product_id) {
            try {
                $ucp_items[] = $this->convertProductToUcpItem($product_id, $language_id, $include_combinations);
            } catch (Exception $e) {
                // Log error and continue with next product
                PrestaShopLogger::addLog(
                    'UCP conversion error for product ' . $product_id . ': ' . $e->getMessage(),
                    3, // Error level
                    null,
                    'UcpItemConverter',
                    0,
                    true
                );
            }
        }

        return $ucp_items;
    }
}
