<?php

namespace App\Services;

use App\Models\Patient;

class ZoneResolver
{
    /**
     * ตรวจสอบว่าตำบล/แขวงที่ให้มาอยู่ในเขตรับผิดชอบหรือไม่ โดยเทียบกับ config/catchment.php
     *
     * คืนค่า null เมื่อยังไม่สามารถตรวจจับได้ (เช่น ไม่ได้กรอกตำบล หรือยังไม่ได้ตั้งค่ารายชื่อตำบล
     * ในเขตไว้เลย) ในกรณีนี้ควรใช้ค่าที่เจ้าหน้าที่เลือกเองแทน
     */
    public function resolve(?string $subDistrict): ?string
    {
        $subDistrict = trim((string) $subDistrict);
        $inAreaList = config('catchment.in_area_sub_districts', []);

        if ($subDistrict === '' || empty($inAreaList)) {
            return null;
        }

        $normalizedList = array_map(fn ($name) => mb_strtolower(trim($name)), $inAreaList);

        return in_array(mb_strtolower($subDistrict), $normalizedList, true)
            ? Patient::ZONE_IN_AREA
            : Patient::ZONE_OUT_AREA;
    }
}
