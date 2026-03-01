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
        $password = $this->connection->real_escape_string((string)$password);
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
            return true;
        }
    }

    public function ensureAdminTables()
    {
        $this->Query("
            CREATE TABLE IF NOT EXISTS user_permissions (
                username VARCHAR(100) PRIMARY KEY,
                can_manage_users TINYINT(1) NOT NULL DEFAULT 0,
                can_manage_faces TINYINT(1) NOT NULL DEFAULT 0,
                can_manage_doors TINYINT(1) NOT NULL DEFAULT 0,
                can_view_logs TINYINT(1) NOT NULL DEFAULT 1,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            )
        ");

        $this->Query("
            CREATE TABLE IF NOT EXISTS admin_logs (
                log_id BIGINT AUTO_INCREMENT PRIMARY KEY,
                actor_username VARCHAR(100) NULL,
                target_username VARCHAR(100) NULL,
                action_type VARCHAR(64) NOT NULL,
                details TEXT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_action_time (action_type, created_at),
                INDEX idx_target_time (target_username, created_at)
            )
        ");

        $this->Query("
            CREATE TABLE IF NOT EXISTS door_control_rooms (
                door_id VARCHAR(50) PRIMARY KEY,
                room_number INT NULL,
                is_locked TINYINT(1) NOT NULL DEFAULT 0,
                lock_mode VARCHAR(40) NOT NULL DEFAULT 'unlocked',
                lock_reason VARCHAR(255) NULL,
                unlock_until DATETIME NULL,
                last_changed_by VARCHAR(100) NULL,
                last_changed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_room_number (room_number)
            )
        ");

        $this->ensureColumnIfMissing('user_permissions', 'can_manage_users', "TINYINT(1) NOT NULL DEFAULT 0");
        $this->ensureColumnIfMissing('user_permissions', 'can_manage_faces', "TINYINT(1) NOT NULL DEFAULT 0");
        $this->ensureColumnIfMissing('user_permissions', 'can_manage_doors', "TINYINT(1) NOT NULL DEFAULT 0");
        $this->ensureColumnIfMissing('user_permissions', 'can_view_logs', "TINYINT(1) NOT NULL DEFAULT 1");
        $this->ensureColumnIfMissing('door_control_rooms', 'unlock_until', "DATETIME NULL");

        $this->Query("
            INSERT INTO door_control_rooms (door_id, room_number, is_locked, lock_mode, lock_reason, unlock_until, last_changed_by)
            SELECT CAST(x.room_number AS CHAR),
                   x.room_number,
                   1,
                   'locked_until_authorized',
                   'Initial state',
                   NULL,
                   'system'
            FROM (
                SELECT DISTINCT roomNumber AS room_number
                FROM Classes
                WHERE roomNumber IS NOT NULL
            ) AS x
            ON DUPLICATE KEY UPDATE room_number = VALUES(room_number)
        ");

        // Normalize existing legacy initial rows to locked-by-default state.
        $this->Query("
            UPDATE door_control_rooms
            SET is_locked = 1,
                lock_mode = 'locked_until_authorized',
                unlock_until = NULL
            WHERE lock_reason = 'Initial state'
              AND (is_locked = 0 OR lock_mode = 'unlocked')
        ");
    }

    private function ensureColumnIfMissing($table, $column, $definition)
    {
        $tableEsc = $this->connection->real_escape_string($table);
        $columnEsc = $this->connection->real_escape_string($column);
        $dbEsc = $this->connection->real_escape_string($this->db);
        $q = "SELECT COLUMN_NAME
              FROM INFORMATION_SCHEMA.COLUMNS
              WHERE TABLE_SCHEMA = '$dbEsc'
                AND TABLE_NAME = '$tableEsc'
                AND COLUMN_NAME = '$columnEsc'
              LIMIT 1";
        $rows = $this->QueryAll($q);
        if (!$rows) {
            $this->Query("ALTER TABLE `$tableEsc` ADD COLUMN `$columnEsc` $definition");
        }
    }

    public function logAdminEvent($actorUsername, $actionType, $targetUsername = null, $details = '')
    {
        $actor = $actorUsername !== null ? "'" . $this->connection->real_escape_string($actorUsername) . "'" : "NULL";
        $target = $targetUsername !== null ? "'" . $this->connection->real_escape_string($targetUsername) . "'" : "NULL";
        $action = $this->connection->real_escape_string($actionType);
        $detailsVal = $details !== null ? "'" . $this->connection->real_escape_string($details) . "'" : "NULL";

        $query = "INSERT INTO admin_logs (actor_username, target_username, action_type, details)
                  VALUES ($actor, $target, '$action', $detailsVal)";
        $this->Query($query);
    }

    public function getAdminLogs($limit = 200)
    {
        $limit = (int)$limit;
        if ($limit < 1) {
            $limit = 200;
        }
        if ($limit > 1000) {
            $limit = 1000;
        }
        $query = "SELECT log_id, actor_username, target_username, action_type, details, created_at
                  FROM admin_logs
                  ORDER BY created_at DESC, log_id DESC
                  LIMIT $limit";
        return $this->QueryAll($query);
    }

    public function getUsersWithPermissions()
    {
        $query = "SELECT u.username,
                         u.full_name,
                         u.is_prof,
                         u.is_admin,
                         u.is_student,
                         COALESCE(p.can_manage_users, 0) AS can_manage_users,
                         COALESCE(p.can_manage_faces, 0) AS can_manage_faces,
                         COALESCE(p.can_manage_doors, 0) AS can_manage_doors,
                         COALESCE(p.can_view_logs, 1) AS can_view_logs
                  FROM users u
                  LEFT JOIN user_permissions p
                    ON p.username COLLATE utf8mb4_uca1400_ai_ci = u.username COLLATE utf8mb4_uca1400_ai_ci
                  ORDER BY u.is_admin DESC, u.is_prof DESC, u.is_student DESC, u.username";
        return $this->QueryAll($query);
    }

    public function getUserPermissions($username)
    {
        $usernameEsc = $this->connection->real_escape_string($username);
        $query = "SELECT COALESCE(can_manage_users, 0) AS can_manage_users,
                         COALESCE(can_manage_faces, 0) AS can_manage_faces,
                         COALESCE(can_manage_doors, 0) AS can_manage_doors,
                         COALESCE(can_view_logs, 1) AS can_view_logs
                  FROM user_permissions
                  WHERE username = '$usernameEsc'
                  LIMIT 1";
        $rows = $this->QueryAll($query);
        if (!$rows || !isset($rows[0])) {
            return [
                'can_manage_users' => 0,
                'can_manage_faces' => 0,
                'can_manage_doors' => 0,
                'can_view_logs' => 1,
            ];
        }
        return $rows[0];
    }

    public function updateUserRoles($username, $isProf, $isAdmin, $isStudent, $fullName = null)
    {
        $usernameEsc = $this->connection->real_escape_string($username);
        $isProf = (int)$isProf;
        $isAdmin = (int)$isAdmin;
        $isStudent = (int)$isStudent;
        $fullNameSql = '';
        if ($fullName !== null) {
            $fullName = trim((string)$fullName);
            $fullNameEsc = $this->connection->real_escape_string($fullName);
            $fullNameSql = ", full_name = '$fullNameEsc'";
        }
        $query = "UPDATE users
                  SET is_prof = $isProf,
                      is_admin = $isAdmin,
                      is_student = $isStudent
                      $fullNameSql
                  WHERE username = '$usernameEsc'
                  LIMIT 1";
        $this->Query($query);
    }

    public function updateUserRolesAndPermissions(
        $username,
        $isProf,
        $isAdmin,
        $isStudent,
        $canManageUsers,
        $canManageFaces,
        $canManageDoors,
        $canViewLogs
    ) {
        $usernameEsc = $this->connection->real_escape_string($username);
        $canManageUsers = (int)$canManageUsers;
        $canManageFaces = (int)$canManageFaces;
        $canManageDoors = (int)$canManageDoors;
        $canViewLogs = (int)$canViewLogs;

        $this->updateUserRoles($username, $isProf, $isAdmin, $isStudent);

        $query2 = "INSERT INTO user_permissions (username, can_manage_users, can_manage_faces, can_manage_doors, can_view_logs)
                   VALUES ('$usernameEsc', $canManageUsers, $canManageFaces, $canManageDoors, $canViewLogs)
                   ON DUPLICATE KEY UPDATE
                     can_manage_users = VALUES(can_manage_users),
                     can_manage_faces = VALUES(can_manage_faces),
                     can_manage_doors = VALUES(can_manage_doors),
                     can_view_logs = VALUES(can_view_logs)";
        $this->Query($query2);
    }

    private function normalizeDoorId($doorId)
    {
        $doorId = trim((string)$doorId);
        if ($doorId === '') {
            return '';
        }
        if (preg_match('/^[A-Za-z0-9_-]{1,50}$/', $doorId) !== 1) {
            return '';
        }
        return $doorId;
    }

    public function getDoorStatesForClassRooms()
    {
        $this->ensureAdminTables();
        $this->applyDoorStateTimeouts();
        $query = "SELECT r.room_number,
                         CAST(r.room_number AS CHAR) AS door_id,
                         r.class_names,
                         COALESCE(d.is_locked, 0) AS is_locked,
                         COALESCE(d.lock_mode, 'unlocked') AS lock_mode,
                         COALESCE(d.lock_reason, 'Initial state') AS lock_reason,
                         COALESCE(d.last_changed_by, 'system') AS last_changed_by,
                         d.last_changed_at
                  FROM (
                      SELECT roomNumber AS room_number,
                             GROUP_CONCAT(DISTINCT class_name ORDER BY class_name SEPARATOR ', ') AS class_names
                      FROM Classes
                      WHERE roomNumber IS NOT NULL
                      GROUP BY roomNumber
                  ) AS r
                  LEFT JOIN door_control_rooms d
                    ON BINARY d.door_id = BINARY CAST(r.room_number AS CHAR)
                  ORDER BY r.room_number";
        return $this->QueryAll($query);
    }

    public function getDoorState($doorId)
    {
        $this->ensureAdminTables();
        $this->applyDoorStateTimeouts();
        $doorId = $this->normalizeDoorId($doorId);
        if ($doorId === '') {
            return [
                'door_id' => '',
                'room_number' => null,
                'is_locked' => 0,
                'lock_mode' => 'unlocked',
                'lock_reason' => '',
                'last_changed_by' => '',
                'last_changed_at' => '',
            ];
        }
        $doorEsc = $this->connection->real_escape_string($doorId);
        $rows = $this->QueryAll("SELECT door_id, room_number, is_locked, lock_mode, lock_reason, last_changed_by, last_changed_at
                                 FROM door_control_rooms
                                 WHERE door_id = '$doorEsc'
                                 LIMIT 1");
        if (!$rows || !isset($rows[0])) {
            return [
                'door_id' => $doorId,
                'room_number' => null,
                'is_locked' => 0,
                'lock_mode' => 'unlocked',
                'lock_reason' => '',
                'last_changed_by' => '',
                'last_changed_at' => '',
            ];
        }
        return $rows[0];
    }

    public function setDoorState($doorId, $isLocked, $lockMode, $lockReason, $changedBy, $roomNumber = null, $unlockAfterSeconds = null)
    {
        $this->ensureAdminTables();
        $doorId = $this->normalizeDoorId($doorId);
        if ($doorId === '') {
            return;
        }
        $isLocked = (int)$isLocked;
        $roomNumberSql = $roomNumber !== null ? (int)$roomNumber : "NULL";
        $unlockAfterSeconds = $unlockAfterSeconds !== null ? (int)$unlockAfterSeconds : null;
        if ($unlockAfterSeconds !== null && $unlockAfterSeconds > 0) {
            $unlockUntilSql = "DATE_ADD(NOW(), INTERVAL $unlockAfterSeconds SECOND)";
        } else {
            $unlockUntilSql = "NULL";
        }
        $doorEsc = $this->connection->real_escape_string($doorId);
        $lockModeEsc = $this->connection->real_escape_string($lockMode);
        $lockReasonEsc = $this->connection->real_escape_string($lockReason);
        $changedByEsc = $this->connection->real_escape_string($changedBy);
        $this->Query("
            INSERT INTO door_control_rooms (door_id, room_number, is_locked, lock_mode, lock_reason, unlock_until, last_changed_by)
            VALUES ('$doorEsc', $roomNumberSql, $isLocked, '$lockModeEsc', '$lockReasonEsc', $unlockUntilSql, '$changedByEsc')
            ON DUPLICATE KEY UPDATE
                room_number = COALESCE(VALUES(room_number), room_number),
                is_locked = VALUES(is_locked),
                lock_mode = VALUES(lock_mode),
                lock_reason = VALUES(lock_reason),
                unlock_until = VALUES(unlock_until),
                last_changed_by = VALUES(last_changed_by),
                last_changed_at = CURRENT_TIMESTAMP
        ");
    }

    private function applyDoorStateTimeouts()
    {
        $this->Query("
            UPDATE door_control_rooms
            SET is_locked = 1,
                lock_mode = 'locked_until_authorized',
                lock_reason = 'Auto re-locked after temporary unlock',
                unlock_until = NULL,
                last_changed_by = 'system_timeout',
                last_changed_at = CURRENT_TIMESTAMP
            WHERE is_locked = 0
              AND (
                    (unlock_until IS NOT NULL AND unlock_until <= NOW())
                    OR (lock_mode = 'temporary_unlocked' AND TIMESTAMPDIFF(SECOND, last_changed_at, NOW()) >= 5)
                  )
        ");
    }

    private function deleteDirectoryRecursive($path)
    {
        if (!is_dir($path)) {
            return true;
        }
        $items = scandir($path);
        if ($items === false) {
            return false;
        }
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $target = $path . DIRECTORY_SEPARATOR . $item;
            if (is_dir($target)) {
                if (!$this->deleteDirectoryRecursive($target)) {
                    return false;
                }
            } else {
                if (!@unlink($target)) {
                    return false;
                }
            }
        }
        return @rmdir($path);
    }

    private function findUserFaceImageFolder($username)
    {
        $username = trim((string)$username);
        if ($username === '') {
            return null;
        }
        $basePath = __DIR__ . '/yilma/training_images';
        if (!is_dir($basePath)) {
            return null;
        }
        $baseReal = realpath($basePath);
        if ($baseReal === false) {
            return null;
        }
        $entries = scandir($baseReal);
        if ($entries === false) {
            return null;
        }
        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            if (strcasecmp($entry, $username) !== 0) {
                continue;
            }
            $candidate = $baseReal . DIRECTORY_SEPARATOR . $entry;
            if (!is_dir($candidate)) {
                continue;
            }
            $candidateReal = realpath($candidate);
            if ($candidateReal === false) {
                continue;
            }
            if (strpos($candidateReal, $baseReal . DIRECTORY_SEPARATOR) !== 0) {
                continue;
            }
            return [
                'entry' => $entry,
                'path' => $candidateReal,
            ];
        }
        return null;
    }

    private function deleteUserFaceWithScript($folderName)
    {
        $folderName = trim((string)$folderName);
        if ($folderName === '') {
            return [
                'deleted' => false,
                'retrain_started' => false,
            ];
        }
        $python = '/var/www//py311env/bin/python3.11';
        $script = __DIR__ . '/yilma/deleteFace.py';
        if (!is_file($python) || !is_file($script)) {
            return [
                'deleted' => false,
                'retrain_started' => false,
            ];
        }

        $cmd = 'sudo ' . escapeshellarg($python) . ' ' . escapeshellarg($script) . ' ' . escapeshellarg($folderName);
        $output = [];
        $code = 1;
        @exec($cmd . ' 2>&1', $output, $code);
        if ($code === 0) {
            return [
                'deleted' => true,
                'retrain_started' => true,
            ];
        }
        return [
            'deleted' => false,
            'retrain_started' => false,
        ];
    }

    private function deleteUserFaceImageFolder($username)
    {
        $match = $this->findUserFaceImageFolder($username);
        if (!$match || !isset($match['path'], $match['entry'])) {
            return [
                'found' => false,
                'deleted' => false,
                'retrain_started' => false,
            ];
        }

        $deleted = $this->deleteDirectoryRecursive($match['path']);
        if ($deleted) {
            return [
                'found' => true,
                'deleted' => true,
                'retrain_started' => false,
            ];
        }

        $fallback = $this->deleteUserFaceWithScript((string)$match['entry']);
        return [
            'found' => true,
            'deleted' => !empty($fallback['deleted']),
            'retrain_started' => !empty($fallback['retrain_started']),
        ];
    }

    private function startTrainerInBackground()
    {
        $python = '/var/www//py311env/bin/python3.11';
        $script = __DIR__ . '/yilma/trainer.py';
        if (!is_file($python) || !is_file($script)) {
            return false;
        }
        $cmd = 'sudo ' . escapeshellarg($python) . ' ' . escapeshellarg($script) . ' >/dev/null 2>&1 &';
        $output = [];
        $code = 1;
        @exec($cmd, $output, $code);
        return $code === 0;
    }

    public function deleteUserByUsername($username)
    {
        $faceDeleteInfo = $this->deleteUserFaceImageFolder($username);
        $faceFolderFound = is_array($faceDeleteInfo) && !empty($faceDeleteInfo['found']);
        $faceFolderDeleted = is_array($faceDeleteInfo) && !empty($faceDeleteInfo['deleted']);
        $retrainStarted = is_array($faceDeleteInfo) && !empty($faceDeleteInfo['retrain_started']);
        if ($faceFolderDeleted) {
            if (!$retrainStarted) {
                $retrainStarted = $this->startTrainerInBackground();
            }
        }
        $usernameEsc = $this->connection->real_escape_string($username);
        $this->Query("DELETE FROM user_permissions WHERE username = '$usernameEsc'");
        $this->Query("DELETE FROM users WHERE username = '$usernameEsc' LIMIT 1");
        return [
            'face_folder_found' => $faceFolderFound,
            'face_folder_deleted' => $faceFolderDeleted,
            'retrain_started' => $retrainStarted,
        ];
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
