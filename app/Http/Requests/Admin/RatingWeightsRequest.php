<?php

namespace App\Http\Requests\Admin;

use App\Models\RatingCriterion;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class RatingWeightsRequest extends FormRequest
{
    /**
     * Every criterion must get a weight, including retired ones, because old ratings still use them.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $criterionIds = RatingCriterion::query()->pluck('id');

        $rules = [
            'weights' => ['required', 'array', 'required_array_keys:'.$criterionIds->implode(',')],
        ];

        foreach ($criterionIds as $criterionId) {
            $rules["weights.{$criterionId}"] = ['required', 'integer', 'between:0,100'];
        }

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'weights.*' => 'weight',
        ];
    }

    /**
     * Only existing criteria count; anything else in the request is ignored.
     *
     * @return array<int, int>
     */
    public function weightsByCriterionId(): array
    {
        $weights = [];

        foreach (RatingCriterion::query()->pluck('id') as $criterionId) {
            $weights[$criterionId] = (int) $this->validated("weights.{$criterionId}");
        }

        return $weights;
    }
}
