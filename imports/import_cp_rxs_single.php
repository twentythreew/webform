<?php

require_once 'dbs/mssql_cp.php';
require_once 'dbs/mysql_wc.php';
require_once 'helpers/helper_imports.php';

function import_cp_rxs_single() {

  $mssql = new Mssql_Cp();
  $mysql = new Mysql_Wc();

  $rxs = $mssql->run("

    DECLARE @today as DATETIME
    SET @today = GETDATE()

    SELECT
      script_no as rx_number,
      pat_id as patient_id_cp,
      ISNULL(generic_name, drug_name) as drug_name,
      drug_name as drug_name_raw,
      cprx.gcn_seqno as gcn,

      (CASE WHEN script_status_cn = 0 AND expire_date > @today THEN refills_left ELSE 0 END) as refills_left,
      refills_orig + 1 as refills_original,
      written_qty as qty_written,
      sig_text_english as sig_raw,

      autofill_yn as rx_autofill,
      orig_disp_date as refill_date_first,
      dispense_date as refill_date_last,
      (CASE WHEN script_status_cn = 0 AND autofill_resume_date >= @today THEN autofill_resume_date ELSE NULL END) as refill_date_manual,
      dispense_date + disp_days_supply as refill_date_default,

      script_status_cn as rx_status,
      ISNULL(IVRCmt, 'Entered') as rx_stage,
      csct_code.name as rx_source,
      last_transfer_type_io as rx_transfer,

      provider_npi,
      provider_first_name,
      provider_last_name,
      provider_phone,

      cprx.chg_date as rx_date_changed,
      expire_date as rx_date_expired

  	FROM cprx

    LEFT JOIN cprx_disp ON
      cprx_disp.rxdisp_id = last_rxdisp_id

    LEFT JOIN csct_code ON
      ct_id = 194 AND code_num = input_src_cn

    LEFT JOIN (
      SELECT
        md_id,
        MAX(name_first) as provider_first_name,
        MAX(name_last) as provider_last_name,
        MAX(npi) as provider_npi,
        MAX(phone) as provider_phone
      FROM cpmd_spi
      WHERE state = 'GA'
      GROUP BY md_id
    ) as md ON
      cprx.md_id = md.md_id

    -- TRANSLATE WEIRD BRAND NAMES TO GENERIC NAMES
  	LEFT JOIN (
  		SELECT STUFF(MIN(gni+fdrndc.ln), 1, 1, '') as generic_name, fdrndc.gcn_seqno -- WE WANT GNI FOR MIN() BUT THEN STUFF() REMOVES IT
  		FROM fdrndc
  		GROUP BY fdrndc.gcn_seqno
  	) as generic_name ON
      generic_name.gcn_seqno = cprx.gcn_seqno

    WHERE
      cprx.status_cn <> 3 AND
      (cprx.status_cn <> 2 OR last_transfer_type_io = 'O') -- NULL/0 is active, 1 is not yet dispensed?, 2 is transferred out/inactive, 3 is voided

  ");

  $keys = result_map($rxs[0],
    function($row) {

      //Clean Drug Name and save in database RTRIM(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(ISNULL(generic_name, cprx.drug_name), ' CAPSULE', ' CAP'),' CAPS',' CAP'),' TABLET',' TAB'),' TABS',' TAB'),' TB', ' TAB'),' HCL',''),' MG','MG'), '\"', ''))
      $row['drug_name'] = str_replace([' CAPSULE', ' CAPS', ' CP', ' TABLET', ' TABS', ' TB', ' HCL', ' MG', '"'], [' CAP', ' CAP', ' CAP', ' TAB', ' TAB', ' TAB', '', 'MG', ''], trim($row['drug_name']));

      echo 'result_map: '.print_r($val1, true).' '.print_r($val2, true);

      return $row;
    }
  );

  //Replace Staging Table with New Data
  $mysql->run('TRUNCATE TABLE gp_rxs_single_cp');

  $mysql->run("INSERT INTO gp_rxs_single_cp $keys VALUES ".$rxs[0]);
}
