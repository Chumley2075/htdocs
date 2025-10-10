CREATE DATABASE UniversityDB;

USE UniversityDB;

CREATE TABLE Students (
    student_id INT AUTO_INCREMENT PRIMARY KEY,
    first_name VARCHAR(50),
    last_name VARCHAR(50)
);

CREATE TABLE Professors (
    professor_id INT AUTO_INCREMENT PRIMARY KEY,
    first_name VARCHAR(50),
    last_name VARCHAR(50)
);

CREATE TABLE Admins (
    admin_id INT AUTO_INCREMENT PRIMARY KEY,
    first_name VARCHAR(50),
    last_name VARCHAR(50)
);

CREATE TABLE Classes (
    class_id INT AUTO_INCREMENT PRIMARY KEY,
    class_name VARCHAR(100),
    roomNumber INT,
    professor_id INT,
    FOREIGN KEY (professor_id) REFERENCES Professors(professor_id)
);
CREATE TABLE ClassSchedule (
    schedule_id INT AUTO_INCREMENT PRIMARY KEY,
    class_id INT NOT NULL,
    day_of_week ENUM('Mon','Tue','Wed','Thu','Fri','Sat','Sun') NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL, 
    FOREIGN KEY (class_id) REFERENCES Classes(class_id)
);



INSERT INTO Professors (professor_id, first_name, last_name) VALUES
(1, 'Alice',  'Nguyen'),
(2, 'Marcus', 'Holt'),
(3, 'Priya',  'Sharma');

-- --------
-- Admins
-- --------
INSERT INTO Admins (admin_id, first_name, last_name) VALUES
(1, 'Dana', 'Reed'),
(2, 'Luis', 'Gomez');

-- --------
-- Students
-- --------
INSERT INTO Students (student_id, first_name, last_name) VALUES
(1, 'Ryan',     'Fors'),
(2, 'Ubah',     'Mohamed'),
(3, 'Jordan',   'Lee'),
(4, 'Amira',    'Patel'),
(5, 'Sofia',    'Martinez'),
(6, 'Ethan',    'Kim'),
(7, 'Noah',     'Bennett'),
(8, 'Chloe',    'Williams');

-- --------
-- Classes (note professor_id must exist)
-- --------
INSERT INTO Classes (class_id, class_name, roomNumber, professor_id) VALUES
(1, 'ICS 101 - Intro to Computing',            115, 1),
(2, 'MATH 220 - Discrete Mathematics',         205, 2),
(3, 'ENG 150 - Academic Writing',              118, 3),
(4, 'ICS 370 - Software Design Modeling',      115, 1),
(5, 'PHYS 110 - General Physics I',            210, 2);



-- ICS 101 - Mon/Wed 09:00–10:15
INSERT INTO ClassSchedule (schedule_id, class_id, day_of_week, start_time, end_time) VALUES
(1, 1, 'Mon', '09:00:00', '10:15:00'),
(2, 1, 'Wed', '09:00:00', '10:15:00');

-- MATH 220 - Tue/Thu 13:30–14:45
INSERT INTO ClassSchedule (schedule_id, class_id, day_of_week, start_time, end_time) VALUES
(3, 2, 'Tue', '13:30:00', '14:45:00'),
(4, 2, 'Thu', '13:30:00', '14:45:00');

-- ENG 150 - Mon 18:00–20:30 (single weekly meeting)
INSERT INTO ClassSchedule (schedule_id, class_id, day_of_week, start_time, end_time) VALUES
(5, 3, 'Mon', '18:00:00', '20:30:00');

-- ICS 370 - Tue/Thu 18:00–21:20 (matches your display example)
INSERT INTO ClassSchedule (schedule_id, class_id, day_of_week, start_time, end_time) VALUES
(6, 4, 'Tue', '18:00:00', '21:20:00'),
(7, 4, 'Thu', '18:00:00', '21:20:00');

-- PHYS 110 - Fri 10:00–12:30
INSERT INTO ClassSchedule (schedule_id, class_id, day_of_week, start_time, end_time) VALUES
(8, 5, 'Fri', '10:00:00', '12:30:00');

INSERT INTO ClassSchedule (schedule_id, class_id, day_of_week, start_time, end_time) VALUES
(9, 1, 'Tue', '15:00:00', '23:00:00');



SELECT 
            c.class_id
        FROM Classes c
        JOIN ClassSchedule cs ON c.class_id = cs.class_id
        WHERE c.roomNumber = 115
        AND cs.day_of_week = DATE_FORMAT(CURDATE(), '%a')
        AND CURTIME() BETWEEN cs.start_time AND cs.end_time;
        
        
       SELECT end_time FROM ClassSchedule WHERE class_id = 1