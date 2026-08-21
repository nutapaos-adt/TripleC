<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreCaseTypeRequest;
use App\Models\CaseType;
use App\Models\VisitRule;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class CaseTypeController extends Controller
{
    public function index(): View
    {
        $caseTypes = CaseType::with('visitRules')->orderBy('name')->get();

        return view('admin.case-types.index', compact('caseTypes'));
    }

    public function create(): View
    {
        return view('admin.case-types.create');
    }

    public function store(StoreCaseTypeRequest $request): RedirectResponse
    {
        DB::transaction(function () use ($request) {
            $caseType = CaseType::create([
                'name' => $request->validated('name'),
                'slug' => $request->validated('slug'),
                'description' => $request->validated('description'),
                'is_active' => $request->boolean('is_active', true),
            ]);

            $this->saveVisitRule($caseType, $request);
        });

        return redirect()
            ->route('admin.case-types.index')
            ->with('status', 'เพิ่มประเภทเคสเรียบร้อยแล้ว');
    }

    public function edit(CaseType $caseType): View
    {
        $caseType->load('visitRules');

        return view('admin.case-types.edit', compact('caseType'));
    }

    public function update(StoreCaseTypeRequest $request, CaseType $caseType): RedirectResponse
    {
        DB::transaction(function () use ($request, $caseType) {
            $caseType->update([
                'name' => $request->validated('name'),
                'slug' => $request->validated('slug'),
                'description' => $request->validated('description'),
                'is_active' => $request->boolean('is_active'),
            ]);

            $this->saveVisitRule($caseType, $request);
        });

        return redirect()
            ->route('admin.case-types.index')
            ->with('status', 'บันทึกการแก้ไขเรียบร้อยแล้ว');
    }

    protected function saveVisitRule(CaseType $caseType, StoreCaseTypeRequest $request): void
    {
        $ruleType = $request->validated('rule_type');

        VisitRule::updateOrCreate(
            ['case_type_id' => $caseType->id, 'is_active' => true],
            [
                'rule_type' => $ruleType,
                'fixed_visit_count' => $ruleType === VisitRule::TYPE_FIXED_COUNT ? $request->validated('fixed_visit_count') : null,
                'fixed_interval_days' => $ruleType === VisitRule::TYPE_FIXED_COUNT ? $request->validated('fixed_interval_days') : null,
                'score_rules' => $ruleType === VisitRule::TYPE_SCORE_BASED ? $request->parsedScoreRules() : null,
                'created_by' => Auth::id(),
            ]
        );
    }
}
