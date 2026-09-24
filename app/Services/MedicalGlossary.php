<?php

namespace App\Services;

class MedicalGlossary
{
    // จำนวนคำสูงสุดที่แนบไปใน prompt ต่อครั้ง (โมเดล local มี context จำกัด)
    public const MAX_TERMS = 60;

    protected const SECTION_AMBIGUOUS = 'ambiguous';

    protected const SECTION_PREFIXES = 'prefixes';

    protected const SECTION_SUFFIXES = 'suffixes';

    /**
     * @param  array<string, array<string, string|array<int, string>>>  $glossary  โครงสร้างเดียวกับ config/medical_glossary.php
     */
    public function __construct(protected array $glossary) {}

    public static function fromConfig(): self
    {
        return new self(config('medical_glossary', []));
    }

    /**
     * หาคำในอภิธานศัพท์ที่ปรากฏในข้อความ — คำย่อ/คำสั้นจับแบบแยกตัวพิมพ์ใหญ่-เล็ก (CC ≠ cc)
     * ส่วนรากศัพท์ (prefix/suffix) จับจากคำภาษาอังกฤษที่ขึ้นต้น/ลงท้ายด้วยรากศัพท์นั้น
     *
     * @return array{
     *     terms: array<string, string>,
     *     ambiguous: array<string, array<int, string>>,
     *     roots: array<string, string>,
     * }
     */
    public function lookup(string ...$texts): array
    {
        $text = implode("\n", array_filter($texts, fn ($t) => $t !== ''));
        $found = ['terms' => [], 'ambiguous' => [], 'roots' => []];

        if (trim($text) === '') {
            return $found;
        }

        $count = 0;

        foreach ($this->glossary as $section => $entries) {
            if (in_array($section, [self::SECTION_PREFIXES, self::SECTION_SUFFIXES], true)) {
                continue;
            }

            foreach ($entries as $term => $meaning) {
                if ($count >= self::MAX_TERMS) {
                    break 2;
                }

                if (! $this->containsTerm($text, (string) $term)) {
                    continue;
                }

                if ($section === self::SECTION_AMBIGUOUS) {
                    $found['ambiguous'][$term] = (array) $meaning;
                } elseif (! isset($found['terms'][$term])) {
                    $found['terms'][$term] = (string) $meaning;
                } else {
                    continue;
                }

                $count++;
            }
        }

        // คำที่กำกวมต้องไม่ถูกแนบความหมายเดียวซ้ำ (เช่น HT ต้องให้โมเดลตีความจากบริบทเท่านั้น)
        $found['terms'] = array_diff_key($found['terms'], $found['ambiguous']);

        $found['roots'] = $this->lookupRoots($text, self::MAX_TERMS - $count);

        return $found;
    }

    /**
     * ข้อความส่วนอภิธานศัพท์สำหรับแนบใน prompt — คืนค่าว่างถ้าไม่พบคำใดเลย
     */
    public function promptSection(string ...$texts): string
    {
        $found = $this->lookup(...$texts);

        if ($found['terms'] === [] && $found['ambiguous'] === [] && $found['roots'] === []) {
            return '';
        }

        $lines = ['อภิธานศัพท์ของคำที่พบในข้อความนี้ (ใช้ช่วยตีความเท่านั้น):'];

        foreach ($found['terms'] as $term => $meaning) {
            $lines[] = "- {$term} = {$meaning}";
        }

        if ($found['ambiguous'] !== []) {
            $lines[] = '';
            $lines[] = 'คำที่มีได้หลายความหมาย — ตีความจากบริบทเท่านั้น ถ้าบริบทไม่ชัดให้เขียนว่า "ไม่แน่ใจ" ห้ามเดา:';

            foreach ($found['ambiguous'] as $term => $meanings) {
                $lines[] = "- {$term} = ".implode(' หรือ ', $meanings);
            }
        }

        if ($found['roots'] !== []) {
            $lines[] = '';
            $lines[] = 'รากศัพท์ที่พบ (ใช้ประกอบความหมายของคำศัพท์หัตถการ/อวัยวะ):';

            foreach ($found['roots'] as $root => $meaning) {
                $lines[] = "- {$root} = {$meaning}";
            }
        }

        $lines[] = '';
        $lines[] = 'เมื่อขยายคำย่อ ให้คงคำย่อเดิมไว้ในวงเล็บเสมอ เช่น "โรคประจำตัว (U/D)" เพื่อให้พยาบาลเทียบกับต้นฉบับได้';

        return implode("\n", $lines);
    }

    protected function containsTerm(string $text, string $term): bool
    {
        // "_" ในอภิธานศัพท์แทนตัวเลข เช่น q_h = q2h, G_P_ = G2P1
        $pattern = str_replace('_', '\d+', preg_quote($term, '/'));
        $flags = $this->isCaseSensitive($term) ? 'u' : 'iu';

        return preg_match('/(?<![\p{L}\p{N}])'.$pattern.'(?![\p{L}\p{N}])/'.$flags, $text) === 1;
    }

    /**
     * คำย่อ (ไม่มีตัวพิมพ์เล็ก) หรือคำสั้น ≤3 ตัวอักษร ต้องตรงตัวพิมพ์ — เช่น CC (อาการสำคัญ) กับ cc (มิลลิลิตร),
     * OR (ห้องผ่าตัด) ต้องไม่ไปจับคำว่า "or" ในประโยคภาษาอังกฤษ
     */
    protected function isCaseSensitive(string $term): bool
    {
        $letters = preg_replace('/[^\p{L}]/u', '', $term);

        return mb_strlen($letters) <= 3 || mb_strtolower($letters) !== $letters && mb_strtoupper($letters) === $letters;
    }

    /**
     * @return array<string, string>
     */
    protected function lookupRoots(string $text, int $limit): array
    {
        if ($limit <= 0) {
            return [];
        }

        preg_match_all('/[A-Za-z]{5,}/', $text, $matches);
        $words = array_unique(array_map('strtolower', $matches[0]));
        $roots = [];

        foreach ($this->glossary[self::SECTION_PREFIXES] ?? [] as $prefix => $meaning) {
            $stem = rtrim($prefix, '-');
            // สระเชื่อม o หายไปเมื่อตามด้วยสระ เช่น gastr + ectomy, nephr + itis
            $pattern = str_ends_with($stem, 'o')
                ? '/^(?:'.preg_quote($stem, '/').'|'.preg_quote(substr($stem, 0, -1), '/').'(?=[aeiou]))/'
                : '/^'.preg_quote($stem, '/').'/';

            foreach ($words as $word) {
                if (strlen($word) > strlen($stem) + 2 && preg_match($pattern, $word) === 1) {
                    $roots[$prefix] = $meaning;
                    break;
                }
            }
        }

        // แต่ละคำนับเฉพาะ suffix ที่ยาวที่สุดที่ตรง — gastrectomy คือ -ectomy ไม่ใช่ -tomy
        $suffixes = $this->glossary[self::SECTION_SUFFIXES] ?? [];

        foreach ($words as $word) {
            $best = null;

            foreach (array_keys($suffixes) as $suffix) {
                $stem = ltrim($suffix, '-');

                if (strlen($word) > strlen($stem) + 2 && str_ends_with($word, $stem)
                    && ($best === null || strlen($stem) > strlen(ltrim($best, '-')))) {
                    $best = $suffix;
                }
            }

            if ($best !== null) {
                $roots[$best] = $suffixes[$best];
            }
        }

        return array_slice($roots, 0, $limit, true);
    }
}
