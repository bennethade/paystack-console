<?php

/*
|--------------------------------------------------------------------------
| Endpoint registry overlay
|--------------------------------------------------------------------------
|
| The registry is imported from Paystack's official OpenAPI specification
| (resources/openapi/paystack.yaml, https://github.com/PaystackOSS/openapi).
| This file layers on what the spec does not express, all of it taken from
| the API reference at https://paystack.com/docs/api/:
|
|  - categories:  how operations are grouped in the sidebar
|  - docs:        the exact reference-page anchor for each operation
|  - endpoints:   per-operation corrections and behaviour:
|        category   override the category (key of `categories`)
|        workflow   payment_redirect | bank_table | transaction_result
|        features   country features required (config/paystack.php countries.*.features)
|        currencies restrict to these currencies
|        dangerous  moves money / irreversible: extra confirmation in live mode
|        enabled    false to import disabled
|        documented false when the operation is in the spec but not on the reference pages
|        notes      shown on the endpoint page
|        params     [name => partial override, or null to remove]
|        rename     [old name => documented name]
|        add        extra documented parameters (full definitions)
|  - manual:     operations documented on the reference pages but missing
|                from the OpenAPI spec (full definitions)
|
| Where the reference page and the spec disagree, the reference page wins.
|
*/

return [

    'categories' => [
        'Transaction' => ['slug' => 'transactions', 'name' => 'Transactions', 'docs' => 'transaction/', 'sort' => 10],
        'Preauthorization' => ['slug' => 'preauthorization', 'name' => 'Preauthorization', 'docs' => 'preauthorization/', 'sort' => 20, 'description' => 'Hold funds on a card and capture or release them later. Currently ZAR only.'],
        'Charge' => ['slug' => 'charges', 'name' => 'Charges', 'docs' => 'charge/', 'sort' => 30],
        'Bulk Charge' => ['slug' => 'bulk-charges', 'name' => 'Bulk Charges', 'docs' => 'bulk-charge/', 'sort' => 40],
        'Customer' => ['slug' => 'customers', 'name' => 'Customers', 'docs' => 'customer/', 'sort' => 50],
        'Direct Debit' => ['slug' => 'direct-debit', 'name' => 'Direct Debit', 'docs' => 'directdebit/', 'sort' => 60],
        'Dedicated Virtual Account' => ['slug' => 'dedicated-virtual-accounts', 'name' => 'Dedicated Virtual Accounts', 'docs' => 'dedicated-virtual-account/', 'sort' => 70],
        'Banks' => ['slug' => 'banks', 'name' => 'Banks', 'docs' => 'miscellaneous/#bank', 'sort' => 80, 'description' => 'Banks supported by Paystack in each market.'],
        'Bank' => ['slug' => 'verification', 'name' => 'Verification', 'docs' => 'verification/', 'sort' => 85, 'description' => 'Resolve and validate bank accounts and card BINs.'],
        'Transfer Recipient' => ['slug' => 'transfer-recipients', 'name' => 'Transfer Recipients', 'docs' => 'transfer-recipient/', 'sort' => 90],
        'Transfer' => ['slug' => 'transfers', 'name' => 'Transfers', 'docs' => 'transfer/', 'sort' => 100],
        'Balance' => ['slug' => 'transfer-control', 'name' => 'Transfer Control', 'docs' => 'transfer-control/', 'sort' => 110, 'description' => 'Balance, ledger and transfer OTP settings.'],
        'Plan' => ['slug' => 'plans', 'name' => 'Plans', 'docs' => 'plan/', 'sort' => 120],
        'Subscription' => ['slug' => 'subscriptions', 'name' => 'Subscriptions', 'docs' => 'subscription/', 'sort' => 130],
        'Subaccount' => ['slug' => 'subaccounts', 'name' => 'Subaccounts', 'docs' => 'subaccount/', 'sort' => 140],
        'Split' => ['slug' => 'transaction-splits', 'name' => 'Transaction Splits', 'docs' => 'split/', 'sort' => 150],
        'Payment Request' => ['slug' => 'payment-requests', 'name' => 'Payment Requests', 'docs' => 'payment-request/', 'sort' => 160],
        'Product' => ['slug' => 'products', 'name' => 'Products', 'docs' => 'product/', 'sort' => 170],
        'Storefront' => ['slug' => 'storefronts', 'name' => 'Storefronts', 'docs' => 'storefront/', 'sort' => 180],
        'Order' => ['slug' => 'orders', 'name' => 'Orders', 'docs' => 'order/', 'sort' => 190],
        'Page' => ['slug' => 'payment-pages', 'name' => 'Payment Pages', 'docs' => 'page/', 'sort' => 200],
        'Refund' => ['slug' => 'refunds', 'name' => 'Refunds', 'docs' => 'refund/', 'sort' => 210],
        'Dispute' => ['slug' => 'disputes', 'name' => 'Disputes', 'docs' => 'dispute/', 'sort' => 220],
        'Settlement' => ['slug' => 'settlements', 'name' => 'Settlements', 'docs' => 'settlement/', 'sort' => 230],
        'Terminal' => ['slug' => 'terminal', 'name' => 'Terminal', 'docs' => 'terminal/', 'sort' => 240],
        'Virtual Terminal' => ['slug' => 'virtual-terminal', 'name' => 'Virtual Terminal', 'docs' => 'virtual-terminal/', 'sort' => 250],
        'Apple Pay' => ['slug' => 'apple-pay', 'name' => 'Apple Pay', 'docs' => 'apple-pay/', 'sort' => 260],
        'Capitec Pay' => ['slug' => 'capitec-pay', 'name' => 'Capitec Pay', 'docs' => 'capitec-pay/', 'sort' => 270, 'description' => 'South Africa only.'],
        'Integration' => ['slug' => 'integration', 'name' => 'Integration', 'docs' => 'integration/', 'sort' => 280],
        'Miscellaneous' => ['slug' => 'miscellaneous', 'name' => 'Miscellaneous', 'docs' => 'miscellaneous/', 'sort' => 290],
    ],

    /*
    | Reference-page anchors, keyed by OpenAPI operationId. Generated by
    | matching method + path between the spec and the reference pages.
    */
    'docs' => [
        'transaction_initialize' => 'transaction/#initialize',
        'transaction_chargeAuthorization' => 'transaction/#charge-authorization',
        'transaction_partialDebit' => 'transaction/#partial-debit',
        'transaction_verify' => 'transaction/#verify',
        'transaction_list' => 'transaction/#list',
        'transaction_fetch' => 'transaction/#fetch',
        'transaction_timeline' => 'transaction/#view-timeline',
        'transaction_totals' => 'transaction/#totals',
        'transaction_export' => 'transaction/#export',
        'charge_create' => 'charge/#create',
        'charge_submitPin' => 'charge/#submit-pin',
        'charge_submitOtp' => 'charge/#submit-otp',
        'charge_submitPhone' => 'charge/#submit-phone',
        'charge_submitBirthday' => 'charge/#submit-birthday',
        'charge_submitAddress' => 'charge/#submit-address',
        'charge_check' => 'charge/#check',
        'bulkCharge_initiate' => 'bulk-charge/#initiate',
        'bulkCharge_list' => 'bulk-charge/#list',
        'bulkCharge_fetch' => 'bulk-charge/#fetch-batch',
        'bulkCharge_charges' => 'bulk-charge/#fetch-charge',
        'bulkCharge_pause' => 'bulk-charge/#pause',
        'bulkCharge_resume' => 'bulk-charge/#resume',
        'subaccount_create' => 'subaccount/#create',
        'subaccount_list' => 'subaccount/#list',
        'subaccount_fetch' => 'subaccount/#fetch',
        'subaccount_update' => 'subaccount/#update',
        'split_create' => 'split/#create',
        'split_list' => 'split/#list',
        'split_fetch' => 'split/#fetch',
        'split_update' => 'split/#update',
        'split_addSubaccount' => 'split/#add-subaccount',
        'split_removeSubaccount' => 'split/#remove-subaccount',
        'terminal_sendEvent' => 'terminal/#send-event',
        'terminal_fetchEventStatus' => 'terminal/#fetch-event-status',
        'terminal_fetchTerminalStatus' => 'terminal/#fetch-terminal-status',
        'terminal_list' => 'terminal/#list',
        'terminal_fetch' => 'terminal/#fetch',
        'terminal_update' => 'terminal/#update',
        'terminal_commission' => 'terminal/#commission',
        'terminal_decommission' => 'terminal/#decommission',
        'virtualTerminal_create' => 'virtual-terminal/#create',
        'virtualTerminal_list' => 'virtual-terminal/#list',
        'virtualTerminal_fetch' => 'virtual-terminal/#fetch',
        'virtualTerminal_update' => 'virtual-terminal/#update',
        'virtualTerminal_deactivate' => 'virtual-terminal/#deactivate',
        'virtualTerminal_destinationAssign' => 'virtual-terminal/#assign-destination',
        'virtualTerminal_destinationUnassign' => 'virtual-terminal/#unassign-destination',
        'virtualTerminal_addSplitCode' => 'virtual-terminal/#add-split-code',
        'virtualTerminal_deleteSplitCode' => 'virtual-terminal/#remove-split-code',
        'customer_create' => 'customer/#create',
        'customer_list' => 'customer/#list',
        'customer_fetch' => 'customer/#fetch',
        'customer_update' => 'customer/#update',
        'customer_riskAction' => 'customer/#whitelist-blacklist',
        'customer_validate' => 'customer/#validate',
        'customer_initializeAuthorization' => 'customer/#initialize-authorization',
        'customer_verifyAuthorization' => 'customer/#verify-authorization',
        'customer_deactivateAuthorization' => 'customer/#deactivate-authorization',
        'customer_initializeDirectDebit' => 'customer/#initialize-direct-debit',
        'customer_directDebitActivationCharge' => 'customer/#directdebit-activation-charge',
        'customer_fetchMandateAuthorizations' => 'customer/#directdebit-mandate-authorizations',
        'directdebit_triggerActivationCharge' => 'directdebit/#activation-charge',
        'directdebit_listMandateAuthorizations' => 'directdebit/#mandate-authorizations',
        'dedicatedAccount_create' => 'dedicated-virtual-account/#create',
        'dedicatedAccount_list' => 'dedicated-virtual-account/#list',
        'dedicatedAccount_assign' => 'dedicated-virtual-account/#assign',
        'dedicatedAccount_fetch' => 'dedicated-virtual-account/#fetch',
        'dedicatedAccount_deactivate' => 'dedicated-virtual-account/#deactivate',
        'dedicatedAccount_requery' => 'dedicated-virtual-account/#requery',
        'dedicatedAccount_addSplit' => 'dedicated-virtual-account/#add-split',
        'dedicatedAccount_removeSplit' => 'dedicated-virtual-account/#remove-split',
        'dedicatedAccount_availableProviders' => 'dedicated-virtual-account/#providers',
        'applePay_registerDomain' => 'apple-pay/#register-domain',
        'applePay_listDomain' => 'apple-pay/#list-domains',
        'applePay_unregisterDomain' => 'apple-pay/#unregister-domain',
        'plan_create' => 'plan/#create',
        'plan_list' => 'plan/#list',
        'plan_fetch' => 'plan/#fetch',
        'plan_update' => 'plan/#update',
        'subscription_create' => 'subscription/#create',
        'subscription_list' => 'subscription/#list',
        'subscription_fetch' => 'subscription/#fetch',
        'subscription_disable' => 'subscription/#disable',
        'subscription_enable' => 'subscription/#enable',
        'subscription_manageLink' => 'subscription/#manage-link',
        'subscription_manageEmail' => 'subscription/#manage-email',
        'transferrecipient_create' => 'transfer-recipient/#create',
        'transferrecipient_list' => 'transfer-recipient/#list',
        'transferrecipient_bulk' => 'transfer-recipient/#bulk',
        'transferrecipient_fetch' => 'transfer-recipient/#fetch',
        'transferrecipient_update' => 'transfer-recipient/#update',
        'transferrecipient_delete' => 'transfer-recipient/#delete',
        'transfer_initiate' => 'transfer/#initiate',
        'transfer_list' => 'transfer/#list',
        'transfer_finalize' => 'transfer/#finalize',
        'transfer_bulk' => 'transfer/#bulk',
        'transfer_fetch' => 'transfer/#fetch',
        'transfer_verify' => 'transfer/#verify',
        'transfer_resendOtp' => 'transfer-control/#resend-otp',
        'transfer_disableOtp' => 'transfer-control/#disable-otp',
        'transfer_disableOtpFinalize' => 'transfer-control/#finalize-disable-otp',
        'transfer_enableOtp' => 'transfer-control/#enable-otp',
        'balance_fetch' => 'transfer-control/#balance',
        'balance_ledger' => 'transfer-control/#balance-ledger',
        'paymentRequest_create' => 'payment-request/#create',
        'paymentRequest_list' => 'payment-request/#list',
        'paymentRequest_fetch' => 'payment-request/#fetch',
        'paymentRequest_update' => 'payment-request/#update',
        'paymentRequest_verify' => 'payment-request/#verify',
        'paymentRequest_notify' => 'payment-request/#send-notification',
        'paymentRequest_totals' => 'payment-request/#total',
        'paymentRequest_finalize' => 'payment-request/#finalize',
        'paymentRequest_archive' => 'payment-request/#archive',
        'product_create' => 'product/#create',
        'product_list' => 'product/#list',
        'product_fetch' => 'product/#fetch',
        'product_update' => 'product/#update',
        'storefront_create' => 'storefront/#create',
        'storefront_list' => 'storefront/#list',
        'storefront_fetch' => 'storefront/#fetch',
        'storefront_update' => 'storefront/#update',
        'storefront_delete' => 'storefront/#delete',
        'storefront_verifySlug' => 'storefront/#verify-slug',
        'storefront_fetchOrders' => 'storefront/#fetch-orders',
        'storefront_addProducts' => 'storefront/#add-products',
        'storefront_listProducts' => 'storefront/#list-products',
        'storefront_publish' => 'storefront/#publish',
        'storefront_duplicate' => 'storefront/#duplicate',
        'order_create' => 'order/#create',
        'order_list' => 'order/#list',
        'order_fetch' => 'order/#fetch',
        'order_product' => 'order/#fetch-product-orders',
        'order_validate' => 'order/#validate',
        'page_create' => 'page/#create',
        'page_list' => 'page/#list',
        'page_fetch' => 'page/#fetch',
        'page_update' => 'page/#update',
        'page_checkSlugAvailability' => 'page/#check-slug',
        'page_addProducts' => 'page/#add-products',
        'settlements_fetch' => 'settlement/#list',
        'settlements_transaction' => 'settlement/#transactions',
        'integration_fetchPaymentSessionTimeout' => 'integration/#fetch-timeout',
        'integration_updatePaymentSessionTimeout' => 'integration/#update-timeout',
        'refund_create' => 'refund/#create',
        'refund_list' => 'refund/#list',
        'refund_retry' => 'refund/#retry',
        'refund_fetch' => 'refund/#fetch',
        'dispute_list' => 'dispute/#list',
        'dispute_fetch' => 'dispute/#fetch',
        'dispute_update' => 'dispute/#update',
        'dispute_uploadUrl' => 'dispute/#upload-url',
        'dispute_download' => 'dispute/#export',
        'dispute_transaction' => 'dispute/#transaction',
        'dispute_resolve' => 'dispute/#resolve',
        'dispute_evidence' => 'dispute/#evidence',
        'bank_list' => 'miscellaneous/#bank',
        'bank_resolveAccountNumber' => 'verification/#resolve-account',
        'bank_validateAccountNumber' => 'verification/#validate-account',
        'miscellaneous_resolveCardBin' => 'verification/#resolve-card',
        'miscellaneous_listCountries' => 'miscellaneous/#country',
        'miscellaneous_avs' => 'miscellaneous/#avs-states',
    ],

    'endpoints' => [

        // Transactions -----------------------------------------------------
        'transaction.initialize' => [
            'workflow' => 'payment_redirect',
        ],
        'transaction.verify' => [
            'workflow' => 'transaction_result',
        ],
        'transaction.list' => [
            'rename' => ['per_page' => 'perPage', 'terminal_id' => 'terminalid'],
            'add' => [
                ['name' => 'customer', 'in' => 'query', 'type' => 'integer', 'description' => 'Specify an ID for the customer whose transactions you want to retrieve'],
            ],
        ],
        'transaction.totals' => [
            'add' => [
                ['name' => 'perPage', 'in' => 'query', 'type' => 'integer', 'description' => 'Specify how many records you want to retrieve per page. Defaults to 50.'],
                ['name' => 'page', 'in' => 'query', 'type' => 'integer', 'description' => 'Specify exactly what page you want to retrieve. Defaults to 1.'],
            ],
        ],
        'transaction.export' => [
            'add' => [
                ['name' => 'perPage', 'in' => 'query', 'type' => 'integer', 'description' => 'Specify how many records you want to retrieve per page. Defaults to 50.'],
                ['name' => 'page', 'in' => 'query', 'type' => 'integer', 'description' => 'Specify exactly what page you want to retrieve. Defaults to 1.'],
                ['name' => 'currency', 'in' => 'query', 'type' => 'string', 'binding' => 'currency', 'description' => 'Specify the transaction currency to export'],
                ['name' => 'amount', 'in' => 'query', 'type' => 'integer', 'description' => 'Filter transactions by amount, using the supported currency'],
                ['name' => 'settled', 'in' => 'query', 'type' => 'boolean', 'description' => 'Set to true to export only settled transactions. false for pending transactions. Leave undefined to export all transactions'],
                ['name' => 'payment_page', 'in' => 'query', 'type' => 'integer', 'description' => 'Specify a payment page\'s id to export only transactions conducted on said page'],
            ],
        ],
        'transaction.charge_authorization' => [
            'dangerous' => true,
            'add' => [
                ['name' => 'channels', 'in' => 'body', 'type' => 'array', 'binding' => 'channels', 'items' => ['type' => 'string', 'enum' => ['card', 'bank']], 'description' => 'Send us \'card\' or \'bank\' or \'card\',\'bank\' as an array to specify what options to show the user paying'],
            ],
        ],
        'transaction.partial_debit' => ['dangerous' => true],

        // Charges ----------------------------------------------------------
        'charge.create' => [
            'dangerous' => true,
            'notes' => 'The Charge API is available in all markets for PCI-compliant businesses. Each channel object (bank, mobile_money, ussd, eft, …) is only usable in the markets where that channel is available.',
            'add' => [
                ['name' => 'split_code', 'in' => 'body', 'type' => 'string', 'description' => 'The split code of the transaction split'],
                ['name' => 'bearer', 'in' => 'body', 'type' => 'string', 'enum' => ['account', 'subaccount'], 'description' => 'Who bears Paystack charges'],
                ['name' => 'bank_transfer', 'in' => 'body', 'type' => 'object', 'description' => 'Takes the settings for the Pay with Transfer (PwT) channel (Nigeria and Ghana)'],
                ['name' => 'qr', 'in' => 'body', 'type' => 'object', 'description' => 'Takes a provider parameter with the value set to the QR scheme (South Africa)'],
            ],
        ],
        'charge.submit_pin' => ['dangerous' => true],
        'charge.submit_otp' => ['dangerous' => true],
        'charge.submit_address' => [
            'rename' => ['zip_code' => 'zipcode'],
        ],
        'bulk_charge.initiate' => [
            'dangerous' => true,
            'notes' => 'The request body is a JSON array of charges (one object per authorization), not an object. Use "Insert template" in the body editor to start.',
            // Sample request from https://paystack.com/docs/api/bulk-charge/#initiate
            'example' => [
                ['authorization' => 'AUTH_ncx8hews93', 'amount' => 2500, 'reference' => 'dam1266638dhhd'],
                ['authorization' => 'AUTH_xfuz7dy4b9', 'amount' => 1500, 'reference' => 'dam1266638dhhe'],
            ],
        ],

        // Customers & direct debit ----------------------------------------
        'customer.initialize_authorization' => [
            'workflow' => 'payment_redirect',
            'features' => ['direct_debit'],
        ],
        'customer.verify_authorization' => ['features' => ['direct_debit']],
        'customer.initialize_direct_debit' => ['workflow' => 'payment_redirect', 'features' => ['direct_debit']],
        'customer.direct_debit_activation_charge' => ['features' => ['direct_debit'], 'dangerous' => true],
        'customer.fetch_mandate_authorizations' => ['features' => ['direct_debit']],
        'directdebit.trigger_activation_charge' => ['features' => ['direct_debit'], 'dangerous' => true],
        'directdebit.list_mandate_authorizations' => ['features' => ['direct_debit']],
        'customer.validate' => [
            'params' => ['country' => ['binding' => 'country_code']],
        ],

        // Dedicated virtual accounts (Nigeria and Ghana) -------------------
        'dedicated_account.create' => [
            'features' => ['dedicated_virtual_account'],
            'add' => [
                ['name' => 'first_name', 'in' => 'body', 'type' => 'string', 'description' => 'Customer\'s first name'],
                ['name' => 'last_name', 'in' => 'body', 'type' => 'string', 'description' => 'Customer\'s last name'],
                ['name' => 'phone', 'in' => 'body', 'type' => 'string', 'description' => 'Customer\'s phone number'],
            ],
        ],
        'dedicated_account.assign' => [
            'features' => ['dedicated_virtual_account'],
            'params' => ['country' => ['binding' => 'country_code']],
        ],
        'dedicated_account.list' => ['features' => ['dedicated_virtual_account']],
        'dedicated_account.fetch' => ['features' => ['dedicated_virtual_account']],
        'dedicated_account.deactivate' => ['features' => ['dedicated_virtual_account'], 'dangerous' => true],
        'dedicated_account.requery' => ['features' => ['dedicated_virtual_account']],
        'dedicated_account.add_split' => [
            'features' => ['dedicated_virtual_account'],
            'params' => ['account_number' => null],
            'add' => [
                ['name' => 'customer', 'in' => 'body', 'type' => 'string', 'required' => true, 'description' => 'Customer ID or code'],
                ['name' => 'preferred_bank', 'in' => 'body', 'type' => 'string', 'description' => 'The bank slug for preferred bank. To get a list of available banks, use the List Providers endpoint'],
            ],
        ],
        'dedicated_account.remove_split' => ['features' => ['dedicated_virtual_account']],
        'dedicated_account.available_providers' => ['features' => ['dedicated_virtual_account']],

        // Banks & verification ---------------------------------------------
        'bank.list' => [
            'category' => 'Banks',
            'workflow' => 'bank_table',
            'features' => ['bank_list'],
            'params' => ['country' => ['binding' => 'bank_country']],
        ],
        'bank.resolve_account_number' => [
            'features' => ['resolve_account'],
            'params' => [
                'account_number' => ['type' => 'string', 'required' => true, 'description' => 'Account Number'],
                'bank_code' => ['type' => 'string', 'required' => true, 'description' => 'You can get the list of bank codes by calling the List Banks endpoint'],
            ],
        ],
        'bank.validate_account_number' => [
            'features' => ['validate_account'],
            'params' => ['country_code' => ['binding' => 'country_code']],
        ],
        'miscellaneous.resolve_card_bin' => ['category' => 'Bank'],

        // Transfers ----------------------------------------------------------
        'transferrecipient.create' => [
            'params' => [
                'type' => ['binding' => 'recipient_type'],
                'account_number' => ['required' => false, 'description' => 'Required for all recipient types except authorization'],
                'bank_code' => ['required' => false, 'description' => 'Required for all recipient types except authorization. You can get the list of Bank Codes by calling the List Banks endpoint.'],
            ],
        ],
        'transferrecipient.list' => ['rename' => ['per_page' => 'perPage']],
        'transfer.initiate' => [
            'features' => ['transfers'],
            'dangerous' => true,
            'add' => [
                ['name' => 'account_reference', 'in' => 'body', 'type' => 'string', 'description' => 'A unique identifier required in Kenya for MPESA Paybill and Till transfers'],
            ],
        ],
        'transfer.finalize' => ['features' => ['transfers'], 'dangerous' => true],
        'transfer.bulk' => ['features' => ['transfers'], 'dangerous' => true],
        'transfer.list' => ['features' => ['transfers'], 'rename' => ['per_page' => 'perPage']],
        'transfer.fetch' => ['features' => ['transfers']],
        'transfer.verify' => ['features' => ['transfers']],
        'transfer.export_transfer' => [
            'features' => ['transfers'],
            'enabled' => false,
            'documented' => false,
            'notes' => 'This operation is in Paystack\'s OpenAPI specification but not on the API reference pages. It is imported disabled; an admin can enable it.',
        ],
        'transfer.resend_otp' => ['category' => 'Balance', 'features' => ['transfers']],
        'transfer.disable_otp' => ['category' => 'Balance', 'features' => ['transfers']],
        'transfer.disable_otp_finalize' => ['category' => 'Balance', 'features' => ['transfers']],
        'transfer.enable_otp' => ['category' => 'Balance', 'features' => ['transfers']],

        // Plans & subscriptions ----------------------------------------------
        'plan.create' => [
            'params' => ['interval' => ['enum' => ['hourly', 'daily', 'weekly', 'monthly', 'quarterly', 'biannually', 'annually']]],
        ],
        'plan.list' => [
            'params' => ['interval' => ['enum' => ['hourly', 'daily', 'weekly', 'monthly', 'quarterly', 'biannually', 'annually']]],
            'add' => [
                ['name' => 'status', 'in' => 'query', 'type' => 'string', 'description' => 'Filter list by plans with specified status'],
            ],
        ],
        'plan.update' => [
            'params' => [
                'interval' => ['enum' => ['hourly', 'daily', 'weekly', 'monthly', 'quarterly', 'biannually', 'annually']],
                'description' => ['type' => 'string', 'description' => 'A description for this plan'],
            ],
            'add' => [
                ['name' => 'update_existing_subscriptions', 'in' => 'body', 'type' => 'boolean', 'description' => 'Set to true if you want the existing subscriptions to use the new changes. Set to false and only new subscriptions will be changed. Defaults to true when not set.'],
            ],
        ],
        'subscription.create' => ['dangerous' => true],

        // Splits -------------------------------------------------------------
        'split.list' => [
            'rename' => ['per_page' => 'perPage'],
            'add' => [
                ['name' => 'sort_by', 'in' => 'query', 'type' => 'string', 'description' => 'Sort by name, defaults to createdAt date'],
            ],
        ],

        // Payment requests, products, storefronts, orders, pages -------------
        'payment_request.create' => [
            'add' => [
                ['name' => 'redirect_url', 'in' => 'body', 'type' => 'string', 'format' => 'uri', 'description' => 'Specify a URL for Paystack to redirect to on a successful payment'],
                ['name' => 'metadata', 'in' => 'body', 'type' => 'string', 'description' => 'Stringified JSON object of custom data'],
            ],
        ],
        'payment_request.list' => [
            'add' => [
                ['name' => 'include_archive', 'in' => 'query', 'type' => 'string', 'description' => 'Show archived payment requests'],
            ],
        ],
        'payment_request.finalize' => [
            'body_type' => 'json',
            'add' => [
                ['name' => 'send_notification', 'in' => 'body', 'type' => 'boolean', 'description' => 'Indicates whether Paystack sends an email notification to customer. Defaults to true'],
            ],
        ],
        'product.delete' => [
            'enabled' => false,
            'documented' => false,
            'dangerous' => true,
            'notes' => 'This operation is in Paystack\'s OpenAPI specification but not on the API reference pages. It is imported disabled; an admin can enable it.',
        ],
        'storefront.create' => [
            'params' => ['slug' => ['required' => false]],
        ],
        'storefront.delete' => ['dangerous' => true],
        'order.create' => [
            'notes' => 'Parameters follow the API reference page, which differs from the OpenAPI specification for this operation.',
            'params' => [
                'email' => null, 'first_name' => null, 'last_name' => null, 'phone' => null, 'currency' => null,
                'items' => null, 'shipping' => null, 'is_gift' => null, 'pay_for_me' => null,
            ],
            'add' => [
                ['name' => 'customer', 'in' => 'body', 'type' => 'string', 'required' => true, 'description' => 'Customer code or ID'],
                ['name' => 'line_items', 'in' => 'body', 'type' => 'array', 'required' => true, 'items' => ['type' => 'object', 'properties' => [['name' => 'product', 'type' => 'integer'], ['name' => 'quantity', 'type' => 'integer']]], 'description' => 'An array of line items. Each item should have product (product ID) and quantity'],
            ],
        ],
        'page.add_products' => [
            'rename' => ['products' => 'product'],
            'params' => ['products' => ['items' => ['type' => 'integer'], 'description' => 'Ids of all the products']],
        ],

        // Refunds, disputes, settlements -------------------------------------
        'refund.create' => ['dangerous' => true],
        'refund.retry' => ['dangerous' => true],
        'refund.list' => [
            'add' => [
                ['name' => 'transaction', 'in' => 'query', 'type' => 'string', 'description' => 'The transaction ID of the refunded transaction'],
                ['name' => 'currency', 'in' => 'query', 'type' => 'string', 'binding' => 'currency', 'description' => 'Any of the supported currencies'],
            ],
        ],
        'dispute.resolve' => ['dangerous' => true],
        'dispute.download' => [
            'add' => [
                ['name' => 'transaction', 'in' => 'query', 'type' => 'string', 'description' => 'Transaction Id'],
            ],
        ],
        'settlements.fetch' => [
            'add' => [
                ['name' => 'status', 'in' => 'query', 'type' => 'string', 'enum' => ['success', 'processing', 'pending', 'failed'], 'description' => 'Fetch settlements with a specific status'],
                ['name' => 'subaccount', 'in' => 'query', 'type' => 'string', 'description' => 'Provide a subaccount ID to export only settlements for that subaccount. Set to none to export only transactions for the account.'],
            ],
        ],
        'settlements.transaction' => [
            'add' => [
                ['name' => 'perPage', 'in' => 'query', 'type' => 'integer', 'description' => 'Specify how many records you want to retrieve per page. Defaults to 50.'],
                ['name' => 'page', 'in' => 'query', 'type' => 'integer', 'description' => 'Specify exactly what page you want to retrieve. Defaults to 1.'],
            ],
        ],

        // Terminal -----------------------------------------------------------
        'terminal.list' => ['rename' => ['per_page' => 'perPage']],
        'virtual_terminal.create' => [
            'add' => [
                ['name' => 'currency', 'in' => 'body', 'type' => 'array', 'items' => ['type' => 'string'], 'description' => 'The transaction currency for the Virtual Terminal. Defaults to your integration currency'],
                ['name' => 'custom_fields', 'in' => 'body', 'type' => 'array', 'items' => ['type' => 'object', 'properties' => [['name' => 'display_name', 'type' => 'string'], ['name' => 'variable_name', 'type' => 'string']]], 'description' => 'An array of objects representing custom fields to display on the form'],
            ],
        ],
        'virtual_terminal.list' => [
            'add' => [
                ['name' => 'status', 'in' => 'query', 'type' => 'string', 'enum' => ['active', 'inactive'], 'description' => 'Filter by status'],
                ['name' => 'search', 'in' => 'query', 'type' => 'string', 'description' => 'Search query string'],
                ['name' => 'next', 'in' => 'query', 'type' => 'string', 'description' => 'Cursor for next page'],
                ['name' => 'previous', 'in' => 'query', 'type' => 'string', 'description' => 'Cursor for previous page'],
            ],
        ],
    ],

    /*
    | Operations documented on the reference pages but absent from the spec.
    */
    'manual' => [
        [
            'key' => 'preauthorization.initialize',
            'category' => 'Preauthorization',
            'name' => 'Initialize Preauthorization',
            'description' => 'Initialize a preauthorization transaction for a new customer',
            'method' => 'POST',
            'path' => '/preauthorization/initialize',
            'docs' => 'preauthorization/#initialize',
            'workflow' => 'payment_redirect',
            'features' => ['preauthorization'],
            'currencies' => ['ZAR'],
            'parameters' => [
                ['name' => 'amount', 'in' => 'body', 'type' => 'string', 'required' => true, 'description' => 'Amount should be in the subunit of the supported currency'],
                ['name' => 'email', 'in' => 'body', 'type' => 'string', 'format' => 'email', 'required' => true, 'description' => 'Customer\'s email address'],
                ['name' => 'currency', 'in' => 'body', 'type' => 'string', 'enum' => ['ZAR'], 'required' => true, 'binding' => 'currency', 'description' => 'Only ZAR is supported for now'],
                ['name' => 'reference', 'in' => 'body', 'type' => 'string', 'description' => 'Unique transaction reference. Only -, ., = and alphanumeric characters allowed.'],
                ['name' => 'callback_url', 'in' => 'body', 'type' => 'string', 'format' => 'uri', 'binding' => 'callback_url', 'description' => 'Fully qualified url, e.g. https://example.com/. Use this to override the callback url provided on the dashboard for this transaction'],
                ['name' => 'metadata', 'in' => 'body', 'type' => 'string', 'description' => 'Stringified JSON object of custom data'],
                ['name' => 'split_code', 'in' => 'body', 'type' => 'string', 'description' => 'The split code of the transaction split. e.g. SPL_98WF13Eb3w'],
                ['name' => 'split', 'in' => 'body', 'type' => 'object', 'description' => 'A dynamic split object'],
                ['name' => 'transaction_charge', 'in' => 'body', 'type' => 'integer', 'description' => 'An amount used to override the split configuration for a single split payment'],
                ['name' => 'bearer', 'in' => 'body', 'type' => 'string', 'enum' => ['account', 'subaccount'], 'description' => 'Specifies who will pay the Paystack transaction charges for this transaction. Defaults to account.'],
                ['name' => 'expire_action', 'in' => 'body', 'type' => 'string', 'enum' => ['capture', 'release'], 'description' => 'Specify the action to take on the expiry date. Defaults to release.'],
                ['name' => 'expire_after_days', 'in' => 'body', 'type' => 'integer', 'description' => 'The number of days until the expire_action is executed. The minimum is 1 day. Defaults to 5 days.'],
            ],
        ],
        [
            'key' => 'preauthorization.capture',
            'category' => 'Preauthorization',
            'name' => 'Capture Preauthorization',
            'description' => 'Charge a preauthorized transaction upon service delivery',
            'method' => 'POST',
            'path' => '/preauthorization/capture',
            'docs' => 'preauthorization/#capture',
            'features' => ['preauthorization'],
            'currencies' => ['ZAR'],
            'dangerous' => true,
            'parameters' => [
                ['name' => 'reference', 'in' => 'body', 'type' => 'string', 'required' => true, 'description' => 'Unique transaction reference. Only -, ., = and alphanumeric characters allowed.'],
                ['name' => 'currency', 'in' => 'body', 'type' => 'string', 'enum' => ['ZAR'], 'required' => true, 'binding' => 'currency', 'description' => 'Only ZAR is supported for now'],
                ['name' => 'amount', 'in' => 'body', 'type' => 'string', 'required' => true, 'description' => 'Amount should be in the subunit of the supported currency'],
            ],
        ],
        [
            'key' => 'preauthorization.reserve_authorization',
            'category' => 'Preauthorization',
            'name' => 'Reserve Preauthorization',
            'description' => 'Hold an amount using an existing customer\'s authorization that\'s marked reusable.',
            'method' => 'POST',
            'path' => '/preauthorization/reserve_authorization',
            'docs' => 'preauthorization/#reserve',
            'features' => ['preauthorization'],
            'currencies' => ['ZAR'],
            'dangerous' => true,
            'parameters' => [
                ['name' => 'email', 'in' => 'body', 'type' => 'string', 'format' => 'email', 'required' => true, 'description' => 'Customer\'s email address'],
                ['name' => 'amount', 'in' => 'body', 'type' => 'string', 'required' => true, 'description' => 'Amount should be in the subunit of the supported currency'],
                ['name' => 'authorization_code', 'in' => 'body', 'type' => 'string', 'required' => true, 'description' => 'This is the code that is used to charge and identify a customer\'s previously used card'],
                ['name' => 'currency', 'in' => 'body', 'type' => 'string', 'enum' => ['ZAR'], 'required' => true, 'binding' => 'currency', 'description' => 'Only ZAR is supported for now'],
                ['name' => 'reference', 'in' => 'body', 'type' => 'string', 'description' => 'Unique transaction reference. Only -, ., = and alphanumeric characters allowed.'],
            ],
        ],
        [
            'key' => 'preauthorization.verify',
            'category' => 'Preauthorization',
            'name' => 'Verify Preauthorization',
            'description' => 'Fetch and confirm the status of a preauthorized transaction.',
            'method' => 'GET',
            'path' => '/preauthorization/verify/{reference}',
            'docs' => 'preauthorization/#verify',
            'workflow' => 'transaction_result',
            'features' => ['preauthorization'],
            'parameters' => [
                ['name' => 'reference', 'in' => 'path', 'type' => 'string', 'required' => true, 'description' => 'The transaction reference used to initiate the transaction'],
            ],
        ],
        [
            'key' => 'preauthorization.release',
            'category' => 'Preauthorization',
            'name' => 'Release Preauthorization',
            'description' => 'For when a customer cancels an order or you want to release the hold from their card.',
            'method' => 'POST',
            'path' => '/preauthorization/release',
            'docs' => 'preauthorization/#release',
            'features' => ['preauthorization'],
            'parameters' => [
                ['name' => 'reference', 'in' => 'body', 'type' => 'string', 'required' => true, 'description' => 'Unique transaction reference. Only -, ., = and alphanumeric characters allowed.'],
            ],
        ],
        [
            'key' => 'capitec_pay.requery',
            'category' => 'Capitec Pay',
            'name' => 'Requery Capitec Pay Transaction',
            'description' => 'Check the status of a charge made with Capitec Pay. Paystack recommends polling after 90 seconds with a minimum 10 second interval; polling in less than 10 second intervals returns cached data.',
            'method' => 'POST',
            'path' => '/capitec-pay/requery/{ref}',
            'docs' => 'capitec-pay/#requery',
            'auth' => 'public',
            'body_type' => 'none',
            'features' => ['capitec_pay'],
            'notes' => 'Paystack authorizes this endpoint with your PUBLIC key. The explorer still sends it from the server.',
            'parameters' => [
                ['name' => 'ref', 'in' => 'path', 'type' => 'string', 'required' => true, 'description' => 'The transaction reference from the previously initiated charge request'],
            ],
        ],
    ],
];
