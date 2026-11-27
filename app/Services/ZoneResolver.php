<?php

namespace App\Services;

use App\Models\Patient;

class ZoneResolver
{
    /**
     * ตรวจสอบว่าที่อยู่อยู่ในเขตรับผิดชอบหรือไม่ โดยเทียบกับ config/catchment.php
     *
     * คืนค่า null เมื่อยังตัดสินไม่ได้ (เช่น ไม่ได้กรอกตำบล ยังไม่ได้ตั้งค่าเขต หรือเขตกำหนดเป็นรายหมู่แต่ไม่พบหมู่ในที่อยู่)
     * ในกรณีนี้ต้องให้เจ้าหน้าที่เลือกเองอย่างชัดเจน — ห้ามตั้งค่าเริ่มต้นเป็น "ในเขต" เงียบๆ
     */
    public function resolve(?string $subDistrict, ?string $district = null, ?string $province = null, ?string $address = null): ?string
    {
        return $this->detect($subDistrict, $district, $province, $address)['zone'];
    }

    /**
     * ตรวจเขตพร้อมเหตุผล (ไว้แสดงให้เจ้าหน้าที่เห็นว่าทำไมระบบตัดสินแบบนี้)
     *
     * เขตตั้งได้ 2 แบบใน config/catchment.php:
     *  - in_area_sub_districts: ทั้งตำบลอยู่ในเขต
     *  - in_area_rules: ระบุตำบล (+อำเภอ/จังหวัด) และ "หมู่" ที่อยู่ในเขต (เช่น เฉพาะ ม.1, ม.2 ของตำบลหนึ่ง)
     *    — เลขหมู่อ่านจากช่องที่อยู่ ("หมู่ 1", "หมู่ที่ 1", "ม.1" รวมเลขไทย) ไม่พบหมู่ = ตัดสินไม่ได้ ต้องเลือกเอง
     * ถ้ากรอกอำเภอ/จังหวัดมาและไม่ตรงกับเขตของตำบลนั้น = นอกเขต (กันตำบลชื่อซ้ำกับที่อื่น)
     *
     * @return array{zone: ?string, reason: string}
     */
    public function detect(?string $subDistrict, ?string $district = null, ?string $province = null, ?string $address = null): array
    {
        $rules = $this->rules();

        if ($rules === []) {
            return ['zone' => null, 'reason' => 'ยังไม่ได้ตั้งค่าเขตรับผิดชอบ'];
        }

        $normSub = $this->normalizePlace((string) $subDistrict, 'ตำบล');
        if ($normSub === '') {
            return ['zone' => null, 'reason' => 'ยังไม่ได้กรอกตำบล/แขวง'];
        }

        $subLabel = trim((string) $subDistrict);
        $matching = array_values(array_filter($rules, fn (array $r) => $this->normalizePlace($r['sub_district'], 'ตำบล') === $normSub));

        if ($matching === []) {
            return ['zone' => Patient::ZONE_OUT_AREA, 'reason' => 'ตำบล "'.$subLabel.'" ไม่อยู่ในเขตรับผิดชอบ (ตรวจการสะกดอีกครั้งถ้าคิดว่าอยู่ในเขต)'];
        }

        $normProvince = $this->normalizePlace((string) $province, 'จังหวัด');
        $normDistrict = $this->normalizePlace((string) $district, 'อำเภอ');

        // อำเภอ/จังหวัดที่กรอกมาต้องตรงกับอย่างน้อยหนึ่งกฎของตำบลนี้ (ไม่ได้กรอก = ไม่เอามาตัดสิน)
        $candidates = array_values(array_filter($matching, fn (array $r) => $this->placeAccepted($r['province'], $normProvince, 'จังหวัด') && $this->placeAccepted($r['district'], $normDistrict, 'อำเภอ')));

        if ($candidates === []) {
            $where = $normProvince !== '' && ! $this->anyAccepts($matching, 'province', $normProvince, 'จังหวัด')
                ? 'จังหวัด "'.trim((string) $province).'"'
                : 'อำเภอ "'.trim((string) $district).'"';

            return ['zone' => Patient::ZONE_OUT_AREA, 'reason' => $where.' ไม่ตรงกับเขตรับผิดชอบของ ต.'.$subLabel.' (ตำบลชื่อซ้ำกับที่อื่น)'];
        }

        // กฎที่ไม่จำกัดหมู่ = ทั้งตำบลอยู่ในเขต
        foreach ($candidates as $rule) {
            if ($rule['moo'] === null) {
                return ['zone' => Patient::ZONE_IN_AREA, 'reason' => 'ตำบล "'.$subLabel.'" อยู่ในเขตรับผิดชอบ'];
            }
        }

        $allowed = collect($candidates)->pluck('moo')->flatten()->unique()->sort()->values()->all();
        $allowedText = 'ม.'.implode(', ม.', $allowed);
        $found = $this->extractMoo($address);

        if ($found === []) {
            return ['zone' => null, 'reason' => 'ต.'.$subLabel.' อยู่ในเขตเฉพาะ '.$allowedText.' แต่ไม่พบหมู่ในที่อยู่ — ระบุเป็น "หมู่ 1" หรือ "ม.1" ในช่องที่อยู่ หรือเลือกเขตเอง'];
        }

        if (count($found) > 1) {
            return ['zone' => null, 'reason' => 'พบหมู่มากกว่า 1 หมู่ในที่อยู่ ('.'ม.'.implode(', ม.', $found).') ตัดสินเขตเองไม่ได้ — ตรวจที่อยู่แล้วเลือกเขตเอง'];
        }

        return in_array($found[0], $allowed, true)
            ? ['zone' => Patient::ZONE_IN_AREA, 'reason' => 'ต.'.$subLabel.' หมู่ '.$found[0].' อยู่ในเขตรับผิดชอบ (เฉพาะ '.$allowedText.')']
            : ['zone' => Patient::ZONE_OUT_AREA, 'reason' => 'ต.'.$subLabel.' หมู่ '.$found[0].' ไม่อยู่ในเขต (เขตรับผิดชอบเฉพาะ '.$allowedText.')'];
    }

    /**
     * เลขหมู่ที่พบในที่อยู่ ("หมู่ 1", "หมู่ที่ 2", "ม.3", "ม. ๔", "moo 5") เรียงและไม่ซ้ำ
     *
     * @return array<int, int>
     */
    public function extractMoo(?string $address): array
    {
        $text = strtr((string) $address, ['๐' => '0', '๑' => '1', '๒' => '2', '๓' => '3', '๔' => '4', '๕' => '5', '๖' => '6', '๗' => '7', '๘' => '8', '๙' => '9']);

        if (! preg_match_all('/(?:หมู่ที่|หมู่|\bม\.|\bmoo\.?)\s*(\d{1,2})(?!\d)/iu', $text, $matches)) {
            return [];
        }

        $numbers = array_values(array_unique(array_map('intval', $matches[1])));
        sort($numbers);

        return $numbers;
    }

    /**
     * รวมกฎจาก in_area_sub_districts (ทั้งตำบล) และ in_area_rules (ระบุหมู่/อำเภอ/จังหวัด)
     *
     * @return array<int, array{sub_district: string, district: ?array, province: ?array, moo: ?array}>
     */
    private function rules(): array
    {
        $rules = [];

        foreach ((array) config('catchment.in_area_sub_districts', []) as $name) {
            $rules[] = ['sub_district' => (string) $name, 'district' => $this->listOf(config('catchment.in_area_districts')), 'province' => $this->listOf(config('catchment.in_area_provinces')), 'moo' => null];
        }

        foreach ((array) config('catchment.in_area_rules', []) as $rule) {
            $moo = $rule['moo'] ?? null;
            $rules[] = [
                'sub_district' => (string) ($rule['sub_district'] ?? ''),
                'district' => $this->listOf($rule['district'] ?? null),
                'province' => $this->listOf($rule['province'] ?? null),
                'moo' => $moo === null ? null : array_values(array_map('intval', (array) $moo)),
            ];
        }

        return array_values(array_filter($rules, fn (array $r) => trim($r['sub_district']) !== ''));
    }

    /** @return array<int, string>|null */
    private function listOf(mixed $value): ?array
    {
        $list = array_values(array_filter(array_map(fn ($v) => trim((string) $v), (array) $value), fn ($v) => $v !== ''));

        return $list === [] ? null : $list;
    }

    /** อำเภอ/จังหวัดที่ผู้กรอกใส่มา ยอมรับเมื่อกฎไม่จำกัด หรือผู้กรอกไม่ได้ใส่ หรือตรงกับชื่อใดชื่อหนึ่งที่กฎรับ */
    private function placeAccepted(?array $accepted, string $normInput, string $kind): bool
    {
        if ($accepted === null || $normInput === '') {
            return true;
        }

        foreach ($accepted as $name) {
            if ($this->normalizePlace($name, $kind) === $normInput) {
                return true;
            }
        }

        return false;
    }

    /** @param  array<int, array<string, mixed>>  $rules */
    private function anyAccepts(array $rules, string $key, string $normInput, string $kind): bool
    {
        foreach ($rules as $rule) {
            if ($this->placeAccepted($rule[$key], $normInput, $kind)) {
                return true;
            }
        }

        return false;
    }

    /**
     * ตัดคำนำหน้า (ต./ตำบล/แขวง/อ./อำเภอ/จ./จังหวัด) ช่องว่าง และตัวอักษรล่องหน แล้วทำเป็นตัวพิมพ์เล็ก
     */
    private function normalizePlace(string $name, string $kind): string
    {
        $name = preg_replace('/[\s\x{200B}\x{00A0}]+/u', '', trim($name)) ?? '';

        $prefixes = match ($kind) {
            'ตำบล' => ['ตำบล', 'แขวง', 'ต.'],
            'อำเภอ' => ['อำเภอ', 'อ.'],
            'จังหวัด' => ['จังหวัด', 'จ.'],
            default => [],
        };

        foreach ($prefixes as $prefix) {
            if (mb_strpos($name, $prefix) === 0 && mb_strlen($name) > mb_strlen($prefix)) {
                $name = mb_substr($name, mb_strlen($prefix));
                break;
            }
        }

        return mb_strtolower($name);
    }
}
