// --- Element Selection ---
const listSection = document.getElementById('resource-list-section');

/**
 * Create an <article> element for a single resource.
 * @param {Object} resource - {id, title, description}
 * @returns {HTMLElement} - The article element
 */
function createResourceArticle(resource) {
    const article = document.createElement('article');

    // Resource title
    const h2 = document.createElement('h2');
    h2.textContent = resource.title;
    article.appendChild(h2);

    // Resource description
    const p = document.createElement('p');
    p.textContent = resource.description;
    article.appendChild(p);

    // Link to resource details
    const a = document.createElement('a');
    a.href = `details.html?id=${resource.id}`;
    a.textContent = 'View Resource & Discussion';
    article.appendChild(a);

    return article;
}

/**
 * Load resources from JSON and populate the section
 */
async function loadResources() {
    try {
        const response = await fetch('resources.json');
        if (!response.ok) throw new Error('Failed to fetch resources.');

        const resources = await response.json();

        // Clear existing content
        listSection.innerHTML = '';

        // Create and append articles
        resources.forEach(resource => {
            const article = createResourceArticle(resource);
            listSection.appendChild(article);
        });
    } catch (error) {
        console.error(error);
        listSection.innerHTML = '<p>Failed to load resources.</p>';
    }
}

// Initial load
loadResources();
