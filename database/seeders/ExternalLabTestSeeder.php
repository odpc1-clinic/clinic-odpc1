<?php

namespace Database\Seeders;

use App\Models\ExternalLabTest;
use Illuminate\Database\Seeder;

/**
 * แคตตาล็อกรายการตรวจแล็บภายนอก — N Health
 * อ้างอิงใบเสนอราคาเลขที่ 10LA20260521-587 ลงวันที่ 21 พ.ค. 2569
 * (สำนักงานป้องกันควบคุมโรคที่ 1 เชียงใหม่ · เครดิต 90 วัน · ยืนราคา 30 วัน)
 * ราคาในตารางนี้คือ "ราคาตามใบเสนอราคา" ใช้เป็นตัวอ้างอิงเท่านั้น
 * ราคาที่เก็บคนไข้จริงให้พยาบาลกรอกเองตอนสั่ง
 */
class ExternalLabTestSeeder extends Seeder
{
    public function run()
    {
        $vendor = 'N Health';

        // [code, name, specimen, turnaround, price]
        $rows = [
            ['A010', 'Complete Blood Count', 'EDTA Blood 2 ml', '1 day', 72],
            ['A025', 'Rh Group for haematology', 'EDTA Blood or Clotted blood', '1 day', 48],
            ['C004', 'Electrolytes', 'serum 1 ml (no hemolysis)', '1 day', 160],
            ['C040', 'Glucose (Fasting)', 'NaF Plasma 1 ml', '1 day', 48],
            ['C061', 'Cholesterol', 'serum 1 ml', '1 day', 64],
            ['C062', 'HDL-Cholesterol', 'serum 1 ml', '1 day', 120],
            ['C071', 'Liver Function Test (8 Tests)', 'serum 1 ml', '1 day', 320],
            ['C100', 'Calcium', 'serum 1 ml', '1 day', 80],
            ['C120', 'Uric Acid', 'serum 1 ml', '1 day', 64],
            ['C340', 'Magnesium', 'serum 1 ml', '1 day', 160],
            ['C500', 'Sodium', 'serum 1 ml', '1 day', 52],
            ['C510', 'Potassium', 'serum 1 ml (no hemolysis)', '1 day', 52],
            ['C520', 'Creatinine', 'Serum 1 ml / Plasma (Lithium Heparin) 1 ml', '1 day', 48],
            ['C530', 'Blood Urea Nitrogen', 'serum 1 ml', '1 day', 48],
            ['C540', 'Chloride', 'serum 1 ml', '1 day', 52],
            ['C555', 'Total CO2', 'serum 1 ml', '1 day', 52],
            ['C560', 'Bilirubin (Total, Direct)', 'serum 1 ml', '1 day', 80],
            ['C580', 'ALP (Alkaline Phosphatase)', 'serum 1 ml', '1 day', 48],
            ['C590', 'GGT (Gamma GT)', 'serum 1 ml', '1 day', 104],
            ['C600', 'ALT (SGPT)', 'serum 1 ml (no hemolysis)', '1 day', 48],
            ['C610', 'AST (SGOT)', 'serum 1 ml (no hemolysis)', '1 day', 64],
            ['C620', 'Total Protein', 'serum 1 ml', '1 day', 80],
            ['C630', 'Albumin', 'serum 1 ml', '1 day', 48],
            ['C640', 'LDL-Cholesterol (Direct)', 'serum 1 ml', '1 day', 120],
            ['C650', 'Triglyceride', 'serum 1 ml', '1 day', 104],
            ['C880', 'Glycated Hb (HbA1c)', 'EDTA Blood 2 ml', '1 day', 240],
            ['D111', 'Urine Examination', 'Random urine 10 ml', '1 day', 64],
            ['D120', 'Pregnancy Test', 'Random urine 5 ml', '1 day', 80],
            ['D330', 'Occult blood (Fecal immunochemical test)', 'Stool (Sterile container)', '1 day', 80],
            ['E031', 'Urine Culture & Sensitivity (OPD)', 'Midstream urine in sterile container', '2-5 days', 240],
            ['E081', 'Sputum Culture & Sensitivity (OPD)', 'Sputum (Sterile container)', '3-5 days', 240],
            ['G112', 'Pap smear, ThinPrep method (BGH)', 'Cervical specimen in Thinprep container', '3-5 days', 440],
            ['K050', 'Prothrombin Time', 'Na Citrate plasma 1 ml', '1 day', 160],
            ['K060', 'Partial Thromboplastin Time', 'Na Citrate plasma 1 ml', '1 day', 160],
            ['M521', 'HPV DNA with Genotyping (High Risk 14 Subtypes) [BML]', 'Liquid based cytology: Cellprep (นอกเหนือจากนี้สอบถามห้องแล็บก่อน)', '5 days', 688],
            ['N005', 'Triiodothyronine (T3)', 'serum 1 ml', '1 day', 120],
            ['N006', 'Thyroxine (T4)', 'Serum 1 ml / Plasma (Sodium/Lithium Heparin) 1 ml', '1 day', 120],
            ['N010', 'Thyroid Stimulating Hormone (TSH)', 'serum 1 ml', '1 day', 160],
            ['N025', 'Triiodothyronine Free (Free T3)', 'serum 1 ml', '1 day', 280],
            ['N030', 'Thyroxine Free (Free T4)', 'serum 1 ml', '1 day', 120],
            ['N620', 'Hepatitis B surface Antigen', 'serum 1 ml', '1 day', 120],
            ['N625', 'Hepatitis B surface Antibody (HBsAb, Anti-HBs)', 'serum 1 ml', '1 day', 160],
            ['N630', 'Hepatitis B Core Antibody (HBcAb, Anti-HBc)', 'serum 1 ml', '1 day', 160],
            ['N635', 'Hepatitis B Core IgM Antibody', 'serum 1 ml', '1 day', 400],
            ['N660', 'Hepatitis C Antibody', 'Serum 1 ml / Plasma 1 ml', '1 day', 232],
            ['N771', 'TSH Receptor Antibody', 'Serum 1 ml', '1 day', 880],
            ['P310', 'Dengue NS1 Antigen', 'serum 1 ml', '1 day', 640],
            ['P842', 'Scrub Typhus Antibody (Rapid Test)', 'serum 1 ml', '1 day', 400],
            ['P855', 'Dengue virus IgG / IgM screening', 'serum 1 ml', '1 day', 352],
            ['P764', 'Varicella Zoster IgG', 'serum 1 ml', '2 days', 288],
            ['N390', 'Syphilis antibody (Treponema pallidum antibody)', 'serum 1 ml', '1 day', 148],
            ['N651', 'Anti Hepatitis A Virus IgG', 'Serum 1 ml', '3 hrs', 500],
            ['N655', 'Hepatitis A Antibody (IgM)', 'serum 1 ml', '1 day', 425],
            ['P751', 'Measles Virus IgG', 'serum 1 ml', '3 days', 340],
            ['P753', 'MUMPS Virus IgG', 'serum 1 ml', '2 days', 450],
            ['N665', 'Rubella IgG', 'serum 1 ml', '1 day', 215],

            // ===== เพิ่มตามใบส่งตรวจของ N Health (พยาบาลขอ 2569-08-28) =====
            // กลุ่ม Microbiology & TB culture และกลุ่มตรวจอุจจาระ/ปัสสาวะที่ยังไม่มีในแคตตาล็อก
            //
            // ราคาเป็น null เพราะ "ใบส่งตรวจ" ไม่มีราคา (คนละใบกับใบเสนอราคา 10LA20260521-587)
            // ระบบรองรับอยู่แล้ว — ช่อง ref_price จะว่าง ส่วนราคาที่เก็บจริงพยาบาลกรอกเองตอนสั่ง
            // ได้ราคาจาก N Health เมื่อไหร่ ค่อยเติมแล้วรัน seeder ซ้ำ (updateOrCreate)
            //
            // specimen เว้นว่าง เพราะใบส่งตรวจให้เขียนเอง ("Specimen : ........")
            // และย้ำว่า 1 specimen ต่อ 1 ใบ สำหรับ Bacteria และ TB

            // -- ตรวจอุจจาระ / ปัสสาวะ --
            ['D602', 'Faecal Occult Blood & Inflammation Screening', null, null, null],
            ['D020', 'Microscopy Examination (Fluid) (Review Crystal for Joint Case)', null, null, null],
            ['D380', 'Stool Examination', null, null, null],

            // -- Stain --
            ['E230', 'AFB Stain', null, null, null],
            ['E220', "Gram's Stain", null, null, null],
            ['E240', 'Modified AFB Stain', null, null, null],

            // -- General Bacteria --
            ['E310', 'Anaerobic Culture', null, null, null],
            ['E005', 'Bacterial Identification', null, null, null],
            ['E006', 'Bacterial Identification & Susceptibility', null, null, null],
            ['E051', 'Body Fluid Culture & Susceptibility', null, null, null],
            ['E903', 'Group B Streptococcus Culture & Susceptibility', null, null, null],
            ['E300', 'Hemo Culture & Susceptibility', null, null, null],
            ['E021', 'Stool Culture & Susceptibility', null, null, null],
            ['E011', 'Swab Culture & Susceptibility', null, null, null],
            ['E041', 'Tissue Culture & Susceptibility', null, null, null],

            // -- Fungus --
            ['E628', 'Hemo for Fungus Culture', null, null, null],

            // -- Mycobacteria --
            ['N247', 'Adenosine Deaminase (ADA)', null, null, null],
            ['E091', 'Hemo for TB Culture', null, null, null],
            ['E258', 'Quantiferon TB', null, null, null],
            ['E330', 'TB Culture (Conventional Method)', null, null, null],
            ['E335', 'TB Culture (Automate) & Susceptibility (Automate)', null, null, null],
            ['E337', 'TB Culture (Automate) & Susceptibility (Automate) & PCR', null, null, null],
        ];

        foreach ($rows as [$code, $name, $specimen, $tat, $price]) {
            // updateOrCreate เพื่อให้รันซ้ำได้ตอนใบเสนอราคาปีใหม่ออก (ราคาจะถูกอัปเดตให้)
            ExternalLabTest::updateOrCreate(
                ['vendor' => $vendor, 'code' => $code],
                [
                    'name'       => $name,
                    'specimen'   => $specimen,
                    'turnaround' => $tat,
                    'price'      => $price,
                    'active'     => true,
                ]
            );
        }
    }
}
