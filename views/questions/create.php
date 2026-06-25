<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h3">Create Question</h1>
    <a href="/admin/questions" class="btn btn-outline-secondary">Cancel</a>
</div>

<?php if (!empty($errors)): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <ul class="mb-0">
            <?php foreach ($errors as $error): ?>
                <li><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></li>
            <?php endforeach; ?>
        </ul>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<div class="card">
    <div class="card-body">
        <form action="/admin/questions/store" method="POST" id="questionForm">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
            <!-- Common fields -->
            <div class="mb-3">
                <label for="question_text" class="form-label">Question Text <span class="text-danger">*</span></label>
                <textarea id="question_text"
                          name="question_text"
                          class="form-control"
                          rows="4"
                          required><?= htmlspecialchars($oldInput['question_text'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
            </div>

            <div class="row mb-3">
                <div class="col-md-6">
                    <label for="topic_id" class="form-label">Topic <span class="text-danger">*</span></label>
                    <select id="topic_id" name="topic_id" class="form-select" required>
                        <option value="">Select a topic...</option>
                        <?php foreach ($topics as $topic): ?>
                            <option value="<?= (int) $topic['id'] ?>"
                                <?= (isset($oldInput['topic_id']) && (int) $oldInput['topic_id'] === (int) $topic['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($topic['name'], ENT_QUOTES, 'UTF-8') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="points" class="form-label">Points</label>
                    <input type="number"
                           id="points"
                           name="points"
                           class="form-control"
                           min="0.1"
                           step="0.5"
                           value="<?= htmlspecialchars($oldInput['points'] ?? '1.0', ENT_QUOTES, 'UTF-8') ?>">
                </div>
            </div>

            <div class="mb-3">
                <label for="explanation" class="form-label">Explanation (corrective feedback)</label>
                <textarea id="explanation"
                          name="explanation"
                          class="form-control"
                          rows="2"><?= htmlspecialchars($oldInput['explanation'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
                <div class="form-text">Shown after the student answers — explain why the correct answer is right.</div>
            </div>

            <!-- Question Type Selector -->
            <div class="mb-4">
                <label class="form-label">Question Type <span class="text-danger">*</span></label>
                <div class="d-flex flex-wrap gap-3" id="typeSelector">
                    <div class="form-check">
                        <input class="form-check-input question-type-radio" type="radio" name="question_type"
                               id="type_mcq_single" value="mcq_single"
                               <?= (isset($oldInput['question_type']) && $oldInput['question_type'] === 'mcq_single') ? 'checked' : '' ?>>
                        <label class="form-check-label" for="type_mcq_single">MCQ Single</label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input question-type-radio" type="radio" name="question_type"
                               id="type_mcq_multi" value="mcq_multi"
                               <?= (isset($oldInput['question_type']) && $oldInput['question_type'] === 'mcq_multi') ? 'checked' : '' ?>>
                        <label class="form-check-label" for="type_mcq_multi">MCQ Multi</label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input question-type-radio" type="radio" name="question_type"
                               id="type_true_false" value="true_false"
                               <?= (isset($oldInput['question_type']) && $oldInput['question_type'] === 'true_false') ? 'checked' : '' ?>>
                        <label class="form-check-label" for="type_true_false">True / False</label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input question-type-radio" type="radio" name="question_type"
                               id="type_fill_blank" value="fill_blank"
                               <?= (isset($oldInput['question_type']) && $oldInput['question_type'] === 'fill_blank') ? 'checked' : '' ?>>
                        <label class="form-check-label" for="type_fill_blank">Fill-in-the-Blank</label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input question-type-radio" type="radio" name="question_type"
                               id="type_short_answer" value="short_answer"
                               <?= (isset($oldInput['question_type']) && $oldInput['question_type'] === 'short_answer') ? 'checked' : '' ?>>
                        <label class="form-check-label" for="type_short_answer">Short Answer</label>
                    </div>
                </div>
            </div>

            <!-- Dynamic Options Area -->
            <div id="optionsArea">
                <!-- MCQ Single / Multi Options -->
                <div id="mcqOptions" class="type-options" style="display: none;">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <label class="form-label fw-medium mb-0">Answer Options</label>
                        <button type="button" class="btn btn-sm btn-outline-success" id="addMcqOption">+ Add Option</button>
                    </div>
                    <div id="mcqOptionsContainer">
                        <!-- Option rows will be added by JS -->
                    </div>
                    <div class="form-text mt-1">Add at least 2 options. Mark the correct option(s). Each option can have optional feedback text.</div>
                </div>

                <!-- True/False Options -->
                <div id="tfOptions" class="type-options" style="display: none;">
                    <label class="form-label fw-medium mb-2">Correct Answer</label>
                    <div class="mb-3">
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="radio" name="tf_correct"
                                   id="tf_true" value="1"
                                   <?= (isset($oldInput['tf_correct']) && (int) $oldInput['tf_correct'] === 1) ? 'checked' : '' ?>>
                            <label class="form-check-label" for="tf_true">True</label>
                        </div>
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="radio" name="tf_correct"
                                   id="tf_false" value="0"
                                   <?= (isset($oldInput['tf_correct']) && (int) $oldInput['tf_correct'] === 0) ? 'checked' : '' ?>>
                            <label class="form-check-label" for="tf_false">False</label>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="tf_feedback" class="form-label">Feedback Text</label>
                        <input type="text" id="tf_feedback" name="tf_feedback" class="form-control"
                               value="<?= htmlspecialchars($oldInput['tf_feedback'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                               placeholder="Optional explanation for both True and False">
                    </div>
                </div>

                <!-- Fill-in-the-Blank Options -->
                <div id="fbOptions" class="type-options" style="display: none;">
                    <div class="mb-3">
                        <label for="fb_answer" class="form-label">Correct Answer <span class="text-danger">*</span></label>
                        <input type="text" id="fb_answer" name="fb_answer" class="form-control"
                               value="<?= htmlspecialchars($oldInput['fb_answer'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                               placeholder="The exact expected answer (case-insensitive)">
                    </div>
                    <div class="mb-3">
                        <label for="fb_alternatives" class="form-label">Alternative Answers</label>
                        <textarea id="fb_alternatives" name="fb_alternatives" class="form-control" rows="2"
                                  placeholder="Comma-separated list of accepted alternatives"><?= htmlspecialchars($oldInput['fb_alternatives'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
                        <div class="form-text">Optional. Separate each acceptable answer with a comma.</div>
                    </div>
                    <div class="mb-3">
                        <label for="fb_feedback" class="form-label">Feedback Text</label>
                        <input type="text" id="fb_feedback" name="fb_feedback" class="form-control"
                               value="<?= htmlspecialchars($oldInput['fb_feedback'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                               placeholder="Optional feedback explanation">
                    </div>
                </div>

                <!-- Short Answer Options -->
                <div id="saOptions" class="type-options" style="display: none;">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <label class="form-label fw-medium mb-0">Required Keywords</label>
                        <button type="button" class="btn btn-sm btn-outline-success" id="addSaKeyword">+ Add Keyword</button>
                    </div>
                    <div id="saKeywordsContainer">
                        <!-- Keyword rows will be added by JS -->
                    </div>
                    <div class="form-text mt-1">Define keywords that must be present in the student's answer. Required keywords must appear; optional keywords add points if present.</div>
                </div>
            </div>

            <div class="d-flex gap-2 mt-4">
                <button type="submit" class="btn btn-primary">Save Question</button>
                <a href="/admin/questions" class="btn btn-outline-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>

<script>
(function() {
    'use strict';

    // ─── Type selector toggle ───
    const typeRadios = document.querySelectorAll('.question-type-radio');
    const optionsSections = document.querySelectorAll('.type-options');

    function showOptionsForType(type) {
        optionsSections.forEach(function(section) {
            section.style.display = 'none';
        });

        if (type === 'mcq_single' || type === 'mcq_multi') {
            document.getElementById('mcqOptions').style.display = 'block';
        } else if (type === 'true_false') {
            document.getElementById('tfOptions').style.display = 'block';
        } else if (type === 'fill_blank') {
            document.getElementById('fbOptions').style.display = 'block';
        } else if (type === 'short_answer') {
            document.getElementById('saOptions').style.display = 'block';
        }
    }

    typeRadios.forEach(function(radio) {
        radio.addEventListener('change', function() {
            if (this.checked) {
                showOptionsForType(this.value);
            }
        });
    });

    // Auto-show on page load if pre-selected
    var selectedType = document.querySelector('.question-type-radio:checked');
    if (selectedType) {
        showOptionsForType(selectedType.value);
    }

    // ─── MCQ Options Management ───
    var mcqOptionIndex = 0;
    var mcqContainer = document.getElementById('mcqOptionsContainer');

    function addMcqOption(optionText, isCorrect, feedbackText) {
        var isMcqMulti = document.getElementById('type_mcq_multi').checked;
        var inputType = isMcqMulti ? 'checkbox' : 'radio';
        var nameAttr = 'option_correct[' + mcqOptionIndex + ']';
        if (!isMcqMulti) {
            // Radio buttons need the same name for single-select
            nameAttr = 'option_correct';
        }

        var div = document.createElement('div');
        div.className = 'row g-2 mb-2 align-items-center option-row';
        div.dataset.index = mcqOptionIndex;

        div.innerHTML =
            '<div class="col-md-5">' +
                '<input type="text" name="option_text[' + mcqOptionIndex + ']" class="form-control form-control-sm" ' +
                       'placeholder="Option text" value="' + escapeHtml(optionText || '') + '" required>' +
            '</div>' +
            '<div class="col-md-1 text-center">' +
                '<div class="form-check">' +
                    '<input class="form-check-input" type="' + inputType + '" ' +
                           'name="' + nameAttr + '" value="1" ' +
                           (isCorrect ? 'checked' : '') + '>' +
                '</div>' +
            '</div>' +
            '<div class="col-md-4">' +
                '<input type="text" name="option_feedback[' + mcqOptionIndex + ']" class="form-control form-control-sm" ' +
                       'placeholder="Feedback (optional)" value="' + escapeHtml(feedbackText || '') + '">' +
            '</div>' +
            '<div class="col-md-2">' +
                '<button type="button" class="btn btn-sm btn-outline-danger remove-option">Remove</button>' +
            '</div>';

        mcqContainer.appendChild(div);
        mcqOptionIndex++;

        // Add remove handler
        div.querySelector('.remove-option').addEventListener('click', function() {
            div.remove();
        });
    }

    document.getElementById('addMcqOption').addEventListener('click', function() {
        addMcqOption('', false, '');
    });

    // Update input type when type changes between single and multi
    function updateMcqInputType() {
        var isMcqMulti = document.getElementById('type_mcq_multi').checked;
        var correctInputs = mcqContainer.querySelectorAll('.option-row .form-check-input');

        correctInputs.forEach(function(input) {
            if (isMcqMulti) {
                input.type = 'checkbox';
                input.name = 'option_correct[' + input.closest('.option-row').dataset.index + ']';
            } else {
                input.type = 'radio';
                input.name = 'option_correct';
            }
        });
    }

    document.getElementById('type_mcq_single').addEventListener('change', function() {
        if (this.checked) updateMcqInputType();
    });
    document.getElementById('type_mcq_multi').addEventListener('change', function() {
        if (this.checked) updateMcqInputType();
    });

    // Add 2 default MCQ options if none exist and editing fresh
    if (mcqContainer.children.length === 0 && !selectedType) {
        addMcqOption('', false, '');
        addMcqOption('', false, '');
    }

    // ─── Short Answer Keywords Management ───
    var saKeywordIndex = 0;
    var saContainer = document.getElementById('saKeywordsContainer');

    function addSaKeyword(keyword, synonyms, isRequired) {
        var div = document.createElement('div');
        div.className = 'row g-2 mb-2 align-items-center keyword-row';
        div.dataset.index = saKeywordIndex;

        div.innerHTML =
            '<div class="col-md-4">' +
                '<input type="text" name="sa_keyword[' + saKeywordIndex + ']" class="form-control form-control-sm" ' +
                       'placeholder="Keyword" value="' + escapeHtml(keyword || '') + '" required>' +
            '</div>' +
            '<div class="col-md-4">' +
                '<input type="text" name="sa_synonyms[' + saKeywordIndex + ']" class="form-control form-control-sm" ' +
                       'placeholder="Synonyms (comma-separated)" value="' + escapeHtml(synonyms || '') + '">' +
            '</div>' +
            '<div class="col-md-2 text-center">' +
                '<div class="form-check">' +
                    '<input class="form-check-input" type="checkbox" ' +
                           'name="sa_required[' + saKeywordIndex + ']" value="1" ' +
                           (isRequired ? 'checked' : '') + '>' +
                    '<label class="form-check-label">Required</label>' +
                '</div>' +
            '</div>' +
            '<div class="col-md-2">' +
                '<button type="button" class="btn btn-sm btn-outline-danger remove-keyword">Remove</button>' +
            '</div>';

        saContainer.appendChild(div);
        saKeywordIndex++;

        div.querySelector('.remove-keyword').addEventListener('click', function() {
            div.remove();
        });
    }

    document.getElementById('addSaKeyword').addEventListener('click', function() {
        addSaKeyword('', '', true);
    });

    // Add 1 default keyword if none exist
    if (saContainer.children.length === 0 && !selectedType) {
        addSaKeyword('', '', true);
    }

    // ─── Utility: HTML escaping ───
    function escapeHtml(str) {
        if (!str) return '';
        return str.replace(/&/g, '&amp;')
                  .replace(/"/g, '&quot;')
                  .replace(/'/g, '&#39;')
                  .replace(/</g, '&lt;')
                  .replace(/>/g, '&gt;');
    }

    // ─── Pre-populate if oldInput exists (validation re-display) ───
    <?php if (isset($oldInput)): ?>
    var oldInput = <?= json_encode($oldInput) ?>;

    // Pre-populate MCQ options
    if (oldInput.option_text && Array.isArray(oldInput.option_text)) {
        // Clear default options
        mcqContainer.innerHTML = '';
        oldInput.option_text.forEach(function(text, i) {
            var isCorrect = oldInput.option_correct && (
                (Array.isArray(oldInput.option_correct) && oldInput.option_correct[i] == 1) ||
                oldInput.option_correct == 1
            );
            var feedback = (oldInput.option_feedback && oldInput.option_feedback[i]) || '';
            addMcqOption(text, isCorrect, feedback);
        });
    }

    // Pre-populate SA keywords
    if (oldInput.sa_keyword && Array.isArray(oldInput.sa_keyword)) {
        saContainer.innerHTML = '';
        oldInput.sa_keyword.forEach(function(kw, i) {
            var syn = (oldInput.sa_synonyms && oldInput.sa_synonyms[i]) || '';
            var req = oldInput.sa_required && oldInput.sa_required[i] == 1;
            addSaKeyword(kw, syn, req);
        });
    }
    <?php endif; ?>
})();
</script>
