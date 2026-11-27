<?php

namespace App\Services;

use App\Models\Patient;

class ZoneResolver
{
    /**
     * ตรวจสอบว่าตำบล/แขวงที่ให้มาอยู่ในเขตรับผิดชอบหรือไม่ โดยเทียบกับ config/catchment.php
     *
     * คืนค่า null เมื่อยังไม่สามารถตรวจจับได้ (เช่น ไม่ได้กรอกตำบล หรือยังไม่ได้ตั้งค่ารายชื่อตำบล
     * ในเขตไว้เลย) ในกรณีนี้ต้องให้เจ้าหน้าที่เลือกเองอย่างชัดเจน — ห้ามตั้งค่าเริ่มต้นเป็น "ในเขต" เงียบๆ
     */
    public function resolve(?string $subDistrict, ?string $district = null, ?string $province = null): ?string
    {
        return $this->detect($subDistrict, $district, $province)['zone'];
    }

    /**
     * ตรวจเขตพร้อมเหตุผล (ไว้แสดงให้เจ้าหน้าที่เห็นว่าทำไมระบบตัดสินแบบนี้)
     *
     * - จังหวัด/อำเภอ (ถ้ากรอกมา และตั้งรายชื่อ in_area_provinces / in_area_districts ไว้) ที่ไม่อยู่ในรายการ = นอกเขตทันที
     *   กันตำบลชื่อซ้ำกับจังหวัด/อำเภออื่น (เช่น "ในเมือง", "บางแก้ว" มีหลายแห่ง)
     * - ตำบลเทียบแบบไม่สนคำนำหน้า (ต./ตำบล/แขวง) ช่องว่าง และตัวพิมพ์
     *
     * @return array{zone: ?string, reason: string}
     */
    public function detect(?string $subDistrict, ?string $district = null, ?string $province = null): array
    {
        $inAreaList = config('catchment.in_area_sub_districts', []);

        if (empty($inAreaList)) {
            return ['zone' => null, 'reason' => 'ยังไม่ได้ตั้งค่ารายชื่อตำบลในเขตรับผิดชอบ'];
        }

        $provinceList = array_filter(array_map(fn ($n) => $this->normalizePlace($n, 'จังหวัด'), (array) config('catchment.in_area_provinces', [])));
        $districtList = array_filter(array_map(fn ($n) => $this->normalizePlace($n, 'อำเภอ'), (array) config('catchment.in_area_districts', [])));

        $normProvince = $this->normalizePlace((string) $province, 'จังหวัด');
        if ($provinceList !== [] && $normProvince !== '' && ! in_array($normProvince, $provinceList, true)) {
            return ['zone' => Patient::ZONE_OUT_AREA, 'reason' => 'จังหวัด "'.trim((string) $province).'" ไม่อยู่ในเขตรับผิดชอบ'];
        }

        $normDistrict = $this->normalizePlace((string) $district, 'อำเภอ');
        if ($districtList !== [] && $normDistrict !== '' && ! in_array($normDistrict, $districtList, true)) {
            return ['zone' => Patient::ZONE_OUT_AREA, 'reason' => 'อำเภอ "'.trim((string) $district).'" ไม่อยู่ในเขตรับผิดชอบ'];
        }

        $normSub = $this->normalizePlace((string) $subDistrict, 'ตำบล');
        if ($normSub === '') {
            return ['zone' => null, 'reason' => 'ยังไม่ได้กรอกตำบล/แขวง'];
        }

        $normalizedList = array_map(fn ($name) => $this->normalizePlace($name, 'ตำบล'), $inAreaList);

        return in_array($normSub, $normalizedList, true)
            ? ['zone' => Patient::ZONE_IN_AREA, 'reason' => 'ตำบล "'.trim((string) $subDistrict).'" อยู่ในเขตรับผิดชอบ']
            : ['zone' => Patient::ZONE_OUT_AREA, 'reason' => 'ตำบล "'.trim((string) $subDistrict).'" ไม่อยู่ในรายชื่อตำบลในเขต (ตรวจการสะกดอีกครั้งถ้าคิดว่าอยู่ในเขต)'];
    }

    /**
     * ตัดคำนำหน้า (ต./ตำบล/แขวง/อ./อำเภอ/จ./จังหวัด) ช่องว่าง และตัวอักษรล่องหน แล้วทำเป็นตัวพิมพ์เล็ก
     */
    private function normalizePlace(string $name, string $kind): string
    {
        $name = preg_replace('/[\s\x{200B}\x{00A0}]+/u', '', trim($name)) ?? '';

        $prefixes = match ($kind) {
            'ตำบล' => ['ตำบล', 'แขวง', 'ต.', 'ต'],
            'อำเภอ' => ['อำเภอ', 'เขต', 'อ.'],
            'จังหวัด' => ['จังหวัด', 'จ.'],
            default => [],
        };

        foreach ($prefixes as $prefix) {
            // "ต" เดี่ยวๆ ตัดเฉพาะเมื่อมี "." กำกับเท่านั้น ไม่งั้นจะตัดตัวอักษรแรกของชื่อตำบลที่ขึ้นต้นด้วย ต
            if ($prefix === 'ต' || $prefix === 'เขต') {
                continue;
            }

            if (mb_strpos($name, $prefix) === 0 && mb_strlen($name) > mb_strlen($prefix)) {
                $name = mb_substr($name, mb_strlen($prefix));
                break;
            }
        }

        return mb_strtolower($name);
    }
}
