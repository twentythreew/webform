<?php

//One-Time Temp Script to Fix one off errors in MSSQL

ini_set('include_path', '/goodpill/webform');
date_default_timezone_set('America/New_York');

require_once 'helpers/helper_log.php';
require_once 'dbs/mysql_wc.php';
require_once 'dbs/mssql_cp.php';

$mysql = new Mysql_Wc();
$mssql = new Mssql_Cp();

$rxs = $mysql->run("
  SELECT
    gp_rxs_single.rx_number,
    rx_message_key,
    stock_level_initial
  FROM
    gp_rxs_single
  JOIN
    gp_order_items ON gp_order_items.rx_number = gp_rxs_single.rx_number
  WHERE
    rx_message_key LIKE 'NO ACTION STANDARD FILL' AND
    stock_level_initial IS NOT NULL
")[0];

$count = count($rxs);

foreach ($rxs as $i => $rx) {
  if ($rx['stock_level_initial'] == 'HIGH SUPPLY') {
    $mssql->run("UPDATE cprx SET priority_cn = 218, chg_date = GETDATE() WHERE script_no = '$rx[rx_number]'");
    $mysql->run("UPDATE gp_rxs_single SET rx_message_key = 'NO ACTION FILL HIGH SUPPLY' WHERE rx_number = '$rx[rx_number]'");

    echo "
    $i of $count, $rx[rx_number] $rx[rx_message_key] $rx[stock_level_initial]";
  }

  if ($rx['stock_level_initial'] == 'LOW SUPPLY') {
    $mssql->run("UPDATE cprx SET priority_cn = 219, chg_date = GETDATE() WHERE script_no = '$rx[rx_number]'");
    $mysql->run("UPDATE gp_rxs_single SET rx_message_key = 'NO ACTION FILL LOW SUPPLY' WHERE rx_number = '$rx[rx_number]'");

    echo "
    $i of $count, $rx[rx_number] $rx[rx_message_key] $rx[stock_level_initial]";
  }

  if ($rx['stock_level_initial'] == 'REFILL ONLY') {
    $mssql->run("UPDATE cprx SET priority_cn = 220, chg_date = GETDATE() WHERE script_no = '$rx[rx_number]'");
    $mysql->run("UPDATE gp_rxs_single SET rx_message_key = 'NO ACTION FILL REFILL ONLY' WHERE rx_number = '$rx[rx_number]'");

    echo "
    $i of $count, $rx[rx_number] $rx[rx_message_key] $rx[stock_level_initial]";
  }

  if ($rx['stock_level_initial'] == 'OUT OF STOCK') {
    $mssql->run("UPDATE cprx SET priority_cn = 221, chg_date = GETDATE() WHERE script_no = '$rx[rx_number]'");
    $mysql->run("UPDATE gp_rxs_single SET rx_message_key = 'NO ACTION FILL OUT OF STOCK' WHERE rx_number = '$rx[rx_number]'");

    echo "
    $i of $count, $rx[rx_number] $rx[rx_message_key] $rx[stock_level_initial]";
  }

  if ($rx['stock_level_initial'] == 'ONE TIME') {
    $mssql->run("UPDATE cprx SET priority_cn = 222, chg_date = GETDATE() WHERE script_no = '$rx[rx_number]'");
    $mysql->run("UPDATE gp_rxs_single SET rx_message_key = 'NO ACTION FILL ONE TIME' WHERE rx_number = '$rx[rx_number]'");

    echo "
    $i of $count, $rx[rx_number] $rx[rx_message_key] $rx[stock_level_initial]";
  }
}
