// --- Global Data Store ---
let resources = [];

// --- Element Selections ---
const resourceForm = document.querySelector('#resource-form');
const resourcesTableBody = document.querySelector('#resources-tbody');

// --- Functions ---

/**
 * Create a table row for a resource
 * @param {Object} resource - {id, title, description, link}
 * @returns <tr> element
 */
function createResourceRow(resource) {
    const tr = document.createElement('tr');

    // Title
    const tdTitle = document.createElement('td');
    tdTitle.textContent = resource.title;
    tr.appendChild(tdTitle);

    // Description
    const tdDesc = document.createElement('td');
    tdDesc.textContent = resource.description;
    tr.appendChild(tdDesc);

    // Actions
    const tdActions = document.createElement('td');
    
    const editBtn = document.createElement('button');
    editBtn.textContent = 'Edit';
    editBtn.classList.add('edit-btn', 'btn', 'btn-sm', 'btn-warning', 'me-2');
    editBtn.dataset.id = resource.id;

    const deleteBtn = document.createElement('button');
    deleteBtn.textContent = 'Delete';
    deleteBtn.classList.add('delete-btn', 'btn', 'btn-sm', 'btn-danger');
    deleteBtn.dataset.id = resource.id;

    tdActions.appendChild(editBtn);
    tdActions.appendChild(deleteBtn);

    tr.appendChild(tdActions);

    return tr;
}

/**
 * Render the resources table
 */
function renderTable() {
    resourcesTableBody.innerHTML = '';
    resources.forEach(resource => {
        const row = createResourceRow(resource);
        resourcesTableBody.appendChild(row);
    });
}

/**
 * Handle form submission to add a new resource
 */
function handleAddResource(event) {
    event.preventDefault();

    const titleInput = document.querySelector('#resource-title');
    const descInput = document.querySelector('#resource-description');
    const linkInput = document.querySelector('#resource-link');

    const newResource = {
        id: `res_${Date.now()}`,
        title: titleInput.value.trim(),
        description: descInput.value.trim(),
        link: linkInput.value.trim()
    };

    resources.push(newResource);
    renderTable();
    resourceForm.reset();
}

/**
 * Handle click events in the table (delegation)
 */
function handleTableClick(event) {
    const target = event.target;

    if (target.classList.contains('delete-btn')) {
        const id = target.dataset.id;
        resources = resources.filter(r => r.id !== id);
        renderTable();
    }

    if (target.classList.contains('edit-btn')) {
        const id = target.dataset.id;
        const resource = resources.find(r => r.id === id);
        if (resource) {
            document.querySelector('#resource-title').value = resource.title;
            document.querySelector('#resource-description').value = resource.description;
            document.querySelector('#resource-link').value = resource.link;

            // Optional: remove old resource while editing
            resources = resources.filter(r => r.id !== id);
            renderTable();
        }
    }
}

/**
 * Load initial data and setup event listeners
 */
async function loadAndInitialize() {
    try {
        const response = await fetch('resources.json');
        if (!response.ok) throw new Error('Failed to fetch resources.');
        const data = await response.json();
        resources = data;
        renderTable();

        resourceForm.addEventListener('submit', handleAddResource);
        resourcesTableBody.addEventListener('click', handleTableClick);

    } catch (error) {
        console.error(error);
        alert('Error loading resources. Check console.');
    }
}

// --- Initial Page Load ---
loadAndInitialize();
