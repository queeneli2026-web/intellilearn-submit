/**
 * Quiz browse page JavaScript.
 *
 * Handles:
 * - Binding "Start Quiz" buttons to quiz start API
 * - Resume prompt button clicks
 * - Checking for incomplete attempts on page load (via /quiz/check-resume)
 * - Topic filter change handler
 */

document.addEventListener('DOMContentLoaded', function () {
    // ─── Start Quiz buttons ───
    // Bind click handlers on start-quiz-btn elements
    // These are inside forms, so we intercept the form submit
    document.querySelectorAll('.start-quiz-form').forEach(function (form) {
        form.addEventListener('submit', function (e) {
            const btn = form.querySelector('.start-quiz-btn');
            if (btn) {
                btn.disabled = true;
                btn.textContent = 'Starting...';
            }
            // Let the form submit naturally (POST with CSRF)
        });
    });

    // ─── Resume button enhancement ───
    // The resume button already links to /quiz/attempt/{id}/next directly
    // We just track the click for analytics purposes
    document.querySelectorAll('[data-resume-quiz]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            // The link already navigates; no extra action needed
        });
    });

    // ─── Check for incomplete attempts on page load ───
    // This is a supplementary check; the server already renders
    // the resume banner via QuizBrowseController::indexAction.
    // We fetch /quiz/check-resume for JS-aware enhancements.
    checkIncompleteAttempt();

    // ─── Topic filter change handler ───
    const topicFilter = document.getElementById('topicFilter');
    if (topicFilter) {
        // The onchange attribute already handles this; we add keyboard support
        topicFilter.addEventListener('keydown', function (e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                if (this.value) {
                    window.location.href = '/quiz/browse/' + this.value;
                } else {
                    window.location.href = '/quiz';
                }
            }
        });
    }
});

/**
 * Check for incomplete quiz attempts and show resume banner if needed.
 */
function checkIncompleteAttempt() {
    fetch('/quiz/check-resume')
        .then(function (r) { return r.json(); })
        .then(function (data) {
            if (data.has_incomplete && data.attempt_id) {
                // If server-side resume banner already rendered, we can enhance it
                const resumeBanner = document.querySelector('.resume-banner');
                if (!resumeBanner) {
                    // No server-side banner — create one via JS
                    showResumeBanner(data);
                }
            }
        })
        .catch(function (err) {
            console.error('Failed to check resume status:', err);
        });
}

/**
 * Show a resume banner at the top of the page.
 *
 * @param {Object} data - Resume data from /quiz/check-resume
 */
function showResumeBanner(data) {
    const container = document.querySelector('main.container') || document.querySelector('main');
    if (!container) return;

    const banner = document.createElement('div');
    banner.className = 'alert alert-info alert-dismissible fade show resume-banner';
    banner.setAttribute('role', 'alert');
    banner.innerHTML = `
        <div class="d-flex flex-column flex-sm-row align-items-start align-items-sm-center gap-2">
            <div class="flex-grow-1">
                <strong>Resume Quiz?</strong>
                You have an incomplete attempt for
                <strong>${escapeHtml(data.quiz_title || '')}</strong>.
                Question ${(data.current_index || 0) + 1} of ${data.total_questions || 0}.
            </div>
            <div class="d-flex gap-2 flex-shrink-0">
                <a href="/quiz/attempt/${data.attempt_id}/next"
                   class="btn btn-primary btn-sm">
                    Resume Quiz
                </a>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Not now"></button>
            </div>
        </div>
    `;

    container.insertBefore(banner, container.firstChild);
}

/**
 * Escape HTML entities for safe insertion.
 *
 * @param {string} str
 * @returns {string}
 */
function escapeHtml(str) {
    var div = document.createElement('div');
    div.textContent = str || '';
    return div.innerHTML;
}
