// resources.js
document.addEventListener('DOMContentLoaded', function() {
    // Search functionality
    const searchInput = document.getElementById('search');
    const filterSelect = document.getElementById('filter');
    
    if (searchInput && filterSelect) {
        searchInput.addEventListener('input', filterResources);
        filterSelect.addEventListener('change', filterResources);
    }
    
    function filterResources() {
        const searchText = searchInput.value.toLowerCase();
        const filterValue = filterSelect.value;
        const cards = document.querySelectorAll('.resource-card');
        
        cards.forEach(card => {
            const title = card.querySelector('h3').textContent.toLowerCase();
            const desc = card.querySelector('p').textContent.toLowerCase();
            const type = card.getAttribute('data-type');
            
            const matchesSearch = title.includes(searchText) || desc.includes(searchText);
            const matchesFilter = filterValue === 'all' || type === filterValue;
            
            card.style.display = (matchesSearch && matchesFilter) ? 'block' : 'none';
        });
    }
});

// Admin: Add new resource
function addResource() {
    const title = prompt('Resource title:');
    if (!title) return;
    
    const desc = prompt('Description:');
    if (!desc) return;
    
    const type = prompt('Type (lecture/book/video/link):', 'lecture');
    if (!type) return;
    
    const newCard = document.createElement('div');
    newCard.className = 'resource-card';
    newCard.setAttribute('data-type', type);
    
    let typeColor = '#4CAF50';
    if (type === 'book') typeColor = '#2196F3';
    if (type === 'video') typeColor = '#FF9800';
    if (type === 'link') typeColor = '#9C27B0';
    
    newCard.innerHTML = `
        <h3>${title}</h3>
        <span class="type" style="background:${typeColor}">${type}</span>
        <p>${desc}</p>
        <a href="view.html">View Details</a>
        <button onclick="addComment('new')" class="secondary">Comments (0)</button>
    `;
    
    document.getElementById('resources-list').appendChild(newCard);
    alert('Resource added!');
}

// Add comment
function addComment(resourceId) {
    const comment = prompt('Enter your comment:');
    if (comment) {
        alert(`Comment added to resource ${resourceId}`);
    }
}
