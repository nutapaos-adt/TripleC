<?php

namespace Tests\Unit;

use App\Services\MedicalGlossary;
use PHPUnit\Framework\TestCase;

class MedicalGlossaryTest extends TestCase
{
    protected function glossary(): MedicalGlossary
    {
        return new MedicalGlossary(require __DIR__.'/../../config/medical_glossary.php');
    }

    public function test_finds_abbreviations_present_in_text(): void
    {
        $found = $this->glossary()->lookup('ผู้ป่วยหญิง U/D DM, ESRD on CAPD มี Pale และ SOB');

        $this->assertArrayHasKey('U/D', $found['terms']);
        $this->assertArrayHasKey('DM', $found['terms']);
        $this->assertArrayHasKey('ESRD', $found['terms']);
        $this->assertArrayHasKey('CAPD', $found['terms']);
        $this->assertArrayHasKey('Pale', $found['terms']);
        $this->assertArrayHasKey('SOB', $found['terms']);
        $this->assertArrayNotHasKey('COPD', $found['terms']);
    }

    public function test_abbreviations_are_case_sensitive(): void
    {
        $chiefComplaint = $this->glossary()->lookup('CC: ไข้สูง 3 วัน');
        $this->assertArrayHasKey('CC', $chiefComplaint['terms']);
        $this->assertArrayNotHasKey('cc', $chiefComplaint['terms']);

        $volume = $this->glossary()->lookup('ให้ยา 5 cc po');
        $this->assertArrayHasKey('cc', $volume['terms']);
        $this->assertArrayNotHasKey('CC', $volume['terms']);

        // "or" ในประโยคภาษาอังกฤษต้องไม่ถูกตีความเป็น OR (ห้องผ่าตัด)
        $this->assertArrayNotHasKey('OR', $this->glossary()->lookup('pain or fever')['terms']);
    }

    public function test_does_not_match_inside_longer_words(): void
    {
        $found = $this->glossary()->lookup('NSTEMI');

        $this->assertArrayHasKey('NSTEMI', $found['terms']);
        $this->assertArrayNotHasKey('STEMI', $found['terms']);
    }

    public function test_longer_words_are_case_insensitive(): void
    {
        $this->assertArrayHasKey('Pneumonia', $this->glossary()->lookup('Dx: pneumonia')['terms']);
    }

    public function test_ambiguous_terms_are_listed_with_all_meanings_and_not_as_single_meaning(): void
    {
        $found = $this->glossary()->lookup('Hx HT 10 ปี, D/C Metformin');

        $this->assertArrayHasKey('HT', $found['ambiguous']);
        $this->assertCount(2, $found['ambiguous']['HT']);
        $this->assertArrayHasKey('D/C', $found['ambiguous']);
        $this->assertArrayNotHasKey('HT', $found['terms']);
    }

    public function test_underscore_placeholder_matches_digits(): void
    {
        $found = $this->glossary()->lookup('Paracetamol 1 tab q6h prn, G2P1 GA 38 wk');

        $this->assertArrayHasKey('q_h', $found['terms']);
        $this->assertArrayHasKey('G_P_', $found['terms']);
    }

    public function test_roots_use_the_longest_matching_suffix(): void
    {
        $found = $this->glossary()->lookup('s/p partial gastrectomy');

        $this->assertArrayHasKey('gastro-', $found['roots']);
        $this->assertArrayHasKey('-ectomy', $found['roots']);
        $this->assertArrayNotHasKey('-tomy', $found['roots']);

        $this->assertArrayHasKey('hepato-', $this->glossary()->lookup('hepatomegaly')['roots']);
    }

    public function test_prompt_section_is_empty_when_no_terms_found(): void
    {
        $this->assertSame('', $this->glossary()->promptSection('ผู้ป่วยทานข้าวได้ดี นอนหลับได้'));
        $this->assertSame('', $this->glossary()->promptSection(''));
    }

    public function test_prompt_section_includes_terms_ambiguity_rule_and_keep_abbreviation_rule(): void
    {
        $section = $this->glossary()->promptSection('U/D HT on NG tube');

        $this->assertStringContainsString('- U/D = โรคประจำตัว', $section);
        $this->assertStringContainsString('- HT = โรคความดันโลหิตสูง (Hypertension) หรือ ส่วนสูง (Height)', $section);
        $this->assertStringContainsString('ไม่แน่ใจ', $section);
        $this->assertStringContainsString('คงคำย่อเดิมไว้ในวงเล็บ', $section);
    }

    public function test_limits_number_of_terms(): void
    {
        $config = require __DIR__.'/../../config/medical_glossary.php';
        $everyAbbreviation = implode(' ', array_keys($config['diagnosis_abbreviations'] + $config['history_abbreviations'] + $config['symptoms']));

        $found = $this->glossary()->lookup($everyAbbreviation);

        $this->assertLessThanOrEqual(MedicalGlossary::MAX_TERMS, count($found['terms']) + count($found['ambiguous']) + count($found['roots']));
    }
}
