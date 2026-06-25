<!--
======================================================================
feedback_card.php — Inline Feedback Card Template (Documentation)

This file documents the HTML structure for the 4 feedback states.
Actual rendering is done by QuizState.displayFeedback() in JavaScript,
which generates equivalent markup dynamically after answer submission.

Four states per D-08, D-09, and 02-UI-SPEC.md:

  1. Correct   — green border + checkmark + explanation
  2. Incorrect — red border + X + correct answer + explanation
  3. Partial   — yellow border + partial credit info (MCQ multi)
  4. Short Answer Review — yellow card + "Pending Review" badge

Usage context:
  <div id="feedback-area"> <!-- populated by JS --> </div>

Threat mitigations (T-02R-04, T-02R-05):
  - All user-facing text (explanation, feedback messages) escaped via
    QuizState._escapeHtml() before insertion
  - Feedback content comes from server JSON response, not client-generated
======================================================================
-->

<!-- ─── State 1: Correct ─── -->
<div class="feedback-card feedback-correct">
    <div class="feedback-icon">&#10004;</div>
    <div class="feedback-body">
        <strong>Correct!</strong>
        <p>Escaped explanation text from question.explanation renders here.</p>
        <small>Points: 1.0/1.0</small>
    </div>
</div>

<!-- ─── State 2: Incorrect ─── -->
<div class="feedback-card feedback-incorrect">
    <div class="feedback-icon">&#10008;</div>
    <div class="feedback-body">
        <strong>Incorrect</strong>
        <p>Escaped explanation text renders here.</p>
        <small>The correct answer was: [text]</small>
        <small>Points: 0.0/1.0</small>
    </div>
</div>

<!-- ─── State 3: Partial (MCQ Multi partial credit) ─── -->
<div class="feedback-card feedback-pending">
    <div class="feedback-icon">&#9888;</div>
    <div class="feedback-body">
        <strong>Partially Correct</strong>
        <p>Escaped explanation text renders here.</p>
        <small>Points: 0.5/1.0</small>
    </div>
</div>

<!-- ─── State 4: Short Answer Pending Review (SANS-03) ─── -->
<div class="feedback-card feedback-pending">
    <div class="feedback-icon">&#9888;</div>
    <div class="feedback-body">
        <strong>Pending Review</strong>
        <p>Escaped explanation text renders here.</p>
        <span class="badge badge-pending">Pending Review — awaiting lecturer grading</span>
        <small>Points: 0.0/1.0</small>
    </div>
</div>
