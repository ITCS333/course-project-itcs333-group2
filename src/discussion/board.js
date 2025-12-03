/*
  Requirement: Make the "Discussion Board" page interactive.
*/

// --- Global Data Store ---
let topics = [];

// --- Element Selections ---
const newTopicForm = document.querySelector("#new-topic-form");   // Select form
const topicListContainer = document.querySelector("#topic-list-container"); // Select container

// --- Functions ---

/**
 * Create a topic <article> element.
 */
function createTopicArticle(topic) {
  const { id, subject, author, date } = topic;

  const article = document.createElement("article");

  // Title + link
  const h3 = document.createElement("h3");
  const link = document.createElement("a");
  link.href = `topic.html?id=${id}`;
  link.textContent = subject;
  h3.appendChild(link);

  // Footer
  const footer = document.createElement("footer");
  footer.textContent = `Posted by: ${author} on ${date}`;

  // Actions container
  const actionsDiv = document.createElement("div");

  const editBtn = document.createElement("button");
  editBtn.textContent = "Edit";

  const deleteBtn = document.createElement("button");
  deleteBtn.textContent = "Delete";
  deleteBtn.classList.add("delete-btn");
  deleteBtn.dataset.id = id;

  actionsDiv.appendChild(editBtn);
  actionsDiv.appendChild(deleteBtn);

  // Build article
  article.appendChild(h3);
  article.appendChild(footer);
  article.appendChild(actionsDiv);

  return article;
}

/**
 * Render all topics in the container.
 */
function renderTopics() {
  topicListContainer.innerHTML = ""; // Clear old content

  topics.forEach((topic) => {
    const article = createTopicArticle(topic);
    topicListContainer.appendChild(article);
  });
}

/**
 * Handle "Create Topic" form submission.
 */
function handleCreateTopic(event) {
  event.preventDefault();

  const subjectInput = document.querySelector("#topic-subject");
  const messageInput = document.querySelector("#topic-message");

  const newTopic = {
    id: `topic_${Date.now()}`,
    subject: subjectInput.value,
    message: messageInput.value,
    author: "Student",
    date: new Date().toISOString().split("T")[0]
  };

  topics.push(newTopic);

  renderTopics();
  newTopicForm.reset();
}

/**
 * Handle Delete button clicks (event delegation).
 */
function handleTopicListClick(event) {
  if (event.target.classList.contains("delete-btn")) {
    const id = event.target.dataset.id;

    topics = topics.filter((t) => t.id !== id);

    renderTopics();
  }
}

/**
 * Load initial data and initialize event listeners.
 */
async function loadAndInitialize() {
  try {
    const response = await fetch("topics.json");
    topics = await response.json();
  } catch (error) {
    console.error("Failed to load topics.json; using empty list.");
    topics = [];
  }

  renderTopics();

  newTopicForm.addEventListener("submit", handleCreateTopic);
  topicListContainer.addEventListener("click", handleTopicListClick);
}

// --- Initial Page Load ---
loadAndInitialize();
