<?php
/*
    File: Database.php
    Description: Handles database connection, query and insertion functions for the Student Course Enrollment Ssystem
    Author: Sarah Manago
    Date: 09-25-2026
*/
class Database
{
    //Database connection properties. For this assignment, default to local MySQL database
    private $host = "localhost";
    private $username = "root";
    private $password = "";
    private $database = "cst499capstone_scesdb";

    /*
        Function: connect
        Description: Creates and returns a connection to a MySQL database.
        Returnes: mysqli connection object
    */
    public function connect()
    {
        //Create the new MySQL connection   
        $con = new mysqli(
            $this->host,
            $this->username,
            $this->password,
            $this->database
        );

        //Check for errors
        if ($con->connect_error) {
            die("Database connection failed: " . $con->connect_error);
        }

        return $con;
    }

    /*
        Function: executeSelectQuery
        Description: Executes a SELECT SQL query and returns the result set.
        Parameters:
            $con: database connection object
            $sql: SQL query string
        Returns: result record set if successful
    */
    public function executeSelectQuery($con, $sql)
    {
        $result = $con->query($sql);

        if (!$result) {
            die("Select query failed: " . $con->error);
        }

        return $result;
    }
    /*
        Function: executeQuery
        Description: Executes INSERT, UPDATE, or DELETE SQL queries.
        Parameters:
            $con: database connections object
            $sql: SQL command string
        Returnes: true if successful
    */
    public function executeQuery($con, $sql)
    {
        if ($con->query($sql) === TRUE) {
            return true;
        } else {
            die("Query failed: " . $con->error);
        }
    }
}
