<?php
class database
{
    protected $username = 'root';
    protected $password = 'ics311';
    protected $db = 'UniversityDB';
    protected $server = 'localhost';
    protected $connection;

    function __construct($username = 'root', $password = 'ics311', $db = 'UniversityDB', $server = 'localhost')
    {
        $this->username = $username;
        $this->password = $password;
        $this->db = $db;
        $this->server = $server;
        $this->connect();
    }

    function __set($name, $value)
    {
        $this->$name = $value;
    }
    function __get($name)
    {
        return $this->$name;
    }

    function connect()
    {
        if (!empty($this->username) && !empty($this->password) && !empty($this->db) && !empty($this->server)) {
            $this->connection = new mysqli("localhost", $this->username, $this->password, $this->db);
            if ($this->connection->connect_errno) {
                echo "Failed to connect to MySQL: " . $this->connection->connect_error;
                exit();
            }
        } else {
            echo "error, empty parameters";
            exit();
        }
    }
    function close()
    {
        $this->connection->close();
    }

    function Query($inputQuery)
    {
        $this->connection->query($inputQuery);
    }


    public function QueryAll($query)
    {
        $cn = $this->connection;
        if ($query == "") {
            return "Error: $query cannot be blank";
        }
        $result = $cn->query($query);
        if ($result === false) {
            return [];
        }
        // Fetch all
        $array = $result->fetch_all(MYSQLI_ASSOC);
        // Free result set
        $result->free_result();
        return $array;
    }


    public function QueryArray($query)
    {
        $cn = $this->connection;
        $result = $cn->query($query);
        $array = [];
        while ($row = $result->fetch_array(MYSQLI_ASSOC)) {
            $array[] = $row;
        }
        $result->free_result();
        return $array;
    }
    

    public function getCurrentClassID($roomNumber) {
        $roomNumber = intval($roomNumber);
        $query = "SELECT 
            c.class_id
        FROM Classes c
        JOIN ClassSchedule cs ON c.class_id = cs.class_id
        WHERE c.roomNumber = $roomNumber
        AND cs.day_of_week = DATE_FORMAT(CURDATE(), '%a')
        AND CURTIME() BETWEEN cs.start_time AND cs.end_time";

        $result = $this->QueryAll($query);
       if (count($result) === 0) {
            return null;
        } else {
            return intval($result[0]['class_id']);
        }
    }
    public function getClassName($classID) {
        $query = "SELECT class_name FROM Classes WHERE classID = $classID";
        $result = $this->QueryAll($query);
        if (count($result) === 0) {
            return null;
        } else {
            return $result[0]['class_name'];
        }
    }
    public function getClassStartTime($classID) {
        $query = "SELECT start_time FROM ClassSchedule WHERE class_id = $classID";
        $result = $this->QueryAll($query);
        return $result;
    }
    public function getClassEndTime($classID) {
        $query = "SELECT end_time FROM ClassSchedule WHERE class_id = $classID";
        $result = $this->QueryAll($query);
        return $result;
    }
}
