<script>
    (function () {
        const subDistrictInput = document.getElementById('patient_sub_district');
        const zoneSelect = document.getElementById('zone_select');
        const zoneOverride = document.getElementById('zone_override');
        const zoneHint = document.getElementById('zone_hint');

        function syncZoneLock() {
            // ไม่ใช้ disabled เพราะฟิลด์ disabled จะไม่ถูกส่งไปพร้อมฟอร์ม — ล็อกด้วย pointer-events แทน
            zoneSelect.style.pointerEvents = zoneOverride.checked ? '' : 'none';
            zoneSelect.style.background = zoneOverride.checked ? '' : 'var(--color-neutral-200)';
        }
        zoneOverride.addEventListener('change', syncZoneLock);

        subDistrictInput.addEventListener('blur', function () {
            const subDistrict = subDistrictInput.value.trim();
            if (! subDistrict) return;

            fetch('{{ route("referrals.zone-lookup") }}?sub_district=' + encodeURIComponent(subDistrict))
                .then((res) => res.json())
                .then((data) => {
                    zoneHint.textContent = data.label;
                    if (data.zone && ! zoneOverride.checked) {
                        zoneSelect.value = data.zone;
                    }
                })
                .catch(() => {
                    zoneHint.textContent = 'ตรวจสอบเขตอัตโนมัติไม่สำเร็จ กรุณาเลือกเอง';
                });
        });

        // สถานะผู้ป่วย → แสดง/ซ่อนหน่วยต้นสังกัด
        const patientStatus = document.getElementById('patient_status');
        const militaryUnitField = document.getElementById('military_unit_field');
        patientStatus.addEventListener('change', function () {
            militaryUnitField.hidden = patientStatus.value === 'civilian';
        });

        // หน่วยต้นสังกัด "อื่นๆ" → แสดงช่องระบุเอง
        const militaryUnit = document.getElementById('military_unit');
        const militaryUnitOther = document.getElementById('military_unit_other');
        militaryUnit.addEventListener('change', function () {
            militaryUnitOther.hidden = militaryUnit.value !== 'other';
        });

        // ประเภทเคส Palliative → แสดงช่อง PPS Score
        const caseTypeSelect = document.getElementById('case_type');
        const ppsField = document.getElementById('pps_field');
        function togglePpsField() {
            const opt = caseTypeSelect.options[caseTypeSelect.selectedIndex];
            ppsField.hidden = ! opt || opt.dataset.slug !== 'palliative-care';
        }
        caseTypeSelect.addEventListener('change', togglePpsField);
        togglePpsField();

        // อายุ — คำนวณอัตโนมัติจากวันเกิด
        const dobInput = document.getElementById('patient_dob');
        const ageDisplay = document.getElementById('age_display');
        dobInput.addEventListener('change', function () {
            if (! dobInput.value) { ageDisplay.value = ''; return; }
            const d = new Date(dobInput.value);
            const t = new Date();
            let age = t.getFullYear() - d.getFullYear();
            const m = t.getMonth() - d.getMonth();
            if (m < 0 || (m === 0 && t.getDate() < d.getDate())) age--;
            ageDisplay.value = age + ' ปี';
        });

        // ชิปโรคประจำตัวที่พบบ่อย — กดแล้วเติม/ตัดออกจากช่องข้อความ (toggle)
        const underlyingField = document.getElementById('underlying_disease');
        function partsOf(field) {
            return field.value.split(',').map((s) => s.trim()).filter(Boolean);
        }
        document.querySelectorAll('.disease-tag').forEach(function (btn) {
            const tag = btn.dataset.tag;
            if (partsOf(underlyingField).includes(tag)) btn.classList.add('btn-primary');
            btn.addEventListener('click', function () {
                const parts = partsOf(underlyingField);
                const idx = parts.indexOf(tag);
                if (idx === -1) {
                    parts.push(tag);
                    btn.classList.add('btn-primary');
                } else {
                    parts.splice(idx, 1);
                    btn.classList.remove('btn-primary');
                }
                underlyingField.value = parts.join(', ');
            });
        });
        document.getElementById('disease_other_btn').addEventListener('click', function () {
            if (underlyingField.value.trim() && ! /,\s*$/.test(underlyingField.value)) {
                underlyingField.value = underlyingField.value.replace(/\s*$/, '') + ', ';
            }
            underlyingField.focus();
            underlyingField.setSelectionRange(underlyingField.value.length, underlyingField.value.length);
        });

        // Clinical tracer — เลือก tracer ใดก็ได้ ต้องยกเลิก "ไม่เข้าเงื่อนไข" โดยอัตโนมัติ และกลับกัน
        const tracerNone = document.getElementById('tr_none');
        const tracerOpts = document.querySelectorAll('.tracer-opt');
        tracerOpts.forEach(function (opt) {
            opt.addEventListener('change', function () {
                if (opt.checked) tracerNone.checked = false;
            });
        });
        tracerNone.addEventListener('change', function () {
            if (tracerNone.checked) tracerOpts.forEach((opt) => opt.checked = false);
        });

        // อุปกรณ์ผู้ป่วย — เลือกอุปกรณ์ใดก็ได้ ต้องยกเลิก "ไม่มี" โดยอัตโนมัติ และกลับกัน
        const eqNone = document.getElementById('eq_none');
        const eqOpts = document.querySelectorAll('.equipment-opt');
        eqOpts.forEach(function (opt) {
            opt.addEventListener('change', function () {
                if (opt.checked) eqNone.checked = false;
            });
        });
        eqNone.addEventListener('change', function () {
            if (eqNone.checked) eqOpts.forEach((opt) => opt.checked = false);
        });
    })();
</script>
