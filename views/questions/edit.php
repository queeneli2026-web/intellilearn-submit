<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h3">Edit Question</h1>
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
        <form action="/admin/questions/update" method="POST" id="questionForm">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="id" value="<?= (int) ($question['id'] ?? $oldInput['id'] ?? 0) ?>">

            <!-- Common fields -->
            <div class="mb-3">
                <label for="question_text" class="form-label">Question Text <span class="text-danger">*</span></label>
                <textarea id="question_text"
                          name="question_text"
                          class="form-control"
                          rows="4"
                          required><?= htmlspecialchars($oldInput['question_text'] ?? $question['question_text'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
            </div>

            <div class="row mb-3">
                <div class="col-md-6">
                    <label for="topic_id" class="form-label">Topic <span class="text-danger">*</span></label>
                    <select id="topic_id" name="topic_id" class="form-select" required>
                        <option value="">Select a topic...</option>
                        <?php foreach ($topics as $topic): ?>
                            <option value="<?= (int) $topic['id'] ?>"
                                <?= ((isset($oldInput['topic_id']) && (int) $oldInput['topic_id'] === (int) $topic['id']) ||
                                     (!isset($oldInput['topic_id']) && isset($question['topic_id']) && (int) $question['topic_id'] === (int) $topic['id'])) ? 'selected' : '' ?>>
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
                           value="<?= htmlspecialchars($oldInput['points'] ?? $question['points'] ?? '1.0', ENT_QUOTES, 'UTF-8') ?>">
                </div>
            </div>

            <div class="mb-3">
                <label for="explanation" class="form-label">Explanation (corrective feedback)</label>
                <textarea id="explanation"
                          name="explanation"
                          class="form-control"
                          rows="2"><?= htmlspecialchars($oldInput['explanation'] ?? $question['explanation'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
                <div class="form-text">Shown after the student answers — explain why the correct answer is right.</div>
            </div>

            <!-- Question Type Display (read-only during edit) -->
            <div class="mb-4">
                <label class="form-label">Question Type</label>
                <div>
                    <input type="hidden" name="question_type" value="<?= htmlspecialchars($question['question_type'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                    <?php
                        $typeLabels = [
                            'mcq_single'   => 'MCQ Single',
                            'mcq_multi'    => 'MCQ Multi',
                            'true_false'   => 'True / False',
                            'fill_blank'   => 'Fill-in-the-Blank',
                            'short_answer' => 'Short Answer',
                        ];
                        $currentType = $question['question_type'] ?? '';
                    ?>
                    <span class="badge bg-secondary fs-6">
                        <?= htmlspecialchars($typeLabels[$currentType] ?? $currentType, ENT_QUOTES, 'UTF-8') ?>
                    </span>
                    <div class="form-text">Question type cannot be changed after creation.</div>
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
                        <!-- Option rows will be populated by JS -->
                    </div>
                    <div class="form-text mt-1">Add at least 2 options. Mark the correct option(s). Each option can have optional feedback text.</div>
                </div>

                <!-- True/False Options -->
                <div id="tfOptions" class="type-options" style="display: none;">
                    <label class="form-label fw-medium mb-2">Correct Answer</label>
                    <div class="mb-3">
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="radio" name="tf_correct"
                                   id="tf_true" value="1">
                            <label class="form-check-label" for="tf_true">True</label>
                        </div>
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="radio" name="tf_correct"
                                   id="tf_false" value="0">
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
                        <!-- Keyword rows will be populated by JS -->
                    </div>
                    <div class="form-text mt-1">Define keywords that must be present in the student's answer.</div>
                </div>
            </div>

            <div class="d-flex gap-2 mt-4">
                <button type="submit" class="btn btn-primary">Update Question</button>
                <a href="/admin/questions" class="btn btn-outline-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>

<script>
(function() {
    'use strict';

    // ─── Show the correct options section based on current type ───
    var questionType = '<?= htmlspecialchars($question['question_type'] ?? '', ENT_QUOTES, 'UTF-8') ?>';
    var optionsSections = document.querySelectorAll('.type-options');

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

    showOptionsForType(questionType);

    // ─── MCQ Options Management ───
    var mcqOptionIndex = <?= $question['question_type'] === 'mcq_single' || $question['question_type'] === 'mcq_multi'
        ? count($question['options'] ?? []) : 0 ?>;
    var mcqContainer = document.getElementById('mcqOptionsContainer');
    var isMcqMulti = questionType === 'mcq_multi';

    function addMcqOption(optionText, isCorrect, feedbackText) {
        var inputType = isMcqMulti ? 'checkbox' : 'radio';
        var nameAttr = isMcqMulti ? 'option_correct[' + mcqOptionIndex + ']' : 'option_correct';

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

        div.querySelector('.remove-option').addEventListener('click', function() {
            div.remove();
        });
    }

    document.getElementById('addMcqOption').addEventListener('click', function() {
        addMcqOption('', false, '');
    });

    // Pre-populate MCQ options from question data
    <?php if (in_array($question['question_type'] ?? '', ['mcq_single', 'mcq_multi'])): ?>
        <?php foreach ($question['options'] ?? [] as $opt): ?>
            addMcqOption(
                <?= json_encode(htmlspecialchars_decode($opt['option_text'] ?? '')) ?>,
                <?= !empty($opt['is_correct']) ? 'true' : 'false' ?>,
                <?= json_encode(htmlspecialchars_decode($opt['feedback_text'] ?? '')) ?>
            );
        <?php endforeach; ?>
    <?php endif; ?>

    // ─── True/False: pre-populate correct radio ───
    <?php if (($question['question_type'] ?? '') === 'true_false'): ?>
        <?php
            $tfCorrect = null;
            foreach ($question['options'] ?? [] as $opt) {
                if (!empty($opt['is_correct'])) {
                    $tfCorrect = $opt['is_true'] ? 1 : 0;
                    break;
                }
            }
            if ($tfCorrect === null) $tfCorrect = 1;
        ?>
        document.querySelector('input[name="tf_correct"][value="<?= $tfCorrect ?>"]').checked = true;
        document.getElementById('tf_feedback').value = <?= json_encode($question['options'][0]['feedback_text'] ?? '') ?>;
    <?php endif; ?>

    // ─── Fill-in-the-Blank: pre-populate ───
    <?php if (($question['question_type'] ?? '') === 'fill_blank'): ?>
        <?php
            $fbOptions = $question['options'][0] ?? [];
            $fbAnswer = $fbOptions['correct_answer'] ?? '';
            $fbAlternatives = '';
            if (!empty($fbOptions['alternative_answers'])) {
                $altArray = json_decode($fbOptions['alternative_answers'], true);
                if (is_array($altArray)) {
                    $fbAlternatives = implode(', ', $altArray);
                }
            }
            $fbFeedback = $fbOptions['feedback_text'] ?? '';
        ?>
        document.getElementById('fb_answer').value = <?= json_encode($fbAnswer) ?>;
        document.getElementById('fb_alternatives').value = <?= json_encode($fbAlternatives) ?>;
        document.getElementById('fb_feedback').value = <?= json_encode($fbFeedback) ?>;
    <?php endif; ?>

    // ─── Short Answer Keywords ───
    var saKeywordIndex = <?= ($question['question_type'] ?? '') === 'short_answer' ? count($question['options'] ?? []) : 0 ?>;
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

    // Pre-populate SA keywords
    <?php if (($question['question_type'] ?? '') === 'short_answer'): ?>
        <?php foreach ($question['options'] ?? [] as $kw): ?>
            <?php
                $synonyms = '';
                if (!empty($kw['synonyms'])) {
                    $synArray = json_decode($kw['synonyms'], true);
                    if (is_array($synArray)) {
                        $synonyms = implode(', ', $synArray);
                    }
                }
            ?>
            addSaKeyword(
                <?= json_encode(htmlspecialchars_decode($kw['keyword'] ?? '')) ?>,
                <?= json_encode($synonyms) ?>,
                <?= !empty($kw['is_required']) ? 'true' : 'false' ?>
            );
        <?php endforeach; ?>
    <?php endif; ?>

    // ─── Utility: HTML escaping ───
    function escapeHtml(str) {
        if (!str) return '';
        return str.replace(/&/g, '&amp;')
                  .replace(/"/g, '&quot;')
                  .replace(/'/g, '&#39;')
                  .replace(/</g, '&lt;')
                  .replace(/>/g, '&gt;');
    }

    // ─── Handle oldInput after validation errors ───
    <?php if (isset($oldInput)): ?>
    var oldInput = <?= json_encode($oldInput) ?>;

    // Re-populate MCQ options
    if (oldInput.option_text && Array.isArray(oldInput.option_text)) {
        mcqContainer.innerHTML = '';
        mcqOptionIndex = 0;
        oldInput.option_text.forEach(function(text, i) {
            var isCorrect = oldInput.option_correct && (
                (Array.isArray(oldInput.option_correct) && oldInput.option_correct[i] == 1) ||
                oldInput.option_correct == 1
            );
            var feedback = (oldInput.option_feedback && oldInput.option_feedback[i]) || '';
            addMcqOption(text, isCorrect, feedback);
        });
    }

    // Re-populate SA keywords
    if (oldInput.sa_keyword && Array.isArray(oldInput.sa_keyword)) {
        saContainer.innerHTML = '';
        saKeywordIndex = 0;
        oldInput.sa_keyword.forEach(function(kw, i) {
            var syn = (oldInput.sa_synonyms && oldInput.sa_synonyms[i]) || '';
            var req = oldInput.sa_required && oldInput.sa_required[i] == 1;
            addSaKeyword(kw, syn, req);
        });
    }

    // Re-populate TF
    if (oldInput.tf_correct !== undefined) {
        document.querySelector('input[name="tf_correct"][value="' + oldInput.tf_correct + '"]').checked = true;
    }
    if (oldInput.tf_feedback !== undefined) {
        document.getElementById('tf_feedback').value = oldInput.tf_feedback;
    }

    // Re-populate FB
    if (oldInput.fb_answer !== undefined) {
        document.getElementById('fb_answer').value = oldInput.fb_answer;
    }
    if (oldInput.fb_alternatives !== undefined) {
        document.getElementById('fb_alternatives').value = oldInput.fb_alternatives;
    }
    if (oldInput.fb_feedback !== undefined) {
        document.getElementById('fb_feedback').value = oldInput.fb_feedback;
    }
    <?php endif; ?>
})();
</script>
