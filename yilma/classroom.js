// Classroom Data
const classrooms = {
    "ICS 370": {
        name: "Software Design Models",
        time: "Thursday 6:00PM - 9:20PM",
        professor: "Prof. Hassan",
        room: "SB-301"
    },
    "CYBER 362": {
        name: "Network Security",
        time: "Monday 4:00PM - 6:30PM",
        professor: "Dr. Smith",
        room: "TC-205"
    },
    "ICS 377": {
        name: "Database Systems",
        time: "Wednesday 2:00PM - 4:30PM",
        professor: "Dr. Johnson",
        room: "BH-110"
    }
};

// Students Data
const students = {
    "ICS 370": [
        { id: "S1001", name: "Ryan Fors" },
        { id: "S1002", name: "Ubah Mohamed" },
        { id: "S1003", name: "Yilma Ayele" },
        { id: "S1004", name: "Alex Johnson" },
        { id: "S1005", name: "Maria Garcia" }
    ],
    "CYBER 362": [
        { id: "S2001", name: "John Davis" },
        { id: "S2002", name: "Sarah Wilson" },
        { id: "S2003", name: "Mike Brown" },
        { id: "S2004", name: "Lisa Taylor" }
    ],
    "ICS 377": [
        { id: "S3001", name: "David Lee" },
        { id: "S3002", name: "Emily Chen" },
        { id: "S3003", name: "Kevin Wang" },
        { id: "S3004", name: "Amy Rodriguez" }
    ]
};

// Generate random attendance data
function generateAttendance() {
    const attendance = {};
    
    Object.keys(classrooms).forEach(className => {
        attendance[className] = students[className].map(student => {
            const rand = Math.random();
            let status;
            if (rand > 0.7) {
                status = "Present";
            } else if (rand > 0.4) {
                status = "Late";
            } else {
                status = "Absent";
            }
            
            return {
                ...student,
                status: status
            };
        });
    });
    
    return attendance;
}

const attendance = generateAttendance();