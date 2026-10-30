<?php

namespace App\Http\Requests\Suppliers;

use App\Models\RatingCriterion;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class RatingRequest extends FormRequest
{
    /**
     * Every criterion in use must get a score, so overall scores stay comparable.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $criterionIds = RatingCriterion::query()->inUse()->pluck('id');

        $rules = [
            'rated_on' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'comment' => ['nullable', 'string', 'max:5000'],
            'scores' => ['required', 'array', 'min:1', 'required_array_keys:'.$criterionIds->implode(',')],
        ];

        foreach ($criterionIds as $criterionId) {
            $rules["scores.{$criterionId}"] = ['required', 'integer', 'between:1,5'];
        }

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'rated_on' => 'rating date',
            'scores.*' => 'score',
        ];
    }

    /**
     * Only criteria that are in use count; anything else in the request is ignored.
     *
     * @return array<int, int>
     */
    public function scoresByCriterionId(): array
    {
        $criterionIds = RatingCriterion::query()->inUse()->pluck('id')->all();
        $scores = [];

        foreach ($criterionIds as $criterionId) {
            $scores[$criterionId] = (int) $this->validated("scores.{$criterionId}");
        }

        return $scores;
    }
}
