/*
  Requirement: Populate the single topic page and manage replies.

  Instructions:
  1. Link this file to `topic.html` using:
     <script src="topic.js" defer></script>

  2. In `topic.html`, add the following IDs:
     - <h1 id="topic-subject">
     - <p id="op-message">
     - <footer id="op-footer">
     - <div id="reply-list-container">
     - <form id="reply-form">
*/

// --- Global Data Store ---
let currentTopicId = null;
let currentReplies = []; // Will hold replies for *this* topic

// --- Element Selections ---
const topicSubject = document.getElementById("topic-subject");
const opMessage = document.getElementById("op-message");
const opFooter = document.getElementById("op-footer");
const replyListContainer = document.getElementById("reply-list-container");
const replyForm = document.getElementById("reply-form");
const newReplyText = document.getElementById("new-reply");

// --- Functions ---

// Get Topic ID from URL
function getTopicIdFromURL() {
  const params = new URLSearchParams(window.location.search);
  return params.get("id");
}

// Render Original Post
function renderOriginalPost(topic) {
  topicSubject.textContent = topic.subject;
  opMessage.textContent = topic.message;
  opFooter.textContent = `Posted by: ${topic.author} on ${topic.date}`;
}

// Create <article> for each reply
function createReplyArticle(reply) {
  const article = document.createElement("article");
  article.classList.add("reply");

  const p = document.createElement("p");
  p.textContent = reply.text;

  const footer = document.createElement("footer");
  footer.textContent = `Posted by: ${reply.author} on ${reply.date}`;

  const btn = document.createElement("button");
  btn.textContent = "Delete";
  btn.classList.add("delete-reply-btn");
  btn.setAttribute("data-id", reply.id);

  article.appendChild(p);
  article.appendChild(footer);
  article.appendChild(btn);

  return article;
}

// Render all replies
function renderReplies() {
  replyListContainer.innerHTML = "";

  currentReplies.forEach(reply => {
    const element = createReplyArticle(reply);
    replyListContainer.appendChild(element);
  });
}

// Add a new reply
function handleAddReply(event) {
  event.preventDefault();

  const text = newReplyText.value.trim();
  if (!text) return;

  const newReply = {
    id: `reply_${Date.now()}`,
    author: "Student",
    date: new Date().toISOString().split("T")[0],
    text: text
  };

  currentReplies.push(newReply);
  renderReplies();
  newReplyText.value = "";
}

// Delete reply (event delegation)
function handleReplyListClick(event) {
  if (event.target.classList.contains("delete-reply-btn")) {
    const id = event.target.getAttribute("data-id");

    currentReplies = currentReplies.filter(r => r.id !== id);

    renderReplies();
  }
}

// Initialize Page
async function initializePage() {
  currentTopicId = getTopicIdFromURL();

  if (!currentTopicId) {
    topicSubject.textContent = "Topic not found.";
    return;
  }

  try {
    const [topicsRes, repliesRes] = await Promise.all([
      fetch("topics.json"),
      fetch("replies.json")
    ]);

    const topics = await topicsRes.json();
    const replies = await repliesRes.json();

    const topic = topics.find(t => t.id === currentTopicId);
    currentReplies = replies[currentTopicId] || [];

    if (!topic) {
      topicSubject.textContent = "Topic not found.";
      return;
    }

    renderOriginalPost(topic);
    renderReplies();

    replyForm.addEventListener("submit", handleAddReply);
    replyListContainer.addEventListener("click", handleReplyListClick);

  } catch (error) {
    topicSubject.textContent = "Error loading data.";
    console.error(error);
  }
}

// --- Initial Page Load ---
initializePage();
