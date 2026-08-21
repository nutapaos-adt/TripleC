<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">รับเคสใหม่ — สร้างใบส่งต่อผู้ป่วย</h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">

                @if ($errors->any())
                    <div class="mb-4 p-4 rounded bg-red-50 text-red-700 text-sm">
                        <p class="font-semibold mb-1">กรุณาตรวจสอบข้อมูลต่อไปนี้:</p>
                        <ul class="list-disc list-inside">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <form method="POST" action="{{ route('referrals.store') }}" enctype="multipart/form-data" class="space-y-6">
                    @csrf

                    <div>
                        <h3 class="text-sm font-semibold text-gray-600 uppercase tracking-wide mb-3">ข้อมูลผู้ป่วยและแหล่งที่มา</h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700">แหล่งข้อมูล</label>
                                <select name="source_type" class="mt-1 block w-full rounded-md border-gray-300">
                                    <option value="ward" @selected(old('source_type') === 'ward')>หอผู้ป่วย</option>
                                    <option value="opd" @selected(old('source_type') === 'opd')>OPD</option>
                                    <option value="internal_dept" @selected(old('source_type') === 'internal_dept')>หน่วยงานภายในโรงพยาบาล</option>
                                    <option value="external_hospital" @selected(old('source_type') === 'external_hospital')>โรงพยาบาลอื่น</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">รายละเอียดแหล่งที่มา <span class="text-gray-400">(เช่น ชื่อหอผู้ป่วย/แผนก)</span></label>
                                <input type="text" name="source_detail" value="{{ old('source_detail') }}" class="mt-1 block w-full rounded-md border-gray-300">
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700">ประเภทเคสเบื้องต้น</label>
                                <select name="case_type_id" class="mt-1 block w-full rounded-md border-gray-300">
                                    <option value="">ให้ AI ประเมินจากข้อมูลด้านล่าง</option>
                                    @foreach ($caseTypes as $caseType)
                                        <option value="{{ $caseType->id }}" @selected((string) old('case_type_id') === (string) $caseType->id)>{{ $caseType->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">เขตพื้นที่</label>
                                <select name="zone" id="zone_select" class="mt-1 block w-full rounded-md border-gray-300" @disabled(! old('zone_override'))>
                                    <option value="in_area" @selected(old('zone', 'in_area') === 'in_area')>ในเขต</option>
                                    <option value="out_area" @selected(old('zone') === 'out_area')>นอกเขต</option>
                                </select>
                                <p id="zone_hint" class="text-xs text-gray-400 mt-1">กรอกตำบล/แขวงด้านล่างเพื่อให้ระบบช่วยตรวจจับเขต</p>
                                <label class="inline-flex items-center gap-2 mt-2 text-xs text-gray-500">
                                    <input type="checkbox" name="zone_override" value="1" id="zone_override" @checked(old('zone_override'))>
                                    ปรับเขตเอง (ไม่ใช้ผลตรวจจับอัตโนมัติ)
                                </label>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700">HN</label>
                                <input type="text" name="patient_hn" value="{{ old('patient_hn') }}" required class="mt-1 block w-full rounded-md border-gray-300">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">ชื่อ-สกุลผู้ป่วย</label>
                                <input type="text" name="patient_name" value="{{ old('patient_name') }}" required class="mt-1 block w-full rounded-md border-gray-300">
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700">เลขบัตรประชาชน</label>
                                <input type="text" name="patient_national_id" value="{{ old('patient_national_id') }}" class="mt-1 block w-full rounded-md border-gray-300">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">วันเกิด</label>
                                <input type="date" name="patient_dob" value="{{ old('patient_dob') }}" class="mt-1 block w-full rounded-md border-gray-300">
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700">เบอร์โทร</label>
                                <input type="text" name="patient_phone" value="{{ old('patient_phone') }}" class="mt-1 block w-full rounded-md border-gray-300">
                            </div>
                            <div></div>

                            <div class="md:col-span-2">
                                <label class="block text-sm font-medium text-gray-700">ที่อยู่</label>
                                <input type="text" name="patient_address" value="{{ old('patient_address') }}" class="mt-1 block w-full rounded-md border-gray-300">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">ตำบล/แขวง</label>
                                <input type="text" name="patient_sub_district" id="patient_sub_district" value="{{ old('patient_sub_district') }}" class="mt-1 block w-full rounded-md border-gray-300">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">อำเภอ/เขต</label>
                                <input type="text" name="patient_district" value="{{ old('patient_district') }}" class="mt-1 block w-full rounded-md border-gray-300">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">จังหวัด</label>
                                <input type="text" name="patient_province" value="{{ old('patient_province') }}" class="mt-1 block w-full rounded-md border-gray-300">
                            </div>
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700">
                            ข้อความสรุปอาการ / สถานการณ์ผู้ป่วย
                            <span class="text-gray-400 font-normal">— พิมพ์เป็นข้อความอิสระ AI จะช่วยอ่านสรุปในขั้นถัดไป</span>
                        </label>
                        <textarea name="raw_notes" rows="5" required class="mt-1 block w-full rounded-md border-gray-300">{{ old('raw_notes') }}</textarea>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700">เอกสารแนบ <span class="text-gray-400 font-normal">(ใช้เปิดดูอ้างอิงเท่านั้น — PDF/JPG/PNG ไม่เกิน 10MB ต่อไฟล์)</span></label>
                        <input type="file" name="attachments[]" multiple class="mt-1 block w-full">
                    </div>

                    <div class="flex items-center gap-3">
                        <button type="submit" class="inline-flex items-center px-4 py-2 bg-teal-700 text-white rounded-md text-sm font-semibold hover:bg-teal-800">
                            บันทึกใบส่งต่อ
                        </button>
                        <a href="{{ route('referrals.index') }}" class="text-sm text-gray-500 hover:text-gray-700">ยกเลิก</a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        (function () {
            const subDistrictInput = document.getElementById('patient_sub_district');
            const zoneSelect = document.getElementById('zone_select');
            const zoneOverride = document.getElementById('zone_override');
            const zoneHint = document.getElementById('zone_hint');

            zoneOverride.addEventListener('change', function () {
                zoneSelect.disabled = ! zoneOverride.checked;
            });

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
        })();
    </script>
</x-app-layout>
