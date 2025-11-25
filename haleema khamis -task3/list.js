/*
  Requirement: Populate the "Weekly Course Breakdown" list page.

  Instructions:
  1. Link this file to `list.html` using:
     <script src="list.js" defer></script>

  2. In `list.html`, add an `id="week-list-section"` to the
     <section> element that will contain the weekly articles.

  3. Implement the TODOs below.
*/

// --- Element Selections ---
const listSection = document.querySelector('#week-list-section'); // Select section


// --- Functions ---

/**
 * Creates one <article> for a week item.
 * week = { id, title, startDate, description }
 */
function createWeekArticle(week) {
    // Create article
    const article = document.createElement("article");

    // Create heading
    const h2 = document.createElement("h2");
    h2.textContent = week.title;

    // Start date
    const dateP = document.createElement("p");
    dateP.textContent = `Starts on: ${week.startDate}`;

    // Description
    const descP = document.createElement("p");
    descP.textContent = week.description;

    // Link
    const link = document.createElement("a");
    link.textContent = "View Details & Discussion";
    link.href = `details.html?id=${week.id}`; // required format

    // Append elements to article
    article.appendChild(h2);
    article.appendChild(dateP);
    article.appendChild(descP);
    article.appendChild(link);

    return article;
}


/**
 * Loads all weeks from weeks.json and displays them.
 */
async function loadWeeks() {
    try {
        // 1. Fetch JSON file
        const response = await fetch('weeks.json');

        // 2. Parse JSON
        const weeks = await response.json();

        // 3. Clear section first
        listSection.innerHTML = "";

        // 4. Loop and append
        weeks.forEach(week => {
            const article = createWeekArticle(week);
            listSection.appendChild(article);
        });

    } catch (error) {
        console.error("Error loading weeks:", error);
        listSection.innerHTML = "<p>Unable to load weeks. Please check the file path.</p>";
    }
}


// --- Initial Page Load ---
loadWeeks();
