<?php

namespace Database\Seeders;

use App\Models\StandardPrice;
use Illuminate\Database\Seeder;

/**
 * ราคากลางตาม "ประกาศสำนักงานป้องกันควบคุมโรคที่ 1 จังหวัดเชียงใหม่
 * เรื่อง อัตราค่าบริการสาธารณสุขของสำนักงานป้องกันควบคุมโรคที่ 1 จังหวัดเชียงใหม่"
 * สั่ง ณ วันที่ 13 มีนาคม พ.ศ. 2569 (ราคาในประกาศเป็นเลขไทย แปลงเป็นเลขอารบิกแล้ว)
 *
 * รันซ้ำได้ (updateOrCreate ตาม category+name) — ปีหน้าประกาศใหม่ออก แก้ไฟล์นี้แล้วรันซ้ำ
 */
class StandardPriceSeeder extends Seeder
{
    public function run()
    {
        $source    = 'ประกาศ สคร.1 เชียงใหม่ อัตราค่าบริการสาธารณสุข';
        $effective = '13/03/2569';

        // [category, name, unit, price, price_foreign]
        $rows = [
            // ===== หมวดที่ 1 ค่ายาและบริการเภสัชกรรม : วัคซีน =====
            ['vaccine', 'วัคซีนป้องกันอหิวาตกโรค (Cholera vaccine) Euvichol-Plus', 'dose', 980, null],
            ['vaccine', 'วัคซีนป้องกันไข้กาฬหลังแอ่น A,C,W,Y (MCV4) Manactra 0.5 ml', 'dose', 1330, null],
            ['vaccine', 'วัคซีนป้องกันไข้กาฬหลังแอ่น A,C,W,Y (Men ACWY-TT) Menquadfi 0.5 ml', 'dose', 3010, null],
            ['vaccine', 'วัคซีนป้องกันไข้กาฬหลังแอ่น group B (Meningococcal B) Bexsero', 'dose', 3080, null],
            ['vaccine', 'วัคซีนป้องกันไข้เหลือง (Yellow Fever vaccine) Stamaril', 'dose', 1360, null],
            ['vaccine', 'วัคซีนป้องกันไข้หวัดใหญ่ (Flu vaccine)', 'dose', 260, null],
            ['vaccine', 'วัคซีนป้องกันโรคพิษสุนัขบ้า (Rabies vaccine) Chirorab', 'dose', 380, null],
            ['vaccine', 'วัคซีนป้องกันมะเร็งปากมดลูก 4 สายพันธุ์ (HPV) Gardasil', 'dose', 2600, null],
            ['vaccine', 'วัคซีนป้องกันมะเร็งปากมดลูก 9 สายพันธุ์ (HPV) Gardasil9 0.5 ml', 'dose', 5600, null],
            ['vaccine', 'วัคซีนป้องกันคอตีบ บาดทะยัก (Dipteria Tetanus vaccine) BE Td', 'dose', 50, null],
            ['vaccine', 'วัคซีนป้องกันบาดทะยัก คอตีบ ไอกรน (Tdap)', 'dose', 650, null],
            ['vaccine', 'วัคซีนป้องกันไวรัสตับอักเสบเอ (Hepatitis A vaccine) Avaxim', 'dose', 1330, null],
            ['vaccine', 'วัคซีนป้องกันไวรัสตับอักเสบบี (Hepatitis B vaccine) Engerix B', 'dose', 300, null],
            ['vaccine', 'วัคซีนป้องกันไวรัสตับอักเสบ A และ B (Twinrix)', 'dose', 1050, null],
            ['vaccine', 'วัคซีนป้องกันไข้เลือดออก (Dengue vaccine) Qdenga', 'dose', 1640, null],
            ['vaccine', 'วัคซีนป้องกันไข้สมองอักเสบ (JE vaccine) Inojev', 'dose', 550, null],
            ['vaccine', 'วัคซีนป้องกันโรคหัด หัดเยอรมัน คางทูม (MMR vaccine)', 'dose', 420, null],
            ['vaccine', 'วัคซีนป้องกันโรค RSV', 'dose', 7220, null],
            ['vaccine', 'วัคซีนป้องกันโรคงูสวัด (Recombinant Zoster vaccine)', 'dose', 5200, null],
            ['vaccine', 'วัคซีนป้องกันโรคไทฟอยด์ (Typhoid vaccine)', 'dose', 610, null],

            // ===== หมวดที่ 1 : ยาและเวชภัณฑ์ =====
            ['medicine', 'Adrenaline injection 1 mg', 'ampule', 15, null],
            ['medicine', 'Chlopheniramine injection 10 mg', 'ampule', 15, null],
            ['medicine', 'Dexamethasone injection 10 mg', 'ampule', 15, null],
            ['medicine', 'Acetazolamide 250 mg', 'เม็ด', 5, null],
            ['medicine', 'Acycloclovia 400 mg', 'เม็ด', 5, null],
            ['medicine', 'Acycloclovia cream 5 gm', 'หลอด', 50, null],
            ['medicine', 'Acetylcysteine 200 mg Powder', 'ซอง', 4.50, null],
            ['medicine', 'Amoxycilline 500 mg', 'เม็ด', 2.50, null],
            ['medicine', 'Antacid suspension 240 ml', 'ขวด', 32, null],
            ['medicine', 'Azithromycin 250 mg', 'เม็ด', 13, null],
            ['medicine', 'Benzathine Penicillin 1.2 mu', 'vial', 85, null],
            ['medicine', 'Brown mixture 60 ml', 'ขวด', 17, null],
            ['medicine', 'Bromhexine 8 mg', 'เม็ด', 2, null],
            ['medicine', 'Calamine lotion 60 ml', 'ขวด', 20, null],
            ['medicine', 'Chlopheniramine 4 mg', 'เม็ด', 0.50, null],
            ['medicine', 'Ceftriazone 1 gm Inj', 'vial', 40, null],
            ['medicine', 'Cetirizine 10 mg', 'เม็ด', 1, null],
            ['medicine', 'Clotrimazole cream 15 g', 'หลอด', 35, null],
            ['medicine', 'Clotrimazole vagina tablet', 'เม็ด', 5, null],
            ['medicine', 'Dextromethrophan 15 mg', 'เม็ด', 1, null],
            ['medicine', 'Diclofenac 25 mg', 'เม็ด', 0.50, null],
            ['medicine', 'Dicloxacillin 250 mg', 'เม็ด', 2, null],
            ['medicine', 'Domperidone 10 mg', 'เม็ด', 1, null],
            ['medicine', 'Doxycycline 100 mg', 'เม็ด', 2, null],
            ['medicine', 'Hydroxyzine 10 mg', 'เม็ด', 1, null],
            ['medicine', 'Hyoscine 10 mg', 'เม็ด', 2, null],
            ['medicine', 'Ibuprofen 400 mg', 'เม็ด', 2, null],
            ['medicine', 'Loratadine 10 mg', 'เม็ด', 3.50, null],
            ['medicine', 'Loperamide 2 mg', 'เม็ด', 1, null],
            ['medicine', 'Mefenamic acid 250 mg', 'เม็ด', 1.50, null],
            ['medicine', 'Methylsalicylate cream 15 gm', 'หลอด', 20, null],
            ['medicine', 'Metronidazole 400 mg', 'เม็ด', 1.50, null],
            ['medicine', 'Molnupiravir 200 mg', 'เม็ด', 15, null],
            ['medicine', 'Multivitamin tablet', 'เม็ด', 1, null],
            ['medicine', 'Mixture Carminative 180 ml', 'ขวด', 20, null],
            ['medicine', 'Norfloxacin 400 mg', 'เม็ด', 2, null],
            ['medicine', 'Oseltamivir 75 mg', 'เม็ด', 31, null],
            ['medicine', 'Omeprazole 20 mg', 'เม็ด', 1.50, null],
            ['medicine', 'Oral Dehydration Salt (O.R.S.)', 'ซอง', 2, null],
            ['medicine', 'Paracetamol 500 mg', 'เม็ด', 1, null],
            ['medicine', 'Paracetamol 450 mg / Orphenadrine 35 mg', 'เม็ด', 1, null],
            ['medicine', 'Simethicone 80 mg', 'เม็ด', 1.50, null],
            ['medicine', 'Tinidazole 500 mg', 'เม็ด', 1.50, null],
            ['medicine', 'Tetracyclin ointment', 'หลอด', 37, null],
            ['medicine', 'Triamcinolone 0.02% cream', 'หลอด', 31, null],
            ['medicine', 'Triamcinolone 0.1% cream', 'หลอด', 15, null],
            ['medicine', 'Vitamin B1-6-12', 'เม็ด', 1.50, null],
            ['medicine', 'Vitamin B complex', 'เม็ด', 0.50, null],
            ['medicine', 'Vitamin B6', 'เม็ด', 1, null],

            // ===== หมวดที่ 2 : โลหิตวิทยา / จุลทรรศน์ / จุลชีววิทยา =====
            ['lab', 'CBC (+ diff. + RBC morphology + platelet count)', 'Test', 130, 130],
            ['lab', 'Giemsa stain for malaria', 'Test', 160, 160],
            ['lab', 'PT (Prothrombin Time) and INR', 'Test', 160, 160],
            ['lab', 'PTT (Partial Thromboplastin Time)', 'Test', 200, 200],
            ['lab', 'Pregnancy test, Urine', 'Test', 100, 100],
            ['lab', 'AFB stain (Acid-Fast Bacilli stain)', 'Test', 100, 100],
            ['lab', 'Gram stain', 'Test', 90, 90],
            ['lab', 'Modified acid fast stain', 'Test', 100, 100],
            ['lab', 'Mycobacteria direct PCR for M. tuberculosis rifampicin resistance', 'Test', 1100, 1100],
            ['lab', 'Mycobacterial culture and sensitivity', 'Test', 600, 600],
            ['lab', 'Routine aerobic culture and identification and Susceptibility', 'Test', 320, 320],
            ['lab', 'Routine aerobic culture and Susceptibility (Automation)', 'Test', 50, 450],
            ['lab', 'Wet smear', 'Test', 60, 60],

            // ===== หมวดที่ 2 : เคมีคลินิก =====
            ['lab', 'Albumin', 'Test', 60, 60],
            ['lab', 'Alkaline phosphatase', 'Test', 60, 60],
            ['lab', 'Bilirubin, Direct', 'Test', 60, 60],
            ['lab', 'Bilirubin, Total', 'Test', 60, 60],
            ['lab', 'BUN (Blood Urea Nitrogen)', 'Test', 60, 60],
            ['lab', 'Calcium', 'Test', 70, 70],
            ['lab', 'Creatinine (Enzymatic reaction)', 'Test', 60, 60],
            ['lab', 'Electrolyte (Na, K, Cl, CO2)', 'Test', 200, 200],
            ['lab', 'Glucose (Blood, Urine, Other)', 'Test', 50, 50],
            ['lab', 'HbA1c', 'Test', 230, 230],
            ['lab', 'Lipid - Cholesterol', 'Test', 60, 60],
            ['lab', 'Lipid - HDL - chol', 'Test', 110, 110],
            ['lab', 'Lipid - TG (Triglyceride)', 'Test', 100, 100],
            ['lab', 'Lipid - LDL - chol (direct) สั่งรายการเดียว', 'Test', 260, 260],
            ['lab', 'Magnesium', 'Test', 100, 100],
            ['lab', 'Phosphorus', 'Test', 70, 70],
            ['lab', 'Potassium (K+)', 'Test', 50, 50],
            ['lab', 'Protein, Total', 'Test', 60, 60],
            ['lab', 'SGOT (AST)', 'Test', 60, 60],
            ['lab', 'SGPT (ALT)', 'Test', 60, 60],
            ['lab', 'Thyroid hormone - Free T3', 'Test', 210, 210],
            ['lab', 'Thyroid hormone - Free T4', 'Test', 210, 210],
            ['lab', 'Thyroid hormone - T3', 'Test', 200, 200],
            ['lab', 'Thyroid hormone - T4', 'Test', 220, 220],
            ['lab', 'Thyroid hormone - TSH', 'Test', 300, 300],

            // ===== หมวดที่ 2 : ภูมิคุ้มกันวิทยาคลินิก =====
            ['lab', 'HBcAb Total (CMIA)', 'Test', 300, 300],
            ['lab', 'HBsAb (Qualitative) (CMIA)', 'Test', 200, 200],
            ['lab', 'HBsAg (Qualitative) (CMIA)', 'Test', 50, 50],
            ['lab', 'HCV Ab (CMIA)', 'Test', 50, 50],
            ['lab', 'HIV Ab (Rapid test) (IC)', 'Test', 270, 270],
            ['lab', 'IGRA-TB (CLIA)', 'Test', 2300, 2300],
            ['lab', 'Syphilis (RPR) (FLOC)', 'Test', 80, 80],
            ['lab', 'Treponema pallidum Ab (IC/GPA)', 'Test', 130, 130],
            ['lab', 'VZV IgG Ab (CLIA)', 'Test', 500, 500],

            // ===== หมวดที่ 2 : อณูชีวโมเลกุล =====
            ['lab', 'Dengue virus, qualitative RT-PCR', 'Test', 2000, 2000],
            ['lab', 'HBV Viral load - Quantitative', 'Test', 1200, 1200],
            ['lab', 'HCV Viral load - Quantitative', 'Test', 1690, 1690],
            ['lab', 'PCR for Dengue/Chikungunya virus', 'Test', 2000, 2000],
            ['lab', 'PCR for Malaria (5 สายพันธุ์)', 'Test', 2000, 2000],
            ['lab', 'PCR for Respiratory Pathogen 33 (Multiplex PCR)', 'Test', 3000, 3000],
            ['lab', 'Molecular identification of NTM-Line Probe', 'Test', 1800, 1800],
            ['lab', 'Mycobacteria : molecular AST for IR', 'Test', 1800, 1800],
            ['lab', 'PCR for MDR/XDR TB', 'Test', 2000, 2000],
            ['lab', 'PCR for TB or NTM (RT-PCR)', 'Test', 2000, 2000],
            ['lab', 'HPV PCR-Qualitative, screen for high risk types', 'Test', 1000, 1000],
            ['lab', 'Real time PCR for Monkeypox Virus', 'Test', 1500, 1500],
            ['lab', 'GeneXpert for Chlamydia and Gonorrhea detection', 'Test', 1500, 1500],

            // ===== หมวดที่ 3 รังสีวิทยา =====
            ['radiology', 'ภาพถ่ายเอกซเรย์ดิจิทัล', 'exposure', 250, 250],

            // ===== หมวดที่ 4-5 ค่าบริการตรวจรักษา =====
            ['service', 'การให้สารน้ำทางหลอดเลือดดำ (ไม่รวมสารน้ำและ set IV)', 'ครั้ง', 120, 120],
            ['service', 'การฉีดยา IV', 'เข็ม', 150, 150],
            ['service', 'การฉีดยา IM', 'เข็ม', 60, 60],
            ['service', 'การฉีดยาใต้ผิวหนัง', 'เข็ม', 60, 60],
            ['service', 'ค่าบริการผู้ป่วยนอก (ในเวลาราชการ)', 'ครั้ง', 120, 120],
            ['service', 'ECG (Electrocardiography)', 'ครั้ง', 450, 450],
            ['service', 'ค่าตรวจมะเร็งปากมดลูก', 'ครั้ง', 180, 180],

            // ===== ตรวจโรคจากการประกอบอาชีพและสิ่งแวดล้อม =====
            ['occmed', 'ตรวจสมรรถภาพการได้ยิน', 'Test', 50, null],
            ['occmed', 'ตรวจสมรรถภาพการมองเห็น', 'Test', 50, null],
            ['occmed', 'ตรวจสมรรถภาพการทำงานของปอด', 'Test', 100, null],
            ['occmed', 'ตรวจวัดระดับเสียง (Spot Sampling)', 'Test', 200, null],
            ['occmed', 'ตรวจวัดแสงสว่าง (Spot Sampling)', 'Test', 200, null],
            ['occmed', 'ตรวจวัดความร้อน', 'Test', 200, null],
            ['occmed', 'ตรวจวัด Air Change Rate', 'Test', 500, null],
            ['occmed', 'ตรวจวัดปริมาณฝุ่นรวม (Total Dust)', 'Test', 700, null],
            ['occmed', 'ตรวจวัดปริมาณฝุ่นฝ้าย', 'Test', 1500, null],
            ['occmed', 'ตรวจวัดทางเคมีที่เป็นไอระเหยและก๊าซ (Direct reading)', 'Test', 1000, null],
            ['occmed', 'ตรวจวัดคุณภาพอากาศภายในอาคาร (CO2, CO, VOCs, PM2.5, PM10)', 'Test', 1000, null],
            ['occmed', 'ตรวจวัดปริมาณเชื้อรา เชื้อแบคทีเรียรวมในอากาศ', 'Test', 1000, null],
        ];

        foreach ($rows as $i => [$cat, $name, $unit, $price, $foreign]) {
            StandardPrice::updateOrCreate(
                ['category' => $cat, 'name' => $name],
                [
                    'unit'           => $unit,
                    'price'          => $price,
                    'price_foreign'  => $foreign,
                    'source'         => $source,
                    'effective_date' => $effective,
                    'active'         => true,
                    'sort_order'     => $i,
                ]
            );
        }
    }
}
