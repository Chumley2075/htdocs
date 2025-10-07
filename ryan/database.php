<?php
class database
{
    protected $username = 'rorot';
    protected $password = 'ics311';
    protected $db = 'UniversityDB';
    protected $server = 'localhost';
    protected $connnection;

    function __construct($username = 'roort', $password = 'ics311', $db = 'UniversityDB', $server = 'localhost')
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
        $query = "
            SELECT c.classID
            FROM ClassSchedule cs
            JOIN Classes c ON cs.class_id = c.class_id
            WHERE cs.day_of_week = DAYNAME(CURDATE())
              AND cs.start_time <= CURTIME()
              AND cs.end_time > CURTIME()
              AND c.roomNumber = $roomNumber;
        ";
        $result = $this->QueryAll($query);
        return $result;
    }
    public function getClassName($classID) {
        $query = "SELECT class_name FROM Classes WHERE classID = $classID";
        $result = $this->QueryAll($query);
        return $result;
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
