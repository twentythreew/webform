<?php


function export_v2_pend_order($order, $mysql) {
  foreach($order as $i => $item)
    v2_pend_item($order[$i], $mysql);
}

function v2_pend_item($item, $mysql) {
  log_notice("v2_pend_item continue:".($item['days_dispensed_default'] ? 'Yes Days Dispensed Default' : 'No Days Dispensed Default'), "$item[rx_number]  $item[rx_dispensed_id] $item[days_dispensed_default]");//.print_r($item, true);

  if ( ! $item['days_dispensed_default'] OR $item['rx_dispensed_id']) return;

  $vals = make_pick_list($item);
  print_pick_list($item, $vals);
  pend_pick_list($item, $vals);
  save_pick_list($item, $vals, $mysql);
}

function export_v2_unpend_order($order) {
  log_notice("export_v2_unpend_order", $order[0]['invoice_number']);
  foreach($order as $item) {
    unpend_pick_list($item);
  }
}

function unpend_pick_list($item) {

  $pend_group_refill = pend_group_refill($item);
  $pend_group_new_rx = pend_group_new_rx($item);
  $pend_group_manual = pend_group_manual($item);

  //Once order is deleted it not longer has items so its hard to determine if the items were New or Refills so just delete both
  $res_refill = v2_fetch("/account/8889875187/pend/$pend_group_refill", 'DELETE');
  $res_new_rx = v2_fetch("/account/8889875187/pend/$pend_group_new_rx", 'DELETE');
  $res_manual = v2_fetch("/account/8889875187/pend/$pend_group_manual", 'DELETE');

  //Delete gdoc pick list
  $args = [
    'method'   => 'removeFiles',
    'file'     => pick_list_prefix($item),
    'folder'   => PICK_LIST_FOLDER_NAME
  ];

  $result = gdoc_post(GD_HELPER_URL, $args);

  log_notice("unpend_pick_list", get_defined_vars());
}

function save_pick_list($item, $vals, $mysql) {

  if ( ! $vals) return; //List could not be made

  $sql = "
    UPDATE
      gp_order_items
    SET
      qty_pended_total = $vals[qty],
      qty_pended_repacks = $vals[qty_repacks],
      count_pended_total = $vals[count],
      count_pended_repacks = $vals[count_repacks]
    WHERE
      invoice_number = $item[invoice_number] AND
      rx_number = $item[rx_number]
  ";

  //log_notice('save_pick_list', get_defined_vars());

  $mysql->run($sql);
}

function pick_list_name($item) {
  return pick_list_prefix($item).pick_list_suffix($item);
}

function pick_list_prefix($item) {
  return 'Pick List #'.$item['invoice_number'].': ';
}

function pick_list_suffix($item) {
  return $item['drug_generic'];
}

function print_pick_list($item, $vals) {

  $pend_group_name = pend_group_name($item);

  if ( ! $vals) return; //List could not be made

  $header = [
    [
      "Pick List: Order #$pend_group_name $item[drug_generic] ($item[drug_name])", '', '' ,'', '', ''],
    [
      "Rx $item[rx_number]. $item[rx_message_key]. Item Added:$item[item_date_added]. Created ".date('Y-m-d H:i:s'), '', '' ,'', '', ''],
    [
      $vals['half_fill'].
      "Count:$vals[count], ".
      "Days:$item[days_dispensed_default], ".
      "Qty:$item[qty_dispensed_default] ($vals[qty]), ".
      "Stock:$item[stock_level_initial], ",
      '', '', '', '', ''
    ],
    ['', '', '', '', '', ''],
    ['id', 'ndc', 'form', 'exp', 'qty', 'bin']
  ];

  $args = [
    'method'   => 'newSpreadsheet',
    'file'     => pick_list_name($item),
    'folder'   => PICK_LIST_FOLDER_NAME,
    'vals'     => array_merge($header, $vals['list']), //merge arrays, make sure array is not associative or json will turn to object
    'widths'   => [1 => 243] //show the full id when it prints
  ];

  $result = gdoc_post(GD_HELPER_URL, $args);

  log_notice("WebForm print_pick_list $pend_group_name", ['item' => $item, 'count list' => count($vals['list']), 'count pend' => count($vals['pend'])]); //We don't need full shopping list cluttering logs

}

function pend_group_refill($item) {

   $pick_time = strtotime($item['order_date_added'].' +2 days'); //Used to be +3 days
   $invoice   = "N$item[invoice_number]"; //N < R so new scripts will appear first on shopping list

   $pick_date = date('Y-m-d', $pick_time);

   return "$pick_date $invoice";
}

function pend_group_new_rx($item) {

   $pick_time = strtotime($item['order_date_added'].' +0 days'); //Used to be +1 days
   $invoice   = "R$item[invoice_number]";

   $pick_date = date('Y-m-d', $pick_time);

   return "$pick_date $invoice";
}

function pend_group_manual($item) {
   return $item['invoice_number'];
}

function pend_group_name($item) {
   return $item['order_source'] == "Auto Refill v2" ? pend_group_refill($item) : pend_group_new_rx($item);
}


function pend_pick_list($item, $vals) {

  if ( ! $vals) return; //List could not be made

  $pend_group_name = pend_group_name($item);
  $qty = round($item['qty_dispensed_default']);

  $pend_url = "/account/8889875187/pend/$pend_group_name?repackQty=$qty";

  //Pend after all forseeable errors are accounted for.
  $res = v2_fetch($pend_url, 'POST', $vals['pend']);

  log_notice("WebForm pend_pick_list", ['res' => $res, 'pend' => $vals['pend'], 'item' => $item, 'pend_url' => $pend_url, 'count list' => count($vals['list']), 'count pend' => count($vals['pend'])]);
}

function make_pick_list($item) {

  if ( ! isset($item['stock_level_initial']) AND $item['rx_gsn']) //If missing GSN then stock level won't be set
    log_error("ERROR make_pick_list: stock_level_initial is not set", get_defined_vars());

  $safety   = 0.15;
  $generic  = $item['drug_generic'];
  $min_days = $item['days_dispensed_default'];
  $min_qty  = $item['qty_dispensed_default'];
  $stock    = $item['stock_level_initial'];

  $min_exp   = explode('-', date('Y-m', strtotime("+".($min_days-2*7)." days"))); //Used to use +14 days rather than -14 days as a buffer for dispensing and shipping. But since lots of prepacks expiring I am going to let almost expired things be prepacked
  $long_exp  = date('Y-m-01', strtotime("+".($min_days+6*7)." days")); //2015-05-13 We want any surplus from packing fast movers to be usable for ~6 weeks.  Otherwise a lot of prepacks expire on the shelf

  $start_key = rawurlencode('["8889875187","month","'.$min_exp[0].'","'.$min_exp[1].'","'.$generic.'"]');
  $end_key   = rawurlencode('["8889875187","month","'.$min_exp[0].'","'.$min_exp[1].'","'.$generic.'",{}]');

  $url  = '/transaction/_design/inventory-by-generic/_view/inventory-by-generic?reduce=false&include_docs=true&limit=500&startkey='.$start_key.'&endkey='.$end_key;

  try {
    $res = v2_fetch($url);
  } catch (Error $e) {
    log_error("WebForm make_pick_list fetch failed.  Retrying $item[invoice_number]", ['item' => $item, 'res' => $res, 'error' => $e]);
    $res = v2_fetch($url);
  }

  $unsorted_ndcs = group_by_ndc($res['rows'], $item);
  $sorted_ndcs   = sort_by_ndc($unsorted_ndcs, $long_exp);
  $list          = get_qty_needed($sorted_ndcs, $min_qty, $safety);

  log_notice("WebForm make_pick_list $item[invoice_number]", $item['drug_name']); //We don't need full shopping list cluttering logs

  if ($list) {
    $list['half_fill'] = '';
    return $list;
  }

  log_error("Webform Pending Error: Not enough qty found #1 of 2, trying half fill and no safety", ['count_inventory' => count($sorted_ndcs), 'item' => $item]);

  $list = get_qty_needed($sorted_ndcs, $min_qty*0.5, 0);

  if ($list) {
    $list['half_fill'] = 'HALF FILL - COULD NOT FIND ENOUGH QUANTITY, ';
    return $list;
  }

  $salesforce = [
    "subject"   => "Order #$item[invoice_number] cannot pend enough $item[drug_name]",
    "body"      => "Determine if there is enough $item[drug_name] to pend for '$item[sig_actual]'. Tried & failed to pend a qty of ".$min_qty,
    "contact"   => "$item[first_name] $item[last_name] $item[birth_date]",
    "assign_to" => "Cindy",
    "due_date"  => date('Y-m-d')
  ];

  $event_title = "$item[invoice_number] Pending Error: $salesforce[contact] Created:".date('Y-m-d H:i:s');

  create_event($event_title, [$salesforce]);

  log_error("Webform Pending Error: Not enough qty found #2 of 2, half fill with no safety failed", ['count_inventory' => count($sorted_ndcs), 'item' => $item]);
}

function group_by_ndc($rows, $item) {
  //Organize by NDC since we don't want to mix them
  $ndcs = [];
  $caps = preg_match('/ cap(?!l)s?| cps?\\b| softgel| sfgl\\b/i', $item['drug_name']); //include cap, caps, capsule but not caplet which is more like a tablet
  $tabs = preg_match('/ tabs?| tbs?| capl\\b/i', $item['drug_name']);  //include caplet which is like a tablet

  foreach ($rows as $row) {

    if (
        isset($row['doc']['next'][0]) AND
        (
          count($row['doc']['next'][0]) > 1 OR
          (
            count($row['doc']['next'][0]) == 1 AND
            ! isset($row['doc']['next'][0]['picked'])
          )
        )
    ) {
      log_error('Shopping list pulled inventory in which "next" is set!', $row, $item);
      if ( ! empty($row['doc']['next']['dispensed'])) continue;
    }

    //Ignore Cindy's makeshift dispensed queue
    if (in_array($row['doc']['bin'], ['M00', 'T00', 'W00', 'R00', 'F00', 'X00', 'Y00', 'Z00'])) continue;
    //Only select the correct form even though v2 gives us both
    if ($caps AND stripos($row['doc']['drug']['form'], 'Tablet') !== false) {
      $msg = 'may only be available in capsule form';
      continue;
    }
    if ($tabs AND stripos($row['doc']['drug']['form'], 'Capsule') !== false) {
      $msg = 'may only be available in tablet form';
      continue;
    }

    $ndc = $row['doc']['drug']['_id'];
    $ndcs[$ndc] = isset($ndcs[$ndc]) ? $ndcs[$ndc] : [];
    $ndcs[$ndc]['rows'] = isset($ndcs[$ndc]['rows']) ? $ndcs[$ndc]['rows'] : [];
    $ndcs[$ndc]['prepack_qty'] = isset($ndcs[$ndc]['prepack_qty']) ? $ndcs[$ndc]['prepack_qty'] : 0; //Hacky to set property on an array

    if (strlen($row['doc']['bin']) == 3) {
      $ndcs[$ndc]['prepack_qty'] += $row['doc']['qty']['to'];

      if (isset($ndcs[$ndc]['prepack_exp']) AND $row['doc']['exp']['to'] < $ndcs[$ndc]['prepack_exp'])
        $ndcs[$ndc]['prepack_exp'] = $row['doc']['exp']['to'];
    }

    $ndcs[$ndc]['rows'][] = $row['doc'];
  }

  return $ndcs;
}

function sort_by_ndc($ndcs, $long_exp) {

  $sorted_ndcs = [];
  //Sort the highest prepack qty first
  foreach ($ndcs as $ndc => $val) {
    $sorted_ndcs[] = ['ndc' => $ndc, 'prepack_qty' => $val['prepack_qty'], 'inventory' => sort_inventory($val['rows'], $long_exp)];
  }
  //Sort in descending order of prepack_qty. TODO should we look Exp date as well?
  usort($sorted_ndcs, function($a, $b) use ($sorted_ndcs) {

    if ( ! isset($a['prepack_qty']) OR ! isset($b['prepack_qty'])) {
      log_error('ERROR: sort_by_ndc but prepack_qty is not set', get_defined_vars());
    } else {
      return $b['prepack_qty'] - $a['prepack_qty'];
    }
  });

  return $sorted_ndcs;
}

function sort_inventory($inventory, $long_exp) {

    //Lots of prepacks were expiring because pulled stock with long exp was being paired with short prepack exp making the surplus shortdated
    //Default to longExp since that simplifies sort() if there are no prepacks
    usort($inventory, function($a, $b) use ($inventory, $long_exp) {

      //Deprioritize ones that are missing data
      if ( ! $b['bin'] OR ! $b['exp'] OR ! $b['qty']) return -1;
      if ( ! $a['bin'] OR ! $a['exp'] OR ! $a['qty']) return 1;

      //Priortize prepacks over other stock
      $aPack = strlen($a['bin']) == 3;
      $bPack = strlen($b['bin']) == 3;
      if ($aPack AND ! $bPack) return -1;
      if ($bPack AND ! $aPack) return 1;

      //Let's shop for non-prepacks that are closest (but not less than) to our min prepack exp date in order to avoid waste
      $aMonths = months_between(isset($inventory['prepack_exp']) ? $inventory['prepack_exp'] : $long_exp, substr($a['exp']['to'], 0, 10)); // >0 if minPrepackExp < a.doc.exp.to (which is what we prefer)
      $bMonths = months_between(isset($inventory['prepack_exp']) ? $inventory['prepack_exp'] : $long_exp, substr($b['exp']['to'], 0, 10)); // >0 if minPrepackExp < b.doc.exp.to (which is what we prefer)

      //Deprioritize anything with a closer exp date than the min prepack exp date.  This - by definition - can only be non-prepack stock
      if ($aMonths >= 0 AND $bMonths < 0) return -1;
      if ($bMonths >= 0 AND $aMonths < 0) return 1;

      //Priorize anything that is closer to - but not under - our min prepack exp
      //If there is no prepack this is set to 3 months out so that any surplus has time to sit on our shelf
      if ($aMonths >= 0 AND $bMonths >= 0 AND $aMonths < $bMonths) return -1;
      if ($aMonths >= 0 AND $bMonths >= 0 AND $bMonths < $aMonths) return 1;

      //If they both expire sooner than our min prepack exp pick the closest
      if ($aMonths < 0 AND $bMonths < 0 AND $aMonths > $bMonths) return -1;
      if ($aMonths < 0 AND $bMonths < 0 AND $bMonths > $aMonths) return 1;

      //keep sorting the same as the view (ascending NDCs) [doc.drug._id, doc.exp.to || doc.exp.from, sortedBin, doc.bin, doc._id]
      return 0;
    });

    return $inventory;
}

function months_between($from, $to) {
  $diff = date_diff(date_create($from), date_create($to));
  return $diff->m + ($diff->y * 12);
}

function get_qty_needed($rows, $min_qty, $safety) {

  foreach ($rows as $row) {

    $ndc = $row['ndc'];
    $inventory = $row['inventory'];

    $list  = [];
    $pend  = [];
    $qty = 0;
    $qty_repacks = 0;
    $count_repacks = 0;
    $left = $min_qty;

    foreach ($inventory as $i => $option) {

      if ($i == 'prepack_qty') continue;

      array_unshift($pend, $option);

      $usable = 1 - $safety;
      if (strlen($pend[0]['bin']) == 3) {
        $usable = 1;
        $qty_repacks += $pend[0]['qty']['to'];
        $count_repacks++;
      }

      $qty += $pend[0]['qty']['to'];
      $left -= $pend[0]['qty']['to'] * $usable;
      $list = pend_to_list($list, $pend);

       //Shop for all matching medicine in the bin, its annoying and inefficient to pick some and leave the others
      if ($left <= 0 AND $pend[0]['bin'] != @$inventory[$i+1]['bin']) {

        usort($list, 'sort_list');

        return [
          'list' => $list,
          'ndc' => $ndc,
          'pend' => $pend,
          'qty' => $qty,
          'count' => count($list),
          'qty_repacks' => $qty_repacks,
          'count_repacks' => $count_repacks
        ];
      }
    }
  }
}

function pend_to_list($list, $pend) {
  $list[] = [
    $pend[0]['_id'],
    $pend[0]['drug']['_id'],
    $pend[0]['drug']['form'],
    substr($pend[0]['exp']['to'], 0, 7),
    $pend[0]['qty']['to'],
    $pend[0]['bin']
  ];
  return $list;
}

function sort_list($a, $b) {

  $aBin = $a[5];
  $bBin = $b[5];

  $aPack = $aBin AND strlen($aBin) == 3;
  $bPack = $bBin AND strlen($bBin) == 3;

  if ($aPack > $bPack) return 1;
  if ($aPack < $bPack) return -1;

  //Flip columns and rows for sorting, since shopping is easier if you never move backwards
  $aFlip = $aBin[0].$aBin[2].$aBin[1].(@$aBin[3] ?: '');
  $bFlip = $bBin[0].$bBin[2].$bBin[1].(@$bBin[3] ?: '');

  if ($aFlip < $bFlip) return 1;
  if ($aFlip > $bFlip) return -1;

  return 0;
}
