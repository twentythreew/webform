<?php

class Mssql {

   function __construct($ipaddress, $username, $password, $db){
      $this->ipaddress  = $ipaddress;
      $this->username   = $username;
      $this->password   = $password;
      $this->db         = $db;
      $this->connection = $this->_connect();
   }

   function _connect() {

        //sqlsrv_configure("WarningsReturnAsErrors", 0);
        $conn = mssql_connect($this->ipaddress, $this->username, $this->password);

        if ( ! is_resource($conn)) {
          $this->_emailError('Error Connection 1 of 2');

          $conn = mssql_connect($this->ipaddress, $this->username, $this->password);

          if ( ! is_resource($conn)) {
            $this->_emailError('Error Connection 2 of 2');
            return false;
          }
        }

        mssql_select_db($this->db, $conn) ?: $this->_emailError('Could not select database '.$this->db);
        return $conn;
    }

    function run($sql, $resultIndex = 0, $all_rows = true, $debug = false) {

        $stmt = mssql_query($sql, $this->connection);

        if ( ! is_resource($stmt)) {

          $this->_emailError( $stmt === true ? 'dbQuery' : 'No Resource', $stmt, $sql, $resultIndex, $all_rows);

          //Transaction (Process ID 67) was deadlocked on lock resources with another process and has been chosen as the deadlock victim. Rerun the transaction.
          if (strpos(mssql_get_last_message(), 'Rerun the transaction') !== false)
            $this->run($sql, $resultIndex, $all_rows, $debug); //Recursive

          return;
        }

        $results = $this->_getResults($stmt, $sql, $debug);

        return $all_rows ? $results[$resultIndex] : $results[$resultIndex][0];
    }

    function _getResults($stmt, $sql, $debug) {

        $results = [];

        do {
          $results[] = $this->_getRows($stmt, $sql);
        } while (mssql_next_result($stmt));

        if ($debug) {
          $this->_emailError('debugInfo', $stmt, $sql,$results);
        }

        return $results;
    }

    function _getRows($stmt, $sql) {

      if ( ! mssql_num_rows($stmt)) {
        $this->_emailError('No Rows', $stmt, $sql);
        return [];
      }

      $rows = [];
      while ($row = mssql_fetch_array($stmt)) {

          if (! empty($row['Message'])) {
            $this->_emailError('dbMessage', $row, $stmt, $sql, $data);
          }

          $rows[] = $row;
      }

      return $rows;
    }

    function _emailError() {
      echo "CRON: Debug MSSQL", print_r(func_get_args(), true).' '.print_r(mssql_get_last_message(), true);
      mail('adam@sirum.org', "CRON: Debug MSSQL ", print_r(func_get_args(), true).' '.print_r(mssql_get_last_message(), true));
    }
}
