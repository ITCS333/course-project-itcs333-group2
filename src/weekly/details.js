/*
  Details Page Script
  Loads week details + comments and allows adding new comments.
*/

// --- Global Data ---
let currentWeekId = null;
let currentComments = [];

// --- Element Selections ---
const weekTitle = document.getElementById("week-title");
const weekStartDate = document.getElementById("week-start-date");
const weekDescription = document.getElementById("week-description");
const weekLinksList = document.getElementById("week-links-list");
const commentList = document.getElementById("comment-list");
const commentForm = document.getElementById("comment-form");
const newCommentText = document.getElementById("new-comment-text");


// ------------------------------
// Get week ID from URL
// ------------------------------
function getWeekIdFromURL() {
    const params = new URLSearchParams(window.location.search);
    return params.get("id");
}


// ------------------------------
// Render Week Details
// ------------------------------
function renderWeekDetails(week) {
    weekTitle.textContent = week.title;
    weekStartDate.textContent = "Starts on: " + week.startDate;
    weekDescription.textContent = week.description;

    // Clear list
    weekLinksList.innerHTML = "";

    // Add links
    week.links.forEach(link => {
        const li = document.createElement("li");
        const a = document.createElement("a");
        a.href = link;
        a.textContent = link;
        li.appendChild(a);
        weekLinksList.appendChild(li);
    });
}


// ------------------------------
// Create comment element
// ------------------------------
function createCommentArticle(comment) {
    const article = document.createElement("article");

    const p = document.createElement("p");
    p.textContent = comment.text;

    const footer = document.createElement("footer");
    footer.textContent = "Posted by: " + comment.author;

    article.appendChild(p);
    article.appendChild(footer);

    return article;
}


// ------------------------------
// Render comments
// ------------------------------
function renderComments() {
    commentList.innerHTML = "";

    currentComments.forEach(comment => {
        const article = createCommentArticle(comment);
        commentList.appendChild(article);
    });
}


// ------------------------------
// Add new comment
// ------------------------------
function handleAddComment(event) {
    event.preventDefault();

    const text = newCommentText.value.trim();
    if (!text) return;

    const newComment = {
        author: "Student",
        text: text
    };

    currentComments.push(newComment);
    renderComments();

    newCommentText.value = "";
}


// ------------------------------
// Initialize Page
// ------------------------------
async function initializePage() {
    currentWeekId = getWeekIdFromURL();

    if (!currentWeekId) {
        weekTitle.textContent = "Week not found.";
        return;
    }

    try {
        const [weeksRes, commentsRes] = await Promise.all([
            fetch("weeks.json"),
            fetch("week-comments.json")
        ]);

        const weeks = await weeksRes.json();
        const commentsData = await commentsRes.json();

        const selectedWeek = weeks.find(w => w.id === currentWeekId);

        currentComments = commentsData[currentWeekId] || [];

        if (!selectedWeek) {
            weekTitle.textContent = "Week not found.";
            return;
        }

        renderWeekDetails(selectedWeek);
        renderComments();

        commentForm.addEventListener("submit", handleAddComment);

    } catch (error) {
        console.error("Error loading data:", error);
        weekTitle.textContent = "Error loading week data.";
    }
}


// --- Start the page ---
initializePage();


