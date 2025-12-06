/*
 * FINAL JSON VERSION: Reverts to using local students.json for login verification.
 * This bypasses the database connection issue.
 */

// --- Global Variables ---
let studentData = [];

// --- Element Selections ---
const loginForm = document.getElementById('login-form');
const emailInput = document.getElementById('email');
const passwordInput = document.getElementById('password');
const messageContainer = document.getElementById('message-container');

// --- Functions (Keep these the same) ---

function displayMessage(message, type) {
    if (!messageContainer) return;
    messageContainer.textContent = message;
    messageContainer.removeAttribute('data-theme');
    messageContainer.classList.remove('success', 'error');

    if (type === 'success') {
        messageContainer.setAttribute('data-theme', 'success');
        messageContainer.classList.add('success');
    } else if (type === 'error') {
        messageContainer.setAttribute('data-theme', 'danger');
        messageContainer.classList.add('error');
    }
}

function isValidEmail(email) {
    const emailRegex = /\S+@\S+\.\S+/;
    return emailRegex.test(email);
}

function isValidPassword(password) {
    return password.length >= 8;
}

// ----------------------------------------------------------------------
// 🔑 Reintroduced JSON Data Fetching
// ----------------------------------------------------------------------

async function fetchStudentData() {
    try {
        // CORRECT PATH for your structure: src/data/students.json
        const response = await fetch('./src/data/students.json'); 

        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        studentData = await response.json();
        console.log("Student data loaded successfully.");
        displayMessage("Data loaded successfully.", "success");
    } catch (error) {
        console.error("Could not fetch student data:", error);
        displayMessage("Could not load student data. Please check the file path and JSON format.", "error");
    }
}

/**
 * Verifies the credentials against the loaded student data (ID is used as password).
 */
function verifyCredentials(email, password) {
    const student = studentData.find(s => s.email.toLowerCase() === email.toLowerCase());

    if (student) {
        // Successful match if the stored ID equals the entered password
        return student.id === password;
    }
    return false;
}


// ----------------------------------------------------------------------
// UPDATED handleLogin function for JSON lookup and redirection
// ----------------------------------------------------------------------

async function handleLogin(event) {
    event.preventDefault();
    displayMessage('', ''); 

    // Check if data is loaded
    if (studentData.length === 0) {
        displayMessage("Data not loaded. Retrying fetch...", "error");
        await fetchStudentData(); 
        if (studentData.length === 0) {
             displayMessage("Critical error: Student data is unavailable.", "error");
             return;
        }
    }

    // Get and Validate Inputs
    const email = emailInput.value.trim();
    const password = passwordInput.value.trim();

    if (!isValidEmail(email)) { displayMessage("Invalid email format.", "error"); emailInput.focus(); return; }
    if (!isValidPassword(password)) { displayMessage("Password must be at least 8 characters.", "error"); passwordInput.focus(); return; }

    // Final verification and Redirection
    if (verifyCredentials(email, password)) {
        const student = studentData.find(s => s.email.toLowerCase() === email.toLowerCase());

        displayMessage(`✅ Login successful! Welcome, ${student.name}. Redirecting...`, "success");

        // Redirect to index.html (main page) after 1.5 seconds
        setTimeout(() => {
            window.location.href = 'index.html'; 
        }, 1500); 

    } else {
        displayMessage("Login failed: Invalid email or password (ID).", "error");
    }
}

// ----------------------------------------------------------------------
// Initial setup
// ----------------------------------------------------------------------

function setupLoginForm() {
    // Load the student data when the page loads
    fetchStudentData(); 
    if (loginForm) {
        loginForm.addEventListener('submit', handleLogin);
    }
}

setupLoginForm();
