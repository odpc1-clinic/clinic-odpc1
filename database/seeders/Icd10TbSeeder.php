<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * รหัส ICD-10 ชุดวัณโรค 24 รหัส (2569-09-29)
 *
 * รายการมาจากหมอของคลินิกวัณโรคโดยตรง ไม่ได้ดึงจากไฟล์มาตรฐานทั้งเล่ม
 * เพราะ ICD-10 เต็มมีหมื่นกว่ารหัส ใส่หมดแล้วหมอต้องเลื่อนหาในกองที่ไม่เกี่ยวกับงาน
 *
 * รันซ้ำได้ — ใช้ updateOrInsert ตามรหัส ของที่แก้ในระบบจะถูกเขียนทับกลับเป็นค่าตั้งต้น
 * ยกเว้น active ที่ปล่อยไว้ตามเดิม เผื่อมีคนปิดรหัสที่ไม่ใช้แล้ว จะได้ไม่กลับมาเอง
 */
class Icd10TbSeeder extends Seeder
{
    public function run()
    {
        $rows = [
            // [code, description_en, description_th, group_type, is_secondary, sort]
            ['Z20.1', 'Contact with and (suspected) exposure to tuberculosis', 'ผู้สัมผัสหรือใกล้ชิดกับผู้ป่วยวัณโรค', 'Contact / Screening', false, 10],
            ['Z22.7', 'Latent tuberculosis infection (LTBI)', 'การติดเชื้อวัณโรคแฝง (มีเชื้อแต่ไม่มีอาการ)', 'Contact / Screening', false, 20],
            ['Z11.1', 'Encounter for screening for respiratory tuberculosis', 'การตรวจคัดกรองวัณโรคปอด', 'Contact / Screening', false, 30],
            ['Z11.7', 'Encounter for testing for latent tuberculosis infection', 'การตรวจหาการติดเชื้อวัณโรคแฝง', 'Contact / Screening', false, 40],
            ['R76.11', 'Nonspecific reaction to tuberculin skin test without active tuberculosis', 'ผลทดสอบผิวหนัง (TST) ให้ผลบวก โดยไม่พบโรค', 'Contact / Screening', false, 50],
            ['R76.12', 'Nonspecific reaction to interferon gamma release assay without active tuberculosis', 'ผลทดสอบเลือด (IGRA) ให้ผลบวก โดยไม่พบโรค', 'Contact / Screening', false, 60],
            ['A15.0', 'Tuberculosis of lung, confirmed by bacteriological and histological studies', 'วัณโรคปอด (ยืนยันโดยการตรวจเสมหะพบเชื้อ/เพาะเชื้อ)', 'Active TB (Respiratory)', false, 70],
            ['A15.4', 'Tuberculosis of intrathoracic lymph nodes, confirmed', 'วัณโรคของต่อมน้ำเหลืองในช่องอก (ยืนยันผลตรวจ)', 'Active TB (Respiratory)', false, 80],
            ['A15.6', 'Tuberculous pleurisy, confirmed', 'เยื่อหุ้มปอดอักเสบจากวัณโรค (ยืนยันผลตรวจ)', 'Active TB (Respiratory)', false, 90],
            ['A16.0', 'Tuberculosis of lung, bacteriologically and histologically negative', 'วัณโรคปอด (ผลเสมหะไม่พบเชื้อ แต่เพาะเชื้อขึ้น)', 'Active TB (Respiratory)', false, 100],
            ['A16.1', 'Tuberculosis of lung, bacteriological and histological studies not done', 'วัณโรคปอด (ไม่พบเชื้อ แต่ยืนยันจากภาพเอกซเรย์ปอด)', 'Active TB (Respiratory)', false, 110],
            ['A16.2', 'Tuberculosis of lung, without mention of bacteriological or histological confirmation', 'วัณโรคปอด (ไม่ได้ระบุการยืนยันทางแบคทีเรีย)', 'Active TB (Respiratory)', false, 120],
            ['A17.0', 'Tuberculous meningitis', 'เยื่อหุ้มสมองอักเสบจากเชื้อวัณโรค', 'Active TB (Extrapulmonary)', false, 130],
            ['A18.0', 'Tuberculosis of bones and joints', 'วัณโรคของกระดูกและข้อ', 'Active TB (Extrapulmonary)', false, 140],
            ['A18.1', 'Tuberculosis of genitourinary system', 'วัณโรคระบบสืบพันธุ์และทางเดินปัสสาวะ', 'Active TB (Extrapulmonary)', false, 150],
            ['A18.2', 'Tuberculous peripheral lymphadenopathy', 'วัณโรคของต่อมน้ำเหลืองส่วนปลาย (เช่น ที่คอ)', 'Active TB (Extrapulmonary)', false, 160],
            ['A18.3', 'Tuberculosis of intestines, peritoneum and mesenteric glands', 'วัณโรคของลำไส้ เยื่อบุช่องท้อง และต่อมน้ำเหลืองในช่องท้อง', 'Active TB (Extrapulmonary)', false, 170],
            ['A19.0', 'Acute miliary tuberculosis of a single specified site', 'วัณโรคชนิดแพร่กระจายทั่วร่างกายเฉียบพลัน (ตำแหน่งเดียว)', 'Active TB (Miliary)', false, 180],
            ['A19.1', 'Acute miliary tuberculosis of multiple sites', 'วัณโรคชนิดแพร่กระจายทั่วร่างกายเฉียบพลัน (หลายตำแหน่ง)', 'Active TB (Miliary)', false, 190],
            ['Z86.11', 'Personal history of active tuberculosis', 'ประวัติส่วนตัวเคยเป็นวัณโรคระยะลุกลาม', 'History of TB', false, 200],
            ['Z86.15', 'Personal history of latent tuberculosis infection', 'ประวัติส่วนตัวเคยติดเชื้อวัณโรคแฝง', 'History of TB', false, 210],
            ['B90.9', 'Sequelae of respiratory and unspecified tuberculosis', 'ผลสืบเนื่องจากวัณโรค (เช่น แผลเป็น/ผังผืดหลังหายแล้ว)', 'History of TB', false, 220],
            ['U84.3', 'Resistance to rifampicin', 'เชื้อวัณโรคดื้อยา Rifampicin (ใช้เป็นรหัสรองร่วมกับกลุ่ม A)', 'Drug Resistance (Secondary)', true, 230],
            ['U84.7', 'Resistance to multiple drugs', 'เชื้อวัณโรคดื้อยาหลายขนาน MDR-TB (ใช้เป็นรหัสรองร่วมกับกลุ่ม A)', 'Drug Resistance (Secondary)', true, 240],
            // Pre-XDR = MDR + ดื้อ FQ — ไม่มีรหัสจำเพาะ ลง U84.7 + U83.2 (ผู้ใช้สั่งเพิ่ม 2569-10-30)
            ['U83.2', 'Resistance to quinolones', 'เชื้อดื้อยากลุ่ม Fluoroquinolone (Levofloxacin, Moxifloxacin) — ใช้คู่ U84.7 กรณี Pre-XDR · รหัสรอง', 'Drug Resistance (Secondary)', true, 245],

            // ตับอักเสบจากยาวัณโรค (เช่น Rifampicin) — คลินิกขอ 2569-10-01
            // ใช้ K71.6 เป็นรหัสหลัก คู่กับรหัสสาเหตุจากยา: T36.6X5A (ICD-10-CM แบบที่ Google ให้มา) หรือ Y41.1 (ICD-10 WHO/TM ที่ สปสช. ใช้)
            ['K71.6', 'Toxic liver disease with hepatitis, not elsewhere classified (drug-induced hepatitis)', 'ตับอักเสบจากพิษ/จากยา (เช่น ยาวัณโรค Rifampicin)', 'Adverse Drug Reaction', false, 300],
            // ตัวที่ 7 ตามระยะการรักษา (คลินิกขอ 2569-10-08): A = รักษาครั้งแรก · D = มาติดตาม · S = ผลสืบเนื่อง
            ['T36.6X5A', 'Adverse effect of rifamycins, initial encounter', 'ผลข้างเคียงจากยากลุ่ม Rifamycin (Rifampicin) — รักษาครั้งแรก (A) · รหัสรอง (ICD-10-CM)', 'Adverse Drug Reaction', true, 310],
            ['T36.6X5D', 'Adverse effect of rifamycins, subsequent encounter', 'ผลข้างเคียงจากยากลุ่ม Rifamycin (Rifampicin) — มาติดตามครั้งถัดไป (D) · รหัสรอง (ICD-10-CM)', 'Adverse Drug Reaction', true, 311],
            ['T36.6X5S', 'Adverse effect of rifamycins, sequela', 'ผลข้างเคียงจากยากลุ่ม Rifamycin (Rifampicin) — ผลสืบเนื่อง/ภาวะหลงเหลือ (S) · รหัสรอง (ICD-10-CM)', 'Adverse Drug Reaction', true, 312],
            // ชุด T36.6X5- ถูกปิด (active=0) ตั้งแต่ 2569-10-30 — คลินิกเลือกใช้ TM อย่างเดียว แถวเหล่านี้เก็บไว้ให้ข้อมูลเก่าอ่านได้
            // รหัส Y แยกตามกลุ่มยา (migration 2026_10_30_150000) — Y41.1 ไม่รวม Rifamycin/Aminoglycoside
            ['Y40.6', 'Rifamycins causing adverse effects in therapeutic use', 'ผลไม่พึงประสงค์จากยากลุ่ม Rifamycin (Rifampicin, Rifapentine, 3HP) ที่ใช้ตามขนาดรักษา — รหัสรอง (ICD-10-TM)', 'Adverse Drug Reaction', true, 315],
            ['Y40.5', 'Aminoglycosides causing adverse effects in therapeutic use', 'ผลไม่พึงประสงค์จากยากลุ่ม Aminoglycoside (Amikacin, Streptomycin) ที่ใช้ตามขนาดรักษา — รหัสรอง (ICD-10-TM)', 'Adverse Drug Reaction', true, 316],
            ['Y41.1', 'Antimycobacterial drugs causing adverse effects in therapeutic use', 'ผลไม่พึงประสงค์จากยาต้านวัณโรค (Isoniazid/INH, Pyrazinamide/PZA, Ethambutol/EMB, Ethionamide, PAS, Clofazimine, Bedaquiline, Delamanid, Pretomanid) — ไม่รวม Rifampicin/Rifapentine (Y40.6) และ Amikacin (Y40.5) · รหัสรอง (ICD-10-TM)', 'Adverse Drug Reaction', true, 320],
            ['Y40.8', 'Other systemic antibiotics causing adverse effects in therapeutic use', 'ผลไม่พึงประสงค์จากยาปฏิชีวนะอื่น (Linezolid, Cycloserine, Levofloxacin, Moxifloxacin) ที่ใช้ตามขนาดรักษา — รหัสรอง (ICD-10-TM)', 'Adverse Drug Reaction', true, 325],

            // เบาหวานชนิดที่ 2 (โรคร่วมที่พบบ่อยในคนไข้วัณโรค) — คลินิก TB ขอ 2569-10-07 ตามรายการที่หมอส่งมา
            // E11.9 ใช้ได้ทั้ง ICD-10 WHO/TM และ CM · ตัวที่เหลือเป็นรหัสแบบ ICD-10-CM (สหรัฐฯ) ระบุไว้ในคำอธิบาย
            ['E11.9', 'Type 2 diabetes mellitus without complications', 'เบาหวานชนิดที่ 2 ไม่มีภาวะแทรกซ้อน (ใช้ได้ทั้ง TM และ CM)', 'Comorbidity (Diabetes)', false, 400],
            ['E11.65', 'Type 2 diabetes mellitus with hyperglycemia', 'เบาหวานชนิดที่ 2 ร่วมกับน้ำตาลในเลือดสูง (ICD-10-CM)', 'Comorbidity (Diabetes)', false, 410],
            ['E11.A', 'Type 2 diabetes mellitus without complications in remission', 'เบาหวานชนิดที่ 2 ไม่มีภาวะแทรกซ้อน อยู่ในระยะสงบ (ICD-10-CM)', 'Comorbidity (Diabetes)', false, 420],
            ['E11.22', 'Type 2 diabetes mellitus with diabetic chronic kidney disease', 'เบาหวานชนิดที่ 2 ร่วมกับโรคไตเรื้อรังจากเบาหวาน (ICD-10-CM)', 'Comorbidity (Diabetes)', false, 430],
            ['E11.319', 'Type 2 diabetes mellitus with unspecified diabetic retinopathy without macular edema', 'เบาหวานชนิดที่ 2 ร่วมกับเบาหวานขึ้นจอตา (ไม่ระบุชนิด) (ICD-10-CM)', 'Comorbidity (Diabetes)', false, 440],
            ['E11.40', 'Type 2 diabetes mellitus with diabetic neuropathy, unspecified', 'เบาหวานชนิดที่ 2 ร่วมกับปลายประสาทเสื่อมจากเบาหวาน (ไม่ระบุชนิด) (ICD-10-CM)', 'Comorbidity (Diabetes)', false, 450],
            // รหัสแบบไทย (ICD-10 WHO/TM ที่ สปสช./แฟ้ม 43 รับ) — คลินิกขอเพิ่มคู่กับชุด CM ด้านบน 2569-10-07
            ['E11.2', 'Type 2 diabetes mellitus with renal complications', 'เบาหวานชนิดที่ 2 ร่วมกับภาวะแทรกซ้อนทางไต (ICD-10-TM)', 'Comorbidity (Diabetes)', false, 402],
            ['E11.3', 'Type 2 diabetes mellitus with ophthalmic complications', 'เบาหวานชนิดที่ 2 ร่วมกับภาวะแทรกซ้อนทางตา (ICD-10-TM)', 'Comorbidity (Diabetes)', false, 403],
            ['E11.4', 'Type 2 diabetes mellitus with neurological complications', 'เบาหวานชนิดที่ 2 ร่วมกับภาวะแทรกซ้อนทางระบบประสาท (ICD-10-TM)', 'Comorbidity (Diabetes)', false, 404],
            ['E11.6', 'Type 2 diabetes mellitus with other specified complications', 'เบาหวานชนิดที่ 2 ร่วมกับภาวะแทรกซ้อนอื่นที่ระบุ เช่น น้ำตาลในเลือดสูง (ICD-10-TM)', 'Comorbidity (Diabetes)', false, 405],
        ];

        foreach ($rows as [$code, $en, $th, $group, $secondary, $sort]) {
            DB::table('icd10_codes')->updateOrInsert(
                ['code' => $code],
                [
                    'description_en' => $en,
                    'description_th' => $th,
                    'group_type'     => $group,
                    'is_secondary'   => $secondary,
                    'sort'           => $sort,
                    'updated_at'     => now(),
                    'created_at'     => now(),
                ]
            );
        }
    }
}
