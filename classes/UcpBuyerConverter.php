<?php

class UcpBuyerConverter
{
    private $context;
    private $anonymize_fields = false;

    public function __construct($anonymize_fields = false)
    {
        $this->context = Context::getContext();
        $this->anonymize_fields = $anonymize_fields;
    }

    /**
     * Convert PrestaShop Customer object to UCP Buyer structure
     *
     * @param Customer $customer PrestaShop Customer object
     * @param array $options Conversion options
     * @return array UCP Buyer structure
     */
    public function convertCustomerToUcpBuyer($customer, $options = [])
    {
        if (!Validate::isLoadedObject($customer)) {
            throw new Exception('Invalid customer object');
        }

        $options = array_merge([
            'include_billing_address' => true,
            'include_shipping_address' => true,
            'anonymize' => $this->anonymize_fields,
            'language_id' => $this->context->language->id
        ], $options);

        $buyer = [
            'id' => (string) $customer->id,
            'name' => [
                'first' => $options['anonymize'] ? $this->anonymizeString($customer->firstname) : $customer->firstname,
                'last' => $options['anonymize'] ? $this->anonymizeString($customer->lastname) : $customer->lastname,
                'full' => $options['anonymize'] 
                    ? $this->anonymizeString($customer->firstname) . ' ' . $this->anonymizeString($customer->lastname)
                    : $customer->firstname . ' ' . $customer->lastname
            ],
            'email' => $options['anonymize'] ? $this->anonymizeEmail($customer->email) : $customer->email,
            'phone' => null,
            'addresses' => [],
            'metadata' => [
                'prestashop_customer_id' => (int) $customer->id,
                'gender' => isset($customer->id_gender) ? (int) $customer->id_gender : null,
                'birthday' => $customer->birthday ?: null,
                'newsletter' => (bool) $customer->newsletter,
                'optin' => (bool) $customer->optin,
                'date_add' => $customer->date_add,
                'date_upd' => $customer->date_upd,
                'is_guest' => (bool) $customer->is_guest,
                'company' => $customer->company ?: null,
                'siret' => $customer->siret ?: null,
                'ape' => $customer->ape ?: null,
                'website' => $customer->website ?: null,
                'allow_other_publications' => isset($customer->allow_other_publications) ? (bool) $customer->allow_other_publications : null
            ]
        ];

        // Add billing address
        if ($options['include_billing_address']) {
            $billing_address = $this->getCustomerBillingAddress($customer->id, $options['language_id'], $options['anonymize']);
            if ($billing_address) {
                $buyer['addresses']['billing'] = $billing_address;
                $buyer['phone'] = $billing_address['phone']; // Use billing phone as primary
            }
        }

        // Add shipping address
        if ($options['include_shipping_address']) {
            $shipping_address = $this->getCustomerShippingAddress($customer->id, $options['language_id'], $options['anonymize']);
            if ($shipping_address) {
                $buyer['addresses']['shipping'] = $shipping_address;
                // Use shipping phone if no billing phone available
                if (!$buyer['phone'] && $shipping_address['phone']) {
                    $buyer['phone'] = $shipping_address['phone'];
                }
            }
        }

        // If no addresses found, add empty structure
        if (empty($buyer['addresses'])) {
            $buyer['addresses'] = [
                'billing' => null,
                'shipping' => null
            ];
        }

        return $buyer;
    }

    /**
     * Convert multiple customers to UCP Buyer structure
     *
     * @param array $customer_ids Array of customer IDs
     * @param array $options Conversion options
     * @return array Array of UCP Buyer structures
     */
    public function convertMultipleCustomers($customer_ids, $options = [])
    {
        $buyers = [];
        
        foreach ($customer_ids as $customer_id) {
            try {
                $customer = new Customer($customer_id);
                if (Validate::isLoadedObject($customer)) {
                    $buyers[] = $this->convertCustomerToUcpBuyer($customer, $options);
                }
            } catch (Exception $e) {
                // Skip invalid customers, continue with others
                continue;
            }
        }
        
        return $buyers;
    }

    /**
     * Get customer billing address
     *
     * @param int $customer_id Customer ID
     * @param int $language_id Language ID
     * @param bool $anonymize Whether to anonymize data
     * @return array|null Formatted billing address or null
     */
    private function getCustomerBillingAddress($customer_id, $language_id, $anonymize = false)
    {
        try {
            // Get default billing address
            $billing_address_id = (int) Address::getFirstCustomerAddressId($customer_id);
            
            if (!$billing_address_id) {
                return null;
            }

            $address = new Address($billing_address_id);
            
            if (!Validate::isLoadedObject($address)) {
                return null;
            }

            return $this->formatAddress($address, $language_id, $anonymize);
            
        } catch (Exception $e) {
            return null;
        }
    }

    /**
     * Get customer shipping address
     *
     * @param int $customer_id Customer ID
     * @param int $language_id Language ID
     * @param bool $anonymize Whether to anonymize data
     * @return array|null Formatted shipping address or null
     */
    private function getCustomerShippingAddress($customer_id, $language_id, $anonymize = false)
    {
        try {
            // Get all customer addresses using SQL query
            $sql = new DbQuery();
            $sql->select('a.id_address');
            $sql->from('address', 'a');
            $sql->where('a.id_customer = ' . (int) $customer_id);
            $sql->where('a.deleted = 0');
            $sql->orderBy('a.date_add', 'DESC');
            
            $address_results = Db::getInstance()->executeS($sql);
            
            if (empty($address_results)) {
                return null;
            }

            // Look for shipping address (address that's not billing)
            $billing_address_id = (int) Address::getFirstCustomerAddressId($customer_id);
            $shipping_address = null;
            
            foreach ($address_results as $address_data) {
                if ($address_data['id_address'] != $billing_address_id) {
                    $shipping_address = new Address($address_data['id_address']);
                    if (Validate::isLoadedObject($shipping_address)) {
                        break;
                    }
                }
            }

            // If no separate shipping address found, use billing address
            if (!$shipping_address && $billing_address_id) {
                $shipping_address = new Address($billing_address_id);
            }

            if (!Validate::isLoadedObject($shipping_address)) {
                return null;
            }

            return $this->formatAddress($shipping_address, $language_id, $anonymize);
            
        } catch (Exception $e) {
            return null;
        }
    }

    /**
     * Format address according to UCP specification
     *
     * @param Address $address PrestaShop Address object
     * @param int $language_id Language ID
     * @param bool $anonymize Whether to anonymize data
     * @return array Formatted address
     */
    private function formatAddress($address, $language_id, $anonymize = false)
    {
        $country = new Country($address->id_country);
        $state = $address->id_state ? new State($address->id_state) : null;

        return [
            'id' => (string) $address->id,
            'type' => 'residential', // Could be 'business' if company is set
            'street' => [
                'line1' => $anonymize ? $this->anonymizeString($address->address1) : $address->address1,
                'line2' => ($address->address2 && !$anonymize) ? $address->address2 : null,
                'line3' => null
            ],
            'city' => $anonymize ? $this->anonymizeString($address->city) : $address->city,
            'postal_code' => $anonymize ? $this->anonymizeString($address->postcode) : $address->postcode,
            'region' => $state ? ($anonymize ? $this->anonymizeString($state->name) : $state->name) : null,
            'country' => [
                'code' => Validate::isLoadedObject($country) ? $country->iso_code : '',
                'name' => Validate::isLoadedObject($country) ? $country->name[$language_id] : ''
            ],
            'phone' => $address->phone && !$anonymize ? $address->phone : null,
            'phone_mobile' => $address->phone_mobile && !$anonymize ? $address->phone_mobile : null,
            'company' => $address->company && !$anonymize ? $address->company : null,
            'vat_number' => $address->vat_number && !$anonymize ? $address->vat_number : null,
            'metadata' => [
                'prestashop_address_id' => (int) $address->id,
                'dni' => $address->dni ?: null,
                'alias' => $address->alias ?: null,
                'other' => $address->other && !$anonymize ? $address->other : null,
                'phone_code' => $address->phone ? $this->extractPhoneCode($address->phone) : null,
                'phone_mobile_code' => $address->phone_mobile ? $this->extractPhoneCode($address->phone_mobile) : null
            ]
        ];
    }

    /**
     * Anonymize string for GDPR/testing purposes
     *
     * @param string $string String to anonymize
     * @return string Anonymized string
     */
    private function anonymizeString($string)
    {
        if (empty($string)) {
            return $string;
        }

        $length = strlen($string);
        $visible_chars = min(3, $length);
        $masked_chars = $length - $visible_chars;
        
        return substr($string, 0, $visible_chars) . str_repeat('*', $masked_chars);
    }

    /**
     * Anonymize email for GDPR/testing purposes
     *
     * @param string $email Email to anonymize
     * @return string Anonymized email
     */
    private function anonymizeEmail($email)
    {
        if (empty($email) || strpos($email, '@') === false) {
            return $email;
        }

        list($local, $domain) = explode('@', $email, 2);
        $local_length = strlen($local);
        $visible_chars = min(2, $local_length);
        
        $anonymized_local = substr($local, 0, $visible_chars) . str_repeat('*', $local_length - $visible_chars);
        
        return $anonymized_local . '@' . $domain;
    }

    /**
     * Extract phone code from phone number
     *
     * @param string $phone Phone number
     * @return string|null Phone code or null
     */
    private function extractPhoneCode($phone)
    {
        if (empty($phone)) {
            return null;
        }

        // Extract country code from phone number
        // This is a simple implementation - you might want to use a more sophisticated solution
        if (preg_match('/^\+(\d{1,3})/', $phone, $matches)) {
            return $matches[1];
        } elseif (preg_match('/^(\d{1,3})/', $phone, $matches)) {
            return $matches[1];
        }

        return null;
    }

    /**
     * Set anonymization mode
     *
     * @param bool $anonymize Whether to anonymize fields
     */
    public function setAnonymizeMode($anonymize)
    {
        $this->anonymize_fields = (bool) $anonymize;
    }

    /**
     * Get customer with addresses by ID
     *
     * @param int $customer_id Customer ID
     * @param array $options Conversion options
     * @return array|null UCP Buyer structure or null
     */
    public function getCustomerById($customer_id, $options = [])
    {
        try {
            $customer = new Customer($customer_id);
            if (Validate::isLoadedObject($customer)) {
                return $this->convertCustomerToUcpBuyer($customer, $options);
            }
            return null;
        } catch (Exception $e) {
            return null;
        }
    }

    /**
     * Search customers by email or name
     *
     * @param string $search Search query
     * @param array $options Search options
     * @return array Array of UCP Buyer structures
     */
    public function searchCustomers($search, $options = [])
    {
        $options = array_merge([
            'limit' => 10,
            'offset' => 0,
            'language_id' => $this->context->language->id
        ], $options);

        try {
            $sql = new DbQuery();
            $sql->select('c.id_customer');
            $sql->from('customer', 'c');
            $sql->where('c.active = 1');
            $sql->where('(c.email LIKE "%' . pSQL($search) . '%" OR c.firstname LIKE "%' . pSQL($search) . '%" OR c.lastname LIKE "%' . pSQL($search) . '%")');
            $sql->orderBy('c.date_add', 'DESC');
            $sql->limit($options['limit'], $options['offset']);

            $results = Db::getInstance()->executeS($sql);
            $customer_ids = array_column($results, 'id_customer');

            return $this->convertMultipleCustomers($customer_ids, $options);

        } catch (Exception $e) {
            return [];
        }
    }
}
