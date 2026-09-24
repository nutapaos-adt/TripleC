<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Medical Glossary (อภิธานศัพท์ทางการแพทย์สำหรับ prompt ของ AiService)
    |--------------------------------------------------------------------------
    |
    | เรียบเรียงจากเอกสาร PNC1110 บทที่ 1 คำศัพท์ทางการแพทย์ (วิทยาลัยพยาบาลและสุขภาพ
    | ม.ราชภัฏสวนสุนันทา) โดยแก้คำที่ต้นฉบับพิมพ์ผิดแล้ว (เช่น Dengue, Benign Prostatic
    | Hyperplasia, EDH = เหนือเยื่อดูรา, hora somni, pro re nata)
    | และ "ศัพท์ทางการแพทย์ คำศัพท์ที่ควรรู้ งานกู้ภัย-กู้ชีพ" (hamclub.wu.ac.th, 2017)
    | เฉพาะหมวดการประเมิน อาการ การบาดเจ็บ และศัพท์อุปกรณ์เฉพาะใน รพ.
    | (แก้ Contusion, Alert, Abortion, Artery, Oximeter, CPR แล้ว)
    |
    | หมวดตั้งแต่ 'continuity_care' ลงไป (และคำเพิ่มใน diagnosis/ambiguous ที่มีหมายเหตุ)
    | เรียบเรียงเพิ่มจากความรู้มาตรฐานทางการพยาบาล ไม่ได้มาจากเอกสารอ้างอิงข้างต้น
    | ให้ตรงกับประเภทเคส 8 ประเภทและการจำแนกกลุ่มความรุนแรง กลุ่ม 1–4 ตาม prototype
    | (prototypes/v1-full-coc-flow/referral-create.html) — CaseTypeSeeder ยังเป็นรายการเดิม
    | — ควรให้พยาบาลทีม COC ตรวจทานก่อนใช้งานจริง โดยเฉพาะเกณฑ์คะแนน
    |
    | วิธีใช้ที่ตั้งใจไว้: ให้ AiService เลือกเฉพาะคำที่ปรากฏในข้อความของเคสนั้นแนบไปใน prompt
    | ไม่ต้องส่งทั้งไฟล์ทุกครั้ง (โมเดล local มี context จำกัด) — การจับคำต้องแยกตัวพิมพ์
    | ใหญ่/เล็ก เพราะบางคำความหมายต่างกัน (CC = อาการสำคัญ, cc = มิลลิลิตร)
    |
    | กติกาที่ต้องบอกโมเดลใน prompt:
    | - ขยายคำย่อแล้วให้คงคำย่อเดิมไว้ในวงเล็บเสมอ เช่น "โรคประจำตัว (U/D)"
    | - คำใน 'ambiguous' ให้ตีความจากบริบท ถ้าไม่ชัดให้ระบุว่า "ไม่แน่ใจ" ห้ามเดา
    |   ผลทั้งหมดยังเป็นแค่ร่าง — พยาบาลต้องตรวจสอบ/ยืนยันเสมอ (DESIGN.md §4.1)
    |
    */

    // คำย่อในการซักประวัติ / บันทึกทางการแพทย์
    'history_abbreviations' => [
        'CC' => 'อาการสำคัญที่มาโรงพยาบาล (Chief Complaint)',
        'PI' => 'ประวัติการเจ็บป่วยปัจจุบัน (Present Illness)',
        'HPI' => 'ประวัติการเจ็บป่วยปัจจุบัน (History of Present Illness)',
        'PH' => 'ประวัติการเจ็บป่วยในอดีต (Past History)',
        'PMH' => 'ประวัติการรักษา/ผ่าตัด/เจ็บป่วยในอดีต (Past Medical History)',
        'FH' => 'ประวัติโรคในครอบครัว (Family History)',
        'SH' => 'ประวัติทางสังคม อาชีพ (Social History)',
        'ROS' => 'การทบทวนอาการตามระบบอวัยวะ (Review of Systems)',
        'U/D' => 'โรคประจำตัว (Underlying Disease)',
        'HEENT' => 'ศีรษะ หู ตา จมูก คอ (Head, Ear, Eye, Nose, Throat)',
        'Abd' => 'ช่องท้อง (Abdomen)',
        'Ext' => 'แขนขา / รยางค์ (Extremity)',
        'IMP' => 'การวินิจฉัยแรกรับ (Impression)',
        'Dx' => 'การวินิจฉัยโรค (Diagnosis)',
        'Ddx' => 'การวินิจฉัยแยกโรค (Differential Diagnosis)',
        'Rx' => 'ใบสั่งยา / การสั่งการรักษา (Prescription)',
        'Tx' => 'การรักษา (Treatment)',
        'V/S' => 'สัญญาณชีพ (Vital Signs)',
        'BP' => 'ความดันโลหิต (Blood Pressure)',
        'PR' => 'อัตราชีพจร (Pulse Rate)',
        'T' => 'อุณหภูมิร่างกาย (Temperature)',
        'BW' => 'น้ำหนักตัว (Body Weight)',
        'IVF' => 'สารน้ำทางหลอดเลือดดำ (Intravenous Fluid)',
        'I/O' => 'ปริมาณน้ำเข้า-ออกต่อวัน (Intake/Output)',
        'GCS' => 'คะแนนประเมินระดับความรู้สึกตัว (Glasgow Coma Scale)',
        'AWS' => 'คะแนนประเมินอาการถอนแอลกอฮอล์ (Alcohol Withdrawal Score)',
        'CPR' => 'การช่วยฟื้นคืนชีพ (Cardiopulmonary Resuscitation)',
        'ALS' => 'การช่วยชีวิตขั้นสูง (Advanced Life Support)',
        'EB' => 'ผ้ายืดพันแผล (Elastic Bandage)',
    ],

    // การประเมินผู้ป่วย / ระดับความรู้สึกตัว
    'assessment' => [
        'AVPU' => 'การประเมินระดับความรู้สึกตัวแบบย่อ: A = รู้สึกตัวดี (Alert), V = ตอบสนองต่อเสียงเรียก (Voice), P = ตอบสนองต่อความเจ็บปวด (Pain), U = ไม่ตอบสนอง (Unresponsive)',
        'ABC' => 'การประเมินเบื้องต้น: ทางเดินหายใจ การหายใจ การไหลเวียนเลือด (Airway, Breathing, Circulation — Primary survey)',
        'Head to toe' => 'การตรวจร่างกายตั้งแต่ศีรษะจรดปลายเท้า',
        'DCAP-BTLS' => 'อักษรช่วยจำลักษณะการบาดเจ็บ: Deformity ผิดรูป, Contusion ฟกช้ำ, Abrasion ถลอก, Puncture/Penetration แผลแทง, Burn แผลไหม้, Tenderness กดเจ็บ, Laceration แผลฉีกขาด, Swelling บวม',
        'Jerk' => 'การตรวจรีเฟล็กซ์ (เช่น knee jerk = เคาะเข่า)',
    ],

    // อาการและภาวะที่พบบ่อยในบันทึกการเยี่ยม/ติดตาม
    'symptoms' => [
        'B.P. drop' => 'ความดันโลหิตต่ำลง',
        'Pale' => 'ซีด',
        'Weak' => 'อ่อนเพลีย / อ่อนแรง',
        'Nausea' => 'คลื่นไส้',
        'Vomit' => 'อาเจียน',
        'Sputum' => 'เสมหะ',
        'Secretion' => 'สารคัดหลั่ง',
        'Cyanosis' => 'ตัวเขียว/ปลายมือปลายเท้าเขียวจากขาดออกซิเจน',
        'Edema' => 'บวม',
        'Chills' => 'หนาวสั่นจากไข้สูง',
        'Coma' => 'หมดสติ ไม่รู้สึกตัว',
        'Unconscious' => 'ไม่รู้สึกตัว',
        'Paralysis' => 'อัมพาต',
        'Pain' => 'ความปวด',
        'Infection' => 'การติดเชื้อ',
        'Complication' => 'ภาวะแทรกซ้อน',
        'Side effect' => 'ผลข้างเคียง (ของยา/การรักษา)',
        'Rupture' => 'การแตก/ฉีกขาด (ของอวัยวะ)',
        'Wound' => 'แผล',
        'Abortion' => 'การแท้ง',
        // เพิ่มเติม
        'Fever' => 'ไข้',
        'Dyspnea' => 'หายใจลำบาก / หอบเหนื่อย',
        'SOB' => 'หายใจเหนื่อย (Shortness of Breath)',
        'DOE' => 'เหนื่อยเมื่อออกแรง (Dyspnea on Exertion)',
        'Orthopnea' => 'นอนราบแล้วหายใจลำบาก',
        'Dizziness' => 'เวียนศีรษะ',
        'Anorexia' => 'เบื่ออาหาร (ในบันทึกมักเขียนว่า poor appetite)',
        'Dysphagia' => 'กลืนลำบาก',
        'Constipation' => 'ท้องผูก',
        'Diarrhea' => 'ท้องเสีย',
        'Insomnia' => 'นอนไม่หลับ',
        'Delirium' => 'ภาวะสับสนเฉียบพลัน',
        'Fall' => 'การพลัดตกหกล้ม',
        'Pressure injury' => 'แผลกดทับ (เดิมเรียก bedsore / pressure ulcer) แบ่ง stage 1–4, unstageable, DTI',
    ],

    // คำที่ใช้ในงานดูแลต่อเนื่อง / ส่งต่อ / เยี่ยมบ้าน (บริบทระบบสุขภาพไทย)
    'continuity_care' => [
        'COC' => 'การดูแลต่อเนื่อง (Continuity of Care)',
        'HHC' => 'การดูแลสุขภาพที่บ้าน (Home Health Care)',
        'HN' => 'เลขประจำตัวผู้ป่วยของโรงพยาบาล (Hospital Number)',
        'AN' => 'เลขประจำตัวการนอนโรงพยาบาลแต่ละครั้ง (Admission Number)',
        'Admit' => 'รับไว้นอนรักษาในโรงพยาบาล',
        'F/U' => 'นัดติดตามอาการ (Follow-up)',
        'LOS' => 'จำนวนวันนอนโรงพยาบาล (Length of Stay)',
        'Refer' => 'ส่งต่อผู้ป่วย (refer in = รับเข้า, refer out = ส่งออก, refer back = ส่งกลับสถานพยาบาลต้นทาง)',
        'Re-admit' => 'กลับมานอนโรงพยาบาลซ้ำ (มักนับภายใน 28 วันหลังจำหน่าย)',
        'PCU' => 'หน่วยบริการปฐมภูมิ (Primary Care Unit)',
        'รพ.สต.' => 'โรงพยาบาลส่งเสริมสุขภาพตำบล',
        'อสม.' => 'อาสาสมัครสาธารณสุขประจำหมู่บ้าน',
        'CG' => 'ผู้ดูแลผู้ป่วย (Caregiver)',
        'CM' => 'ผู้จัดการการดูแล (Care Manager)',
        'LTC' => 'การดูแลระยะยาว (Long-Term Care)',
        'INHOMESSS' => 'แนวทางประเมินการเยี่ยมบ้าน: Immobility, Nutrition, Housing, Other people, Medication, Examination, Safety, Spiritual health, Services',
    ],

    // การจำแนกกลุ่มความรุนแรง (ตัดข้ามทุกประเภทเคส) — ใช้ร่วมกับประเภทเคสในการกำหนดจำนวนครั้งเยี่ยม
    // ตรงกับตัวเลือกใน referral-create.html / care-plan-confirm.html
    'severity_groups' => [
        'กลุ่ม 1' => 'บ้านสีเขียว — ช่วยเหลือตนเองได้ทั้งหมด',
        'กลุ่ม 2' => 'บ้านสีเหลือง — ช่วยเหลือตนเองได้บางส่วน',
        'กลุ่ม 3' => 'บ้านสีแดง — ช่วยเหลือตนเองไม่ได้เลย',
        'กลุ่ม 4' => 'Palliative Care — ผู้ป่วยระยะประคับประคอง ติดตามตามคะแนน PPS',
    ],

    // แบบประเมิน / คะแนนที่ใช้ในการติดตามผู้ป่วย
    'scores' => [
        'PPS' => 'Palliative Performance Scale ประเมินสมรรถภาพผู้ป่วยระยะประคับประคอง 0–100% (ขั้นละ 10) ยิ่งต่ำยิ่งอาการหนัก',
        'ADL' => 'กิจวัตรประจำวัน (Activities of Daily Living) มักใช้ Barthel ADL Index 0–20: ≥12 ติดสังคม, 5–11 ติดบ้าน, 0–4 ติดเตียง',
        'ESAS' => 'แบบประเมินอาการผู้ป่วยระยะประคับประคอง (Edmonton Symptom Assessment System) แต่ละอาการ 0–10',
        'NRS' => 'คะแนนความปวดแบบตัวเลข (Numeric Rating Scale) 0–10',
        'Pain score' => 'คะแนนความปวด 0–10 (0 = ไม่ปวด, 10 = ปวดมากที่สุด)',
        '2Q' => 'แบบคัดกรองโรคซึมเศร้า 2 คำถาม',
        '9Q' => 'แบบประเมินความรุนแรงของโรคซึมเศร้า 9 คำถาม (<7 ไม่มี/น้อยมาก, 7–12 น้อย, 13–18 ปานกลาง, ≥19 รุนแรง)',
        '8Q' => 'แบบประเมินความเสี่ยงการฆ่าตัวตาย 8 คำถาม',
        'EPDS' => 'แบบคัดกรองภาวะซึมเศร้าหลังคลอด (Edinburgh Postnatal Depression Scale)',
        'Braden' => 'แบบประเมินความเสี่ยงการเกิดแผลกดทับ (Braden Scale) คะแนนยิ่งต่ำยิ่งเสี่ยง',
        'BMI' => 'ดัชนีมวลกาย (Body Mass Index)',
    ],

    // ผลตรวจทางห้องปฏิบัติการ / ค่าที่วัดที่บ้าน
    'lab_values' => [
        'DTX' => 'ระดับน้ำตาลในเลือดจากปลายนิ้ว (Dextrostix) — ความหมายเดียวกับ CBG',
        'CBG' => 'ระดับน้ำตาลในเลือดจากเส้นเลือดฝอย (Capillary Blood Glucose)',
        'FBS' => 'ระดับน้ำตาลในเลือดขณะอดอาหาร (Fasting Blood Sugar)',
        'HbA1c' => 'ค่าน้ำตาลสะสมเฉลี่ย 2–3 เดือน (Hemoglobin A1c)',
        'SpO2' => 'ค่าความอิ่มตัวของออกซิเจนในเลือดจากเครื่องวัดปลายนิ้ว',
        'Cr' => 'ครีเอตินีน ค่าการทำงานของไต (Creatinine)',
        'eGFR' => 'อัตราการกรองของไตโดยประมาณ (Estimated Glomerular Filtration Rate)',
        'BUN' => 'ค่าของเสียยูเรียในเลือด (Blood Urea Nitrogen)',
        'CBC' => 'การตรวจความสมบูรณ์ของเม็ดเลือด (Complete Blood Count)',
        'Hb' => 'ฮีโมโกลบิน (Hemoglobin)',
        'Hct' => 'ความเข้มข้นของเลือด (Hematocrit)',
        'Na / K' => 'โซเดียม / โพแทสเซียมในเลือด (Electrolytes)',
        'INR' => 'ค่าการแข็งตัวของเลือด ใช้ติดตามผู้ที่ได้ยา warfarin',
        'LPM' => 'หน่วยอัตราการให้ออกซิเจน ลิตรต่อนาที (Liters Per Minute)',
    ],

    // อุปกรณ์/สายที่ติดตัวผู้ป่วยกลับบ้าน (ในบันทึกมักเขียนว่า "on ..." เช่น on NG tube)
    'home_devices' => [
        'NG tube' => 'สายให้อาหารทางจมูก (Nasogastric tube)',
        'PEG' => 'สายให้อาหารทางหน้าท้อง (Percutaneous Endoscopic Gastrostomy)',
        'Foley catheter' => 'สายสวนปัสสาวะค้างไว้ (retained urinary catheter)',
        'Tracheostomy' => 'ท่อเจาะคอ (tracheostomy tube)',
        'O2 cannula' => 'สายให้ออกซิเจนทางจมูก',
        'O2 concentrator' => 'เครื่องผลิตออกซิเจน',
        'Colostomy bag' => 'ถุงรองรับอุจจาระทางหน้าท้อง',
        'Air mattress' => 'ที่นอนลมป้องกันแผลกดทับ',
        'on' => 'คำที่ใช้ในบันทึกหมายถึง "กำลังใช้อยู่" เช่น on O2 cannula 3 LPM, on NG tube',
        'off' => 'คำที่ใช้ในบันทึกหมายถึง "ถอด/หยุดใช้" เช่น off Foley, off stitches',
    ],

    // Palliative Care
    'palliative' => [
        'DNR' => 'ไม่ต้องการให้ช่วยฟื้นคืนชีพ (Do Not Resuscitate)',
        'ACP' => 'การวางแผนดูแลล่วงหน้า (Advance Care Planning)',
        'Living will' => 'หนังสือแสดงเจตนาไม่ประสงค์จะรับบริการสาธารณสุขในวาระสุดท้าย',
        'EOL' => 'ระยะท้ายของชีวิต (End of Life)',
        'Opioid' => 'ยากลุ่มโอปิออยด์ระงับปวด เช่น morphine',
        'Breakthrough pain' => 'อาการปวดที่แทรกขึ้นมาระหว่างใช้ยาแก้ปวดประจำ',
        'Death rattle' => 'เสียงเสมหะครืดคราดในลำคอในช่วงใกล้เสียชีวิต',
        'Hospice' => 'การดูแลผู้ป่วยระยะสุดท้าย',
    ],

    // การดูแลหลังคลอด (มารดาและทารก)
    'postpartum' => [
        'NL' => 'คลอดปกติ (Normal Labor)',
        'NSD' => 'คลอดปกติทางช่องคลอด (Normal Spontaneous Delivery)',
        'C/S' => 'ผ่าตัดคลอด (Cesarean Section)',
        'G_P_' => 'จำนวนการตั้งครรภ์ / จำนวนการคลอด (Gravida / Para) เช่น G2P1',
        'EDC' => 'วันกำหนดคลอด (Expected Date of Confinement)',
        'Lochia' => 'น้ำคาวปลา: rubra (แดง) วันที่ 1–3, serosa (ชมพู-น้ำตาล) วันที่ 4–10, alba (ขาวเหลือง) หลังจากนั้น',
        'Episiotomy' => 'แผลฝีเย็บจากการตัดขยายช่องคลอด',
        'Uterine involution' => 'การเข้าอู่ของมดลูก (ตรวจระดับยอดมดลูก)',
        'PPH' => 'ตกเลือดหลังคลอด (Postpartum Hemorrhage)',
        'BF' => 'การเลี้ยงลูกด้วยนมแม่ (Breastfeeding)',
        'LATCH' => 'แบบประเมินการเข้าเต้าของทารก',
        'Engorgement' => 'เต้านมคัดตึง',
        'Neonatal jaundice' => 'ภาวะตัวเหลืองในทารกแรกเกิด',
    ],

    // การดูแลหลังผ่าตัด
    'post_surgery' => [
        'POD' => 'วันหลังผ่าตัด (Post-Operative Day) เช่น POD 3 = วันที่ 3 หลังผ่าตัด',
        'SSI' => 'การติดเชื้อที่แผลผ่าตัด (Surgical Site Infection)',
        'Dehiscence' => 'แผลผ่าตัดแยก',
        'Stitches off' => 'ตัดไหม',
        'Dry dressing' => 'การทำแผลแห้ง',
        'Drain' => 'สายระบายจากแผลผ่าตัด (เช่น Radivac, JP drain)',
        'Discharge (wound)' => 'สิ่งคัดหลั่งจากแผล: serous (ใส), serosanguineous (ปนเลือด), purulent (หนอง)',
        'Ambulation' => 'การลุกเดินหลังผ่าตัด (early ambulation = ลุกเดินเร็ว)',
        'TKR' => 'ผ่าตัดเปลี่ยนข้อเข่าเทียมทั้งข้อ (Total Knee Replacement)',
        'UKA' => 'ผ่าตัดเปลี่ยนข้อเข่าเทียมบางส่วน (Unicompartmental Knee Arthroplasty)',
        'THR' => 'ผ่าตัดเปลี่ยนข้อสะโพกเทียม (Total Hip Replacement)',
        'ORIF' => 'ผ่าตัดจัดกระดูกและยึดตรึงด้วยโลหะ (Open Reduction Internal Fixation)',
        'NPO' => 'งดน้ำและอาหารทางปาก (nil per os)',
    ],

    // กระดูกและข้อ
    'orthopedics' => [
        'NWB' => 'ห้ามลงน้ำหนักขาข้างที่บาดเจ็บ (Non-Weight Bearing)',
        'PWB' => 'ลงน้ำหนักได้บางส่วน (Partial Weight Bearing)',
        'FWB' => 'ลงน้ำหนักได้เต็มที่ (Full Weight Bearing)',
        'ROM' => 'พิสัยการเคลื่อนไหวของข้อ (Range of Motion)',
        'Traction' => 'การดึงถ่วงกระดูก',
        'Cast care' => 'การดูแลเฝือก (สังเกตอาการบวม ชา ซีด ปลายมือปลายเท้าเย็น)',
        'Neurovascular check' => 'การประเมินการไหลเวียนเลือดและประสาทส่วนปลาย (5P: Pain, Pallor, Pulselessness, Paresthesia, Paralysis)',
        'Compartment syndrome' => 'ภาวะความดันในช่องกล้ามเนื้อสูง (ภาวะฉุกเฉิน)',
        'Osteoporosis' => 'โรคกระดูกพรุน',
        'Hip fracture' => 'กระดูกสะโพกหัก',
        'Pin site care' => 'การทำแผลบริเวณหมุดยึดกระดูก',
    ],

    // กุมารเวชกรรม
    'pediatrics' => [
        'EPI' => 'แผนการให้วัคซีนพื้นฐานตามวัย (Expanded Program on Immunization)',
        'DSPM' => 'คู่มือเฝ้าระวังและส่งเสริมพัฒนาการเด็กปฐมวัย',
        'Developmental milestone' => 'พัฒนาการตามวัย',
        'Growth chart' => 'กราฟการเจริญเติบโต (น้ำหนัก/ส่วนสูงตามเกณฑ์อายุ)',
        'Preterm' => 'ทารกคลอดก่อนกำหนด (อายุครรภ์น้อยกว่า 37 สัปดาห์)',
        'LBW' => 'ทารกน้ำหนักแรกเกิดน้อย (Low Birth Weight, น้อยกว่า 2,500 กรัม)',
        'NICU' => 'หอผู้ป่วยหนักทารกแรกเกิด (Neonatal Intensive Care Unit)',
        'Febrile convulsion' => 'ชักจากไข้สูง',
        'Dehydration' => 'ภาวะขาดน้ำ',
        'Bronchiolitis' => 'หลอดลมฝอยอักเสบ',
        'RSV' => 'เชื้อไวรัสทางเดินหายใจที่พบบ่อยในเด็กเล็ก (Respiratory Syncytial Virus)',
        'Asthma' => 'โรคหืด',
        'Thalassemia' => 'โรคโลหิตจางธาลัสซีเมีย',
    ],

    // ยาที่พบบ่อยในผู้ป่วยติดตามที่บ้าน (ชื่อย่อที่ใช้ใน รพ. ไทย)
    'common_drugs' => [
        'PCM' => 'พาราเซตามอล (Paracetamol)',
        'ASA' => 'แอสไพริน (Acetylsalicylic Acid)',
        'NSAIDs' => 'ยาแก้ปวดต้านการอักเสบที่ไม่ใช่สเตียรอยด์',
        'ATB' => 'ยาปฏิชีวนะ (Antibiotic)',
        'RI' => 'อินซูลินออกฤทธิ์สั้น (Regular Insulin)',
        'NPH' => 'อินซูลินออกฤทธิ์ปานกลาง',
        'Fe' => 'ยาธาตุเหล็ก (Ferrous)',
        'ORS' => 'ผงเกลือแร่ (Oral Rehydration Salts)',
        'Warfarin' => 'ยาต้านการแข็งตัวของเลือด (ต้องติดตามค่า INR)',
        'Furosemide' => 'ยาขับปัสสาวะ (ชื่อการค้าที่พบบ่อย: Lasix)',
    ],

    // การบาดเจ็บ
    'injuries' => [
        'Head injury' => 'การบาดเจ็บที่ศีรษะ',
        'Concussion' => 'สมองกระทบกระเทือน',
        'C-spine injury' => 'การบาดเจ็บกระดูกสันหลังส่วนคอ (Cervical spine injury)',
        'Blunt chest' => 'การบาดเจ็บจากแรงกระแทกที่หน้าอก',
        'Pneumothorax' => 'ภาวะมีลมในช่องเยื่อหุ้มปอด',
        'Tension pneumothorax' => 'ภาวะมีลมในช่องเยื่อหุ้มปอดชนิดมีแรงดัน (ภาวะฉุกเฉิน)',
        'Hemothorax' => 'ภาวะมีเลือดในช่องเยื่อหุ้มปอด',
        'Dislocation' => 'ข้อเคลื่อน/หลุด',
        'Sprain' => 'ข้อแพลง (เอ็นยึดข้อบาดเจ็บ)',
        'Strain' => 'กล้ามเนื้อ/เอ็นกล้ามเนื้อยืดหรือฉีก',
        'Exposed bone' => 'กระดูกโผล่ (กระดูกหักแบบเปิด)',
        'Crushing injury' => 'การบาดเจ็บจากการถูกบดทับ',
        'Amputation' => 'อวัยวะถูกตัดขาด',
        'Mass casualty' => 'อุบัติเหตุหมู่ / ผู้บาดเจ็บจำนวนมาก',
    ],

    // ศัพท์อุปกรณ์ที่ใช้เฉพาะใน รพ. ไทย (ชื่อแบรนด์/ภาษาปาก ที่โมเดลอาจไม่รู้)
    'equipment' => [
        'Medicut' => 'เข็มพลาสติกสำหรับแทงให้สารน้ำทางหลอดเลือดดำ (IV catheter)',
        'I.V. set' => 'ชุดสายให้สารน้ำ',
        'Extension' => 'สายต่อชุดให้สารน้ำ',
        '3-way' => 'ข้อต่อสามทาง (three-way stopcock)',
        'Top gauze' => 'ผ้าก๊อซหุ้มสำลี สำหรับแผลขนาดใหญ่',
        'Micropore' => 'พลาสเตอร์ปิดแผลชนิดกระดาษ',
        'Transpore' => 'พลาสเตอร์ปิดแผลชนิดพลาสติกใส',
        'Ambu bag' => 'ถุงบีบช่วยหายใจ',
        'Glucometer' => 'เครื่องตรวจระดับน้ำตาลในเลือดจากปลายนิ้ว',
        'Pulse oximeter' => 'เครื่องวัดค่าความอิ่มตัวของออกซิเจนในเลือด (SpO2)',
        'Oropharyngeal airway' => 'ท่อเปิดทางเดินหายใจทางปาก',
        'Hard collar' => 'เฝือกดามคอชนิดแข็ง',
        'Long spinal board' => 'กระดานรองหลังชนิดยาว',
        'KED' => 'อุปกรณ์ดามหลังสำหรับเคลื่อนย้าย (Kendrick Extrication Device)',
        'Scoop stretcher' => 'เปลตัก',
    ],

    // คำย่อชื่อโรค / การวินิจฉัย
    'diagnosis_abbreviations' => [
        'DM' => 'โรคเบาหวาน (Diabetes Mellitus)',
        'DLP' => 'ภาวะไขมันในเลือดผิดปกติ (Dyslipidemia)',
        'CVA' => 'โรคหลอดเลือดสมอง (Cerebrovascular Accident)',
        'STEMI' => 'กล้ามเนื้อหัวใจตายเฉียบพลันชนิด ST ยก (ST-Elevation Myocardial Infarction)',
        'NSTEMI' => 'กล้ามเนื้อหัวใจตายเฉียบพลันชนิด ST ไม่ยก (Non-ST-Elevation Myocardial Infarction)',
        'AF' => 'หัวใจห้องบนสั่นพลิ้ว (Atrial Fibrillation)',
        'VF' => 'หัวใจห้องล่างสั่นพลิ้ว (Ventricular Fibrillation)',
        'VT' => 'หัวใจห้องล่างเต้นเร็วผิดจังหวะ (Ventricular Tachycardia)',
        'SVT' => 'หัวใจเต้นเร็วผิดจังหวะจากเหนือห้องล่าง (Supraventricular Tachycardia)',
        'SDH' => 'เลือดออกใต้เยื่อหุ้มสมองชั้นดูรา (Subdural Hematoma)',
        'EDH' => 'เลือดออกเหนือเยื่อหุ้มสมองชั้นดูรา (Epidural Hematoma)',
        'SAH' => 'เลือดออกใต้เยื่อหุ้มสมองชั้นอะแรคนอยด์ (Subarachnoid Hemorrhage)',
        'ICH' => 'เลือดออกในเนื้อสมอง (Intracerebral Hemorrhage)',
        'UTI' => 'การติดเชื้อทางเดินปัสสาวะ (Urinary Tract Infection)',
        'ARF' => 'ไตวายเฉียบพลัน (Acute Renal Failure)',
        'CRF' => 'ไตวายเรื้อรัง (Chronic Renal Failure)',
        'ESRD' => 'ไตวายระยะสุดท้าย (End Stage Renal Disease)',
        'CAPD' => 'การล้างไตทางช่องท้องด้วยตนเองแบบต่อเนื่อง (Continuous Ambulatory Peritoneal Dialysis)',
        'BPH' => 'ต่อมลูกหมากโต (Benign Prostatic Hyperplasia)',
        'URI' => 'การติดเชื้อทางเดินหายใจส่วนบน (Upper Respiratory Infection)',
        'COPD' => 'โรคปอดอุดกั้นเรื้อรัง (Chronic Obstructive Pulmonary Disease)',
        'DHF' => 'ไข้เลือดออกเดงกีชนิดมีภาวะเลือดออก (Dengue Hemorrhagic Fever)',
        'AGE' => 'ลำไส้อักเสบเฉียบพลัน (Acute Gastroenteritis)',
        'Fx' => 'กระดูกหัก (Fracture)',
        'LBP' => 'ปวดหลังส่วนล่าง (Low Back Pain)',
        'STD' => 'โรคติดต่อทางเพศสัมพันธ์ (Sexually Transmitted Disease)',
        // เพิ่มเติม: โรคที่พบบ่อยในเคสติดตามต่อเนื่อง
        'CKD' => 'โรคไตเรื้อรัง (Chronic Kidney Disease) แบ่งระยะ 1–5 ตาม eGFR',
        'AKI' => 'ไตบาดเจ็บเฉียบพลัน (Acute Kidney Injury)',
        'CHF' => 'ภาวะหัวใจล้มเหลว (Congestive Heart Failure)',
        'HF' => 'ภาวะหัวใจล้มเหลว (Heart Failure)',
        'IHD' => 'โรคหัวใจขาดเลือด (Ischemic Heart Disease)',
        'CAD' => 'โรคหลอดเลือดหัวใจตีบ (Coronary Artery Disease)',
        'MI' => 'กล้ามเนื้อหัวใจตาย (Myocardial Infarction)',
        'DVT' => 'ลิ่มเลือดอุดตันหลอดเลือดดำส่วนลึก (Deep Vein Thrombosis)',
        'TB' => 'วัณโรค (Tuberculosis)',
        'HIV' => 'เชื้อเอชไอวี (Human Immunodeficiency Virus)',
        'OA' => 'ข้อเสื่อม (Osteoarthritis)',
        'GERD' => 'กรดไหลย้อน (Gastroesophageal Reflux Disease)',
        'NCD' => 'โรคไม่ติดต่อเรื้อรัง (Non-Communicable Disease)',
        'Sepsis' => 'ภาวะติดเชื้อในกระแสเลือด',
        'Pneumonia' => 'ปอดอักเสบ',
        'Stroke' => 'โรคหลอดเลือดสมอง (ความหมายเดียวกับ CVA)',
        'Dementia' => 'ภาวะสมองเสื่อม',
    ],

    // คำย่อที่มีได้หลายความหมาย — ต้องตีความจากบริบท ถ้าไม่ชัดให้ระบุว่า "ไม่แน่ใจ"
    'ambiguous' => [
        'HT' => ['โรคความดันโลหิตสูง (Hypertension)', 'ส่วนสูง (Height)'],
        'OD' => ['วันละครั้ง (Omni die)', 'ตาขวา (Oculus dexter)'],
        'N/S' => ['สัญญาณทางระบบประสาท (Neuro signs)', 'น้ำเกลือ Normal Saline'],
        'PE' => ['การตรวจร่างกาย (Physical Examination)', 'ลิ่มเลือดอุดกั้นในปอด (Pulmonary Embolism)'],
        'RR' => ['อัตราการหายใจ (Respiratory Rate)', 'ห้องพักฟื้น (Recovery Room)'],
        'AE' => ['การกำเริบเฉียบพลัน (Acute Exacerbation)', 'เหตุการณ์ไม่พึงประสงค์ (Adverse Event)'],
        'CA' => ['โรคมะเร็ง (Cancer)', 'แคลเซียม (Calcium)'],
        'PT' => ['กายภาพบำบัด (Physical Therapy)', 'ค่าการแข็งตัวของเลือด (Prothrombin Time)'],
        'DF' => ['ไข้เดงกี (Dengue Fever)', 'แผลเบาหวานที่เท้า (Diabetic Foot)'],
        'ID' => ['ฉีดเข้าชั้นผิวหนัง (Intradermal)', 'โรคติดเชื้อ (Infectious Disease)'],
        'expire' => ['เสียชีวิต (บริบทผู้ป่วย)', 'หมดอายุ (บริบทยา/อุปกรณ์)'],
        // เพิ่มเติม
        'GA' => ['ลักษณะภายนอกทั่วไป (General Appearance)', 'อายุครรภ์ (Gestational Age)'],
        'D/C' => ['จำหน่ายผู้ป่วย (Discharge)', 'หยุดยา/หยุดการรักษา (Discontinue)'],
        'D/S' => 'ทำแผล (Dressing)',
        'PD' => ['การล้างไตทางช่องท้อง (Peritoneal Dialysis)', 'โรคพาร์กินสัน (Parkinson\'s Disease)'],
        'MS' => ['มอร์ฟีนซัลเฟต (Morphine Sulfate)', 'ลิ้นหัวใจไมทรัลตีบ (Mitral Stenosis)', 'โรคปลอกประสาทอักเสบ (Multiple Sclerosis)'],
        'BS' => ['ระดับน้ำตาลในเลือด (Blood Sugar)', 'เสียงลำไส้ (Bowel Sounds)', 'เสียงหายใจ (Breath Sounds)'],
        'CP' => ['เจ็บหน้าอก (Chest Pain)', 'สมองพิการ (Cerebral Palsy)'],
        'MO' => ['มอร์ฟีน (Morphine)', 'แพทย์ (Medical Officer)'],
    ],

    // คำย่อการสั่งยา: เวลาให้ยา
    'medication_timing' => [
        'a.c.' => 'ก่อนอาหาร (ante cibum)',
        'p.c.' => 'หลังอาหาร (post cibum)',
        'h.s.' => 'ก่อนนอน (hora somni)',
        'p.r.n.' => 'เมื่อมีอาการ / เมื่อต้องการ (pro re nata)',
        'stat' => 'ให้ทันที (statim)',
        'q.d.' => 'วันละ 1 ครั้ง (quaque die)',
        'b.i.d.' => 'วันละ 2 ครั้ง (bis in die)',
        't.i.d.' => 'วันละ 3 ครั้ง (ter in die)',
        'q.i.d.' => 'วันละ 4 ครั้ง (quater in die)',
        'q_h' => 'ทุก ๆ _ ชั่วโมง เช่น q2h = ทุก 2 ชั่วโมง (quaque _ hora)',
    ],

    // คำย่อการสั่งยา: ช่องทางให้ยา
    'medication_route' => [
        'po' => 'รับประทาน (per os)',
        'Inj.' => 'ยาฉีด (Injection)',
        'IM' => 'ฉีดเข้ากล้ามเนื้อ (Intramuscular)',
        'IV' => 'ให้ทางหลอดเลือดดำ (Intravenous)',
        'SL' => 'อมใต้ลิ้น (Sublingual)',
        'Rectal supp.' => 'ยาเหน็บทางทวารหนัก (Rectal Suppository)',
        'Vag. supp.' => 'ยาเหน็บทางช่องคลอด (Vaginal Suppository)',
    ],

    // หน่วยและรูปแบบยา
    'medication_units' => [
        'cc' => 'ลูกบาศก์เซนติเมตร เท่ากับมิลลิลิตร (cubic centimeter)',
        'ml' => 'มิลลิลิตร (milliliter)',
        'mg' => 'มิลลิกรัม (milligram)',
        'g' => 'กรัม (gram)',
        'gtt' => 'หยด (drops)',
        'tsp' => 'ช้อนชา (teaspoon)',
        'tbsp' => 'ช้อนโต๊ะ (tablespoon)',
        'Tab' => 'ยาเม็ด (Tablet)',
        'Cap' => 'ยาแคปซูล (Capsule)',
        'Susp' => 'ยาน้ำแขวนตะกอน (Suspension)',
    ],

    // แผนกในโรงพยาบาล
    'departments' => [
        'OPD' => 'แผนกผู้ป่วยนอก (Outpatient Department)',
        'IPD' => 'แผนกผู้ป่วยใน (Inpatient Department)',
        'ER' => 'ห้องอุบัติเหตุและฉุกเฉิน (Emergency Room)',
        'ICU' => 'หอผู้ป่วยหนัก (Intensive Care Unit)',
        'OR' => 'ห้องผ่าตัด (Operating Room)',
        'LR' => 'ห้องคลอด (Labor Room)',
        'ANC' => 'การฝากครรภ์ / ดูแลก่อนคลอด (Antenatal Care)',
        'MED' => 'อายุรกรรม (Medicine)',
        'SUR' => 'ศัลยกรรม (Surgery)',
        'ORTHO' => 'ศัลยกรรมกระดูก (Orthopedics)',
        'PED' => 'กุมารเวชกรรม (Pediatrics)',
        'OB' => 'สูติกรรม (Obstetrics)',
        'GYN' => 'นรีเวชกรรม (Gynecology)',
        'Anes' => 'วิสัญญี (Anesthesia)',
        'HD' => 'หน่วยไตเทียม / การฟอกเลือดด้วยเครื่องไตเทียม (Hemodialysis)',
        'LAB' => 'ห้องปฏิบัติการ (Laboratory)',
    ],

    // รากศัพท์นำหน้า (อวัยวะ) — ช่วยให้โมเดลตีความคำที่ไม่อยู่ในรายการได้
    'prefixes' => [
        'adreno-' => 'ต่อมหมวกไต (adrenal gland)',
        'broncho-' => 'หลอดลม (bronchus)',
        'chole-' => 'น้ำดี (bile)',
        'chondro-' => 'กระดูกอ่อน (cartilage)',
        'cranio-' => 'กะโหลกศีรษะ (skull)',
        'colo-' => 'ลำไส้ใหญ่ (colon)',
        'colpo-' => 'ช่องคลอด (vagina)',
        'cysto-' => 'กระเพาะปัสสาวะ / ถุง (bladder, cyst)',
        'gastro-' => 'กระเพาะอาหาร (stomach)',
        'entero-' => 'ลำไส้เล็ก (small intestine)',
        'hepato-' => 'ตับ (liver)',
        'hernio-' => 'ไส้เลื่อน (hernia)',
        'hystero-' => 'มดลูก (uterus)',
        'laparo-' => 'ช่องท้อง (abdomen)',
        'litho-' => 'นิ่ว (stone)',
        'nephro-' => 'ไต (kidney)',
        'orchido-' => 'อัณฑะ (testis)',
        'osteo-' => 'กระดูก (bone)',
        'oophoro-' => 'รังไข่ (ovary)',
        'procto-' => 'ไส้ตรง (rectum)',
        'pyelo-' => 'กรวยไต (renal pelvis)',
        'salpingo-' => 'ท่อนำไข่ (fallopian tube)',
        'tracheo-' => 'หลอดลมคอ (trachea)',
        'uretero-' => 'ท่อไต (ureter)',
        'urethro-' => 'ท่อปัสสาวะ (urethra)',
    ],

    // คำลงท้าย (หัตถการ)
    'suffixes' => [
        '-ectomy' => 'ผ่าตัดเอาออก (total = ทั้งหมด, subtotal = เกือบทั้งหมด, partial = บางส่วน)',
        '-tomy' => 'ผ่าตัดเปิดเข้าไปในอวัยวะแล้วเย็บปิด เช่น craniotomy',
        '-stomy' => 'ทำรูเปิดจากอวัยวะสู่ภายนอก หรือเชื่อมสองอวัยวะ เช่น colostomy, gastrostomy',
        '-plasty' => 'ผ่าตัดตกแต่ง/ซ่อมแซม เช่น urethroplasty',
        '-pexy' => 'ผ่าตัดตรึงอวัยวะให้อยู่ตำแหน่งปกติ เช่น nephropexy',
        '-rrhaphy' => 'เย็บซ่อม เช่น herniorrhaphy',
        '-scopy' => 'ส่องกล้องตรวจ เช่น arthroscopy',
        '-tripsy' => 'บด/สลาย (นิ่ว) เช่น lithotripsy',
        '-lapaxy' => 'บดนิ่วแล้วล้างออก เช่น litholapaxy',
    ],
];
