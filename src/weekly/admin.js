/*
  Requirement: Make the "Manage Weekly Breakdown" page interactive.
*/

// --- Global Data Store ---
let weeks = [];

// --- Element Selections ---
const weekForm = document.querySelector("#week-form");
const weeksTableBody = document.querySelector("#weeks-tbody");

// --- Functions ---

// Create the table row for each week
function createWeekRow(week) {
  const tr = document.createElement("tr");

  // Title cell
  const titleTd = document.createElement("td");
  titleTd.textContent = week.title;

  // Description cell
  const descTd = document.createElement("td");
  descTd.textContent = week.description;

  // Actions cell
  const actionsTd = document.createElement("td");

  const editBtn = document.createElement("button");
  editBtn.textContent = "Edit";
  editBtn.classList.add("edit-btn");
  editBtn.dataset.id = week.id;

  const deleteBtn = document.createElement("button");
  deleteBtn.textContent = "Delete";
  deleteBtn.classList.add("delete-btn");
  deleteBtn.dataset.id = week.id;

  actionsTd.appendChild(editBtn);
  actionsTd.appendChild(deleteBtn);

  // Append all cells to row
  tr.appendChild(titleTd);
  tr.appendChild(descTd);
  tr.appendChild(actionsTd);

  return tr;
}

// Render the table
function renderTable() {
  weeksTableBody.innerHTML = "";

  weeks.forEach((week) => {
    const row = createWeekRow(week);
    weeksTableBody.appendChild(row);
  });
}

// Handle Add Week
function handleAddWeek(event) {
  event.preventDefault();

  const title = document.querySelector("#week-title").value.trim();
  const startDate = document.querySelector("#week-start-date").value;
  const description = document.querySelector("#week-description").value.trim();
  const linksRaw = document.querySelector("#week-links").value.trim();

  const links = linksRaw.length > 0 ? linksRaw.split("\n") : [];

  const newWeek = {
    id: `week_${Date.now()}`,
    title: title,
    startDate: startDate,
    description: description,
    links: links
  };

  weeks.push(newWeek);
  renderTable();
  weekForm.reset();
}

// Handle Delete Button
function handleTableClick(event) {
  if (event.target.classList.contains("delete-btn")) {
    const id = event.target.dataset.id;

    weeks = weeks.filter((week) => week.id !== id);

    renderTable();
  }
}

// Load JSON + Initialize Events
async function loadAndInitialize() {
  try {
    const response = await fetch("weeks.json");
    weeks = await response.json();
  } catch (error) {
    console.log("Could not load weeks.json — starting with empty list.");
  }

  renderTable();

  weekForm.addEventListener("submit", handleAddWeek);
  weeksTableBody.addEventListener("click", handleTableClick);
}

// Start App
loadAndInitialize();
