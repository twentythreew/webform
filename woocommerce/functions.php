<?php //For IDE styling only

//TODO
//RUN REVISED CHRIS SCRIPTS
//CALL DB FUNCTIONS AT RIGHT PLACES
//MAKE ALLERGY LIST MATCH GUARDIAN
//FIX EDIT ADDRESS FIELD LABELS
//FIX BLANK BUTTON ON SAVE CARD

// Register custom style sheets and javascript.
add_action( 'wp_enqueue_scripts', 'register_custom_plugin_styles' );
function register_custom_plugin_styles() {
  //is_wc_endpoint_url('orders') and is_wc_endpoint_url('account-details') seem to work
  wp_enqueue_script('dscsa-common', 'https://dscsa.github.io/webform/woocommerce/common.js', ['jquery']);
  wp_enqueue_style('dscsa-common', 'https://dscsa.github.io/webform/woocommerce/common.css');
  wp_enqueue_style('dscsa-select', 'https://dscsa.github.io/webform/woocommerce/select2.css');

  if (is_checkout() AND ! is_wc_endpoint_url()) {
     wp_enqueue_style('dscsa-checkout', 'https://dscsa.github.io/webform/woocommerce/checkout.css');
     wp_enqueue_script('dscsa-checkout', 'https://dscsa.github.io/webform/woocommerce/checkout.js', ['jquery']);
  }
}

add_action('wp_enqueue_scripts', 'remove_sticky_checkout', 99);
function remove_sticky_checkout() {
  wp_dequeue_script('storefront-sticky-payment');
}

function get_meta($field, $user_id) {
  return get_user_meta($user_id ?: get_current_user_id(), $field, true);
}

function get_default($field, $user_id) {
  return $_POST ? $_POST[$field] : get_meta($field, $user_id);
}

//do_action( 'woocommerce_stripe_add_card', $this->get_id(), $token, $response );
add_action('woocommerce_stripe_add_card', 'custom_stripe_add_card', 10, 3);
function custom_stripe_add_card($stripe_id, $card, $response) {

   $card = [
     'last4' => $card->get_last4(),
     'card' => $card->get_token(),
     'customer' => $stripe_id,
     'type' => $card->get_card_type(),
     'year' => $card->get_expiry_year(),
     'month' => $card->get_expiry_month()
   ];

   $user_id = get_current_user_id();

   update_user_meta($user_id, 'stripe', $card);
   //Meet guardian 50 character limit
   //Customer 18, Card 29, Delimiter 1 = 48
   update_stripe_tokens($card['customer'].','.$card['card'].',');

   $coupon = get_user_meta($user_id, 'coupon', true);

   update_card_and_coupon($card, $coupon);
}

//do_action( 'woocommerce_applied_coupon', $coupon_code );
add_action('woocommerce_applied_coupon', 'custom_applied_coupon');
function custom_applied_coupon($coupon) {
   $user_id = get_current_user_id();

   update_user_meta($user_id, 'coupon', $coupon);

   $card = get_user_meta($user_id, 'stripe', true);

   update_card_and_coupon($card, $coupon);
}

function order_fields() {
  return [
    'rx_source' => [
      'type'   	  => 'radio',
      'required'  => true,
      'default'  => 'pharmacy',
      'options'   => [
        'erx'     => __('Prescription(s) were sent from my doctor'),
        'pharmacy' => __('Transfer prescription(s) from my pharmacy')

      ]
    ],
    'medication[]'  => [
      'type'   	  => 'select',
      'label'     => __('Search and select medications by generic name that you want to transfer to Good Pill'),
      'options'   => ['']
    ]
  ];
}

function account_fields($user_id) {

  return [
    'language' => [
      'type'   	  => 'radio',
      'label'     => __('Language'),
      'label_class' => ['radio'],
      'required'  => true,
      'options'   => ['EN' => __('English'), 'ES' => __('Spanish')],
      'default'   => get_default('language', $user_id) ?: 'EN'
    ],
    'birth_date' => [
      'label'     => __('Date of Birth'),
      'required'  => true,
      'default'   => get_default('birth_date', $user_id)
    ]
  ];
}

function shared_fields($user_id) {

    $pharmacy = [
      'type'  => 'select',
      'required' => true,
      'label' => __('<span class="erx">Name and address of a backup pharmacy to fill your prescriptions if we are out-of-stock</span><span class="pharmacy">Name and address of pharmacy from which we should transfer your medication(s)</span>'),
      'options' => ['' => __('Please choose a pharmacy')]
    ];
    //https://docs.woocommerce.com/wc-apidocs/source-function-woocommerce_form_field.html#1841-2061
    //Can't use get_default here because $POST check messes up the required property below.
    $pharmacy_meta = get_meta('backup_pharmacy', $user_id);

    if ($pharmacy_meta) {
      $store = json_decode($pharmacy_meta);
      $pharmacy['options'] = [$pharmacy_meta => $store->name.', '.$store->street.', '.$store->city.', GA '.$store->zip.' - Phone: '.$store->phone];
    }

    return [
    'backup_pharmacy' => $pharmacy,
    'medications_other' => [
        'label'     =>  __('List any other medication(s) or supplement(s) you are currently taking'),
        'default'   => get_default('medications_other', $user_id)
    ],
    'allergies_none' => [
        'type'   	  => 'radio',
        'label'     => __('Allergies'),
        'label_class' => ['radio'],
        'options'   => ['' => __('Allergies Selected Below'), 99 => __('No Medication Allergies')],
    	'default'   => get_default('allergies_none', $user_id)
    ],
    'allergies_aspirin' => [
        'type'      => 'checkbox',
        'class'     => ['allergies', 'form-row-wide'],
        'label'     => __('Aspirin'),
        'default'   => get_default('allergies_aspirin', $user_id)
    ],
    'allergies_amoxicillin' => [
        'type'      => 'checkbox',
        'class'     => ['allergies', 'form-row-wide'],
        'label'     => __('Amoxicillin'),
        'default'   => get_default('allergies_amoxicillin', $user_id)
    ],
    'allergies_ampicillin' => [
        'type'      => 'checkbox',
        'class'     => ['allergies', 'form-row-wide'],
        'label'     => __('Ampicillin'),
        'default'   => get_default('allergies_ampicillin', $user_id)
    ],
    'allergies_azithromycin' => [
        'type'      => 'checkbox',
        'class'     => ['allergies', 'form-row-wide'],
        'label'     => __('Azithromycin'),
        'default'   => get_default('allergies_azithromycin', $user_id)
    ],
    'allergies_cephalosporins' => [
        'type'      => 'checkbox',
        'class'     => ['allergies', 'form-row-wide'],
        'label'     => __('Cephalosporins'),
        'default'   => get_default('allergies_cephalosporins', $user_id)
    ],
    'allergies_codeine' => [
        'type'      => 'checkbox',
        'class'     => ['allergies', 'form-row-wide'],
        'label'     => __('Codeine'),
        'default'   => get_default('allergies_codeine', $user_id)
    ],
    'allergies_erythromycin' => [
        'type'      => 'checkbox',
        'class'     => ['allergies', 'form-row-wide'],
        'label'     => __('Erythromycin'),
        'default'   => get_default('allergies_erythromycin', $user_id)
    ],
    'allergies_nsaids' => [
        'type'      => 'checkbox',
        'class'     => ['allergies', 'form-row-wide'],
        'label'     => __('NSAIDS e.g., ibuprofen, Advil'),
        'default'   => get_default('allergies_nsaids', $user_id)
    ],
    'allergies_penicillin' => [
        'type'      => 'checkbox',
        'class'     => ['allergies', 'form-row-wide'],
        'label'     => __('Penicillin'),
        'default'   => get_default('allergies_penicillin', $user_id)
    ],
    'allergies_salicylates' => [
        'type'      => 'checkbox',
        'class'     => ['allergies', 'form-row-wide'],
        'label'     => __('Salicylates'),
        'default'   => get_default('allergies_salicylates', $user_id)
    ],
    'allergies_sulfa' => [
        'type'      => 'checkbox',
        'class'     => ['allergies', 'form-row-wide'],
        'label'     => __('Sulfa (Sulfonamide Antibiotics)'),
        'default'   => get_default('allergies_sulfa', $user_id)
    ],
    'allergies_tetracycline' => [
        'type'      => 'checkbox',
        'class'     => ['allergies', 'form-row-wide'],
        'label'     => __('Tetracycline antibiotics'),
        'default'   => get_default('allergies_tetracycline', $user_id)
    ],
    'allergies_other' => [
        'type'      => 'checkbox',
        'class'     => ['allergies', 'form-row-wide'],
        'label'     =>__('List Other Allergies Below').'<input class="input-text " name="allergies_other" id="allergies_other_input" value="'.get_default('allergies_other', $user_id).'">'
    ],
    'phone' => [
        'label'     => __('Phone'),
        'required'  => true,
        'type'      => 'tel',
        'validate'  => ['phone'],
        'autocomplete' => 'tel',
        'default'   => get_default('phone', $user_id)
    ]
  ];
}

//Display custom fields on account/details
add_action('woocommerce_admin_order_data_after_order_details', 'custom_admin_edit_account');
function custom_admin_edit_account($order) {
  return custom_edit_account_form($order->user_id);
}
add_action( 'woocommerce_edit_account_form_start', 'custom_user_edit_account');
function custom_user_edit_account() {
  return custom_edit_account_form();
}

function custom_edit_account_form($user_id) {

  $fields = shared_fields($user_id)+account_fields($user_id);

  foreach ($fields as $key => $field) {
    echo woocommerce_form_field($key, $field);
  }
}

add_action('woocommerce_register_form_start', 'custom_register_form');
function custom_register_form() {
  $account_fields = account_fields();
  $first_name = [
    'class' => ['form-row-first'],
    'label'  => __('First name'),
    'default' => $_POST['first_name']
  ];

  $last_name = [
    'class' => ['form-row-last'],
    'label'  => __('Last name'),
    'default' => $_POST['last_name']
  ];

  echo woocommerce_form_field('language', $account_fields['language']);
  echo woocommerce_form_field('first_name', $first_name);
  echo woocommerce_form_field('last_name', $last_name);
  echo woocommerce_form_field('birth_date', $account_fields['birth_date']);
}

add_action('woocommerce_register_form', 'custom_register_form_acknowledgement');
function custom_register_form_acknowledgement() {
  echo __('<div style="margin-bottom:8px">By clicking "Register" below, you agree to our <a href="/terms">Terms of Use</a></div>');
}


//After Registration, set default shipping/billing/account fields
//then save the user into GuardianRx
add_action('woocommerce_created_customer', 'customer_created');
function customer_created($user_id) {
  $first_name = sanitize_text_field($_POST['first_name']);
  $last_name = sanitize_text_field($_POST['last_name']);
  $birth_date = sanitize_text_field($_POST['birth_date']);
  $email = sanitize_text_field($_POST['email']);
  $language = sanitize_text_field($_POST['language']);

  foreach(['', 'billing_', 'shipping_'] as $field) {
    update_user_meta($user_id, $field.'first_name', $first_name);
    update_user_meta($user_id, $field.'last_name', $last_name);
  }
  update_user_meta($user_id, 'birth_date', $birth_date);
  update_user_meta($user_id, 'language', $language);

  $patient_id = add_patient($first_name, $last_name, $birth_date, $email, $language);

  update_user_meta($user_id, 'guardian_id', $patient_id);
}

// Function to change email address
add_filter('wp_mail_from', 'email_address');
function email_address() {
  return 'rx@goodpill.org';
}
add_filter('wp_mail_from_name', 'email_name');
function email_name() {
  return 'Good Pill Pharmacy';
}

// After registration and login redirect user to account/orders.
// Clicking on Dashboard/New Order in Nave will add the actual product
add_action('woocommerce_registration_redirect', 'custom_redirect', 2);
function custom_redirect() {
  return home_url('/account/?add-to-cart=30#/');
}

add_filter ('wp_redirect', 'custom_wp_redirect');
function custom_wp_redirect($location) {
  //This goes back to account/details rather than /account after saving account details
  if (substr($location, -9) == '/account/')
    return $location.'details/';

  //After successful order, add another item back into cart.
  //Add to card won't work unless we replace query params e.g., key=wc_order_594de1d38152e
  if (substr($_GET['key'], 0, 9) == 'wc_order_')
   return substr($location, 0, -26).'add-to-cart=30';

  //Hacky, but only way I could get add-to-cart not to be called twice in a row.
  if (substr($location, -15) == '?add-to-cart=30')
   return substr($location, 0, -15);

  return $location;
}

add_filter ('woocommerce_account_menu_items', 'custom_my_account_menu');
function custom_my_account_menu($nav) {
  $nav['dashboard'] = __('New Order');
  return $nav;
}

add_action('woocommerce_save_account_details_errors', 'custom_account_validation');
function custom_account_validation() {
   custom_validation(shared_fields()+account_fields());
}
add_action('woocommerce_checkout_process', 'custom_order_validation');
function custom_order_validation() {
   custom_validation(order_fields()+shared_fields());
}

function custom_validation($fields) {
  $allergy_missing = true;
  foreach ($fields as $key => $field) {
    if ($field['required'] AND ! $_POST[$key]) {
       wc_add_notice('<strong>'.__($field['label']).'</strong> '.__('is a required field'), 'error');
    }

    if (substr($key, 0, 10) == 'allergies_' AND $_POST[$key])
 	  $allergy_missing = false;
  }

  if ($allergy_missing) {
    wc_add_notice('<strong>'.__('Allergies').'</strong> '.__('is a required field'), 'error');
  }
}

//On new order and account/details save account fields back to user
//TODO should changing checkout fields overwrite account fields if they are set?
add_action('woocommerce_save_account_details', 'custom_save_account_details');
function custom_save_account_details($user_id) {
  //TODO should save if they don't exist, but what if they do, should we be overriding?
  update_email(sanitize_text_field($_POST['account_email']));

  custom_save_patient($user_id, shared_fields($user_id) + account_fields($user_id));
}

add_action('woocommerce_checkout_update_user_meta', 'custom_save_order_details');
function custom_save_order_details($user_id) {
  //TODO should save if they don't exist, but what if they do, should we be overriding?
  custom_save_patient($user_id, shared_fields($user_id) + order_fields($user_id));

  $prefix = $_POST['ship_to_different_address'] ? 'shipping_' : 'billing_';

  //TODO this should be called on the edit address page as well
  $address = update_shipping_address(
    sanitize_text_field($_POST[$prefix.'address_1']),
    sanitize_text_field($_POST[$prefix.'address_2']),
    sanitize_text_field($_POST[$prefix.'city']),
    sanitize_text_field($_POST[$prefix.'postcode'])
  );
  if ($_POST['medication']) {
    foreach ($_POST['medication'] as $drug_name) {
      add_preorder($drug_name, $_POST['backup_pharmacy']);
    }
  }
}

function custom_save_patient($user_id, $fields) {

  $allergy_codes = [
    'allergies_tetracycline' => 1,
    'allergies_cephalosporins' => 2,
    'allergies_sulfa' => 3,
    'allergies_aspirin' => 4,
    'allergies_penicillin' => 5,
    'allergies_ampicillin' => 6,
    'allergies_erythromycin' => 7,
    'allergies_codeine' => 8,
    'allergies_nsaids' => 9,
    'allergies_salicylates' => 10,
    'allergies_azithromycin' => 11,
    'allergies_amoxicillin' => 12,
    'allergies_none' => 99,
    'allergies_other' => 100
  ];

  foreach ($fields as $key => $field) {

    //In case of backup pharmacy json, sanitize gets rid of it
    $val = sanitize_text_field($_POST[$key]);

    update_user_meta($user_id, $key, $val);

    if ($allergy_codes[$key]) {
      //Since all checkboxes submitted even with none selected.  If none
      //is selected manually set value to false for all except none
      $val = ($_POST['allergies_none'] AND $key != 'allergies_none') ? NULL : $val;
      //wp_mail('adam.kircher@gmail.com', 'save allergies', "$key $allergy_codes[$key] $val");
      add_remove_allergy($allergy_codes[$key], $val);
    }
  }

  update_pharmacy($_POST['backup_pharmacy']);

  append_comment(sanitize_text_field($_POST['medications_other']));

  update_phone(sanitize_text_field($_POST['phone']));
}

//Didn't work: https://stackoverflow.com/questions/38395784/woocommerce-overriding-billing-state-and-post-code-on-existing-checkout-fields
//Did work: https://stackoverflow.com/questions/36619793/cant-change-postcode-zip-field-label-in-woocommerce
global $lang;
add_filter('ngettext', 'custom_translate', 10, 3);
add_filter('gettext', 'custom_translate', 10, 3);
function custom_translate($term, $raw, $domain) {

  //if (strpos($term, 'been added to your cart') !== false)
    //wp_mail('adam.kircher@gmail.com', 'been added to your cart', $term);

  $toEnglish = [
    'Spanish'  => 'Espanol',
    'Username or email address' => 'Email Address',
    'From your account dashboard you can view your <a href="%1$s">recent orders</a>, manage your <a href="%2$s">shipping and billing addresses</a> and <a href="%3$s">edit your password and account details</a>.' => '',
    'ZIP' => 'Zip code',
    'Your order' => '',
    'No saved methods found.' => 'No credit or debit cards are saved to your account',
    '%s has been added to your cart.' => 'Thank you for your order!  Your prescription(s) should arrive within 3-5 days.',
    'Email Address' => 'Email address',
    'Additional information' => ''
  ];

  $toSpanish = [
    'Language' => 'Idioma',
    'Use a new credit card' => 'Use una tarjeta de crédito nueva',
    'Place New Order' => 'Haga un pedido nuevo',
    'Place order' => 'Haga un pedido',
    'Billing details' => 'Detalles de facturas',
    'Ship to a different address?' => '¿Desea envíos a una dirección diferente?',
    'Search and select medications by generic name that you want to transfer to Good Pill' => 'Busque y seleccione los medicamentos por nombre genérico que usted desea transferir a Good Pill',
    '<span class="erx">Name and address of a backup pharmacy to fill your prescriptions if we are out-of-stock</span><span class="pharmacy">Name and address of pharmacy from which we should transfer your medication(s)</span>' => '<span class="erx">Nombre y dirección de una farmacia de respaldo para surtir sus recetas si no tenemos los medicamentos en existencia</span><span class="pharmacy">Nombre & dirección de la farmacia de la que debemos transferir sus medicamentos.</span>',
    'Allergies' => 'Alergias',
    'Allergies Selected Below' => 'Alergias seleccionadas abajo',
    'No Medication Allergies' => 'No hay alergias a medicamentos',
    'Aspirin' => 'Aspirina',
    'Erythromycin' => 'Eritromicina',
    'NSAIDS e.g., ibuprofen, Advil' => 'NSAIDS; por ejemplo, ibuprofeno, Advil',
    'Penicillin' => 'Penicilina',
    'Ampicillin' => 'Ampicilina',
    'Sulfa (Sulfonamide Antibiotics)' => 'Sulfamida (antibióticos de sulfonamidas)',
    'Tetracycline antibiotics' => 'Antibióticos de tetraciclina',
    'List Other Allergies Below' => 'Indique otras alergias abajo',
    'Phone' => 'Teléfono',
    'List any other medication(s) or supplement(s) you are currently taking' => 'Indique cualquier otro medicamento o suplemento que usted toma actualmente',
    'Email address' => 'Dirección de correo electrónico',
    'First name' => 'Nombre',
    'Last name' => 'Apellido',
    'Date of Birth' => 'Fecha de nacimiento',
    'Address' => 'Dirección',
    'Addresses' => 'Direcciónes',
    'State' => 'Estado',
    'Zip code' => 'Código postal',
    'Town / City' => 'Poblado / Ciudad',
    'Password change' => 'Cambio de contraseña',
    'Current password (leave blank to leave unchanged)' => 'Contraseña actual (deje en blanco si no hay cambios)',
    'New password (leave blank to leave unchanged)' => 'Contraseña nueva (deje en blanco si no hay cambios)',
    'Confirm new password' => 'Confirmar contraseña nueva',
    'Have a coupon?' => '¿Tiene un cupón?',
    'Click here to enter your code' => 'Haga clic aquí para ingresar su código',
    'Coupon code' => 'Cupón',
    'Apply Coupon' => 'Haga un Cupón',
    'Free shipping coupon' => 'Cupón para envíos gratuitos',
    '[Remove]' => '[Remover]',
    'Card number' => 'Número de tarjeta',
    'Expiry (MM/YY)' => 'Fecha de expiración (MM/AA)',
    'Card code' => 'Código de tarjeta',
    'New Order' => 'Pedido Nuevo',
    'Orders' => 'Pedidos',
    'Shipping & Billing' => 'Dirección',
    'Email:' => 'Email:',
    'Prescription(s) were sent from my doctor' => 'Prescription(s) were sent from my doctor',
    'Transfer prescription(s) from my pharmacy' => 'Transfer prescription(s) from my pharmacy',
    'Street address' => 'Street address',
    'Apartment, suite, unit etc. (optional)' => 'Apartment, suite, unit etc. (optional)',
    'Payment methods' => 'Payment methods',
    'Account details' => 'Account details',
    'Logout' => 'Logout',
    'No order has been made yet.' => 'No order has been made yet.',
    'The following addresses will be used on the checkout page by default.' => 'The following addresses will be used on the checkout page by default.',
    'Billing address' => 'Billing address',
    'Shipping address' => 'Shipping address',
    'Save address' => 'Save address',
    'No credit or debit cards are saved to your account' => 'No credit or debit cards are saved to your account',
    'Add payment method' => 'Add payment method',
    'Save changes' => 'Save changes',
    'is a required field' => 'is a required field',
    'Order #%1$s was placed on %2$s and is currently %3$s.' => 'Order #%1$s was placed on %2$s and is currently %3$s.',
    'Payment method:' => 'Payment method:',
    'Order details' => 'Order details',
    'Customer details' => 'Customer details',
    'Amoxicillin' => 'Amoxicillin',
    'Azithromycin' => 'Azithromycin',
    'Cephalosporins' => 'Cephalosporins',
    'Codeine' => 'Codeine',
    'Salicylates' => 'Salicylates',
    'Thank you for your order!  Your prescription(s) should arrive within 3-5 days.' => 'Thank you for your order!  Your prescription(s) should arrive within 3-5 days.',
    'Please choose a pharmacy' => 'Please choose a pharmacy',
    '<div style="margin-bottom:8px">By clicking "Register" below, you agree to our <a href="/terms">Terms of Use</a></div>' => '<div style="margin-bottom:8px">By clicking "Register" below, you agree to our <a href="/terms">Terms of Use</a></div>',
  ];

  $english = isset($toEnglish[$term]) ? $toEnglish[$term] : $term;
  $spanish = $toSpanish[$english];

  $user_id = get_current_user_id();

  if (is_admin() OR ! isset($spanish))
	return $english;

  global $lang;
  $lang = $lang ?: get_user_meta($user_id, 'language', true);

  if ($lang == 'EN')
    return $english;

  if ($lang == 'ES')
    return $spanish;

  //This allows client side translating based on jQuery listening to radio buttons
  if (isset($_GET['register']))
    return  "<span class='english'>$english</span><span class='spanish'>$spanish</span>";

  return $english;
}

// Hook in
add_filter( 'woocommerce_checkout_fields' , 'custom_checkout_fields' );
function custom_checkout_fields( $fields ) {

  $shared_fields = shared_fields();

  //Add some order fields that are not in patient profile
  $order_fields = order_fields();

  $fields['order'] = $order_fields + $shared_fields;

  //Allow billing out of state but don't allow shipping out of state
  $fields['shipping']['shipping_state']['type'] = 'select';
  $fields['shipping']['shipping_state']['options'] = ['GA' => 'Georgia'];

  $fields['billing']['billing_state']['type'] = 'select';
  $fields['billing']['billing_state']['options'] = ['GA' => 'Georgia'];

  //Remove Some Fields
  unset($fields['billing']['billing_first_name']['autofocus']);
  unset($fields['billing']['shipping_first_name']['autofocus']);
  unset($fields['billing']['billing_phone']);
  unset($fields['billing']['billing_email']);
  unset($fields['billing']['billing_company']);
  unset($fields['billing']['billing_country']);
  unset($fields['shipping']['shipping_country']);
  unset($fields['shipping']['shipping_company']);

  return $fields;
}

// SirumWeb_AddRemove_Allergy(
//   @PatID int,     --Carepoint Patient ID number
//   @AddRem int = 1,-- 1=Add 0= Remove
//   @AlrNumber int,  -- From list
//   @OtherDescr varchar(80) = '' -- Description for "Other"
// )
/*
    Allergies supported
  if      @AlrNumber = 1  -- TETRACYCLINE
  else if @AlrNumber = 2  -- Cephalosporins
  else if @AlrNumber = 3  -- Sulfa (Sulfonamide Antibiotics)
  else if @AlrNumber = 4  -- Aspirin
  else if @AlrNumber = 5  -- Penicillins
  else if @AlrNumber = 6  -- Ampicillin
  else if @AlrNumber = 7  -- Erythromycin Base
  else if @AlrNumber = 8  -- Codeine
  else if @AlrNumber = 9  -- NSAIDS e.g., ibuprofen, Advil
  else if @AlrNumber = 10  -- Salicylates
  else if @AlrNumber = 11  -- azithromycin,
  else if @AlrNumber = 12  -- amoxicillin,
  else if @AlrNumber = 99  -- none
  else if @AlrNumber = 100 -- other
*/
function add_remove_allergy($allergy_id, $value) {
  return db_run("SirumWeb_AddRemove_Allergy(?, ?, ?, ?)", [
    guardian_id(), $value ? 1 : 0, $allergy_id, $value
  ]);
}

// SirumWeb_AddUpdateHomePhone(
//   @PatID int,  -- ID of Patient
//   @PatCellPhone VARCHAR(20)
// }
function update_phone($cell_phone) {
  $cell_phone = preg_replace("/[^0-9]/", "", $cell_phone);
  return db_run("SirumWeb_AddUpdatePatHomePhone(?, ?)", [
    guardian_id(), $cell_phone
  ]);
}

// dbo.SirumWeb_AddUpdatePatShipAddr(
//  @PatID int
// ,@Addr1 varchar(50)    -- Address Line 1
// ,@Addr2 varchar(50)    -- Address Line 2
// ,@Addr3 varchar(50)    -- Address Line 3
// ,@City varchar(20)     -- City Name
// ,@State varchar(2)     -- State Name
// ,@Zip varchar(10)      -- Zip Code
// ,@Country varchar(3)   -- Country Code
function update_shipping_address($address_1, $address_2, $city, $zip) {
  $params = [
    guardian_id(), $address_1, $address_2, $city, $zip
  ];

  //wp_mail('adam.kircher@gmail.com', "update_shipping_address", print_r($params, true));

  return db_run("SirumWeb_AddUpdatePatHomeAddr(?, ?, ?, NULL, ?, 'GA', ?, 'US')", $params);
}

//$query = sqlsrv_query( $db, "select * from cppat where cppat.pat_id=1003";);
// SirumWeb_FindPatByNameandDOB(
//   @LName varchar(30),           -- LAST NAME
//   @FName varchar(20),           -- FIRST NAME
//   @MName varchar(20)=NULL,     -- Middle Name (optional)
//   @DOB DateTime                -- Birth Date
// )
function find_patient($first_name, $last_name, $birth_date) {
  return db_run("SirumWeb_FindPatByNameandDOB(?, ?, ?)", [$first_name, $last_name, $birth_date]);
}

// SirumWeb_AddEditPatient(
//    @FirstName varchar(20)
//   ,@MiddleName varchar(20)= NULL -- Optional
//   ,@LastName varchar(30)
//   ,@BirthDate datetime
//   ,@ShipAddr1 varchar(50)    -- Address Line 1
//   ,@ShipAddr2 varchar(50)    -- Address Line 2
//   ,@ShipAddr3 varchar(50)    -- Address Line 3
//   ,@ShipCity varchar(20)     -- City Name
//   ,@ShipState varchar(2)     -- State Name
//   ,@ShipZip varchar(10)      -- Zip Code
//   ,@ShipCountry varchar(3)   -- Country Code
//   ,@CellPhone varchar(20)    -- Cell Phone
// )
function add_patient($first_name, $last_name, $birth_date, $email, $language) {
  return db_run("SirumWeb_AddEditPatient(?, ?, ?, ?, ?)", [$first_name, $last_name, $birth_date, $email, $language])['PatID'];
}

// Procedure dbo.SirumWeb_AddToPatientComment (@PatID int, @CmtToAdd VARCHAR(4096)
// The comment will be appended to the existing comment if it is not already in the comment field.
function append_comment($comment) {
  return db_run("SirumWeb_AddToPatientComment(?, ?)", [guardian_id(), $comment]);
}

// Create Procedure dbo.SirumWeb_AddToPreorder(
//    @PatID int
//   ,@DrugName varchar(60) ='' -- Drug Name to look up NDC
//   ,@PharmacyOrgID int
//   ,@PharmacyName varchar(80)
//   ,@PharmacyAddr1 varchar(50)    -- Address Line 1
//   ,@PharmacyCity varchar(20)     -- City Name
//   ,@PharmacyState varchar(2)     -- State Name
//   ,@PharmacyZip varchar(10)      -- Zip Code
//   ,@PharmacyPhone varchar(20)   -- Phone Number
//   ,@PharmacyFaxNo varchar(20)   -- Phone Fax Number
// If you send the NDC, it will use it.  If you do not send and NCD it will attempt to look up the drug by the name.  I am not sure that this will work correctly, the name you pass in would most likely have to be an exact match, even though I am using  like logic  (ie “%Aspirin 325mg% “) to search.  We may have to work on this a bit more
function add_preorder($drug_name, $pharmacy) {

   $store = json_decode(stripslashes($pharmacy));

   return db_run("SirumWeb_AddToPreorder(?, ?, ?, ?, ?, ?, ?, ?, ?, ?)", [
       guardian_id(),
       explode(",", $drug_name)[0],
       $store->npi,
       $store->name,
       $store->street,
       $store->city,
       $store->state,
       $store->zip,
       $store->phone,
       $store->fax
   ]);
}

// Procedure dbo.SirumWeb_AddUpdatePatientUD (@PatID int, @UDNumber int, @UDValue varchar(50) )
// Set the @UD number can be 1-4 for the field that you want to update, and set the text value.
// 1 is backup pharmacy, 2 is stripe billing token.
function update_pharmacy($pharmacy) {
  $store = json_decode(stripslashes($pharmacy));
  db_run("SirumWeb_AddUpdatePatientUD(?, 1, ?)", [guardian_id(), $store->name]);
  //Because of 50 character limit, the street will likely be cut off.
  return db_run("SirumWeb_AddUpdatePatientUD(?, 2, ?)", [guardian_id(), $store->npi.','.$store->fax.','.$store->phone.','.$store->street]);
}

function update_stripe_tokens($value) {
  return db_run("SirumWeb_AddUpdatePatientUD(?, 3, ?)", [guardian_id(), $value]);
}

function update_card_and_coupon($card, $coupon) {
  //Meet guardian 50 character limit
  //Last4 4, Month 2, Year 2, Type (Mastercard = 10), Delimiter 4, So coupon will be truncated if over 28 characters
  $value = $card['last4'].','.$card['month'].'/'.substr($card['year'], 2).','.$card['type'].','.$coupon;

  return db_run("SirumWeb_AddUpdatePatientUD(?, 4, ?)", [guardian_id(), $value]);
}

//Procedure dbo.SirumWeb_AddUpdatePatEmail (@PatID int, @EMailAddress VARCHAR(255)
//Set the patID and the new email address
function update_email($email) {
  return db_run("SirumWeb_AddUpdatePatEmail(?, ?)", [guardian_id(), $email]);
}

global $conn;
function db_run($sql, $params, $noresults) {
  global $conn;
  $conn = $conn ?: db_connect();
  $stmt = db_query($conn, "{call $sql}", $params);

  if ( ! sqlsrv_has_rows($stmt)) {
    email_error("no rows for result of $sql");
    return [];
  }

  $data = db_fetch($stmt) ?: email_error("fetching $sql");

  sqlsrv_free_stmt($stmt);

  //wp_mail('adam.kircher@gmail.com', "db query: $sql", print_r($params, true).print_r($data, true));
  //wp_mail('adam.kircher@gmail.com', "db testing", print_r(sqlsrv_errors(), true));

  return $data;
}

function db_fetch($stmt) {
 return sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
}

function db_connect() {
  sqlsrv_configure("WarningsReturnAsErrors", 0);
  return sqlsrv_connect("GOODPILL-SERVER", ["Database"=>"cph"]) ?: email_error('Error Connection');
}

function db_query($conn, $sql, $params) {
  return sqlsrv_query($conn, $sql, $params) ?: email_error("Query $sql");
}

function email_error($heading) {
   wp_mail('adam.kircher@gmail.com', "db error: $heading", print_r(sqlsrv_errors(), true));
}

function guardian_id() {
   return get_user_meta(get_current_user_id(), 'guardian_id', true);
}
