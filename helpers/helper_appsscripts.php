<?php

function gdoc_post($url, $content) {

  $content = json_encode(utf8ize($content), JSON_UNESCAPED_UNICODE);

  $opts = [
    'http' => [
      'method'  => 'POST',
      'content' => $content,
      'header'  => "Content-Type: application/json\r\n".
                   "Accept: application/json\r\n".
                   'Content-Length: '.strlen($content)."\r\n" //Apps Scripts seems to sometimes to require this e.g Invoice for 33701 or returns an HTTP 411 error
    ]
  ];

  $context = stream_context_create($opts);
  return file_get_contents($url.'?GD_KEY='.GD_KEY, false, $context);
}

function watch_invoices() {

  $args = [
    'method'       => 'watchFiles',
    'folder'       => INVOICE_PUBLISHED_FOLDER_NAME
  ];

  $invoices = json_decode(gdoc_post(GD_HELPER_URL, $args), true);

  if ( ! is_array($invoices) OR ! is_array($invoices['parent']) OR ! is_array($invoices['printed']) OR ! is_array($invoices['faxed']))
    return log_error('ERROR watch_invoices', [$invoices, $args]);

  $invoices = $invoices['parent'] + $invoices['printed'] + $invoices['faxed'];

  $mysql = new Mysql_Wc();

  foreach ($invoices as $invoice) {

    preg_match_all('/(Total:? +|Due:? +)\$(\d+)/', $invoice['part0'], $totals);

    //Table columns seem to be divided by table breaks
    preg_match_all('/\\n\$(\d+)/', $invoice['part0'], $items);

    //Differentiate from the four digit year
    preg_match_all('/\d{5,}/', $invoice['name'], $invoice_number);

    if ( ! isset($totals[2][0]) OR ! isset($totals[2][1])) {
      log_error('watch_invoices: incorrect totals', $invoice['part0']);
      continue;
    }

    if ( ! isset($invoice_number[0][0])) {
      log_error('watch_invoices: incorrect invoice number', $invoice_number);
      continue;
    }

    $invoice_number = $invoice_number[0][0];

    $payment = [
      'count_filled' => count($items[1]),
      'total' => array_sum($items[1]),
      'fee'   => $totals[2][0],
      'due'   => $totals[2][1]
    ];

    $sql = "SELECT * FROM gp_orders WHERE invoice_number = $invoice_number";

    $order = $mysql->run($sql)[0][0];

    $log = "
      Filled:$order[count_filled] -> $payment[count_filled],
      Total:$order[payment_total_default] ($order[payment_total_actual]) -> $payment[total],
      Fee:$order[payment_fee_default] ($order[payment_fee_actual]) -> $payment[fee],
      Due:$order[payment_due_default] ($order[payment_due_actual]) -> $payment[due]
    ";

    if (
      $order['count_filled'] == $payment['count_filled'] AND
      $order['payment_total_actual'] ?: $order['payment_total_default'] == $payment['total'] AND
      $order['payment_fee_actual'] ?: $order['payment_fee_default'] == $payment['fee'] AND
      $order['payment_due_actual'] ?: $order['payment_due_default'] == $payment['due']
    )
      return log_notice("watch_invoice $invoice_number", $log); //Most likely invoice was correct and just moved

    log_error("watch_invoice $invoice_number", $log);

    set_payment_actual($invoice_number, $payment, $mysql);
    export_wc_update_order_payment($invoice_number, $payment['fee']);
  }

  return $invoices;
}
