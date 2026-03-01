<?php
class database
{
    protected $username = 'root';
    protected $password = 'password';
    protected $db = 'UniversityDB';
    protected $server = 'localhost';
    protected $connection;

    function __construct($username = 'root', $password = 'password', $db = 'UniversityDB', $server = 'localhost')
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
            $this->connection = new mysqli($this->server, $this->username, $this->password, $this->db);
            if ($this->connection->connect_errno) {
                echo "Failed to connect to MySQL: " . $this->connection->connect_error;
                exit();
            }
            if (method_exists($this->connection, 'set_charset')) {
                $this->connection->set_charset('utf8mb4');
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
        $array = $result->fetch_all(MYSQLI_ASSOC);
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

    public function getCurrentClassID($roomNumber)
    {
        $roomNumber = intval($roomNumber);
        $query = "SELECT c.class_id
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

    public function getNextClassID($roomNumber): ?int
    {
        $roomNumber = intval($roomNumber);
        $query = "SELECT c.class_id
                  FROM Classes c
                  JOIN ClassSchedule cs ON c.class_id = cs.class_id
                  WHERE c.roomNumber = $roomNumber
                    AND cs.day_of_week = DATE_FORMAT(CURDATE(), '%a')
                    AND cs.start_time > (
                        SELECT csCurrent.end_time
                        FROM Classes cCurrent
                        JOIN ClassSchedule csCurrent ON cCurrent.class_id = csCurrent.class_id
                        WHERE cCurrent.roomNumber = $roomNumber
                          AND csCurrent.day_of_week = DATE_FORMAT(CURDATE(), '%a')
                          AND CURTIME() BETWEEN csCurrent.start_time AND csCurrent.end_time
                        ORDER BY csCurrent.end_time ASC
                        LIMIT 1
                    )
                  ORDER BY cs.start_time ASC
                  LIMIT 1";
        $result = $this->QueryAll($query);
        if (count($result) === 0) {
            return null;
        } else {
            return intval($result[0]['class_id']);
        }
    }

    public function getClassName($classID)
    {
        $classID = intval($classID);
        $query = "SELECT class_name FROM Classes WHERE class_id = $classID";
        $result = $this->QueryAll($query);
        if (count($result) === 0) {
            return null;
        } else {
            return $result[0]['class_name'];
        }
    }

    public function getClassStartTime($classID)
    {
        $classID = intval($classID);
        $query = "SELECT start_time
                  FROM ClassSchedule
                  WHERE class_id = $classID
                    AND day_of_week = DATE_FORMAT(CURDATE(), '%a')
                  ORDER BY start_time ASC
                  LIMIT 1";
        $result = $this->QueryAll($query);
        if (count($result) === 0) {
            return null;
        } else {
            return $result[0]['start_time'];
        }
    }

    public function getClassEndTime($classID)
    {
        $classID = intval($classID);
        $query = "SELECT end_time
                  FROM ClassSchedule
                  WHERE class_id = $classID
                    AND day_of_week = DATE_FORMAT(CURDATE(), '%a')
                  ORDER BY end_time DESC
                  LIMIT 1";
        $result = $this->QueryAll($query);
        if (count($result) === 0) {
            return null;
        } else {
            return $result[0]['end_time'];
        }
    }

    function userExist($username)
    {
        $username = $this->connection->real_escape_string($username);
        $query = "SELECT * FROM users WHERE username = '$username'";
        if (count($this->QueryAll($query)) === 0) {
            return "error";
        } else {
            return true;
        }
    }

    function getHashedPass($username)
    {
        $username = $this->connection->real_escape_string($username);
        $query = "SELECT password_hash FROM users WHERE username = '$username'";
        $result = $this->QueryAll($query);
        if (count($result) === 0) {
            return '';
        } else {
            return $result[0]['password_hash'];
        }
    }

    function getUserInfo($username, $column)
    {
        $username = $this->connection->real_escape_string($username);
        $column = preg_replace('/[^a-zA-Z0-9_]/', '', $column);
        $query = "SELECT $column AS val FROM users WHERE username = '$username' LIMIT 1";
        $result = $this->QueryAll($query);
        if (!$result || !isset($result[0]['val'])) {
            return '';
        } else {
            return $result[0]['val'];
        }
    }

    function isAdmin($username)
    {
        $username = $this->connection->real_escape_string($username);
        $query = "SELECT is_admin FROM users WHERE username = '$username'";
        $rows = $this->QueryAll($query);
        if (!$rows || !isset($rows[0]['is_admin'])) {
            return false;
        }
        return (int)$rows[0]['is_admin'] === 1;
    }

    function createUser($username, $full_name, $password, $is_prof, $is_admin, $is_student)
    {
        $username = $this->connection->real_escape_string($username);
        $full_name = $this->connection->real_escape_string($full_name);
        $password = $this->connection->real_escape_string($password);
        $is_prof = (int)$is_prof;
        $is_admin = (int)$is_admin;
        $is_student = (int)$is_student;

        $query = "SELECT * FROM users WHERE username = '$username'";
        if (count($this->QueryAll($query)) >= 1) {
            return "Error";
        } else {
            $query = "INSERT INTO users (username, full_name, password_hash, is_prof, is_admin, is_student)
                      VALUES ('$username','$full_name','$password',$is_prof,$is_admin,$is_student)";
            $this->Query($query);
        }
    }

    function isProf($username)
    {
        $username = $this->connection->real_escape_string($username);
        $query = "SELECT is_prof FROM users WHERE username = '$username'";
        $rows = $this->QueryAll($query);
        if (!$rows || !isset($rows[0]['is_prof'])) {
            return false;
        }
        return (int)$rows[0]['is_prof'] === 1;
    }

    function getClasses($username)
    {
        $username = $this->connection->real_escape_string($username);
        $query = "SELECT * FROM Classes WHERE professor_username = '$username' ORDER BY class_name";
        $result = $this->QueryAll($query);
        if (count($result) === 0) {
            return [];
        } else {
            return $result;
        }
    }

    public function getClassList($profUsername)
    {
        $profUsername = $this->connection->real_escape_string($profUsername);
        $q = "SELECT class_id, class_name, roomNumber
              FROM Classes
              WHERE professor_username = '$profUsername'
              ORDER BY class_name";
        return $this->QueryAll($q);
    }

    public function getAttendanceToday($classID)
    {
        $classID = (int)$classID;
        $q = "SELECT a.student_username,
                     COALESCE(u.full_name, a.student_username) AS full_name,
                     a.scanned_at
              FROM Attendance a
              LEFT JOIN users u
                ON u.username COLLATE utf8mb4_uca1400_ai_ci = a.student_username COLLATE utf8mb4_uca1400_ai_ci
              WHERE a.class_id = $classID
                AND a.meeting_date = CURDATE()
              ORDER BY full_name";
        return $this->QueryAll($q);
    }

    public function getRosterWithAttendanceToday($classID)
    {
        $classID = (int)$classID;
        $q = "SELECT e.student_username,
                     COALESCE(u.full_name, e.student_username) AS full_name,
                     a.scanned_at
              FROM Enrollments e
              JOIN users u
                ON u.username COLLATE utf8mb4_uca1400_ai_ci = e.student_username COLLATE utf8mb4_uca1400_ai_ci
              LEFT JOIN Attendance a
                ON a.class_id = e.class_id
               AND a.student_username COLLATE utf8mb4_uca1400_ai_ci = e.student_username COLLATE utf8mb4_uca1400_ai_ci
               AND a.meeting_date = CURDATE()
              WHERE e.class_id = $classID
              ORDER BY full_name";
        return $this->QueryAll($q);
    }

    public function insertAttendance($classID, $username)
    {
        $classID = (int)$classID;
        $username = $this->connection->real_escape_string($username);
        $q = "INSERT INTO Attendance (class_id, student_username, meeting_date, scanned_at)
              VALUES ($classID, '$username', CURDATE(), NOW())
              ON DUPLICATE KEY UPDATE scanned_at = VALUES(scanned_at)";
        $this->Query($q);
    }
}
