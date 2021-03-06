<?php

    /**
     * @desc The database abstraction layer is here
     * @author Joshua Kissoon
     * @date very long ago
     * @note Magic quotes is not utilized here, we use escape_string since magic_quotes will be deprecated from php 5.4
     */
    class SQLiDatabase implements Database
    {

        private $connection;
        public $resultset, $last_query, $current_row, $field_value;

        /**
         * @desc Automatically connect to the database in the constructor
         */
        function __construct($connect = true)
        {
            if ($connect)
            {
                return $this->connect();
            }
        }

        /**
         * @desc Connect to the database
         * @return Boolean Whether the connection was successful
         */
        public function tryConnect()
        {
            $conn = mysqli_connect(DB_SERVER, DB_USER, DB_PASS);
            if ($conn)
            {
                $db_select = mysqli_select_db($conn, DB_NAME);
                if ($db_select)
                {
                    return true;
                }
            }
            return false;
        }

        /**
         * @desc Connect to the database
         */
        public function connect()
        {
            $this->connection = mysqli_connect(DB_SERVER, DB_USER, DB_PASS);
            if ($this->connection)
            {
                $db_select = mysqli_select_db($this->connection, DB_NAME);
                if ($db_select)
                {
                    return true;
                }
            }
            return false;
        }

        /**
         * @desc Select a database to use
         * @param $database The name of the database
         */
        public function selectDatabase($database)
        {
            if ($database)
            {
                /* Select the specified database */
                mysqli_select_db($this->connection, $database);
            }
            else
            {
                /* If no database specified, select the default database */
                mysqli_select_db($this->connection, DB_NAME);
            }
        }

        /**
         * @desc Queries the database to produce a result
         * @param $query The SQL statement to be executed
         * @param $variables An array of variables to replace in the query, these are passed in an array so that they can be escaped
         * @example query("SELECT * FROM user WHERE name LIKE ':name'", array(":name" => "John Smith"))
         */
        public function query($query, $variables = array(), $log_query = false)
        {
            foreach ((array) $variables as $key => $value)
            {
                $value = mysqli_real_escape_string($this->connection, @$value);
                $query = str_replace($key, $value, $query);
            }
            $this->last_query = $query;
            $this->resultset = mysqli_query($this->connection, $query);

            if (!$this->resultset)
            {
                /* If we had an error while making a query, log it into the database */
                $message = $this->escapeString("Error: " . mysqli_error($this->connection) . " Last Query: $this->last_query");
                $res = mysqli_query($this->connection, "INSERT INTO system_log (type, message) VALUES ('mysql', '$message')");
            }
            if ($log_query)
            {
                $message = $this->escapeString($this->last_query);
                $res = mysqli_query($this->connection, "INSERT INTO system_log (type, message) VALUES ('mysqli_query', '$message')");
            }
            return $this->resultset;
        }

        /**
         * @desc Quickly update a field or fields in a table
         * @param $table The table to update
         * @param $fields_values An associative array with the key being the fieldname and the value is the value
         * @param $where The where clause to limit the update
         */
        public function updateFields($table, $fields_values, $where = "1=1")
        {
            $sql = "UPDATE $table SET ";
            $last_element = end($fields_values);
            $count = 0;
            $values = array();
            foreach ($fields_values as $key => $value)
            {
                $count++;
                $s = " $key='::$count::', ";
                $values["::$count::"] = $value;
                if ($last_element == $value)
                {
                    $s = " $key='::$count::'";
                }
                $sql .= $s;
            }
            $sql .= " WHERE $where";
            $res = $this->query($sql, $values);
            return $res;
        }

        /**
         * @desc Quickly grab the data from a field from a specified table
         * @param $table The name of the table to update
         * @param $field The field which to return
         * @param $where The where clause to limit the resultset
         */
        public function getFieldValue($table, $field_name, $where = "1=1")
        {
            $sql = "SELECT $field_name FROM $table WHERE $where LIMIT 1";
            $res = $this->fetchObject($this->query($sql));
            if ($res)
            {
                $this->field_value = $field_name;
                return $res->$field_name;
            }
        }

        /**
         * @desc Method to fetch a row from the resultset in the form of an array
         * @param $resultset The result set from which to fetch the row
         */
        public function fetchArray($resultset = null)
        {
            if (!$resultset)
            {
                return false;
            }
            $this->current_row = mysqli_fetch_array($resultset);
            return $this->current_row;
        }

        /**
         * @desc Method to fetch a row from the resultset in the form of an object
         * @param $resultset The result set from which to fetch the row
         */
        public function fetchObject($resultset = null)
        {
            if (!$resultset)
            {
                return false;
            }
            $this->current_row = mysqli_fetch_object($resultset);
            return $this->current_row;
        }

        /**
         * @desc Returns the number of rows in a resultset
         * @param $resultset The result set from which to check the number of rows
         */
        public function resultNumRows($resultset = null)
        {
            if (!$resultset)
            {
                $resultset = $this->resultset;
            }
            return mysqli_num_rows($resultset);
        }

        /**
         * @desc Returns the ID value for the last row inserted into the database
         */
        public function lastInsertId()
        {
            return mysqli_insert_id($this->connection);
        }

        /**
         * @desc Escapes a string so that it is safe to be used in a query
         * @param $value The value to be escaped
         */
        public function escapeString($value)
        {
            if (get_magic_quotes_gpc())
            {
                /* undo any magic quote effects so mysqli_real_escape_string can do the work */
                $value = stripslashes($value);
            }
            return mysqli_real_escape_string($this->connection, $value);
        }

    }
    