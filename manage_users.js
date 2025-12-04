/*
 * Requirement: Add interactivity and data management to the Admin Portal.
*/

// --- Global Data Store ---
let students = []; // Holds the list of students

// --- Element Selections ---

const studentTableBody = document.querySelector('#student-table tbody');
const addStudentForm = document.getElementById('add-student-form'); 

// العناصر الجديدة لنموذج التعديل (EDIT):
const editModal = document.getElementById('edit-student-modal');
const editStudentForm = document.getElementById('edit-student-form');
const editNameInput = document.getElementById('edit-name');
const editEmailInput = document.getElementById('edit-email');
const editPasswordInput = document.getElementById('edit-password');
const editStudentIdInput = document.getElementById('edit-student-id');


// --- Utility Functions (displayMessage and renderTable remain the same) ---
// (No changes needed for displayMessage or renderTable functions - they are correct)

// ... [The existing displayMessage function is here] ...

// ... [The existing renderTable function is here] ...

// ... [The existing handleAddStudent function is here] ...


/**
 * دالة جديدة: لمعالجة إرسال نموذج التعديل.
 */
function handleEditStudent(event) {
    event.preventDefault();

    const studentIdToEdit = editStudentIdInput.value;
    const studentIndex = students.findIndex(s => s.student_id === studentIdToEdit);

    if (studentIndex === -1) {
        displayMessage("Error: Student not found for editing.", "error", editStudentForm);
        return;
    }

    // 1. جلب القيم الجديدة من حقول الإدخال
    const updatedName = editNameInput.value.trim();
    const updatedEmail = editEmailInput.value.trim();
    const newPassword = editPasswordInput.value.trim(); // إذا تم إدخال قيمة جديدة

    // 2. تحديث البيانات في المصفوفة (تجاهل كلمة المرور للحساب المحلي)
    students[studentIndex].name = updatedName;
    students[studentIndex].email = updatedEmail;

    // 3. التحقق من تكرار البريد الإلكتروني (باستثناء الطالب الذي يتم تعديله)
    const isDuplicateEmail = students.some((s, index) => s.email === updatedEmail && index !== studentIndex);
    if (isDuplicateEmail) {
        displayMessage("Error: This email is already used by another student.", "error", editStudentForm);
        return;
    }
    
    // 4. إعادة رسم الجدول وإغلاق النموذج
    renderTable(students);
    editModal.removeAttribute('open'); // إغلاق الـ modal
    
    // 5. عرض رسالة النجاح في قسم إدارة الطلاب
    const studentSection = document.querySelector('section:nth-child(2)');
    displayMessage(`Student ID ${studentIdToEdit} updated successfully!`, "success", studentSection);
}


/**
 * تم تعديل هذه الدالة: للتعامل مع النقر على زر "Edit" أيضاً.
 */
function handleTableClick(event) {
    const target = event.target;
    
    if (target.tagName === 'BUTTON') {
        const action = target.getAttribute('data-action');
        const studentId = target.getAttribute('data-id');

        if (action === 'delete') {
            // ... [كود وظيفة الحذف كما هو] ...
            if (confirm(`Are you sure you want to delete student ID ${studentId}?`)) {
                const initialLength = students.length;
                students = students.filter(student => student.student_id !== studentId);
                
                if (students.length < initialLength) {
                    renderTable(students);
                    const studentSection = document.querySelector('section:nth-child(2)');
                    displayMessage(`Student ID ${studentId} deleted successfully.`, "error", studentSection);
                } 
            }
        } 
        
        if (action === 'edit') { 
            // 1. العثور على الطالب
            const studentToEdit = students.find(s => s.student_id === studentId);
            
            if (studentToEdit) {
                // 2. ملء حقول النموذج بالبيانات الحالية
                editStudentIdInput.value = studentToEdit.student_id;
                editNameInput.value = studentToEdit.name;
                editEmailInput.value = studentToEdit.email;
                editPasswordInput.value = ''; // مسح حقل كلمة المرور افتراضياً

                // 3. فتح نموذج التعديل (Modal)
                editModal.setAttribute('open', '');
                
                // 4. مسح أي رسائل قديمة
                const messageContainer = editStudentForm.querySelector('#edit-message-container');
                if (messageContainer) messageContainer.innerHTML = '';
            } else {
                console.error(`Student with ID ${studentId} not found.`);
            }
        }
    }
}


// --- Initialization ---

/**
 * Entry point: loads initial data and sets up event listeners.
 */
async function loadStudentsAndInitialize() {
    // 1. Load initial dummy data 
    students = [
        { student_id: '100123', name: 'Ahmad Al-Hashimi', email: 'ahmad.h@uni.edu' },
        { student_id: '100456', name: 'Fatima Al-Zahra', email: 'fatima.z@uni.edu' },
        { student_id: '100789', name: 'Salem Al-Ali', email: 'salem.a@uni.edu' }
    ];
    
    // 2. Populate the table for the first time
    renderTable(students);

    // 3. Set up all the event listeners:
    
    // Add Student Form Submission
    if (addStudentForm) {
        addStudentForm.addEventListener('submit', handleAddStudent);
    } else {
        console.error("Initialization Error: Add Student Form not found.");
    }
    
    // Table Click Listener (for Delete/Edit buttons)
    if (studentTableBody) {
        studentTableBody.addEventListener('click', handleTableClick);
    }
    
    // NEW: Edit Student Form Submission
    if (editStudentForm) {
        editStudentForm.addEventListener('submit', handleEditStudent);
    }
}


// 4. Call the initialization function when the script loads
loadStudentsAndInitialize();