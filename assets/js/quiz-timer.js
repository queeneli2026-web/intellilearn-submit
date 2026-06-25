/**
 * Quiz countdown timer.
 *
 * Client-side countdown timer display. Uses server-provided ends_at
 * as authoritative deadline. Display only — server rejects over-time
 * submissions independently (T-02F-05 accepts this limitation).
 *
 * Timer bar color transitions (D-04):
 *   - green (>50% remaining)
 *   - yellow (25-50% remaining)
 *   - red (<25% remaining, pulsing)
 *
 * On expiry: calls onExpire callback which triggers QuizState.submitQuiz()
 * for auto-submit per D-11.
 */
class QuizTimer {
    /**
     * @param {number} endsAtTimestamp - Unix timestamp (ms) when quiz ends
     * @param {Function} onExpire - Callback invoked when timer reaches 0
     */
    constructor(endsAtTimestamp, onExpire) {
        this.endsAt = endsAtTimestamp;
        this.onExpire = onExpire || (() => {});
        this.intervalId = null;
        this.startedAt = Date.now();
        this.totalDuration = this.endsAt - this.startedAt;
        this._stopped = false;
    }

    /**
     * Start the countdown.
     * Updates every 1s via setInterval.
     */
    start() {
        if (this.intervalId) return;

        // Immediate first update
        this.updateDisplay();

        this.intervalId = setInterval(() => {
            this.updateDisplay();
        }, 1000);
    }

    /**
     * Update the visual display.
     * Shows mm:ss format in #timer-display.
     * Updates #timer-bar width percentage and CSS class.
     * When <= 0, calls onExpire callback.
     */
    updateDisplay() {
        if (this._stopped) return;

        const remaining = this.getRemainingSeconds();
        const timerDisplay = document.getElementById('timer-display');
        const timerBar = document.getElementById('timer-bar');

        if (remaining <= 0) {
            // Timer expired
            if (timerDisplay) {
                timerDisplay.textContent = '00:00';
                timerDisplay.style.color = '#dc3545';
            }
            if (timerBar) {
                timerBar.style.width = '0%';
                timerBar.className = 'timer-bar timer-red';
            }
            this.stop();
            this.onExpire();
            return;
        }

        // Format mm:ss
        const minutes = Math.floor(remaining / 60);
        const seconds = Math.floor(remaining % 60);
        const formatted = String(minutes).padStart(2, '0') + ':' + String(seconds).padStart(2, '0');

        if (timerDisplay) {
            timerDisplay.textContent = formatted;
        }

        // Calculate percentage remaining
        const pctRemaining = this.totalDuration > 0
            ? Math.max(0, (remaining * 1000 / this.totalDuration) * 100)
            : 0;

        // Update timer bar
        if (timerBar) {
            timerBar.style.width = pctRemaining + '%';

            // Update color class based on thresholds (D-04)
            if (pctRemaining > 50) {
                timerBar.className = 'timer-bar timer-green';
            } else if (pctRemaining > 25) {
                timerBar.className = 'timer-bar timer-yellow';
            } else {
                timerBar.className = 'timer-bar timer-red';
            }
        }

        // Update timer display color
        if (timerDisplay) {
            if (pctRemaining > 50) {
                timerDisplay.style.color = '#28a745';
            } else if (pctRemaining > 25) {
                timerDisplay.style.color = '#856404';
            } else {
                timerDisplay.style.color = '#dc3545';
            }
        }
    }

    /**
     * Get current remaining seconds.
     * @returns {number}
     */
    getRemainingSeconds() {
        const diff = this.endsAt - Date.now();
        return Math.max(0, diff / 1000);
    }

    /**
     * Stop the timer (on quiz finish).
     */
    stop() {
        this._stopped = true;
        if (this.intervalId) {
            clearInterval(this.intervalId);
            this.intervalId = null;
        }
    }
}

// Make QuizTimer available globally
window.QuizTimer = QuizTimer;
