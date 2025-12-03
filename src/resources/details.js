// --- Global Data Store ---
let currentResourceId = null;
let currentComments = [];

// --- Element Selections ---
const resourceTitle = document.querySelector('#resource-title');
const resourceDescription = document.querySelector('#resource-description');
const resourceLink = document.querySelector('#resource-link');
const commentList = document.querySelector('#comment-list');
const commentForm = document.querySelector('#comment-form');
const newComment = document.querySelector('#new-comment');

// --- Functions ---
function getResourceIdFromURL() {
    const params = new URLSearchParams(window.location.search);
    return params.get('id');
}

function renderResourceDetails(resource) {
    resourceTitle.textContent = resource.subject || "Untitled Resource";
    resourceDescription.textContent = resource.message || "No description available.";
    resourceLink.href = resource.link || "#";
}

function createCommentArticle(comment) {
    const article = document.createElement('article');
    article.classList.add('comment');

    const p = document.createElement('p');
    p.textContent = comment.text;

    const footer = document.createElement('footer');
    footer.classList.add('comment-author');
    footer.textContent = `Posted by: ${comment.author}`;

    article.appendChild(p);
    article.appendChild(footer);

    return article;
}

function renderComments() {
    commentList.innerHTML = '';
    currentComments.forEach(comment => {
        const commentArticle = createCommentArticle(comment);
        commentList.appendChild(commentArticle);
    });
}

function handleAddComment(event) {
    event.preventDefault();
    const commentText = newComment.value.trim();
    if (!commentText) return;

    const newCommentObj = {
        author: 'Student',
        text: commentText
    };

    currentComments.push(newCommentObj);
    renderComments();
    newComment.value = '';
}

async function initializePage() {
    currentResourceId = getResourceIdFromURL();
    if (!currentResourceId) {
        resourceTitle.textContent = "Resource not found.";
        return;
    }

    try {
        const [resResponse, comResponse] = await Promise.all([
            fetch('resources.json'),
            fetch('resource-comments.json')
        ]);

        const resourcesData = await resResponse.json();
        const commentsData = await comResponse.json();

        const resource = resourcesData.find(r => r.id === currentResourceId);
        currentComments = commentsData[currentResourceId] || [];

        if (resource) {
            renderResourceDetails(resource);
            renderComments();
            commentForm.addEventListener('submit', handleAddComment);
        } else {
            resourceTitle.textContent = "Resource not found.";
        }

    } catch (error) {
        console.error(error);
        resourceTitle.textContent = "Error loading resource.";
    }
}

// --- Initial Page Load ---
initializePage();

