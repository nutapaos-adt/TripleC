<?php

namespace App\Services;

/**
 * เสนอ Clinical tracer จากข้อความการวินิจฉัย/บันทึกอาการ — เป็นเพียง "ข้อเสนอ" ให้เจ้าหน้าที่เห็นว่าอาจลืมติ๊ก
 * ไม่ได้ติ๊กให้เอง (human-in-the-loop: ผู้กรอกและพยาบาลที่รับเคสเป็นผู้ยืนยัน tracer เสมอ)
 *
 * ใช้คำค้นแบบตรงตัว (ไม่ใช่ AI) เพื่อให้ผลคงที่และตรวจสอบได้ ตัวย่อภาษาอังกฤษสั้นๆ (CAP/HAP/VAP/STEMI) ต้องเป็นตัวพิมพ์ใหญ่และคั่นด้วยขอบคำ
 * ถ้าข้อความมีคำปฏิเสธนำหน้า (no/not/ไม่/ปฏิเสธ) จะไม่เสนอ — แต่ "r/o" (สงสัย) ยังเสนอ เพราะเคสสงสัยก็ควรถูกติดตามตาม tracer
 */
class ClinicalTracerSuggester
{
    /** ชื่อ tracer ต้องตรงกับค่าในฟอร์ม (referrals/_form-fields.blade.php) และรายงานรายเดือน */
    public const TRACERS = ['Sepsis', 'Stroke', 'Heat stroke', 'STEMI', 'Pneumonia'];

    /**
     * คำค้นต่อ tracer: ['t' => คำ, 'cs' => true ถ้าต้องตรงตัวพิมพ์ (ตัวย่อ), 'w' => true ถ้าต้องมีขอบคำ (ตัวอักษรละตินล้วน), 'not_after' => regex ของข้อความก่อนหน้าที่ทำให้ไม่นับ (เช่น "heat stroke" ไม่ใช่ Stroke)]
     *
     * @var array<string, array<int, array{t: string, cs?: bool, w?: bool, not_after?: string}>>
     */
    private const TERMS = [
        'Sepsis' => [
            ['t' => 'sepsis', 'w' => true], ['t' => 'septic', 'w' => true], ['t' => 'septicemia', 'w' => true], ['t' => 'septicaemia', 'w' => true],
            ['t' => 'bacteremia', 'w' => true], ['t' => 'ติดเชื้อในกระแสเลือด'], ['t' => 'ภาวะติดเชื้อรุนแรง'], ['t' => 'ช็อกจากการติดเชื้อ'],
        ],
        'Stroke' => [
            ['t' => 'stroke', 'w' => true, 'not_after' => '/heat[\s-]*$/i'], ['t' => 'cva', 'w' => true], ['t' => 'cerebrovascular'], ['t' => 'cerebral infarct'], ['t' => 'cerebral infarction'],
            ['t' => 'intracerebral'], ['t' => 'ischemic stroke'], ['t' => 'หลอดเลือดสมอง'], ['t' => 'สมองขาดเลือด'], ['t' => 'อัมพฤกษ์'], ['t' => 'อัมพาตครึ่งซีก'],
        ],
        'Heat stroke' => [
            ['t' => 'heat stroke'], ['t' => 'heatstroke'], ['t' => 'heat-stroke'], ['t' => 'ลมแดด'], ['t' => 'โรคลมร้อน'],
        ],
        'STEMI' => [
            ['t' => 'stemi', 'w' => true], ['t' => 'st elevation'], ['t' => 'st-elevation'], ['t' => 'st elevated'],
            ['t' => 'กล้ามเนื้อหัวใจตายเฉียบพลัน'], ['t' => 'กล้ามเนื้อหัวใจขาดเลือดเฉียบพลัน'], ['t' => 'acute myocardial infarction'],
        ],
        'Pneumonia' => [
            ['t' => 'pneumonia', 'w' => true], ['t' => 'pneumonitis', 'w' => true], ['t' => 'ปอดอักเสบ'], ['t' => 'ปอดบวม'], ['t' => 'ปอดติดเชื้อ'],
            ['t' => 'CAP', 'cs' => true, 'w' => true], ['t' => 'HAP', 'cs' => true, 'w' => true], ['t' => 'VAP', 'cs' => true, 'w' => true],
        ],
    ];

    /** คำปฏิเสธที่นำหน้าคำค้นในระยะสั้น (ไม่ใช่ตัวย่อ "r/o" ซึ่งแปลว่าสงสัย) */
    private const NEGATION = '/(?:\bno|\bnot|\bwithout|\bdenies|ไม่(?:พบ|มี|เป็น|ใช่)?|ปฏิเสธ)\s*(?:evidence\s+of\s+|history\s+of\s+|hx\s+of\s+|การ)?\s*$/iu';

    /**
     * @param  string|null  ...$texts  ข้อความที่ต้องตรวจ (การวินิจฉัย, บันทึกอาการ, โรคประจำตัว ฯลฯ)
     * @return array<string, string>  [ชื่อ tracer => คำที่พบ] เรียงตามลำดับ tracer ในฟอร์ม
     */
    public function suggest(?string ...$texts): array
    {
        $haystack = trim(implode("\n", array_filter($texts, fn ($t) => $t !== null && trim($t) !== '')));

        if ($haystack === '') {
            return [];
        }

        $found = [];

        foreach (self::TERMS as $tracer => $terms) {
            foreach ($terms as $term) {
                $pattern = '/'.($term['w'] ?? false ? '\b' : '').preg_quote($term['t'], '/').($term['w'] ?? false ? '\b' : '').'/u'.(($term['cs'] ?? false) ? '' : 'i');

                if (! preg_match_all($pattern, $haystack, $matches, PREG_OFFSET_CAPTURE)) {
                    continue;
                }

                foreach ($matches[0] as [$word, $offset]) {
                    $before = mb_strcut($haystack, max(0, $offset - 30), min(30, $offset));

                    if (isset($term['not_after']) && preg_match($term['not_after'], $before)) {
                        continue;
                    }

                    if (! preg_match(self::NEGATION, $before)) {
                        $found[$tracer] = $word;
                        break 2;
                    }
                }
            }
        }

        return $found;
    }
}
