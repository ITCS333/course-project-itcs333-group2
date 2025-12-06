/*
  Requirement: Add interactivity and data management to the Admin Portal.
  This script handles C(reate), R(ead), U(pdate), D(elete), Search, and Sort.
*/

// --- Global Data Store ---
let students = [];
let sortState = { column: null, direction: 'asc' };

// --- Element Selections (Ensure these IDs match your HTML) ---
const studentTableBody = document.querySelector('#student-table tbody');
const addStudentForm = document.getElementById('add-student-form');
const changePasswordForm = document.getElementById('password-form');
const searchInput = document.getElementById('search-input');
const tableHeaders = document.querySelectorAll('#student-table thead th');
const STUDENT_DATA_PATH = './data/students.json';


// --- Helper Functions ---

/** Helper function to create a table data cell */
function createTableCell(content) {
    const td = document.createElement('td');
    td.textContent = content;
    return td;
}

/**
 * CRITICAL FOR DELETE/EDIT: Creates a table row (<tr>) and adds data-id to buttons.
 */
function createStudentRow(student) {
    const row = document.createElement('tr');
    
    // Data Cells
    row.appendChild(createTableCell(student.name));
    row.appendChild(createTableCell(student.id));
    row.appendChild(createTableCell(student.email));
    
    // Actions Cell
    const actionCell = document.createElement('td');
    
    // Edit Button
    const editBtn = document.createElement('button');
    editBtn.textContent = 'Edit';
    editBtn.classList.add('edit-btn');
    editBtn.setAttribute('data-id', student.id); // <--- ID is required for Edit
    actionCell.appendChild(editBtn);

    // Delete Button
    const deleteBtn = document.createElement('button');
    deleteBtn.textContent = 'Delete';
    deleteBtn.classList.add('delete-btn');
    deleteBtn.setAttribute('data-id', student.id); // <--- ID is required for Delete
    actionCell.appendChild(deleteBtn);

    row.appendChild(actionCell);
    return row;
}

/**
 * Renders the table view from an array of student objects.
 */
function renderTable(studentArray) {
    if (!studentTableBody) return;
    studentTableBody.innerHTML = '';
    
    if (studentArray.length === 0) {
        studentTableBody.innerHTML = '<tr><td colspan="4" style="text-align: center; color: #6c757d;">No students found.</td></tr>';
        return;
    }

    studentArray.forEach(student => {
        studentTableBody.appendChild(createStudentRow(student));
    });
}

/**
 * Handles the form submission for changing the admin password.
 */
function handleChangePassword(event) {
    event.preventDefault();

    const currentPass = document.getElementById('current-password').value;
    const newPass = document.getElementById('new-password').value;
    const confirmPass = document.getElementById('confirm-password').value;

    if (newPass !== confirmPass) {
        alert("🚨 Passwords do not match.");
        return;
    }
    
    if (newPass.length < 8) {
        alert("🔒 Password must be at least 8 characters.");
        return;
    }

    alert("✅ Password updated successfully! (Note: This is a client-side simulation.)");
    
    changePasswordForm.reset();
}

// ---------------------------------------------
// CORE ADD (CREATE) FUNCTIONALITY
// ---------------------------------------------

/**
 * Implemented: Handles the form submission for adding a new student (C - Create).
 */
function handleAddStudent(event) {
    event.preventDefault();

    // 1. Get Values
    const name = document.getElementById('student-name').value.trim();
    const id = document.getElementById('student-id').value.trim();
    const email = document.getElementById('student-email').value.trim();
    const defaultPassword = document.getElementById('default-password').value.trim(); 

    // 2. Validation
    if (!name || !id || !email) {
        alert("⚠️ Please fill out all required fields (Name, ID, Email).");
        return;
    }

    // Check for duplicate ID
    if (students.some(s => s.id === id)) {
        alert(`❌ A student with ID ${id} already exists.`);
        return;
    }

    // 3. Data Manipulation
    const newStudent = { name, id, email, password: defaultPassword };
    students.push(newStudent);
    
    // 4. View Update
    renderTable(students);
    alert(`🎉 Student ${name} (ID: ${id}) added successfully.`);
    addStudentForm.reset();
}


// ---------------------------------------------
// CORE DELETE & EDIT (UPDATE) FUNCTIONALITY
// ---------------------------------------------

/**
 * Implemented: Handles clicks within the table body (Delete and Edit actions).
 */
function handleTableClick(event) {
    // Event delegation: listen on the table body and check the specific target
    const target = event.target;
    const studentId = target.getAttribute('data-id');
    
    if (!studentId) return;

    // --- DELETE Action (D - Delete) ---
    if (target.classList.contains('delete-btn')) {
        
        if (!confirm(`Are you sure you want to delete student ID ${studentId}? This action cannot be undone.`)) {
            return;
        }

        // 1. Data Manipulation: Filter out the student from the global array
        students = students.filter(student => student.id !== studentId);
        
        // 2. View Update
        renderTable(students);
        alert(`🗑️ Student ID ${studentId} deleted.`);

    // --- EDIT Action (U - Update) ---
    } else if (target.classList.contains('edit-btn')) {
        
        const studentToEdit = students.find(s => s.id === studentId);
        if (!studentToEdit) return;

        // 1. Get new name using prompt (simple UI for now)
        const newName = prompt(`Editing Student: ${studentToEdit.name}\nEnter new Full Name:`, studentToEdit.name);
        
        if (newName === null || newName.trim() === "") {
            if (newName !== null) alert("Edit cancelled or name was empty.");
            return;
        }
        
        // 2. Get new email
        const newEmail = prompt(`Enter new Email for ${newName.trim()}:`, studentToEdit.email);
        
        if (newEmail === null || newEmail.trim() === "") {
            if (newEmail !== null) alert("Edit cancelled or email was empty.");
            return;
        }

        // 3. Data Manipulation: Update the object properties
        studentToEdit.name = newName.trim();
        studentToEdit.email = newEmail.trim();

        // 4. View Update
        renderTable(students);
        alert(`✏️ Student ID ${studentId} updated successfully.`);
    }
}

// ---------------------------------------------
// SEARCH, SORT, AND INITIALIZATION FUNCTIONS
// ---------------------------------------------

/**
 * Implemented: Filters the table based on user input in the search box.
 */
function handleSearch(event) {
    const searchTerm = event.target.value.toLowerCase().trim();

    if (searchTerm === '') {
        renderTable(students);
        return;
    }

    const filteredStudents = students.filter(student =>
        student.name.toLowerCase().includes(searchTerm) ||
        student.id.includes(searchTerm) ||
        student.email.toLowerCase().includes(searchTerm)
    );

    renderTable(filteredStudents);
}

/**
 * Implemented: Sorts the table when a header is clicked.
 */
function handleSort(event) {
    const header = event.currentTarget;
    const property = header.getAttribute('data-sort-prop'); 
    
    if (!property) return;

    let direction = 'asc';
    if (sortState.column === property) {
        direction = sortState.direction === 'asc' ? 'desc' : 'asc';
    }
    sortState = { column: property, direction };

    students.sort((a, b) => {
        let aVal = a[property];
        let bVal = b[property];
        let comparison = 0;

        if (property === 'id') {
            comparison = parseInt(aVal, 10) - parseInt(bVal, 10);
        } else {
            comparison = aVal.localeCompare(bVal);
        }

        return direction === 'asc' ? comparison : -comparison;
    });

    tableHeaders.forEach(th => th.removeAttribute('data-sort-dir'));
    header.setAttribute('data-sort-dir', direction);
    
    renderTable(students);
}

/**
 * Implemented: Loads data from JSON, assigns it to the global array, and sets up listeners.
 */
async function loadStudentsAndInitialize() {
    try {
        const response = await fetch(STUDENT_DATA_PATH);
        
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        
        const data = await response.json();
        students = data;
        renderTable(students);
        
        // Setup all event listeners (CRITICAL: Ensures buttons work)
        if (changePasswordForm) changePasswordForm.addEventListener('submit', handleChangePassword);
        if (addStudentForm) addStudentForm.addEventListener('submit', handleAddStudent);
        if (studentTableBody) {
            // Event Delegation for Edit/Delete buttons
            studentTableBody.addEventListener('click', handleTableClick);
        }
        if (searchInput) searchInput.addEventListener('input', handleSearch);
        
        // Setup Sort Listeners
        if (tableHeaders.length >= 3) {
            const props = ['name', 'id', 'email', 'actions']; 
            tableHeaders.forEach((header, index) => {
                const prop = props[index];
                if (prop !== 'actions') {
                    header.setAttribute('data-sort-prop', prop);
                    header.addEventListener('click', handleSort);
                    header.style.cursor = 'pointer'; 
                }
            });
        }

    } catch (error) {
        console.error("Critical error loading student data:", error);
        if (studentTableBody) {
            studentTableBody.innerHTML = `<tr><td colspan="4" style="color: red; text-align: center;">Failed to load data from ${STUDENT_DATA_PATH}. Check console for error.</td></tr>`;
        }
    }
}

// --- Initial Page Load ---
loadStudentsAndInitialize();
