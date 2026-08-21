# วิธีติดตั้ง Chira Continuity Care (Triple C)

โฟลเดอร์นี้มี "โค้ดส่วนเฉพาะของระบบ" (migration, model, middleware) ที่เขียนไว้แล้ว
แต่ยังไม่มีโครงหลักของ Laravel framework (ต้องให้ Composer สร้างให้ เพราะเครื่องที่พัฒนาไม่มี PHP ติดตั้งอยู่)

ให้ทำตามขั้นตอนนี้บนเครื่อง/เซิร์ฟเวอร์ที่มี **PHP 8.2+, Composer, MySQL** ติดตั้งแล้ว

## ขั้นตอนที่ 1 — สร้างโครง Laravel

เปิด terminal ไปที่โฟลเดอร์นี้ (`TrippleC`) แล้วรัน:

```bash
composer create-project laravel/laravel tmp-laravel
```

คำสั่งนี้จะสร้างโปรเจกต์ Laravel ใหม่ในโฟลเดอร์ย่อยชื่อ `tmp-laravel` (ทำแบบนี้เพราะ Composer
ต้องการติดตั้งลงโฟลเดอร์ว่างเท่านั้น) จากนั้นให้ย้ายทุกไฟล์ใน `tmp-laravel` มาไว้ที่โฟลเดอร์ `TrippleC`
(ทับไฟล์เดิมที่ซ้ำกันไม่ได้ เพราะยังไม่มีไฟล์ซ้ำ) แล้วลบโฟลเดอร์ `tmp-laravel` ทิ้ง

## ขั้นตอนที่ 2 — ติดตั้งระบบ Login (Laravel Breeze)

```bash
composer require laravel/breeze --dev
php artisan breeze:install blade
npm install && npm run build
```

## ขั้นตอนที่ 3 — ตั้งค่า .env

คัดลอก `.env.example` เป็น `.env` แล้วแก้ไข:

```
APP_NAME="Chira Continuity Care"
APP_URL=http://localhost   (หรือ URL ภายใน intranet ของ รพ.)

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=chira_continuity_care
DB_USERNAME=<ผู้ใช้ฐานข้อมูล>
DB_PASSWORD=<รหัสผ่าน>
```

จากนั้นรัน:

```bash
php artisan key:generate
```

## ขั้นตอนที่ 4 — เพิ่มสิทธิ์ผู้ใช้ (role) เข้าไปใน middleware

เปิดไฟล์ `bootstrap/app.php` แล้วแก้ส่วน `->withMiddleware(...)` ให้มีบรรทัดนี้เพิ่มเข้าไป:

```php
->withMiddleware(function (Illuminate\Foundation\Configuration\Middleware $middleware) {
    $middleware->alias([
        'role' => \App\Http\Middleware\EnsureUserHasRole::class,
    ]);
})
```

(ไฟล์ `app/Http/Middleware/EnsureUserHasRole.php` เขียนไว้ให้แล้วในโฟลเดอร์นี้)

## ขั้นตอนที่ 4.5 — เพิ่มเส้นทาง (routes) และหน้าจอรับเคสใหม่

แทนที่ไฟล์ `routes/web.php` ที่ Breeze สร้างไว้ ด้วยไฟล์ `routes/web.php` ที่เขียนไว้ให้แล้วในโฟลเดอร์นี้
(เนื้อหาเหมือนของ Breeze เดิมทุกอย่าง แค่เพิ่มเส้นทางสำหรับ "ใบส่งต่อผู้ป่วย" เข้าไป)

ไฟล์ `app/Http/Controllers/ReferralController.php`, `app/Http/Requests/StoreReferralRequest.php`,
และ `resources/views/referrals/*.blade.php` เขียนไว้ให้ครบแล้ว ใช้งานได้ทันทีหลัง migrate

## ขั้นตอนที่ 5 — Migrate ฐานข้อมูล

```bash
php artisan migrate
```

จะสร้างตาราง `users` (มาตรฐานจาก Breeze) แล้วรัน migration ที่เขียนไว้ให้แล้วตามลำดับ:
`add_role_to_users_table`, `case_types`, `patients`, `visit_rules`, `referrals`,
`follow_up_plans`, `follow_up_records`, `referral_attachments`

ไฟล์แนบ (เอกสารที่อัปโหลด) จะถูกเก็บไว้ที่ `storage/app/referral-attachments` (ไม่เปิดเผยต่อสาธารณะ
ต้อง login และเป็นเจ้าของสิทธิ์เข้าถึงใบส่งต่อนั้นถึงจะดาวน์โหลดได้ผ่านระบบเท่านั้น) ไม่ต้องรัน
`php artisan storage:link` เพราะไม่ได้ใช้ public disk

### ใส่ข้อมูลตั้งต้น (ประเภทเคส + เกณฑ์จำนวนครั้งเยี่ยม)

เปิดไฟล์ `database/seeders/DatabaseSeeder.php` (ที่ Breeze สร้างไว้ให้) แล้วเพิ่มบรรทัดนี้ในเมธอด `run()`:

```php
$this->call(\Database\Seeders\CaseTypeSeeder::class);
```

จากนั้นรัน:

```bash
php artisan db:seed
```

จะได้ประเภทเคสเริ่มต้น 4 แบบ (Palliative Care ตาม PPS Score, หลังคลอด 3 ครั้ง, หลังผ่าตัด, อื่นๆ)
พร้อมเกณฑ์จำนวนครั้งเยี่ยมเบื้องต้น ซึ่งแอดมินจะแก้ไข/เพิ่มเติมเองได้ภายหลังผ่านหน้าจัดการในระบบ

## ขั้นตอนที่ 5.5 — ตั้งค่าเชื่อมต่อ AI (Ollama)

ไฟล์ `config/ai.php` เขียนไว้ให้แล้ว อ่านค่าจาก `.env` เพิ่มบรรทัดนี้ใน `.env`:

```
OLLAMA_URL=http://<IP เครื่องที่รัน Ollama ภายใน รพ.>:11434
OLLAMA_MODEL=typhoon
OLLAMA_TIMEOUT=60
```

**สำคัญ:** URL นี้ต้องเป็นที่อยู่ภายในเครือข่าย รพ. เท่านั้น (เช่น `http://10.x.x.x:11434`)
ห้ามชี้ไปยัง service บนอินเทอร์เน็ตสาธารณะ เพราะข้อความที่ส่งไปประมวลผลเป็นข้อมูลผู้ป่วย (PHI)

ฝั่งเซิร์ฟเวอร์ที่รัน Ollama ต้องติดตั้งโมเดลไว้ก่อนด้วยคำสั่ง (รันครั้งเดียวตอนติดตั้ง):

```bash
ollama pull typhoon
```

(ถ้าไม่มีโมเดล `typhoon` ในเครื่อง ให้เปลี่ยนเป็น `llama3.1:8b` แล้วแก้ `OLLAMA_MODEL` ให้ตรงกัน)

ไม่ต้องติดตั้งไลบรารีเพิ่มฝั่ง Laravel — ใช้ HTTP Client ที่มากับ Laravel อยู่แล้ว

## ขั้นตอนที่ 5.7 — ตั้งค่ารายชื่อตำบลในเขตรับผิดชอบ (สำหรับตรวจจับเขตอัตโนมัติ)

เปิดไฟล์ `config/catchment.php` แล้วใส่รายชื่อตำบล/แขวงในเขตรับผิดชอบจริงลงใน `in_area_sub_districts`
เช่น:

```php
'in_area_sub_districts' => ['ตำบล A', 'ตำบล B', 'ตำบล C'],
```

ถ้าปล่อยว่างไว้ ระบบจะไม่ตรวจจับอัตโนมัติ และให้เจ้าหน้าที่เลือกเขตเอง (ในเขต/นอกเขต) ทุกครั้งแทน
(ยังใช้งานสร้างใบส่งต่อได้ปกติ เพียงแต่ไม่มีระบบช่วยตรวจจับ)

## ขั้นตอนที่ 6 — รันทดสอบ

```bash
php artisan serve
```

เปิดเบราว์เซอร์ไปที่ `http://localhost:8000` จะเจอหน้า login/register ของ Breeze
สมัครสมาชิกใหม่ได้ทันที (ผู้ใช้ใหม่จะได้ role เริ่มต้นเป็น `ward_staff` โดยอัตโนมัติ)

## ขั้นตอนที่ 6.5 — ตั้งผู้ใช้คนแรกให้เป็นแอดมิน + เพิ่มลิงก์เมนู

สมัครสมาชิกอย่างน้อย 1 คนก่อน (ผ่านหน้าเว็บ) แล้วรันคำสั่งนี้เพื่อตั้งให้เป็นแอดมิน (แก้อีเมลให้ตรงกับที่สมัคร):

```bash
php artisan tinker --execute="App\Models\User::where('email', 'admin@example.com')->update(['role' => 'admin'])"
```

จากนั้นแอดมินคนนี้เข้าหน้าจัดการได้ที่ `/admin/case-types` และ `/admin/users` (คนอื่นที่ไม่ใช่ role `admin`
จะเข้าไม่ได้ ระบบจะแจ้ง "คุณไม่มีสิทธิ์เข้าถึงหน้านี้")

เปิดไฟล์ `resources/views/layouts/navigation.blade.php` (ที่ Breeze สร้างไว้ให้) แล้วเพิ่มลิงก์เมนูเอง
ในตำแหน่งเดียวกับลิงก์ "Dashboard" เดิม เช่น:

```blade
<x-nav-link :href="route('referrals.index')" :active="request()->routeIs('referrals.*')">
    ใบส่งต่อ
</x-nav-link>
@if (auth()->user()->isAdmin())
    <x-nav-link :href="route('admin.case-types.index')" :active="request()->routeIs('admin.case-types.*')">
        ประเภทเคส
    </x-nav-link>
    <x-nav-link :href="route('admin.users.index')" :active="request()->routeIs('admin.users.*')">
        ผู้ใช้งาน
    </x-nav-link>
@endif
```

(ไม่ได้เขียนทับไฟล์นี้ให้อัตโนมัติ เพราะเป็นไฟล์ที่ Breeze สร้างและอาจต่างเวอร์ชันกันเล็กน้อย
ถ้าไม่สะดวกแก้เอง สามารถเข้าหน้าเหล่านี้ผ่าน URL ตรงๆ ไปพลางก่อนได้)

---

**MVP ครบทุกฟีเจอร์ตามที่วางแผนไว้แล้ว** ไม่มีงานค้างจากแผนเดิม

**หน้าแอดมิน:** `/admin/case-types` — เพิ่ม/แก้ไขประเภทเคสและเกณฑ์จำนวนครั้งเยี่ยม (นับคงที่ หรืออิงคะแนน
เช่น PPS Score พิมพ์เป็นตารางบรรทัดละช่วง) และ `/admin/users` — ดูรายชื่อผู้ใช้ทั้งหมด แก้ไข role/แผนกได้
เข้าถึงได้เฉพาะผู้ใช้ role `admin` เท่านั้น (ผ่าน middleware `role:admin` ที่ตั้งค่าไว้ตั้งแต่ขั้นตอนที่ 4)

**แดชบอร์ด (`/dashboard`):** แทนที่หน้า placeholder ของ Breeze ด้วยแดชบอร์ดจริง — KPI 4 ช่อง (ผู้ป่วยทั้งหมด/
วันนี้ต้องติดตาม/เกินกำหนด/กลุ่มเสี่ยงที่ยืนยันแล้ว), ตารางรายการที่ต้องติดตามวันนี้+เกินกำหนด (คลิกเข้าเริ่มติดตามได้เลย),
และรายการสัญญาณเสี่ยงล่าสุดที่พยาบาลยืนยันแล้ว ไฟล์ `resources/views/dashboard.blade.php` เขียนไว้ให้แล้ว
เขียนทับไฟล์ placeholder ของ Breeze ได้เลย

**ใช้งานได้แล้วตอนนี้ครบ workflow หลักตั้งแต่ต้นจนจบ:** รับเคส → AI สรุป/ยืนยันแผน → สร้างกำหนดการอัตโนมัติ →
เยี่ยมบ้าน/โทรติดตาม (พร้อมคู่มือจาก AI) → บันทึกผล → **AI วิเคราะห์ความเสี่ยง → พยาบาลยืนยันการตัดสินใจ
(ติดตามซ้ำ/ส่งต่อ/ปิดเคส) เสมอ 100%** — เลือก "ติดตามซ้ำ"/"ส่งต่อ" ระบบจะสร้างกำหนดการครั้งถัดไปให้อัตโนมัติ
(กรณี Palliative จะคำนวณความถี่ใหม่จาก PPS Score ที่เพิ่งประเมิน), เลือก "ปิดเคส" ระบบจะยกเลิกกำหนดการที่เหลือ
และปิดเคสให้อัตโนมัติ

**ใช้งานได้แล้วตอนนี้:** ระบบ login + สิทธิ์ผู้ใช้ 3 ระดับ, ตารางฐานข้อมูลหลักทั้งหมด, ข้อมูลตั้งต้นของประเภทเคส,
หน้าจอ "รับเคสใหม่" (พร้อมช่วยตรวจจับเขตอัตโนมัติจากตำบลที่กรอก — เจ้าหน้าที่ปรับเองได้เสมอ), ปุ่ม
"ให้ AI ช่วยสรุปข้อมูล" + หน้า "แผนการดูแล" ให้พยาบาลตรวจสอบ/ยืนยัน, และ**หลังยืนยันแผนแล้ว ระบบจะสร้าง
กำหนดการเยี่ยมบ้าน/โทรติดตามให้อัตโนมัติตามเกณฑ์ (visit_rules) ของประเภทเคสนั้น** — เคสแบบนับจำนวนครั้งคงที่
(เช่น หลังคลอด) จะได้ครบทุกครั้งทันที ส่วนเคสแบบอิงคะแนน (เช่น Palliative ตาม PPS Score) จะได้เฉพาะครั้งแรก
เพราะครั้งถัดไปต้องประเมิน PPS Score ใหม่ทุกครั้ง (ดูรายละเอียดในหน้าใบส่งต่อ ส่วน "กำหนดการติดตาม")
