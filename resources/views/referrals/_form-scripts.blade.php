<script>
    (function () {
        const subDistrictInput = document.getElementById('patient_sub_district');
        const districtInput = document.getElementById('patient_district');
        const provinceInput = document.getElementById('patient_province');
        const addressInput = document.getElementById('patient_address');
        const zoneSelect = document.getElementById('zone_select');
        const zoneOverride = document.getElementById('zone_override');
        const zoneHint = document.getElementById('zone_hint');
        const zoneWarn = document.getElementById('zone_warn');
        const zoneLabels = { in_area: 'ในเขต', out_area: 'นอกเขต' };
        let detectedZone = null;
        let zoneSetByDetection = false; // ค่าในช่องมาจากผลตรวจอัตโนมัติ (ไม่ใช่ที่เจ้าหน้าที่เลือกเอง)

        // ระบบตรวจเขตได้ (และไม่ได้ติ๊ก "ปรับเขตเอง") = เลือกให้และล็อกไว้ · ตรวจไม่ได้ = ปลดล็อกและต้องเลือกเอง (ไม่มีค่าเริ่มต้น "ในเขต")
        // ไม่ใช้ disabled เพราะฟิลด์ disabled จะไม่ถูกส่งไปพร้อมฟอร์ม — ล็อกด้วย pointer-events แทน
        function syncZoneLock() {
            const locked = detectedZone !== null && ! zoneOverride.checked;
            if (locked) {
                zoneSelect.value = detectedZone;
                zoneSetByDetection = true;
            } else if (detectedZone === null && zoneSetByDetection) {
                // ผลตรวจหายไป (เช่น ล้างตำบล) — ไม่ปล่อยค่าเดิมค้างไว้เงียบๆ ให้เลือกใหม่
                zoneSelect.value = '';
                zoneSetByDetection = false;
            }
            zoneSelect.style.pointerEvents = locked ? 'none' : '';
            zoneSelect.style.background = locked ? 'var(--color-neutral-200)' : '';
            const mismatch = detectedZone !== null && zoneSelect.value !== '' && zoneSelect.value !== detectedZone;
            zoneWarn.hidden = ! mismatch;
            if (mismatch) {
                zoneWarn.textContent = 'ระบบตรวจพบ "' + zoneLabels[detectedZone] + '" แต่คุณเลือก "' + zoneLabels[zoneSelect.value] + '" — ตรวจที่อยู่อีกครั้ง (เขตมีผลกับวิธีติดตาม: ในเขต=เยี่ยมบ้าน, นอกเขต=โทร)';
            }
        }
        zoneOverride.addEventListener('change', syncZoneLock);
        zoneSelect.addEventListener('change', function () { zoneSetByDetection = false; syncZoneLock(); });

        let zoneLookupSeq = 0; // ใช้ผลของคำขอล่าสุดเท่านั้น (เน็ตช้า คำตอบเก่าอาจมาถึงทีหลัง)
        function lookupZone() {
            const seq = ++zoneLookupSeq;
            const params = new URLSearchParams({
                sub_district: subDistrictInput.value.trim(),
                district: districtInput.value.trim(),
                province: provinceInput.value.trim(),
                address: addressInput.value.trim(),
            });
            fetch('{{ route("referrals.zone-lookup") }}?' + params.toString())
                .then((res) => res.json())
                .then((data) => {
                    if (seq !== zoneLookupSeq) return;
                    detectedZone = data.zone || null;
                    zoneHint.textContent = data.label + (data.reason ? ' (' + data.reason + ')' : '');
                    zoneHint.style.color = detectedZone ? '' : 'var(--color-warning)';
                    zoneHint.style.fontWeight = detectedZone ? '' : '700';
                    syncZoneLock();
                })
                .catch(() => {
                    if (seq !== zoneLookupSeq) return;
                    detectedZone = null;
                    zoneHint.textContent = 'ตรวจเขตอัตโนมัติไม่สำเร็จ — กรุณาเลือกเขตเองจากที่อยู่';
                    syncZoneLock();
                });
        }
        [subDistrictInput, districtInput, provinceInput, addressInput].forEach(function (input) {
            input.addEventListener('blur', lookupZone);
            input.addEventListener('change', lookupZone);
        });
        lookupZone();

        // เตือนเมื่อการวินิจฉัย/ข้อความมีคำที่เกี่ยวกับ clinical tracer แต่ยังไม่ได้ติ๊ก (ข้อเสนอเท่านั้น — ไม่ติ๊กให้เอง)
        const diagnosisInput = document.querySelector('input[name="diagnosis"]');
        const notesInput = document.querySelector('textarea[name="raw_notes"]');
        const tracerHint = document.getElementById('tracer_hint');
        const csrfToken = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';
        function renderTracerHint(suggestions) {
            tracerHint.innerHTML = '';
            const missing = suggestions.filter(function (s) {
                const box = document.querySelector('.tracer-opt[value="' + s.tracer + '"]');
                return box && ! box.checked;
            });
            tracerHint.hidden = missing.length === 0;
            missing.forEach(function (s) {
                const row = document.createElement('div');
                row.style.cssText = 'display:flex;flex-wrap:wrap;align-items:center;gap:8px;padding:6px 10px;border:1px solid var(--color-warning);border-radius:8px;background:var(--color-warning-tint);';
                const text = document.createElement('span');
                text.style.fontWeight = '600';
                text.textContent = 'พบคำว่า "' + s.term + '" ในการวินิจฉัย/ข้อความ — ยังไม่ได้ติ๊ก Clinical tracer: ' + s.tracer;
                const btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'btn btn-secondary btn-sm';
                btn.textContent = 'ติ๊ก ' + s.tracer + ' ให้';
                btn.addEventListener('click', function () {
                    const box = document.querySelector('.tracer-opt[value="' + s.tracer + '"]');
                    box.checked = true;
                    box.dispatchEvent(new Event('change', { bubbles: true }));
                    renderTracerHint(suggestions);
                });
                row.appendChild(text);
                row.appendChild(btn);
                tracerHint.appendChild(row);
            });
        }
        function lookupTracers() {
            fetch('{{ route("referrals.tracer-lookup") }}', {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json', 'Content-Type': 'application/json' },
                body: JSON.stringify({ diagnosis: diagnosisInput ? diagnosisInput.value : '', notes: notesInput ? notesInput.value : '' }),
            })
                .then((res) => res.json())
                .then((data) => { window.__tracerSuggestions = data.suggestions || []; renderTracerHint(window.__tracerSuggestions); })
                .catch(function () { tracerHint.hidden = true; });
        }
        [diagnosisInput, notesInput].forEach(function (el) { if (el) el.addEventListener('blur', lookupTracers); });
        document.querySelectorAll('.tracer-opt, #tr_none').forEach(function (box) {
            box.addEventListener('change', function () { renderTracerHint(window.__tracerSuggestions || []); });
        });
        lookupTracers();

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
        // ชิปประวัติผ่าตัดที่พบบ่อย (TKA/UKA) — กดแล้วเติม/ตัดออกจากช่องข้อความ (toggle) เช่นเดียวกับโรคประจำตัว
        const surgeryField = document.getElementById('surgery_history');
        document.querySelectorAll('.surgery-tag').forEach(function (btn) {
            const tag = btn.dataset.tag;
            if (partsOf(surgeryField).includes(tag)) btn.classList.add('btn-primary');
            btn.addEventListener('click', function () {
                const parts = partsOf(surgeryField);
                const idx = parts.indexOf(tag);
                if (idx === -1) {
                    parts.push(tag);
                    btn.classList.add('btn-primary');
                } else {
                    parts.splice(idx, 1);
                    btn.classList.remove('btn-primary');
                }
                surgeryField.value = parts.join(', ');
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
