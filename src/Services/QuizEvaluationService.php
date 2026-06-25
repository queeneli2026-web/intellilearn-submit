<?php
declare(strict_types=1);

namespace App\Services;

/**
 * Pure evaluation service for all 5 question types.
 *
 * No PDO/state dependencies — all methods accept primitives/arrays
 * and return arrays. Used by AttemptController to evaluate student
 * answers server-side.
 *
 * Supports:
 *   - MCQ Single (perfect score or zero)
 *   - MCQ Multi (partial credit per ARCHITECTURE.md §6.3)
 *   - True/False (binary correctness)
 *   - Fill-in-blank (case-insensitive with alternative answers)
 *   - Short Answer (keyword + synonym matching with manual review flag)
 *   - Aggregate scoring (percentage + pass/fail)
 */
class QuizEvaluationService
{
    /**
     * Main dispatcher. Routes to type-specific evaluator based on question_type.
     *
     * @param  string     $questionType    One of: mcq_single, mcq_multi, true_false, fill_blank, short_answer
     * @param  array      $questionData    Question row with type-specific options/data
     * @param  mixed      $submittedAnswer Student's answer (scalar or array depending on type)
     * @param  float|null $pointsOverride  Quiz-level points_override from quiz_question pivot
     * @return array{is_correct: bool, points_earned: float, max_points: float, feedback_type: string, needs_review: bool}
     */
    public static function evaluateAnswer(
        string $questionType,
        array $questionData,
        mixed $submittedAnswer,
        ?float $pointsOverride = null
    ): array {
        $maxPoints = $pointsOverride ?? (float) ($questionData['points'] ?? 1.0);

        $result = match ($questionType) {
            'mcq_single' => self::evaluateMcqSingle(
                (int) ($submittedAnswer['selected_option_id'] ?? 0),
                $questionData['options'] ?? []
            ),
            'mcq_multi' => self::evaluateMcqMulti(
                (array) ($submittedAnswer['selected_option_ids'] ?? []),
                $questionData['options'] ?? []
            ),
            'true_false' => self::evaluateTrueFalse(
                (int) ($submittedAnswer['selected_option_id'] ?? 0),
                $questionData['options'] ?? []
            ),
            'fill_blank' => self::evaluateFillBlank(
                (string) ($submittedAnswer['answer_text'] ?? ''),
                $questionData
            ),
            'short_answer' => self::evaluateShortAnswer(
                (string) ($submittedAnswer['answer_text'] ?? ''),
                $questionData['options'] ?? []
            ),
            default => [
                'is_correct'    => false,
                'points_earned' => 0.0,
                'feedback_type' => 'unknown_type',
                'needs_review'  => true,
            ],
        };

        // Scale points by maxPoints for correct answers, or compute partial
        if ($result['is_correct']) {
            $result['points_earned'] = $maxPoints;
            $result['max_points'] = $maxPoints;
        } else {
            $result['max_points'] = $maxPoints;
            // For multi-select with partial credit, points_earned is already proportional
            // For other types, earned is 0 when incorrect
        }

        return $result;
    }

    /**
     * Evaluate a single-correct MCQ.
     *
     * @param  int   $selectedOptionId The ID of the option the student selected
     * @param  array $options          Array of mcq_single_options rows
     * @return array{is_correct: bool, points_earned: float, feedback_type: string, needs_review: bool}
     */
    public static function evaluateMcqSingle(int $selectedOptionId, array $options): array
    {
        $correct = false;
        foreach ($options as $option) {
            if ((int) $option['id'] === $selectedOptionId) {
                $correct = !empty($option['is_correct']);
                break;
            }
        }

        return [
            'is_correct'    => $correct,
            'points_earned' => $correct ? 1.0 : 0.0,
            'feedback_type' => $correct ? 'correct' : 'incorrect',
            'needs_review'  => false,
        ];
    }

    /**
     * Evaluate a multi-correct MCQ with partial credit.
     *
     * Implements ARCHITECTURE.md §6.3 partial credit formula:
     *   positiveRatio = correctSelections / totalCorrect
     *   penaltyRatio  = incorrectSelections / totalCorrect
     *   earned = max(0, (positiveRatio - penaltyRatio) * maxPoints)
     *
     * @param  array $selectedIds Array of selected option IDs
     * @param  array $options     Array of mcq_multi_options rows
     * @return array{is_correct: bool, points_earned: float, feedback_type: string, needs_review: bool}
     */
    public static function evaluateMcqMulti(array $selectedIds, array $options): array
    {
        $selectedIds = array_map('intval', $selectedIds);
        $correctIds = [];
        foreach ($options as $option) {
            if (!empty($option['is_correct'])) {
                $correctIds[] = (int) $option['id'];
            }
        }

        $totalCorrect = count($correctIds);
        if ($totalCorrect === 0) {
            return [
                'is_correct'    => false,
                'points_earned' => 0.0,
                'feedback_type' => 'incorrect',
                'needs_review'  => false,
            ];
        }

        $correctCount = count(array_intersect($selectedIds, $correctIds));
        $incorrectCount = count(array_diff($selectedIds, $correctIds));

        // Perfect selection
        if ($correctCount === $totalCorrect && $incorrectCount === 0) {
            return [
                'is_correct'    => true,
                'points_earned' => 1.0,
                'feedback_type' => 'correct',
                'needs_review'  => false,
            ];
        }

        // No correct selections
        if ($correctCount === 0) {
            return [
                'is_correct'    => false,
                'points_earned' => 0.0,
                'feedback_type' => 'incorrect',
                'needs_review'  => false,
            ];
        }

        // Partial credit formula
        $positiveRatio = $correctCount / $totalCorrect;
        $penaltyRatio = $incorrectCount / $totalCorrect;
        $earned = max(0.0, ($positiveRatio - $penaltyRatio));

        return [
            'is_correct'    => false,
            'points_earned' => round($earned, 2),
            'feedback_type' => 'partial',
            'needs_review'  => false,
        ];
    }

    /**
     * Evaluate a True/False question.
     *
     * Checks if the selected option's is_correct flag matches.
     *
     * @param  int   $selectedOptionId The ID of the option the student selected (from true_false_options)
     * @param  array $options          Array of true_false_options rows (2 rows: True and False)
     * @return array{is_correct: bool, points_earned: float, feedback_type: string, needs_review: bool}
     */
    public static function evaluateTrueFalse(int $selectedOptionId, array $options): array
    {
        $correct = false;
        foreach ($options as $option) {
            if ((int) $option['id'] === $selectedOptionId) {
                $correct = !empty($option['is_correct']);
                break;
            }
        }

        return [
            'is_correct'    => $correct,
            'points_earned' => $correct ? 1.0 : 0.0,
            'feedback_type' => $correct ? 'correct' : 'incorrect',
            'needs_review'  => false,
        ];
    }

    /**
     * Evaluate a Fill-in-the-blank question.
     *
     * Case-insensitive comparison against correct_answer and alternative_answers (JSON decoded).
     * Returns correct if ANY match is found.
     *
     * @param  string $answer        Student's submitted answer text
     * @param  array  $questionData  Question row containing correct_answer data
     * @return array{is_correct: bool, points_earned: float, feedback_type: string, needs_review: bool}
     */
    public static function evaluateFillBlank(string $answer, array $questionData): array
    {
        $normalizedAnswer = trim($answer);
        if ($normalizedAnswer === '') {
            return [
                'is_correct'    => false,
                'points_earned' => 0.0,
                'feedback_type' => 'incorrect',
                'needs_review'  => false,
            ];
        }

        // Collect all acceptable answers from options
        $acceptableAnswers = [];
        foreach (($questionData['options'] ?? []) as $option) {
            $acceptableAnswers[] = trim((string) ($option['correct_answer'] ?? ''));

            // Parse alternative_answers JSON
            if (!empty($option['alternative_answers'])) {
                $alternatives = is_string($option['alternative_answers'])
                    ? json_decode($option['alternative_answers'], true)
                    : $option['alternative_answers'];
                if (is_array($alternatives)) {
                    foreach ($alternatives as $alt) {
                        $acceptableAnswers[] = trim((string) $alt);
                    }
                }
            }
        }

        $acceptableAnswers = array_filter($acceptableAnswers, fn(string $v) => $v !== '');
        $acceptableAnswers = array_unique($acceptableAnswers);

        $correct = false;
        foreach ($acceptableAnswers as $acceptable) {
            if (strcasecmp($normalizedAnswer, $acceptable) === 0) {
                $correct = true;
                break;
            }
        }

        return [
            'is_correct'    => $correct,
            'points_earned' => $correct ? 1.0 : 0.0,
            'feedback_type' => $correct ? 'correct' : 'incorrect',
            'needs_review'  => false,
        ];
    }

    /**
     * Evaluate a Short Answer question using keyword + synonym matching.
     *
     * For each keyword:
     *   - Check if keyword OR any synonym appears in the answer string (case-insensitive)
     *   - If a required keyword (is_required=1) is missing → needs_review=true
     *
     * Returns matched/unmatched lists for feedback display.
     *
     * @param  string $answer   Student's submitted answer text
     * @param  array  $keywords Array of short_answer_keywords rows with keyword, synonyms (JSON), is_required
     * @return array{is_correct: bool, points_earned: float, feedback_type: string, needs_review: bool, matched: array, unmatched: array}
     */
    public static function evaluateShortAnswer(string $answer, array $keywords): array
    {
        $normalizedAnswer = mb_strtolower(trim($answer));

        if ($normalizedAnswer === '' || empty($keywords)) {
            return [
                'is_correct'    => false,
                'points_earned' => 0.0,
                'feedback_type' => 'incorrect',
                'needs_review'  => true,
                'matched'       => [],
                'unmatched'     => [],
            ];
        }

        $matched = [];
        $unmatched = [];
        $allRequiredMatched = true;

        foreach ($keywords as $kw) {
            $keyword = mb_strtolower(trim((string) ($kw['keyword'] ?? '')));
            if ($keyword === '') {
                continue;
            }

            // Check if the keyword itself appears in the answer
            $found = str_contains($normalizedAnswer, $keyword);

            // If not found, check synonyms
            if (!$found && !empty($kw['synonyms'])) {
                $synonyms = is_string($kw['synonyms'])
                    ? json_decode($kw['synonyms'], true)
                    : $kw['synonyms'];

                if (is_array($synonyms)) {
                    foreach ($synonyms as $synonym) {
                        $synonym = mb_strtolower(trim((string) $synonym));
                        if ($synonym !== '' && str_contains($normalizedAnswer, $synonym)) {
                            $found = true;
                            break;
                        }
                    }
                }
            }

            if ($found) {
                $matched[] = $kw['keyword'];
            } else {
                $unmatched[] = $kw['keyword'];
                if (!empty($kw['is_required'])) {
                    $allRequiredMatched = false;
                }
            }
        }

        $needsReview = !$allRequiredMatched;
        $isCorrect = $allRequiredMatched;

        return [
            'is_correct'    => $isCorrect,
            'points_earned' => $isCorrect ? 1.0 : 0.0,
            'feedback_type' => $isCorrect ? 'correct' : ($needsReview ? 'pending_review' : 'incorrect'),
            'needs_review'  => $needsReview,
            'matched'       => $matched,
            'unmatched'     => $unmatched,
        ];
    }

    /**
     * Calculate aggregate score from all responses.
     *
     * @param  array $responsesWithPoints Array of responses with points_earned and max_points
     * @param  float $maxPossible         Total possible points for the quiz
     * @param  float $passPercentage      Percentage required to pass (0-100)
     * @return array{score: float, maxScore: float, percentage: float, passed: bool}
     */
    public static function calculateScore(array $responsesWithPoints, float $maxPossible, float $passPercentage): array
    {
        $score = 0.0;
        foreach ($responsesWithPoints as $response) {
            $score += (float) ($response['points_earned'] ?? 0);
        }

        $percentage = $maxPossible > 0
            ? round(($score / $maxPossible) * 100, 1)
            : 0.0;

        $passed = $percentage >= $passPercentage;

        return [
            'score'      => round($score, 1),
            'maxScore'   => round($maxPossible, 1),
            'percentage' => $percentage,
            'passed'     => $passed,
        ];
    }
}
