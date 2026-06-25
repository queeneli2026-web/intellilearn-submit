/**
 * Quiz state manager.
 *
 * Manages the entire quiz-taking lifecycle:
 *   LOADING → QUESTION → FEEDBACK → QUESTION → ... → RESULTS
 *
 * Handles navigation, answer submission, feedback display, and quiz lifecycle.
 * All API calls use fetch() with JSON payloads — no jQuery (per plan).
 *
 * Threat mitigations:
 *   - CSRF token sent with all POST requests (T-02F-06)
 *   - Error handling: show toast for network errors, disable buttons during submission
 *   - Confirm dialog before quiz submission (Bootstrap modal)
 */
class QuizState {
    /**
     * @param {Object} config - Quiz configuration from QUIZ_CONFIG
     * @param {number} config.attemptId
     * @param {string} config.quizTitle
     * @param {number} config.totalQuestions
     * @param {number|null} config.timeLimit
     * @param {string} config.startedAt
     * @param {number} config.currentIndex
     * @param {string} config.csrfToken
     */
    constructor(config) {
        this.attemptId = config.attemptId;
        this.quizTitle = config.quizTitle || '';
        this.totalQuestions = config.totalQuestions || 0;
        this.currentIndex = config.currentIndex || 0;
        this.csrfToken = config.csrfToken || this._getCsrfFromMeta();
        this._submitting = false;
        this._answeredQuestions = new Set(); // track answered question IDs
    }

    /**
     * Start a new quiz attempt.
     * POST to /quiz/attempt/start/{quizId}.
     * On success: redirect to /quiz/take/{attemptId}.
     *
     * @param {number} quizId
     */
    startQuiz(quizId) {
        const form = document.querySelector(`.start-quiz-form[action*="/quiz/attempt/start/${quizId}"]`);
        // If it's a form, let it submit naturally — no JS override needed for browse page
        // For JS-initiated starts:
        if (!form) {
            this._post(`/quiz/attempt/start/${quizId}`, {})
                .then(data => {
                    if (data.attempt_id) {
                        window.location.href = `/quiz/attempt/${data.attempt_id}/next`;
                    }
                })
                .catch(err => {
                    console.error('Failed to start quiz:', err);
                    this._showToast('Failed to start quiz. Please try again.', 'error');
                });
        }
    }

    /**
     * Submit current answer via POST /quiz/attempt/{attemptId}/answer.
     * Collects answer data based on question type.
     * On success: displays feedback card, updates progress, shows "Saved" toast (D-05).
     */
    submitAnswer() {
        if (this._submitting) return;
        this._submitting = true;

        const form = document.getElementById('answer-form');
        if (!form) {
            this._submitting = false;
            return;
        }

        const questionId = parseInt(form.querySelector('[name="question_id"]')?.value || '0', 10);
        if (!questionId) {
            this._submitting = false;
            return;
        }

        // Collect answer data based on question type
        const selectedOptionInput = form.querySelector('[name="selected_option_id"]');
        const answerTextInput = form.querySelector('[name="answer_text"]');
        const checkboxes = form.querySelectorAll('[name="answer_options[]"]:checked');

        let selectedOptionId = null;
        let answerText = null;

        if (selectedOptionInput) {
            const val = selectedOptionInput.value;
            if (val !== '') {
                selectedOptionId = parseInt(val, 10);
            }
        }

        if (answerTextInput) {
            answerText = answerTextInput.value.trim() || null;
        }

        // For multi-select (mcq_multi), pack selected option IDs
        if (checkboxes.length > 0) {
            // Just use first checked for single selection, but we'll send all via answer_text as JSON
            // Actually for mcq_multi, send via selected_option_id as comma-separated or JSON
            const selectedIds = Array.from(checkboxes).map(cb => parseInt(cb.value, 10));
            selectedOptionId = selectedIds[0] || null;
            answerText = selectedIds.length > 0 ? JSON.stringify(selectedIds) : null;
        }

        const payload = {
            question_id: questionId,
            csrf_token: this.csrfToken,
            time_taken_sec: 0
        };

        if (selectedOptionId !== null) {
            payload.selected_option_id = selectedOptionId;
        }

        if (answerText !== null) {
            payload.answer_text = answerText;
        }

        this._post(`/quiz/attempt/${this.attemptId}/answer`, payload)
            .then(data => {
                this._submitting = false;
                this._answeredQuestions.add(questionId);
                this.displayFeedback(data);
                this.showSavedToast();
                this._updateProgress(data.progress);
            })
            .catch(err => {
                this._submitting = false;
                console.error('Failed to submit answer:', err);
                this._showToast('Failed to save answer. Please try again.', 'error');
            });
    }

    /**
     * Navigate to next question: GET /quiz/attempt/{attemptId}/next.
     * Updates the question area with new question data.
     * Shows Previous/Next buttons accordingly.
     */
    nextQuestion() {
        if (this._submitting) return;

        // Submit current answer first
        this.submitAnswer();

        this._loadNextQuestion();
    }

    /**
     * Load the next question from the server.
     * @private
     */
    _loadNextQuestion() {
        const questionId = document.querySelector('[name="question_id"]')?.value;
        const currentQId = questionId ? parseInt(questionId, 10) : null;

        fetch(`/quiz/attempt/${this.attemptId}/next`)
            .then(r => r.json())
            .then(data => {
                if (data.error) {
                    this._showToast(data.error, 'error');
                    return;
                }

                // Clear feedback area
                const feedbackArea = document.getElementById('feedback-area');
                if (feedbackArea) feedbackArea.innerHTML = '';

                // Update question content
                this._renderQuestion(data);

                // Ensure previous answer was submitted before advancing
                if (currentQId && !this._answeredQuestions.has(currentQId)) {
                    // Wait a moment for the answer submission to complete
                    setTimeout(() => {
                        this._loadNextQuestion();
                    }, 500);
                    return;
                }
            })
            .catch(err => {
                console.error('Failed to load next question:', err);
                this._showToast('Failed to load next question.', 'error');
            });
    }

    /**
     * Navigate to previous question (client-side, loading from server).
     */
    previousQuestion() {
        if (this.currentIndex <= 0) return;

        this.currentIndex--;

        fetch(`/quiz/attempt/${this.attemptId}/next`)
            .then(r => r.json())
            .then(data => {
                if (data.error) {
                    this._showToast(data.error, 'error');
                    return;
                }

                const feedbackArea = document.getElementById('feedback-area');
                if (feedbackArea) feedbackArea.innerHTML = '';

                this._renderQuestion(data);
            })
            .catch(err => {
                console.error('Failed to load previous question:', err);
                this._showToast('Failed to load question.', 'error');
            });
    }

    /**
     * Submit the entire quiz via POST /quiz/attempt/{attemptId}/finish.
     * After success: redirect to /quiz/attempt/{attemptId}/results.
     */
    submitQuiz() {
        if (this._submitting) return;
        this._submitting = true;

        // Close the modal
        const modalEl = document.getElementById('submitModal');
        if (modalEl) {
            const modal = bootstrap.Modal.getInstance(modalEl);
            if (modal) modal.hide();
        }

        const confirmBtn = document.getElementById('confirm-submit-btn');
        if (confirmBtn) confirmBtn.disabled = true;

        // Submit current answer first if there's one
        const lastAnswerPromise = new Promise(resolve => {
            this.submitAnswer();
            // Give the answer submission a moment
            setTimeout(resolve, 500);
        });

        lastAnswerPromise.then(() => {
            this._post(`/quiz/attempt/${this.attemptId}/finish`, {})
                .then(data => {
                    if (data.status === 'completed' || data.status === 'timed_out') {
                        window.location.href = `/quiz/attempt/${this.attemptId}/results`;
                    } else {
                        this._showToast(data.message || 'Failed to submit quiz.', 'error');
                        this._submitting = false;
                        if (confirmBtn) confirmBtn.disabled = false;
                    }
                })
                .catch(err => {
                    console.error('Failed to submit quiz:', err);
                    this._showToast('Failed to submit quiz. Please try again.', 'error');
                    this._submitting = false;
                    if (confirmBtn) confirmBtn.disabled = false;
                });
        });
    }

    /**
     * Display feedback after answer submission (D-08).
     * Renders a colored card below the question with explanation.
     * Four states per D-09 and 02-UI-SPEC.md:
     *   - Correct: green border + checkmark
     *   - Incorrect: red border + X
     *   - Partial: yellow border + warning icon
     *   - Pending Review: yellow border + "Pending Review" badge (SANS-03)
     *
     * Threat mitigation (T-02R-04, T-02R-05):
     *   - All user-facing text escaped via _escapeHtml()
     *   - Content from server JSON, never client-generated
     *
     * @param {Object} data - Response from submitAnswer
     */
    displayFeedback(data) {
        const feedbackArea = document.getElementById('feedback-area');
        if (!feedbackArea) return;

        const isCorrect = data.correct;
        const feedback = data.feedback || {};
        const needsReview = data.needs_review || false;
        const feedbackType = feedback.type || '';

        let cssClass = 'feedback-correct';
        let icon = '&#10004;';
        let title = 'Correct!';
        let reviewBadgeHtml = '';

        // Check feedback type for all 4 states
        if (needsReview || feedbackType === 'pending_review') {
            cssClass = 'feedback-pending';
            icon = '&#9888;';
            title = 'Pending Review';
            reviewBadgeHtml = '<span class="badge badge-pending mt-1">Pending Review</span>'
                + '<small class="d-block mt-1 text-muted">Pending Review — awaiting lecturer grading</small>';
        } else if (feedbackType === 'partial') {
            cssClass = 'feedback-pending';
            icon = '&#9888;';
            title = 'Partially Correct';
        } else if (!isCorrect) {
            cssClass = 'feedback-incorrect';
            icon = '&#10008;';
            title = 'Incorrect';
        }

        const explanation = feedback.explanation || '';
        const pointsEarned = typeof feedback.points_earned === 'number' ? feedback.points_earned : 0;
        const maxPoints = typeof feedback.max_points === 'number' ? feedback.max_points : 1;

        feedbackArea.innerHTML = `
            <div class="feedback-card ${cssClass}">
                <div class="feedback-icon">${icon}</div>
                <div class="feedback-body">
                    <strong>${title}</strong>
                    ${explanation ? `<p>${this._escapeHtml(explanation)}</p>` : ''}
                    ${reviewBadgeHtml}
                    <small>Points: ${pointsEarned}/${maxPoints}</small>
                </div>
            </div>
        `;

        // Enable navigation buttons after feedback is displayed
        const nextBtn = document.getElementById('next-btn');
        const submitBtn = document.getElementById('submit-quiz-btn');
        if (nextBtn) nextBtn.disabled = false;
        if (submitBtn) submitBtn.disabled = false;
    }

    /**
     * Show auto-save indicator (D-05).
     * Brief "Saved" toast that fades after 2 seconds.
     * Uses Bootstrap toast if available in DOM; falls back
     * to a floating auto-created div.
     */
    showSavedToast() {
        const toastEl = document.getElementById('saved-toast');

        if (toastEl) {
            // Bootstrap toast element exists in DOM (take.php)
            try {
                const toast = bootstrap.Toast.getInstance(toastEl) || new bootstrap.Toast(toastEl);
                toast.show();
            } catch (e) {
                toastEl.classList.add('show');
                setTimeout(() => {
                    toastEl.classList.remove('show');
                }, 2000);
            }
            return;
        }

        // Fallback: create a floating saved-toast element
        const savedToast = document.createElement('div');
        savedToast.className = 'saved-toast';
        savedToast.textContent = 'Saved';
        savedToast.style.cssText = 'position:fixed;bottom:24px;right:24px;z-index:1070;'
            + 'padding:10px 22px;border-radius:8px;background:#d4edda;color:#155724;'
            + 'border:1px solid #c3e6cb;font-weight:500;opacity:0;'
            + 'transition:opacity 0.3s ease;';
        document.body.appendChild(savedToast);

        // Fade in
        requestAnimationFrame(() => {
            savedToast.style.opacity = '1';
        });

        // Auto-remove after 2 seconds
        setTimeout(() => {
            savedToast.style.opacity = '0';
            setTimeout(() => {
                savedToast.remove();
            }, 300);
        }, 2000);
    }

    /**
     * Render a question from server data into the page.
     *
     * @param {Object} data - Question data from /next endpoint
     * @private
     */
    _renderQuestion(data) {
        const question = data.question || {};
        const index = data.index || 0;
        const total = data.total || this.totalQuestions;

        this.currentIndex = index;
        this.totalQuestions = total;

        // Update progress bar
        const displayIndex = index + 1;
        const progressPct = total > 0 ? Math.round((index / total) * 100) : 0;

        const progressBar = document.getElementById('progress-bar');
        const questionCounter = document.getElementById('question-counter');
        if (progressBar) {
            progressBar.style.width = progressPct + '%';
            progressBar.setAttribute('aria-valuenow', String(progressPct));
        }
        if (questionCounter) {
            questionCounter.textContent = `Question ${displayIndex} of ${total}`;
        }

        // Update question display in the form
        // Get the question text display
        const questionHeader = document.querySelector('.take-question h4');
        if (questionHeader) {
            questionHeader.innerHTML = this._escapeHtml(question.question_text || '').replace(/\n/g, '<br>');
        }

        // For now, render the options area (simplified — full re-render on AJAX)
        // In practice this would replace the form content via innerHTML
        // but for the initial server-rendered version, subsequent questions
        // would be handled by page navigation
        this._showToast('Answer saved!', 'success');
    }

    /**
     * Update the progress display.
     *
     * @param {Object} progress - { answered, total, percentage }
     * @private
     */
    _updateProgress(progress) {
        if (!progress) return;

        const progressBar = document.getElementById('progress-bar');
        const questionCounter = document.getElementById('question-counter');

        if (progressBar) {
            progressBar.style.width = (progress.percentage || 0) + '%';
            progressBar.setAttribute('aria-valuenow', String(progress.percentage || 0));
        }

        if (questionCounter && progress.answered !== undefined && progress.total !== undefined) {
            questionCounter.textContent = `Question ${progress.answered} of ${progress.total}`;
        }
    }

    /**
     * Show a toast notification.
     *
     * @param {string} message
     * @param {string} type - 'success' | 'error'
     * @private
     */
    _showToast(message, type) {
        const toastContainer = document.querySelector('.toast-container');
        if (!toastContainer) return;

        const toastEl = document.createElement('div');
        toastEl.className = `toast align-items-center text-bg-${type === 'error' ? 'danger' : 'success'} border-0`;
        toastEl.setAttribute('role', 'alert');
        toastEl.setAttribute('aria-live', 'assertive');
        toastEl.setAttribute('aria-atomic', 'true');
        toastEl.innerHTML = `
            <div class="d-flex">
                <div class="toast-body">
                    ${this._escapeHtml(message)}
                </div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
            </div>
        `;

        toastContainer.appendChild(toastEl);

        try {
            const toast = new bootstrap.Toast(toastEl, { delay: 3000 });
            toast.show();
            // Remove element after hidden
            toastEl.addEventListener('hidden.bs.toast', () => toastEl.remove());
        } catch (e) {
            setTimeout(() => toastEl.remove(), 3000);
        }
    }

    /**
     * POST JSON data to the server with CSRF token.
     *
     * @param {string} url
     * @param {Object} data
     * @returns {Promise<Object>}
     * @private
     */
    _post(url, data) {
        // Ensure CSRF token is in the data
        const payload = { ...data, csrf_token: this.csrfToken };

        return fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            },
            body: JSON.stringify(payload)
        }).then(r => {
            if (!r.ok) {
                return r.json().then(errData => {
                    throw new Error(errData.error || 'Request failed');
                }).catch(e => {
                    if (e.message !== 'Request failed') throw e;
                    throw new Error('Network error');
                });
            }
            return r.json();
        });
    }

    /**
     * Get CSRF token from meta tag.
     * @returns {string}
     * @private
     */
    _getCsrfFromMeta() {
        const meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? meta.getAttribute('content') : '';
    }

    /**
     * Escape HTML to prevent XSS.
     * @param {string} str
     * @returns {string}
     * @private
     */
    _escapeHtml(str) {
        const div = document.createElement('div');
        div.textContent = str || '';
        return div.innerHTML;
    }
}

// Make QuizState available globally
window.QuizState = QuizState;
