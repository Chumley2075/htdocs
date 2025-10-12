// Users Data
const users = {
    P2001: { 
        name: "Prof. Hassan", 
        role: "Professor", 
        password: "prof123", 
        classes: ["ICS 370"] 
    },
    A3001: { 
        name: "Admin", 
        role: "Administrator", 
        password: "admin123", 
        classes: ["ICS 370", "CYBER 362", "ICS 377"] 
    }
};

let currentUser = null;
let loginAttempts = 0;
const MAX_ATTEMPTS = 3;

// Login Function
document.getElementById("loginForm").addEventListener("submit", function(e) {
    e.preventDefault();
    
    const uid = document.getElementById("uid").value.trim();
    const pwd = document.getElementById("pwd").value.trim();
    
    // Input validation
    if (!uid || !pwd) {
        showMessage("Please enter both User ID and Password", "error");
        return;
    }
    
    // Check login attempts
    if (loginAttempts >= MAX_ATTEMPTS) {
        showMessage("Too many failed attempts. Please try again later.", "error");
        return;
    }
    
    // Show loading state
    const loginBtn = document.querySelector("button[type='submit']");
    loginBtn.classList.add("loading");
    
    // Simulate API call
    setTimeout(() => {
        // Authenticate user
        if (users[uid] && users[uid].password === pwd) {
            // Successful login
            currentUser = users[uid];
            loginAttempts = 0;
            showMessage(`Welcome to Metro State University, ${currentUser.name}!`, "success");
            setTimeout(showDashboard, 1000);
        } else {
            // Failed login
            loginAttempts++;
            const remaining = MAX_ATTEMPTS - loginAttempts;
            showMessage(`Invalid User ID or Password. ${remaining} attempt(s) remaining.`, "error");
            
            // Clear form after max attempts
            if (loginAttempts >= MAX_ATTEMPTS) {
                document.getElementById("loginForm").reset();
                setTimeout(() => {
                    loginAttempts = 0;
                }, 30000);
            }
        }
        loginBtn.classList.remove("loading");
    }, 1000);
});

// Show message function
function showMessage(message, type) {
    // Remove existing messages
    const existingMsg = document.querySelector(".message");
    if (existingMsg) {
        existingMsg.remove();
    }
    
    // Create message element
    const messageDiv = document.createElement("div");
    messageDiv.className = `message ${type}`;
    messageDiv.textContent = message;
    
    // Insert message
    const form = document.getElementById("loginForm");
    form.parentNode.insertBefore(messageDiv, form);
    
    // Auto remove
    setTimeout(() => {
        if (messageDiv.parentNode) {
            messageDiv.remove();
        }
    }, 4000);
}

// Show Dashboard
function showDashboard() {
    document.getElementById("login").style.display = "none";
    document.getElementById("dashboard").style.display = "block";
    document.getElementById("logoutBtn").style.display = "inline-block";
    document.getElementById("userInfo").style.display = "inline-block";
    document.getElementById("userInfo").textContent = `${currentUser.name} - ${currentUser.role}`;
    
    // Populate class dropdown
    const select = document.getElementById("classSelect");
    select.innerHTML = '<option value="">Select a Class</option>';
    
    currentUser.classes.forEach(className => {
        const option = document.createElement("option");
        option.value = className;
        option.textContent = `${className} - ${classrooms[className].name}`;
        select.appendChild(option);
    });
    
    // Show initial attendance
    if (currentUser.classes.length > 0) {
        select.value = currentUser.classes[0];
        showAttendance();
    }
}

// Show Attendance
function showAttendance() {
    const className = document.getElementById("classSelect").value;
    if (!className) return;
    
    const classData = classrooms[className];
    const attendanceData = attendance[className];
    
    // Update class info
    document.getElementById("className").textContent = `${className} - ${classData.name}`;
    document.getElementById("classTime").textContent = classData.time;
    document.getElementById("classProfessor").textContent = classData.professor;
    
    // Update table
    const tbody = document.getElementById("data");
    tbody.innerHTML = "";
    
    attendanceData.forEach(student => {
        const time = student.status === "Present" ? 
            new Date().toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'}) : "—";
        
        const row = document.createElement("tr");
        row.innerHTML = `
            <td>${student.id}</td>
            <td>${student.name}</td>
            <td><span class="${student.status.toLowerCase()}">${student.status}</span></td>
            <td>${time}</td>
        `;
        tbody.appendChild(row);
    });
}

// Logout Function
document.getElementById("logoutBtn").addEventListener("click", function() {
    currentUser = null;
    document.getElementById("dashboard").style.display = "none";
    document.getElementById("login").style.display = "block";
    document.getElementById("logoutBtn").style.display = "none";
    document.getElementById("userInfo").style.display = "none";
    document.getElementById("loginForm").reset();
    loginAttempts = 0;
});

// Enter key support for login
document.getElementById("pwd").addEventListener("keypress", function(e) {
    if (e.key === "Enter") {
        document.getElementById("loginForm").dispatchEvent(new Event('submit'));
    }
});

// Auto-focus on user ID field
document.addEventListener('DOMContentLoaded', function() {
    document.getElementById("uid").focus();
    
    // Add event listener for class selection
    document.getElementById("classSelect").addEventListener("change", showAttendance);
});